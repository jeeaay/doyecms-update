<?php
/**
 * IP 黑名单控制器
 */
namespace app\admin\controller\system;

use app\admin\model\system\ConfigModel;
use app\admin\model\system\IpBlacklistModel;
use app\admin\model\system\MenuModel;
use core\basic\Config;
use core\basic\Controller;
use core\basic\Db;
use core\basic\IpBlacklist;

/**
 * 提供黑名单、规则和基础设置的后台管理入口。
 */
class IpBlacklistController extends Controller
{
    private $model;

    private $configModel;

    private $menuModel;

    /**
     * 初始化数据模型并确保表结构存在。
     */
    public function __construct()
    {
        $this->model = new IpBlacklistModel();
        $this->configModel = new ConfigModel();
        $this->menuModel = new MenuModel();
        $this->model->ensureStorage();
        $this->ensureMenuItems();
        $this->ensureConfigItems();
    }

    /**
     * 显示黑名单管理首页。
     *
     * @return void
     */
    public function index()
    {
        $this->assign('list', true);
        $this->assign('keyword', trim((string) get('keyword')));
        $this->assign('rule_keyword', trim((string) get('rule_keyword')));
        $this->assign('blacklists', $this->model->getBlacklistList(trim((string) get('keyword'))));
        $this->assign('rules', $this->model->getRuleList(trim((string) get('rule_keyword'))));
        $this->assign('settings', $this->getSettings());
        $this->assign('cache_handler', Config::get('cache.handler'));
        $this->assign('cache_supported', IpBlacklist::isCacheDriverSupported());
        $this->display('system/ipblacklist.html');
    }

    /**
     * 新增手动黑名单。
     *
     * @return void
     */
    public function add()
    {
        if (! $_POST) {
            error('请求方式错误！', -1);
        }

        $ip = $this->validateIp(post('ip'));
        $expireTime = $this->normalizeExpireTime(post('expire_time'));
        $data = array(
            'ip' => $ip,
            'source_type' => 'manual',
            'reason' => trim((string) post('reason')) ?: 'manual',
            'status' => post('status', 'int') ? '1' : '0',
            'expire_time' => $expireTime,
            'hit_count' => 0,
            'last_uri' => '',
            'remark' => trim((string) post('remark')),
            'create_user' => session('username'),
            'update_user' => session('username')
        );

        if ($this->model->addBlacklist($data)) {
            IpBlacklist::refreshRuntimeCache();
            $this->log('新增 IP 黑名单成功：' . $ip);
            success('新增成功！', url('/admin/IpBlacklist/index'));
        }

        error('新增失败！', -1);
    }

    /**
     * 修改黑名单记录或切换启用状态。
     *
     * @return void
     */
    public function mod()
    {
        if (! $id = get('id', 'int')) {
            error('传递的参数值错误！', -1);
        }

        if (($field = get('field', 'var')) && ! is_null($value = get('value', 'var'))) {
            if ($field !== 'status') {
                error('不支持的字段修改！', -1);
            }
            $data = array(
                'status' => $value ? '1' : '0',
                'update_user' => session('username')
            );
            if ($this->model->modBlacklist($id, $data)) {
                IpBlacklist::refreshRuntimeCache();
                $this->log('切换 IP 黑名单状态成功：' . $id);
                location(-1);
            }
            alert_back('修改失败！');
        }

        if ($_POST) {
            $ip = $this->validateIp(post('ip'));
            $expireTime = $this->normalizeExpireTime(post('expire_time'));
            $data = array(
                'ip' => $ip,
                'reason' => trim((string) post('reason')) ?: 'manual',
                'status' => post('status', 'int') ? '1' : '0',
                'expire_time' => $expireTime,
                'remark' => trim((string) post('remark')),
                'update_user' => session('username')
            );
            if ($this->model->modBlacklist($id, $data)) {
                IpBlacklist::refreshRuntimeCache();
                $this->log('修改 IP 黑名单成功：' . $id);
                success('修改成功！', url('/admin/IpBlacklist/index'));
            }
            location(-1);
        }

        $result = $this->model->getBlacklist($id);
        if (! $result) {
            error('编辑的黑名单记录已经不存在！', -1);
        }

        $this->assign('mod', true);
        $this->assign('blacklist', $result);
        $this->display('system/ipblacklist.html');
    }

