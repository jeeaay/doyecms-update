<?php
/**
 * @copyright (C)2016-2099 Hnaoyun Inc.
 * @author XingMeng
 * @email hnxsh@foxmail.com
 * @date 2017年2月7日
 *  系统环境初始化
 */

/**
 * 安全防护：拦截包含模板注入标记的恶意请求
 * 触发条件：URL/路径中包含 "{pboot:", "{if:", "{php:" 等模板引擎指令
 * 设计原则：
 * - 在初始化最早阶段执行，避免进入路由和模板解析
 * - 不依赖框架常量（如 ROOT_PATH/RUN_PATH），自行计算项目根路径
 * - 日志落盘到 runtime/security.log，便于溯源
 */

/**
 * 将输入内容进行规范化，统一大小写、解码常见编码形式
 * 单一职责：对字符串做标准化以便进行安全匹配
 * 异常处理：若输入不是字符串则返回空字符串
 * @param mixed $input 待处理的原始输入
 * @return string 规范化后的字符串
 */
function normalizeRequestString($input)
{
    if (!is_string($input)) {
        return '';
    }
    $raw = $input;
    // 统一为小写，便于不区分大小写匹配
    $lower = strtolower($raw);
    // 先尝试 URL 解码（处理 %7B 这类编码）
    $decodedOnce = urldecode($lower);
    // 处理可能的双重编码
    $decodedTwice = urldecode($decodedOnce);
    // 处理 \xNN 十六进制转义序列（例如 \x7b -> {）
    $normalized = preg_replace_callback('/\\x([0-9a-f]{2})/i', function ($m) {
        return chr(hexdec($m[1]));
    }, $decodedTwice);
    // 处理全角字符到半角（特别是括号和大括号）
    $normalized = mb_convert_kana($normalized, 'as');
    return $normalized;
}

/**
 * 判断字符串中是否包含模板注入危险标记
 * 单一职责：对给定内容进行危险特征判断
 * 异常处理：输入为空时返回 false
 * @param string $content 规范化后的字符串
 * @return bool 是否包含危险标记
 */
function containsTemplateInjectionMarker($content)
{
    if ($content === '') {
        return false;
    }
    // 仅在出现模板指令模式时拦截，避免误杀普通大括号
    $dangerPatterns = [
        '{pboot:',    // Pboot 模板指令
        '{/pboot:',   // Pboot 结束指令（防御冗余）
        '{if:',       // 条件指令模式
        '{php:',      // 执行 PHP 的指令模式
    ];
    foreach ($dangerPatterns as $pattern) {
        if (strpos($content, $pattern) !== false) {
            return true;
        }
    }
    return false;
}

/**
 * 写入安全日志到 runtime/security.log
 * 单一职责：可靠地将安全事件写入日志文件
 * 异常处理：任何文件系统异常捕获后静默，不影响主流程
 * @param string $message 日志消息内容
 * @return void
 */
function writeSecurityLog($message)
{
    try {
        // 计算项目根目录（init.php 位于 core/ 下）
        $projectRootPath = str_replace('\\', '/', dirname(__DIR__));
        $runtimeDir = $projectRootPath . '/runtime';
        $logFilePath = $runtimeDir . '/security.log';
        if (!is_dir($runtimeDir)) {
            // 递归创建 runtime 目录
            @mkdir($runtimeDir, 0777, true);
        }
        $ip = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '-';
        if (function_exists('get_user_ip')) {
            $resolvedIp = get_user_ip();
            if ($resolvedIp !== '') {
                $ip = $resolvedIp;
            }
        }
        $method = isset($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : '-';
        $uri = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '-';
        $time = date('Y-m-d H:i:s');
        $logLine = "[$time] ip=$ip method=$method uri=$uri msg=" . str_replace("\n", ' ', $message) . "\n";
        @file_put_contents($logFilePath, $logLine, FILE_APPEND);
    } catch (Throwable $e) {
        // 日志写入失败时不影响主流程
    }
}

/**
 * 在初始化的最早阶段拦截恶意请求
 * 单一职责：收集各来源的原始路径/URL，标准化并判断是否命中危险标记，若命中则终止
 * 异常处理：所有步骤均有保护，不抛出致命错误；拦截时返回 403
 * @return void
 */
function blockIfMaliciousTemplateInjection()
{
    // 收集可能包含路径/URL 的来源（保持原始字符串以便日志记录）
    $rawRequestList = [
        isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '',
        isset($_SERVER['PATH_INFO']) ? $_SERVER['PATH_INFO'] : '',
        isset($_SERVER['QUERY_STRING']) ? $_SERVER['QUERY_STRING'] : '',
        isset($_SERVER['REDIRECT_URL']) ? $_SERVER['REDIRECT_URL'] : '',
        isset($_SERVER['ORIG_PATH_INFO']) ? $_SERVER['ORIG_PATH_INFO'] : '',
        isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '',
    ];

    // 同时检查原始内容与规范化内容，尽量覆盖编码与混淆场景
    foreach ($rawRequestList as $raw) {
        if ($raw === '') {
            continue;
        }
        $normalizedContent = normalizeRequestString($raw);
        if (containsTemplateInjectionMarker($raw) || containsTemplateInjectionMarker($normalizedContent)) {
            writeSecurityLog('Blocked template injection: raw=' . $raw . ' normalized=' . $normalizedContent);
            // 返回 403 以表明拒绝访问
            if (!headers_sent()) {
                header('Content-Type: text/html; charset=utf-8');
                header('X-Doye-Defense: template-injection');
            }
            http_response_code(403);
            echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>403 Forbidden</title></head><body><h1>403 Forbidden</h1><p>请求被安全策略拦截：检测到模板指令。</p></body></html>';
            exit;
        }
    }
}

// 在任何常量定义之前立即执行拦截
blockIfMaliciousTemplateInjection();

/**
 * 加载 .env 文件到环境变量
 * 解析项目根目录下的 .env 文件，将每一行 KEY=VALUE 设置为 PHP 环境变量
 * 跳过注释行（# 开头）和空行
 * @return void
 */
function loadDotEnv()
{
    $envFile = dirname(__DIR__) . '/.env';
    if (!file_exists($envFile)) {
        return;
    }
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        // 跳过注释行
        if ($line === '' || strpos($line, '#') === 0) {
            continue;
        }
        // 解析 KEY=VALUE
        if (strpos($line, '=') !== false) {
            list($key, $value) = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);
            // 去除值两端的引号
            if ($value !== '' && (($value[0] === '"' && substr($value, -1) === '"') || ($value[0] === "'" && substr($value, -1) === "'"))) {
                $value = substr($value, 1, -1);
            }
            // 仅当环境变量尚未设置时才写入，避免覆盖服务器级配置
            if (getenv($key) === false) {
                putenv($key . '=' . $value);
            }
        }
    }
}
loadDotEnv();

