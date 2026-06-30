<?php
/**
 * IP 黑名单运行时服务
 */
namespace core\basic;

/**
 * 统一处理 IP 黑名单入口拦截、规则缓存和高风险请求自动封禁。
 */
class IpBlacklist
{
    protected static $blacklistIndexCacheKey = 'security:blacklist:index';

    protected static $ruleCacheKey = 'security:blacklist:rules';

    protected static $ruleCacheSeconds = 300;

    protected static $blacklistIndexCacheSeconds = 300;

    protected static $tableAvailability = array();

    /**
     * 在请求最早阶段执行黑名单拦截。
     *
     * @return void
     */
    public static function blockCurrentRequest()
    {
        if (! self::isFeatureEnabled()) {
            return;
        }

        $ip = self::getCurrentIp();
        if (! $ip || self::isWhiteIp($ip)) {
            return;
        }

        $entry = self::getBlacklistEntry($ip);
        if (! $entry) {
            return;
        }

        self::writeSecurityEvent('Blocked blacklisted IP: ip=' . $ip . ' uri=' . self::getRequestUri());
        self::sendBlockedResponse();
    }

    /**
     * 对请求路径进行高风险识别与自动封禁。
     *
     * @param string $path 请求路径
     * @return void
     */
    public static function trackPath($path)
    {
        if (! self::isFeatureEnabled() || ! self::isAutoEnabled()) {
            return;
        }

        $ip = self::getCurrentIp();
        if (! $ip || self::isWhiteIp($ip)) {
            return;
        }

        $path = self::normalizePath($path);
        if (! $path) {
            return;
        }

        $reason = self::matchHighRiskPath($path);
        if ($reason === false) {
            return;
        }

        $counter = self::increaseHighRiskCounter($ip, $path);
        if ($counter['count'] < self::getAutoThreshold()) {
            return;
        }

        self::addAutoBlacklist($ip, $path, $counter['count'], $reason);
        Cache::delete(self::getHighRiskCounterCacheKey($ip));
        self::writeSecurityEvent('Auto blocked high risk IP: ip=' . $ip . ' path=' . $path . ' reason=' . $reason);
        self::sendBlockedResponse();
    }

    /**
     * 后台修改后刷新黑名单和规则的运行时缓存。
     *
     * @return void
     */
    public static function refreshRuntimeCache()
    {
        $oldIndex = Cache::get(self::$blacklistIndexCacheKey);
        $oldItems = (is_array($oldIndex) && isset($oldIndex['items']) && is_array($oldIndex['items'])) ? $oldIndex['items'] : array();

        $newItems = self::queryActiveBlacklistMap();
        self::writeCache(self::$blacklistIndexCacheKey, array(
            'expire_at' => time() + self::$blacklistIndexCacheSeconds,
            'items' => $newItems
        ));

        foreach ($oldItems as $ip => $value) {
            if (! isset($newItems[$ip])) {
                Cache::delete(self::getBlacklistCacheKey($ip));
            }
        }

        foreach ($newItems as $ip => $value) {
            self::writeCache(self::getBlacklistCacheKey($ip), $value);
        }

        self::refreshRuleCache();
    }

    /**
     * 刷新规则缓存。
     *
     * @return void
     */
    public static function refreshRuleCache()
    {
        self::writeCache(self::$ruleCacheKey, array(
            'expire_at' => time() + self::$ruleCacheSeconds,
            'rules' => self::queryEnabledRules()
        ));
    }

    /**
     * 判断当前缓存驱动是否允许启用黑名单功能。
     *
     * @return bool
     */
    public static function isCacheDriverSupported()
    {
        return Config::get('cache.handler') !== 'file';
    }

    /**
     * 获取当前请求 IP。
     *
     * @return string
     */
    public static function getCurrentIp()
    {
        $ip = trim((string) get_user_ip());
        if ($ip === '') {
            return '';
        }
        if (strpos($ip, ',') !== false) {
            $parts = explode(',', $ip);
            $ip = trim($parts[0]);
        }
        return $ip;
    }

    /**
     * 判断功能总开关是否启用。
     *
     * @return bool
     */
    protected static function isFeatureEnabled()
    {
        return self::isCacheDriverSupported() && (string) Config::get('ip_blacklist_status') === '1';
    }

    /**
     * 判断自动封禁是否启用。
     *
     * @return bool
     */
    protected static function isAutoEnabled()
    {
        return (string) Config::get('ip_blacklist_auto_enable') === '1';
    }

    /**
     * 判断是否命中白名单。
     *
     * @param string $ip IP 地址
     * @return bool
     */
    protected static function isWhiteIp($ip)
    {
        // 本地回环地址永不封禁
        if ($ip === '127.0.0.1' || $ip === '::1') {
            return true;
        }

        $ipAllow = Config::get('ip_allow', true);
        foreach ($ipAllow as $value) {
            if (network_match($ip, $value)) {
                return true;
            }
        }
        return false;
    }