    /**
     * 删除黑名单记录。
     *
     * @return void
     */
    public function del()
    {
        if (! $id = get('id', 'int')) {
            error('传递的参数值错误！', -1);
        }

        if ($this->model->delBlacklist($id)) {
            IpBlacklist::refreshRuntimeCache();
            $this->log('删除 IP 黑名单成功：' . $id);
            success('删除成功！', -1);
        }

        error('删除失败！', -1);
    }

    /**
     * 新增高风险规则。
     *
     * @return void
     */
    public function addRule()
    {
        if (! $_POST) {
            error('请求方式错误！', -1);
        }

        $data = array(
            'name' => $this->requireText(post('name'), '规则名称不能为空！'),
            'match_type' => $this->validateMatchType(post('match_type')),
            'pattern' => $this->requireText(post('pattern'), '规则内容不能为空！'),
            'risk_level' => 'high',
            'status' => post('status', 'int') ? '1' : '0',
            'description' => trim((string) post('description')),
            'create_user' => session('username'),
            'update_user' => session('username')
        );

        if ($this->model->addRule($data)) {
            IpBlacklist::refreshRuleCache();
            $this->log('新增 IP 黑名单规则成功：' . $data['name']);
            success('新增成功！', url('/admin/IpBlacklist/index' . get_tab('t3'), false));
        }

        error('新增失败！', -1);
    }

    /**
     * 修改规则或切换启用状态。
     *
     * @return void
     */
    public function modRule()
    {
        if (! $id = get('id', 'int')) {
            error('传递的参数值错误！', -1);
        }

        if (($field = get('field', 'var')) && ! is_null($value = get('value', 'var'))) {
            if ($field !== 'status') {
                error('不支持的字段修改！', -1);
            }
            $data = array(
                'status' => $value ? '1' : '0',
                'update_user' => session('username')
            );
            if ($this->model->modRule($id, $data)) {
                IpBlacklist::refreshRuleCache();
                $this->log('切换 IP 黑名单规则状态成功：' . $id);
                location(-1);
            }
            alert_back('修改失败！');
        }

        if ($_POST) {
            $data = array(
                'name' => $this->requireText(post('name'), '规则名称不能为空！'),
                'match_type' => $this->validateMatchType(post('match_type')),
                'pattern' => $this->requireText(post('pattern'), '规则内容不能为空！'),
                'risk_level' => 'high',
                'status' => post('status', 'int') ? '1' : '0',
                'description' => trim((string) post('description')),
                'update_user' => session('username')
            );
            if ($this->model->modRule($id, $data)) {
                IpBlacklist::refreshRuleCache();
                $this->log('修改 IP 黑名单规则成功：' . $id);
                success('修改成功！', url('/admin/IpBlacklist/index' . get_tab('t3'), false));
            }
            location(-1);
        }

        $result = $this->model->getRule($id);
        if (! $result) {
            error('编辑的规则已经不存在！', -1);
        }

        $this->assign('rulemod', true);
        $this->assign('rule', $result);
        $this->display('system/ipblacklist.html');
    }

    /**
     * 删除规则。
     *
     * @return void
     */
    public function delRule()
    {
        if (! $id = get('id', 'int')) {
            error('传递的参数值错误！', -1);
        }

        if ($this->model->delRule($id)) {
            IpBlacklist::refreshRuleCache();
            $this->log('删除 IP 黑名单规则成功：' . $id);
            success('删除成功！', -1);
        }

        error('删除失败！', -1);
    }

