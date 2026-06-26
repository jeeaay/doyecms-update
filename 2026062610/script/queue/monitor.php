<?php
/**
 * DoyeCMS AI队列处理器监控脚本
 * 
 * 功能特性:
 * - 进程状态检查
 * - 健康状态监控
 * - 性能指标收集
 * - 自动重启机制
 * - 报警通知
 * 
 * 使用方法:
 * php monitor.php [--check|--restart|--status|--health]
 * 
 * @author DoyeCMS
 * @version 1.0
 */

// 设置错误报告
error_reporting(E_ALL);
ini_set('display_errors', 1);

// 设置时区
date_default_timezone_set('Asia/Shanghai');

// 定义常量
define('SCRIPT_DIR', __DIR__);
define('ROOT_DIR', dirname(__DIR__));
define('PROJECT_ROOT', realpath(__DIR__ . '/../../'));
define('CORE_PATH', PROJECT_ROOT . '/core');
define('APP_PATH', PROJECT_ROOT . '/apps');
define('CONF_PATH', PROJECT_ROOT . '/config');
define('RUN_PATH', PROJECT_ROOT . '/runtime');
define('LOG_DIR', ROOT_DIR . '/logs');
define('PID_FILE', SCRIPT_DIR . '/queue_processor.pid');
define('MONITOR_LOG', LOG_DIR . '/monitor_' . date('Y-m-d') . '.log');

require_once CORE_PATH . '/function/handle.php';
require_once CORE_PATH . '/basic/Config.php';

/**
 * 队列处理器监控类
 */
class QueueProcessorMonitor
{
    private $pidFile;
    private $logFile;
    private $serviceName = 'DoyeCMS_Queue_Processor';
    
    /**
     * 构造函数
     */
    public function __construct()
    {
        $this->pidFile = PID_FILE;
        $this->logFile = MONITOR_LOG;
        
        // 创建日志目录
        if (!is_dir(LOG_DIR)) {
            mkdir(LOG_DIR, 0755, true);
        }
    }
    
    /**
     * 记录日志
     */
    private function log($message, $level = 'INFO')
    {
        $timestamp = date('Y-m-d H:i:s');
        $logEntry = "[{$timestamp}] [{$level}] {$message}" . PHP_EOL;
        file_put_contents($this->logFile, $logEntry, FILE_APPEND | LOCK_EX);
        
        // 同时输出到控制台
        echo $logEntry;
    }
    
    /**
     * 检查进程是否运行
     */
    public function isProcessRunning()
    {
        // 检查PID文件
        if (!file_exists($this->pidFile)) {
            return false;
        }
        
        $pid = trim(file_get_contents($this->pidFile));
        if (empty($pid) || !is_numeric($pid)) {
            return false;
        }
        
        // Windows系统检查进程
        if (PHP_OS_FAMILY === 'Windows') {
            $output = [];
            exec("tasklist /FI \"PID eq {$pid}\" /FO CSV 2>nul", $output);
            return count($output) > 1; // 第一行是标题
        } else {
            // Linux系统检查进程
            return file_exists("/proc/{$pid}");
        }
    }
    
    /**
     * 检查Windows服务状态
     */
    public function checkWindowsService()
    {
        if (PHP_OS_FAMILY !== 'Windows') {
            return null;
        }
        
        $output = [];
        $returnVar = 0;
        exec("nssm status \"{$this->serviceName}\" 2>nul", $output, $returnVar);
        
        if ($returnVar === 0 && !empty($output)) {
            return trim($output[0]);
        }
        
        return null;
    }
    