    /**
     * 获取黑名单缓存记录。
     *
     * @param string $ip IP 地址
     * @return array|false
     */
    protected static function getBlacklistEntry($ip)
    {
        $entry = Cache::get(self::getBlacklistCacheKey($ip));
        if (is_array($entry)) {
            if (self::isBlacklistEntryExpired($entry)) {
                Cache::delete(self::getBlacklistCacheKey($ip));
            } else {
                return $entry;
            }
        }

        $items = self::loadBlacklistIndex();
        if (isset($items[$ip]) && is_array($items[$ip])) {
            $entry = $items[$ip];
            if (! self::isBlacklistEntryExpired($entry)) {
                self::writeCache(self::getBlacklistCacheKey($ip), $entry);
                return $entry;
            }
        }

        return false;
    }

    /**
     * 加载黑名单索引缓存，缺失时从数据库回填。
     *
     * @return array
     */
    protected static function loadBlacklistIndex()
    {
        $index = Cache::get(self::$blacklistIndexCacheKey);
        if (is_array($index) && isset($index['expire_at']) && $index['expire_at'] >= time() && isset($index['items']) && is_array($index['items'])) {
            return $index['items'];
        }

        $items = self::queryActiveBlacklistMap();
        self::writeCache(self::$blacklistIndexCacheKey, array(
            'expire_at' => time() + self::$blacklistIndexCacheSeconds,
            'items' => $items
        ));

        foreach ($items as $ip => $entry) {
            self::writeCache(self::getBlacklistCacheKey($ip), $entry);
        }

        return $items;
    }

    /**
     * 从数据库读取当前有效的黑名单映射。
     *
     * @return array
     */
    protected static function queryActiveBlacklistMap()
    {
        $items = array();
        if (! self::isTableAvailable('ay_ip_blacklist')) {
            return $items;
        }
        $result = self::safeTableSelect('ay_ip_blacklist', "status='1'");
        foreach ($result as $value) {
            $entry = self::normalizeBlacklistRecord($value);
            if (self::isBlacklistEntryExpired($entry)) {
                continue;
            }
            $items[$entry['ip']] = $entry;
        }
        return $items;
    }

    /**
     * 从数据库读取启用中的规则。
     *
     * @return array
     */
    protected static function queryEnabledRules()
    {
        $rules = array();
        if (! self::isTableAvailable('ay_ip_blacklist_rule')) {
            return $rules;
        }
        $result = self::safeTableSelect('ay_ip_blacklist_rule', "status='1'", 'id DESC');
        foreach ($result as $value) {
            $rules[] = array(
                'id' => isset($value->id) ? (int) $value->id : 0,
                'name' => isset($value->name) ? (string) $value->name : '',
                'match_type' => isset($value->match_type) ? (string) $value->match_type : 'contains',
                'pattern' => isset($value->pattern) ? (string) $value->pattern : '',
                'risk_level' => isset($value->risk_level) ? (string) $value->risk_level : 'high'
            );
        }
        return $rules;
    }

    /**
     * 加载规则缓存，缺失时从数据库回填。
     *
     * @return array
     */
    protected static function loadRuleList()
    {
        $rules = Cache::get(self::$ruleCacheKey);
        if (is_array($rules) && isset($rules['expire_at']) && $rules['expire_at'] >= time() && isset($rules['rules']) && is_array($rules['rules'])) {
            return $rules['rules'];
        }

        $rules = self::queryEnabledRules();
        self::writeCache(self::$ruleCacheKey, array(
            'expire_at' => time() + self::$ruleCacheSeconds,
            'rules' => $rules
        ));
        return $rules;
    }

    /**
     * 对路径执行高风险匹配。
     *
     * @param string $path 请求路径
     * @return string|false
     */
    protected static function matchHighRiskPath($path)
    {
        $reason = self::matchBuiltInHighRiskPath($path);
        if ($reason !== false) {
            return $reason;
        }

        $rules = self::loadRuleList();
        $lowerPath = strtolower($path);
        foreach ($rules as $rule) {
            $pattern = isset($rule['pattern']) ? trim((string) $rule['pattern']) : '';
            if ($pattern === '') {
                continue;
            }
            $matchType = isset($rule['match_type']) ? (string) $rule['match_type'] : 'contains';
            $lowerPattern = strtolower($pattern);
            switch ($matchType) {
                case 'exact':
                    $matched = $lowerPath === $lowerPattern;
                    break;
                case 'prefix':
                    $matched = strpos($lowerPath, $lowerPattern) === 0;
                    break;
                case 'regex':
                    $matched = @preg_match($pattern, $path) === 1;
                    break;
                default:
                    $matched = strpos($lowerPath, $lowerPattern) !== false;
                    break;
            }
            if ($matched) {
                return 'rule:' . (isset($rule['name']) ? $rule['name'] : $pattern);
            }
        }

        return false;
    }