    /**
     * 保存基础设置。
     *
     * @return void
     */
    public function saveSettings()
    {
        if (! $_POST) {
            error('请求方式错误！', -1);
        }

        $status = post('ip_blacklist_status', 'int') ? '1' : '0';
        $autoEnable = post('ip_blacklist_auto_enable', 'int') ? '1' : '0';
        if (($status === '1' || $autoEnable === '1') && ! IpBlacklist::isCacheDriverSupported()) {
            error('当前缓存驱动为 file，不能启用 IP 黑名单功能！', -1);
        }

        $configItems = array(
            'ip_blacklist_status' => $status,
            'ip_blacklist_auto_enable' => $autoEnable,
            'ip_blacklist_auto_threshold' => $this->validatePositiveInt(post('ip_blacklist_auto_threshold', 'int'), 5, '高风险阈值必须大于 0！'),
            'ip_blacklist_auto_window' => $this->validatePositiveInt(post('ip_blacklist_auto_window', 'int'), 86400, '高风险统计窗口必须大于 0！'),
            'ip_blacklist_auto_block_ttl' => $this->validateNonNegativeInt(post('ip_blacklist_auto_block_ttl', 'int'), 86400, '自动封禁时长不能小于 0！')
        );

        foreach ($configItems as $name => $value) {
            $this->saveConfigItem($name, (string) $value);
        }

        path_delete(RUN_PATH . '/config');
        cache_config(true);
        IpBlacklist::refreshRuntimeCache();
        $this->log('保存 IP 黑名单设置成功！');
        success('保存成功！', url('/admin/IpBlacklist/index' . get_tab('t5'), false));
    }

    /**
     * 获取设置项并补默认值。
     *
     * @return array
     */
    private function getSettings()
    {
        return array(
            'ip_blacklist_status' => (string) (Config::get('ip_blacklist_status') === null ? '0' : Config::get('ip_blacklist_status')),
            'ip_blacklist_auto_enable' => (string) (Config::get('ip_blacklist_auto_enable') === null ? '1' : Config::get('ip_blacklist_auto_enable')),
            'ip_blacklist_auto_threshold' => (string) (Config::get('ip_blacklist_auto_threshold') === null ? '5' : Config::get('ip_blacklist_auto_threshold')),
            'ip_blacklist_auto_window' => (string) (Config::get('ip_blacklist_auto_window') === null ? '86400' : Config::get('ip_blacklist_auto_window')),
            'ip_blacklist_auto_block_ttl' => (string) (Config::get('ip_blacklist_auto_block_ttl') === null ? '86400' : Config::get('ip_blacklist_auto_block_ttl'))
        );
    }

    /**
     * 保证配置项存在，避免首次进入页面没有默认值。
     *
     * @return void
     */
    private function ensureConfigItems()
    {
        $defaults = array(
            'ip_blacklist_status' => '0',
            'ip_blacklist_auto_enable' => '1',
            'ip_blacklist_auto_threshold' => '5',
            'ip_blacklist_auto_window' => '86400',
            'ip_blacklist_auto_block_ttl' => '86400'
        );

        $changed = false;
        foreach ($defaults as $name => $value) {
            if (! $this->configModel->checkConfig("name='" . escape_string($name) . "'")) {
                $this->configModel->addConfig(array(
                    'name' => $name,
                    'value' => $value,
                    'type' => 2,
                    'sorting' => 255,
                    'description' => ''
                ));
                $changed = true;
            }
        }

        if ($changed) {
            path_delete(RUN_PATH . '/config');
            cache_config(true);
        }
    }

    /**
     * 保证后台菜单、动作权限和管理员默认授权存在。
     *
     * @return void
     */
    private function ensureMenuItems()
    {
        $menuCode = 'M134';
        $menuUrl = '/admin/IpBlacklist/index';
        $menuData = array(
            'mcode' => $menuCode,
            'pcode' => 'M110',
            'name' => 'IP黑名单',
            'url' => $menuUrl,
            'sorting' => 404,
            'status' => '1',
            'shortcut' => '0',
            'ico' => 'fa-ban',
            'create_user' => 'admin',
            'update_user' => 'admin'
        );
        $actions = array(
            'index',
            'add',
            'mod',
            'del',
            'addRule',
            'modRule',
            'delRule',
            'saveSettings'
        );

        if (! $this->menuModel->checkMenu(array('mcode' => $menuCode))) {
            $this->menuModel->addMenu($menuData, $actions);
        }

        $this->ensureMenuActionItems($menuCode, $actions);
        $this->ensureRoleLevelItems('R101', $menuUrl, $actions);
    }