use core\basic\Config;
use core\basic\Basic;
use core\basic\Check;

// 启动程序时间
define('START_TIME', microtime(true));

// 设置字符集编码、IE文档模式
header('Content-Type:text/html; charset=utf-8');
header('X-UA-Compatible:IE=edge,chrome=1');
header('X-Powered-By:DoyeCMS');

// 设置中国时区
date_default_timezone_set('Asia/Shanghai');

// 定义站点虚拟目录（自适应获取多级目录），此处保证DOCUMENT_ROOT、 __DIR__路径的一致性
$file_path = str_replace('\\', '/', dirname(__DIR__)); // 系统部署路径
if (!defined('SITE_DIR')) {
    if (isset($_SERVER['PATH_INFO'])) {
        $_SERVER['SCRIPT_NAME'] = preg_replace('{' . $_SERVER['PATH_INFO'] . '$}', '', $_SERVER['SCRIPT_NAME']); // 替换掉PATH_INFO,避免部分服务商路径不对
    }
    $script_path = explode('/', $_SERVER['SCRIPT_NAME']); // 当前执行文件路径
    if (count($script_path) > 2) { // 根目录下"/index.php"长度为2
        if (! ! $path_pos = strripos($file_path, '/' . $script_path[1])) {
            define('SITE_DIR', substr($file_path, $path_pos));
            $_SERVER['SCRIPT_NAME'] = preg_replace('{^' . SITE_DIR . '}i', SITE_DIR, $_SERVER['SCRIPT_NAME']); // 规避大小写URL问题
        } else {
            define('SITE_DIR', '');
        }
    } else {
        define('SITE_DIR', '');
    }
}

// 定义入口文件地址
if (!defined('SITE_INDEX_DIR')) {
    $script_dir = dirname($_SERVER['SCRIPT_NAME']);
    if ($script_dir == '\\' || $script_dir == '/') {
        define('SITE_INDEX_DIR', '');
    } else {
        define('SITE_INDEX_DIR', dirname($_SERVER['SCRIPT_NAME']));
    }
}

// 定义网站部署根路径
define('ROOT_PATH', $file_path);

// 定义站点物理路径
define('DOC_PATH', preg_replace('{' . SITE_DIR . '$}i', '', ROOT_PATH));
$_SERVER['DOCUMENT_ROOT'] = DOC_PATH; // 统一该环境变量值
                                      
// 定义内核文件目录
define('CORE_DIR', SITE_DIR . '/' . basename(__DIR__));

// 定义内核文件物理路径
define('CORE_PATH', DOC_PATH . CORE_DIR);

// 定义应用存放物理路径
define('APP_PATH', ROOT_PATH . '/apps');

// 定义应用文件目录
define('APP_DIR', str_replace(DOC_PATH, '', APP_PATH));

// 定义应用运行文件路径
defined('RUN_PATH') ?: define('RUN_PATH', ROOT_PATH . '/runtime');

// 定义公共配置文件路径
defined('CONF_PATH') ?: define('CONF_PATH', ROOT_PATH . '/config');

// 定义静态文件目录
defined('STATIC_DIR') ?: define('STATIC_DIR', SITE_DIR . '/static');

// 载入基础函数库
require CORE_PATH . '/function/handle.php';
require CORE_PATH . '/function/helper.php';
require CORE_PATH . '/function/file.php';

// 载入基础类文件
require CORE_PATH . '/basic/Basic.php';

// 注册自动加载函数
spl_autoload_register('core\basic\Basic::autoLoad', true, true);

// 在自动加载可用后执行 IP 黑名单入口拦截。
\core\basic\IpBlacklist::blockCurrentRequest();

// 设置错误处理函数
set_error_handler('core\basic\Basic::errorHandler');

// 设置异常捕获函数
set_exception_handler('core\basic\Basic::exceptionHandler');

// 注册异常中止函数
register_shutdown_function('core\basic\Basic::shutdownFunction');

// 调试模式设置错误报告级别并进行环境检查
if (Config::get('debug')) {
    ini_set('display_errors', 1); // 开启显示错误
    error_reporting(E_ALL ^ E_WARNING ^ E_NOTICE);
} else {
    error_reporting(E_ERROR);
}

// 定义版本常量
define('CORE_VERSION', Config::get('core_version'));
define('APP_VERSION', Config::get('app_version'));
define('RELEASE_TIME', Config::get('release_time'));

// 环境检查
Check::checkPHP();//检查php版本
Check::checkApp(); // 检查APP配置
Check::checkBasicDir(); // 检查基础目录
Check::checkSession();//检查session文件夹
Basic::setSessionHandler();// 会话处理程序选择