    /**
     * 匹配内置的高风险请求特征。
     *
     * @param string $path 请求路径
     * @return string|false
     */
    protected static function matchBuiltInHighRiskPath($path)
    {
        $path = ltrim(strtolower($path), '/');
        if ($path === '') {
            return false;
        }

        $allowList = array(
            'index.php',
            'admin.php',
            'api.php'
        );
        if (in_array($path, $allowList)) {
            return false;
        }

        if (preg_match('/(^|\/)\.env([\.\/]|$)/', $path)) {
            return 'built_in:.env';
        }

        if (preg_match('/(^|\/)(wp-login\.php|xmlrpc\.php|adminer(\.php)?|phpinfo(\.php)?)(\/|$)/', $path)) {
            return 'built_in:attack_entry';
        }

        if (strpos($path, 'phpmyadmin') !== false) {
            return 'built_in:phpmyadmin';
        }

        if (preg_match('/\.(php\d*|phtml|phar|asp|aspx|jsp|cgi)$/', $path)) {
            return 'built_in:script_extension';
        }

        if (preg_match('/(^|\/)(composer\.(json|lock)|vendor\/)/', $path)) {
            return 'built_in:sensitive_file';
        }

        return false;
    }

    /**
     * 增加高风险请求计数。
     *
     * @param string $ip IP 地址
     * @param string $path 请求路径
     * @return array
     */
    protected static function increaseHighRiskCounter($ip, $path)
    {
        $key = self::getHighRiskCounterCacheKey($ip);
        $counter = Cache::get($key);
        $now = time();
        $window = self::getAutoWindowSeconds();

        if (! is_array($counter) || ! isset($counter['expire_at']) || (int) $counter['expire_at'] < $now) {
            $counter = array(
                'count' => 1,
                'expire_at' => $now + $window,
                'last_uri' => $path
            );
        } else {
            $counter['count'] = isset($counter['count']) ? ((int) $counter['count'] + 1) : 1;
            $counter['last_uri'] = $path;
        }

        self::writeCache($key, $counter);
        return $counter;
    }

    /**
     * 写入自动封禁记录并同步缓存。
     *
     * @param string $ip IP 地址
     * @param string $path 请求路径
     * @param int $hitCount 触发次数
     * @param string $reason 触发原因
     * @return void
     */
    protected static function addAutoBlacklist($ip, $path, $hitCount, $reason)
    {
        if (! self::isTableAvailable('ay_ip_blacklist')) {
            self::writeSecurityEvent('IpBlacklist storage table missing, skip auto block write.');
            return;
        }

        $expireTime = self::getAutoBlockSeconds() > 0 ? get_datetime(time() + self::getAutoBlockSeconds()) : '';
        $data = array(
            'ip' => $ip,
            'source_type' => 'auto_high_risk',
            'reason' => $reason,
            'status' => '1',
            'expire_time' => $expireTime,
            'hit_count' => $hitCount,
            'last_uri' => $path,
            'remark' => '',
            'create_user' => 'system',
            'update_user' => 'system'
        );

        $escapedIp = escape_string($ip);
        try {
            $exists = Db::table('ay_ip_blacklist')->where("ip='$escapedIp'")->where("status='1'")->find();
            if ($exists) {
                Db::table('ay_ip_blacklist')->where("id=" . (int) $exists->id)->autoTime()->update($data);
            } else {
                Db::table('ay_ip_blacklist')->autoTime()->insert($data);
            }
        } catch (\Throwable $e) {
            self::$tableAvailability['ay_ip_blacklist'] = false;
            self::writeSecurityEvent('IpBlacklist auto write failed: ' . $e->getMessage());
            return;
        }

        self::refreshRuntimeCache();
    }

    /**
     * 规范化黑名单记录。
     *
     * @param object $record 数据库记录
     * @return array
     */
    protected static function normalizeBlacklistRecord($record)
    {
        return array(
            'ip' => isset($record->ip) ? (string) $record->ip : '',
            'source_type' => isset($record->source_type) ? (string) $record->source_type : 'manual',
            'expire_time' => isset($record->expire_time) ? (string) $record->expire_time : '',
            'reason' => isset($record->reason) ? (string) $record->reason : '',
            'status' => isset($record->status) ? (string) $record->status : '0',
            'hit_count' => isset($record->hit_count) ? (int) $record->hit_count : 0,
            'last_uri' => isset($record->last_uri) ? (string) $record->last_uri : '',
            'remark' => isset($record->remark) ? (string) $record->remark : ''
        );
    }