    /**
     * 获取进程信息
     */
    public function getProcessInfo()
    {
        if (!$this->isProcessRunning()) {
            return null;
        }
        
        $pid = trim(file_get_contents($this->pidFile));
        $info = [
            'pid' => $pid,
            'status' => 'running',
            'memory' => 0,
            'cpu' => 0,
            'uptime' => 0
        ];
        
        if (PHP_OS_FAMILY === 'Windows') {
            // Windows系统获取进程信息
            $output = [];
            exec("wmic process where \"ProcessId={$pid}\" get WorkingSetSize,PageFileUsage /format:csv 2>nul", $output);
            
            foreach ($output as $line) {
                if (strpos($line, $pid) !== false) {
                    $parts = explode(',', $line);
                    if (count($parts) >= 3) {
                        $info['memory'] = intval($parts[1]) / 1024 / 1024; // MB
                    }
                    break;
                }
            }
        } else {
            // Linux系统获取进程信息
            if (file_exists("/proc/{$pid}/stat")) {
                $stat = file_get_contents("/proc/{$pid}/stat");
                $statArray = explode(' ', $stat);
                
                // 获取内存使用（RSS * 页面大小）
                $rss = intval($statArray[23]);
                $pageSize = 4096; // 通常是4KB
                $info['memory'] = ($rss * $pageSize) / 1024 / 1024; // MB
            }
        }
        
        // 计算运行时间
        if (file_exists($this->pidFile)) {
            $info['uptime'] = time() - filemtime($this->pidFile);
        }
        
        return $info;
    }
    