    /**
     * 补齐菜单动作关联，避免仅有菜单主记录时缺少按钮权限。
     *
     * @param string $menuCode 菜单编码
     * @param array $actions 动作列表
     * @return void
     */
    private function ensureMenuActionItems($menuCode, array $actions)
    {
        foreach ($actions as $action) {
            $escapedMenuCode = escape_string($menuCode);
            $escapedAction = escape_string($action);
            if (! Db::table('ay_menu_action')->where("mcode='$escapedMenuCode'")->where("action='$escapedAction'")->find()) {
                Db::table('ay_menu_action')->insert(array(
                    'mcode' => $menuCode,
                    'action' => $action
                ));
            }
        }
    }

    /**
     * 补齐管理员角色默认授权，确保菜单可见且接口可访问。
     *
     * @param string $roleCode 角色编码
     * @param string $menuUrl 菜单 URL
     * @param array $actions 动作列表
     * @return void
     */
    private function ensureRoleLevelItems($roleCode, $menuUrl, array $actions)
    {
        $levels = array($menuUrl);
        foreach ($actions as $action) {
            $levels[] = '/admin/IpBlacklist/' . $action;
        }

        foreach ($levels as $level) {
            $escapedRoleCode = escape_string($roleCode);
            $escapedLevel = escape_string($level);
            if (! Db::table('ay_role_level')->where("rcode='$escapedRoleCode'")->where("level='$escapedLevel'")->find()) {
                Db::table('ay_role_level')->insert(array(
                    'rcode' => $roleCode,
                    'level' => $level
                ));
            }
        }
    }

    /**
     * 保存单个配置项。
     *
     * @param string $name 配置名
     * @param string $value 配置值
     * @return void
     */
    private function saveConfigItem($name, $value)
    {
        if ($this->configModel->checkConfig("name='" . escape_string($name) . "'")) {
            $this->configModel->modValue($name, $value);
        } else {
            $this->configModel->addConfig(array(
                'name' => $name,
                'value' => $value,
                'type' => 2,
                'sorting' => 255,
                'description' => ''
            ));
        }
    }

    /**
     * 校验 IP 输入。
     *
     * @param string $ip 原始 IP
     * @return string
     */
    private function validateIp($ip)
    {
        $ip = trim((string) $ip);
        if ($ip === '' || ! filter_var($ip, FILTER_VALIDATE_IP)) {
            alert_back('请输入合法的 IP 地址！');
        }
        return $ip;
    }

    /**
     * 规范化过期时间输入。
     *
     * @param string $expireTime 原始时间
     * @return string
     */
    private function normalizeExpireTime($expireTime)
    {
        $expireTime = trim((string) $expireTime);
        if ($expireTime === '') {
            return '';
        }
        $timestamp = strtotime($expireTime);
        if ($timestamp === false) {
            alert_back('过期时间格式不正确！');
        }
        return date('Y-m-d H:i:s', $timestamp);
    }

    /**
     * 校验规则匹配类型。
     *
     * @param string $matchType 匹配类型
     * @return string
     */
    private function validateMatchType($matchType)
    {
        $matchType = trim((string) $matchType);
        $allowTypes = array('exact', 'prefix', 'contains', 'regex');
        if (! in_array($matchType, $allowTypes)) {
            alert_back('规则匹配类型不正确！');
        }
        return $matchType;
    }

    /**
     * 校验必填文本。
     *
     * @param string $value 输入值
     * @param string $message 错误消息
     * @return string
     */
    private function requireText($value, $message)
    {
        $value = trim((string) $value);
        if ($value === '') {
            alert_back($message);
        }
        return $value;
    }

    /**
     * 校验正整数配置值。
     *
     * @param int $value 输入值
     * @param int $default 默认值
     * @param string $message 错误消息
     * @return int
     */
    private function validatePositiveInt($value, $default, $message)
    {
        $value = (int) $value;
        if ($value <= 0) {
            if ($default > 0 && post('submit') !== null) {
                alert_back($message);
            }
            return $default;
        }
        return $value;
    }

    /**
     * 校验非负整数配置值。
     *
     * @param int $value 输入值
     * @param int $default 默认值
     * @param string $message 错误消息
     * @return int
     */
    private function validateNonNegativeInt($value, $default, $message)
    {
        $value = (int) $value;
        if ($value < 0) {
            if ($default >= 0 && post('submit') !== null) {
                alert_back($message);
            }
            return $default;
        }
        return $value;
    }
}