    /**
     * 判断黑名单记录是否过期。
     *
     * @param array $entry 黑名单记录
     * @return bool
     */
    protected static function isBlacklistEntryExpired(array $entry)
    {
        if (! isset($entry['expire_time']) || $entry['expire_time'] === '' || $entry['expire_time'] === '0') {
            return false;
        }

        $expireTime = strtotime($entry['expire_time']);
        if ($expireTime === false) {
            return false;
        }

        return $expireTime < time();
    }

    /**
     * 规范化路径内容。
     *
     * @param string $path 原始路径
     * @return string
     */
    protected static function normalizePath($path)
    {
        $path = trim((string) $path);
        if ($path === '') {
            $path = self::getRequestUri();
        }
        $parsed = parse_url($path, PHP_URL_PATH);
        if ($parsed !== null && $parsed !== false) {
            $path = $parsed;
        }
        $path = rawurldecode(str_replace('\\', '/', $path));
        return '/' . ltrim($path, '/');
    }

    /**
     * 获取请求 URI。
     *
     * @return string
     */
    protected static function getRequestUri()
    {
        return isset($_SERVER['REQUEST_URI']) ? (string) $_SERVER['REQUEST_URI'] : '';
    }

    /**
     * 返回高风险计数缓存键。
     *
     * @param string $ip IP 地址
     * @return string
     */
    protected static function getHighRiskCounterCacheKey($ip)
    {
        return 'security:suspect:high:' . md5($ip);
    }

    /**
     * 返回单个 IP 的黑名单缓存键。
     *
     * @param string $ip IP 地址
     * @return string
     */
    protected static function getBlacklistCacheKey($ip)
    {
        return 'security:blacklist:ip:' . md5($ip);
    }

    /**
     * 获取自动封禁阈值。
     *
     * @return int
     */
    protected static function getAutoThreshold()
    {
        $value = (int) Config::get('ip_blacklist_auto_threshold');
        return $value > 0 ? $value : 5;
    }

    /**
     * 获取高风险计数窗口时长。
     *
     * @return int
     */
    protected static function getAutoWindowSeconds()
    {
        $value = (int) Config::get('ip_blacklist_auto_window');
        return $value > 0 ? $value : 86400;
    }

    /**
     * 获取自动封禁时长。
     *
     * @return int
     */
    protected static function getAutoBlockSeconds()
    {
        $value = (int) Config::get('ip_blacklist_auto_block_ttl');
        return $value >= 0 ? $value : 86400;
    }

    /**
     * 检测指定表是否可访问，避免升级未落库时影响前台请求。
     *
     * @param string $table 表名
     * @return bool
     */
    protected static function isTableAvailable($table)
    {
        if (array_key_exists($table, self::$tableAvailability)) {
            return self::$tableAvailability[$table];
        }

        try {
            Db::table($table)->limit(1)->find();
            self::$tableAvailability[$table] = true;
        } catch (\Throwable $e) {
            self::$tableAvailability[$table] = false;
            self::writeSecurityEvent('IpBlacklist table unavailable: table=' . $table . ' error=' . $e->getMessage());
        }

        return self::$tableAvailability[$table];
    }

    /**
     * 安全执行表查询，失败时仅记录日志并返回空数组。
     *
     * @param string $table 表名
     * @param string $where 查询条件
     * @param string $order 排序条件
     * @return array
     */
    protected static function safeTableSelect($table, $where, $order = '')
    {
        try {
            $query = Db::table($table)->where($where);
            if ($order !== '') {
                $query->order($order);
            }
            return $query->select();
        } catch (\Throwable $e) {
            self::$tableAvailability[$table] = false;
            self::writeSecurityEvent('IpBlacklist select failed: table=' . $table . ' error=' . $e->getMessage());
            return array();
        }
    }

    /**
     * 统一写入缓存并吞掉异常，避免安全功能拖垮主流程。
     *
     * @param string $key 缓存键
     * @param mixed $value 缓存值
     * @return void
     */
    protected static function writeCache($key, $value)
    {
        try {
            Cache::set($key, $value);
        } catch (\Throwable $e) {
            self::writeSecurityEvent('IpBlacklist cache set failed: key=' . $key . ' error=' . $e->getMessage());
        }
    }

    /**
     * 写入安全日志。
     *
     * @param string $message 日志内容
     * @return void
     */
    protected static function writeSecurityEvent($message)
    {
        if (function_exists('writeSecurityLog')) {
            writeSecurityLog($message);
        }
    }

    /**
     * 输出统一的黑名单拦截响应。
     *
     * @return void
     */
    protected static function sendBlockedResponse()
    {
        if (! headers_sent()) {
            header('Content-Type: text/plain; charset=utf-8');
            header('X-Doye-Defense: ip-blacklist');
        }
        http_response_code(403);
        echo 'Your IP has been blocked.';
        exit;
    }
}