    /**
     * 检查队列健康状态
     */
    public function checkQueueHealth()
    {
        try {
            if (! extension_loaded('redis')) {
                throw new \Exception('PHP运行环境未安装redis扩展！');
            }
            $config = \core\basic\Config::get('redis');
            $host = isset($config['host']) ? $config['host'] : '127.0.0.1';
            $port = isset($config['port']) ? (int) $config['port'] : 6379;
            $timeout = isset($config['timeout']) ? (float) $config['timeout'] : 3600;
            $password = isset($config['password']) ? $config['password'] : '';
            $select = isset($config['select']) ? (int) $config['select'] : 0;
            $prefix = isset($config['prefix']) ? $config['prefix'] : '';

            $redis = new \Redis();
            $redis->connect($host, $port, $timeout);
            if ($password !== '') {
                $redis->auth($password);
            }
            if ($select > 0) {
                $redis->select($select);
            }
            if ($prefix !== '') {
                $redis->setOption(\Redis::OPT_PREFIX, $prefix);
            }
            
            // 检查队列长度
            $queueLength = $redis->lLen('ai_task_queue');
            
            // 检查失败队列长度
            $failedLength = $redis->lLen('ai_task_failed');
            
            $redis->close();
            
            return [
                'queue_length' => $queueLength,
                'failed_length' => $failedLength,
                'redis_connected' => true
            ];
            
        } catch (\Exception $e) {
            $this->log("Redis连接失败: " . $e->getMessage(), 'ERROR');
            return [
                'queue_length' => -1,
                'failed_length' => -1,
                'redis_connected' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * 执行健康检查
     */
    public function performHealthCheck()
    {
        $this->log("开始健康检查");
        
        $results = [
            'timestamp' => date('Y-m-d H:i:s'),
            'process' => $this->getProcessInfo(),
            'service' => $this->checkWindowsService(),
            'queue' => $this->checkQueueHealth()
        ];
        
        // 分析结果
        $issues = [];
        
        // 检查进程状态
        if (!$results['process']) {
            $issues[] = '队列处理器进程未运行';
        } elseif ($results['process']['memory'] > 200) {
            $issues[] = '内存使用过高: ' . round($results['process']['memory'], 2) . 'MB';
        }
        
        // 检查服务状态
        if ($results['service'] && $results['service'] !== 'SERVICE_RUNNING') {
            $issues[] = '服务状态异常: ' . $results['service'];
        }
        
        // 检查队列状态
        if (!$results['queue']['redis_connected']) {
            $issues[] = 'Redis连接失败';
        } elseif ($results['queue']['queue_length'] > 1000) {
            $issues[] = '队列积压严重: ' . $results['queue']['queue_length'] . '个任务';
        }
        
        // 输出结果
        if (empty($issues)) {
            $this->log("健康检查通过");
            if ($results['process']) {
                $this->log(sprintf(
                    "进程状态: PID=%s, 内存=%.2fMB, 运行时间=%s",
                    $results['process']['pid'],
                    $results['process']['memory'],
                    $this->formatDuration($results['process']['uptime'])
                ));
            }
            if ($results['queue']['redis_connected']) {
                $this->log(sprintf(
                    "队列状态: 待处理=%d, 失败=%d",
                    $results['queue']['queue_length'],
                    $results['queue']['failed_length']
                ));
            }
        } else {
            $this->log("健康检查发现问题:", 'WARNING');
            foreach ($issues as $issue) {
                $this->log("- " . $issue, 'WARNING');
            }
        }
        
        return $results;
    }
    
    /**
     * 重启队列处理器
     */
    public function restartProcessor()
    {
        $this->log("准备重启队列处理器");
        
        if (PHP_OS_FAMILY === 'Windows') {
            // Windows服务重启
            $serviceStatus = $this->checkWindowsService();
            if ($serviceStatus) {
                $this->log("重启Windows服务");
                exec("nssm restart \"{$this->serviceName}\" 2>nul", $output, $returnVar);
                
                if ($returnVar === 0) {
                    $this->log("服务重启成功");
                    return true;
                } else {
                    $this->log("服务重启失败", 'ERROR');
                    return false;
                }
            }
        }
        
        // 直接进程重启
        if ($this->isProcessRunning()) {
            $pid = trim(file_get_contents($this->pidFile));
            $this->log("终止进程: {$pid}");
            
            if (PHP_OS_FAMILY === 'Windows') {
                exec("taskkill /PID {$pid} /F 2>nul");
            } else {
                exec("kill -TERM {$pid}");
            }
            
            sleep(3); // 等待进程结束
        }
        
        // 启动新进程
        $this->log("启动新进程");
        $scriptPath = SCRIPT_DIR . '/queue_processor.php';
        
        if (PHP_OS_FAMILY === 'Windows') {
            $cmd = "start /B php \"" . $scriptPath . "\"";
        } else {
            $cmd = "nohup php '" . $scriptPath . "' > /dev/null 2>&1 &";
        }
        
        exec($cmd);
        sleep(2); // 等待启动
        
        if ($this->isProcessRunning()) {
            $this->log("进程重启成功");
            return true;
        } else {
            $this->log("进程重启失败", 'ERROR');
            return false;
        }
    }
    
    /**
     * 格式化时间长度
     */
    private function formatDuration($seconds)
    {
        $hours = floor($seconds / 3600);
        $minutes = floor(($seconds % 3600) / 60);
        $seconds = $seconds % 60;
        return sprintf('%02d:%02d:%02d', $hours, $minutes, $seconds);
    }
    
    /**
     * 显示状态信息
     */
    public function showStatus()
    {
        echo "\n=== DoyeCMS AI队列处理器状态 ===\n\n";
        
        $processInfo = $this->getProcessInfo();
        if ($processInfo) {
            echo "进程状态: 运行中\n";
            echo "进程ID: {$processInfo['pid']}\n";
            echo "内存使用: " . round($processInfo['memory'], 2) . "MB\n";
            echo "运行时间: " . $this->formatDuration($processInfo['uptime']) . "\n";
        } else {
            echo "进程状态: 未运行\n";
        }
        
        $serviceStatus = $this->checkWindowsService();
        if ($serviceStatus) {
            echo "服务状态: {$serviceStatus}\n";
        }
        
        $queueHealth = $this->checkQueueHealth();
        if ($queueHealth['redis_connected']) {
            echo "队列长度: {$queueHealth['queue_length']}\n";
            echo "失败任务: {$queueHealth['failed_length']}\n";
        } else {
            echo "Redis状态: 连接失败\n";
        }
        
        echo "\n";
    }
}

// 主程序
if (php_sapi_name() === 'cli') {
    $monitor = new QueueProcessorMonitor();
    
    $action = $argv[1] ?? '--status';
    
    switch ($action) {
        case '--check':
        case '--health':
            $monitor->performHealthCheck();
            break;
            
        case '--restart':
            $monitor->restartProcessor();
            break;
            
        case '--status':
        default:
            $monitor->showStatus();
            break;
    }
} else {
    echo "此脚本只能在命令行模式下运行\n";
}
