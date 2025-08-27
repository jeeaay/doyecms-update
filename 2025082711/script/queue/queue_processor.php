<?php
/**
 * AI任务队列处理脚本
 * 用于后台持续处理AI接口请求
 * 运行方式: php queue_processor.php
 * 
 * 功能特性:
 * - 内存管理和泄漏防护
 * - 进程锁防止重复运行
 * - 详细日志记录
 * - 优雅重启和信号处理
 * - 健康检查和监控
 */

// 设置脚本运行环境
define('ROOT_PATH', realpath(__DIR__ . '/../../'));
define('CORE_PATH', ROOT_PATH . '/core');
define('APP_PATH', ROOT_PATH . '/apps');
define('CONF_PATH', ROOT_PATH . '/config');
define('RUN_PATH', ROOT_PATH . '/runtime');
define('CACHE_PATH', RUN_PATH . '/cache');
define('DOC_PATH', ROOT_PATH . '/apps/home/view/default');
define('STATIC_DIR', '/static');
define('STATIC_PATH', ROOT_PATH . STATIC_DIR);
define('SCRIPT_PATH', __DIR__);
define('LOG_PATH', SCRIPT_PATH . '/logs');
define('PID_FILE', SCRIPT_PATH . '/queue_processor.pid');

// 设置内存限制和时区
ini_set('memory_limit', '512M');
date_default_timezone_set('Asia/Shanghai');

// 引入核心文件
require_once CORE_PATH . '/function/handle.php';
require_once CORE_PATH . '/basic/Basic.php';
require_once CORE_PATH . '/basic/Config.php';
require_once CORE_PATH . '/basic/RedisQueue.php';

// 注册自动加载
spl_autoload_register('core\\basic\\Basic::autoLoad');

// 设置错误处理
set_error_handler('core\\basic\\Basic::errorHandler');
set_exception_handler('core\\basic\\Basic::exceptionHandler');
register_shutdown_function('core\\basic\\Basic::shutdownFunction');

use core\basic\RedisQueue;
use core\basic\Config;

/**
 * AI队列处理器类
 * 提供稳定的长期运行支持
 */
class AiQueueProcessor
{
    private $queue;
    private $isRunning = true;
    private $maxExecutionTime = 6 * 60 * 60; // 最大运行时间6小时
    private $startTime;
    private $logFile;
    private $pidFile;
    private $shouldReload = false;
    
    // 内存管理
    private $maxMemoryUsage = 400 * 1024 * 1024; // 400MB内存限制
    private $lastMemoryCheck = 0;
    
    // 统计信息
    private $processedCount = 0;
    private $errorCount = 0;
    
    /**
     * 构造函数
     * 初始化队列处理器，设置日志和进程锁
     */
    public function __construct()
    {
        $this->startTime = time();
        $this->lastMemoryCheck = time();
        $this->pidFile = PID_FILE;
        
        // 内存管理设置 (默认128MB)
         $this->maxMemoryUsage = 128 * 1024 * 1024;
         $this->lastMemoryCheck = time();
        
        // 创建日志目录
        if (!is_dir(LOG_PATH)) {
            mkdir(LOG_PATH, 0755, true);
        }
        
        // 设置日志文件
        $this->logFile = LOG_PATH . '/queue_processor_' . date('Y-m-d') . '.log';
        
        // 检查进程锁
        $this->checkProcessLock();
        
        // 创建PID文件
        $this->createPidFile();
        
        // 初始化队列
        $this->queue = new RedisQueue('ai_queue');
        
        // 注册信号处理器，优雅停止
        if (function_exists('pcntl_signal')) {
            pcntl_signal(SIGTERM, [$this, 'handleSignal']);
            pcntl_signal(SIGINT, [$this, 'handleSignal']);
            pcntl_signal(SIGUSR1, [$this, 'handleReload']); // 重载信号
        }
        
        $this->log('AI队列处理器启动', 'INFO');
    }
    
    /**
     * 检查进程锁，防止重复运行
     */
    private function checkProcessLock()
    {
        if (file_exists($this->pidFile)) {
            $pid = trim(file_get_contents($this->pidFile));
            if ($pid && $this->isProcessRunning($pid)) {
                $this->log("队列处理器已在运行 (PID: {$pid})", 'ERROR');
                exit(1);
            }
        }
    }
    
    /**
     * 检查进程是否正在运行
     */
    private function isProcessRunning($pid)
    {
        if (PHP_OS_FAMILY === 'Windows') {
            $output = shell_exec("tasklist /FI \"PID eq {$pid}\" 2>NUL");
            return strpos($output, (string)$pid) !== false;
        } else {
            return file_exists("/proc/{$pid}");
        }
    }
    
    /**
     * 创建PID文件
     */
    private function createPidFile()
    {
        file_put_contents($this->pidFile, getmypid());
        register_shutdown_function([$this, 'removePidFile']);
    }
    
    /**
     * 删除PID文件
     */
    public function removePidFile()
    {
        if (file_exists($this->pidFile)) {
            unlink($this->pidFile);
        }
    }
    
    /**
     * 日志记录方法
     */
    private function log($message, $level = 'INFO')
    {
        $timestamp = date('Y-m-d H:i:s');
        $logMessage = "[{$timestamp}] [{$level}] {$message}" . PHP_EOL;
        
        // 写入日志文件
        file_put_contents($this->logFile, $logMessage, FILE_APPEND | LOCK_EX);
        
        // 同时输出到控制台
        echo $logMessage;
    }
    
    /**
     * 信号处理器 - 优雅停止
     */
    public function handleSignal($signal)
    {
        $this->log("接收到停止信号 ({$signal})，正在优雅关闭...", 'INFO');
        $this->isRunning = false;
    }
    
    /**
     * 信号处理器 - 重载配置
     */
    public function handleReload($signal)
    {
        $this->log("接收到重载信号 ({$signal})，重新加载配置...", 'INFO');
        // 这里可以添加重载配置的逻辑
    }
    
    /**
     * 开始处理队列
     * 主循环，包含内存监控和健康检查
     */
    public function start()
    {
        $this->log("开始处理队列，PID: " . getmypid(), 'INFO');
        
        while ($this->isRunning && $this->shouldContinueRunning()) {
            try {
                // 检查信号
                if (function_exists('pcntl_signal_dispatch')) {
                    pcntl_signal_dispatch();
                }
                
                // 内存检查
                $this->checkMemoryUsage();
                
                // 健康检查
                $this->performHealthCheck();
                
                // 从队列获取任务，阻塞等待5秒
                $task = $this->queue->popTask(5);
                
                if ($task) {
                    $this->log("处理任务: {$task['task_id']}", 'INFO');
                    $this->processTask($task);
                    $this->processedCount++;
                } else {
                    // 没有任务时短暂休息
                    sleep(1);
                }
                
            } catch (Exception $e) {
                $this->errorCount++;
                $this->log("处理异常: " . $e->getMessage(), 'ERROR');
                $this->log("异常堆栈: " . $e->getTraceAsString(), 'DEBUG');
                
                // 如果错误过多，考虑重启
                if ($this->errorCount > 10) {
                    $this->log("错误次数过多，准备重启", 'WARNING');
                    $this->isRunning = false;
                }
                
                sleep(5); // 异常时等待5秒再继续
            } catch (Error $e) {
                $this->log("严重错误: " . $e->getMessage(), 'CRITICAL');
                $this->log("错误堆栈: " . $e->getTraceAsString(), 'DEBUG');
                $this->isRunning = false;
            }
        }
        
        $this->shutdown();
    }
    
    /**
     * 检查是否应该继续运行
     */
    private function shouldContinueRunning()
    {
        return (time() - $this->startTime) < $this->maxExecutionTime;
    }
    
    /**
     * 检查内存使用情况
     */
    private function checkMemoryUsage()
    {
        $currentTime = time();
        
        // 每分钟检查一次内存
        if ($currentTime - $this->lastMemoryCheck >= 60) {
            $memoryUsage = memory_get_usage(true);
            $memoryPeak = memory_get_peak_usage(true);
            
            $this->log(sprintf(
                "内存使用情况 - 当前: %s, 峰值: %s",
                $this->formatBytes($memoryUsage),
                $this->formatBytes($memoryPeak)
            ), 'DEBUG');
            
            // 如果内存使用超过限制，触发垃圾回收
            if ($memoryUsage > $this->maxMemoryUsage) {
                $this->log("内存使用过高，执行垃圾回收", 'WARNING');
                gc_collect_cycles();
                
                // 再次检查，如果还是过高则准备重启
                $newMemoryUsage = memory_get_usage(true);
                if ($newMemoryUsage > $this->maxMemoryUsage) {
                    $this->log("内存使用仍然过高，准备重启", 'CRITICAL');
                    $this->isRunning = false;
                }
            }
            
            $this->lastMemoryCheck = $currentTime;
        }
    }
    
    /**
     * 执行健康检查
     */
    private function performHealthCheck()
    {
        static $lastHealthCheck = 0;
        $currentTime = time();
        
        // 每5分钟执行一次健康检查
        if ($currentTime - $lastHealthCheck >= 300) {
            try {
                // 检查Redis连接
                $this->queue->getQueueLength();
                
                // 记录统计信息
                $uptime = $currentTime - $this->startTime;
                $this->log(sprintf(
                    "健康检查 - 运行时间: %s, 处理任务: %d, 错误次数: %d",
                    $this->formatDuration($uptime),
                    $this->processedCount,
                    $this->errorCount
                ), 'INFO');
                
                $lastHealthCheck = $currentTime;
                
            } catch (Exception $e) {
                $this->log("健康检查失败: " . $e->getMessage(), 'ERROR');
            }
        }
    }
    
    /**
     * 优雅关闭
     */
    private function shutdown()
    {
        $uptime = time() - $this->startTime;
        $this->log(sprintf(
            "AI队列处理器停止 - 运行时间: %s, 处理任务: %d, 错误次数: %d",
            $this->formatDuration($uptime),
            $this->processedCount,
            $this->errorCount
        ), 'INFO');
        
        $this->removePidFile();
    }
    
    /**
     * 格式化字节数
     */
    private function formatBytes($bytes)
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= pow(1024, $pow);
        return round($bytes, 2) . ' ' . $units[$pow];
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
     * 处理单个任务
     */
    private function processTask($task)
    {
        $taskId = $task['task_id'];
        $taskData = $task['data'];
        
        try {
            $result = null;
            
            // 根据任务类型处理
            switch ($taskData['type']) {
                case 'optimize_title':
                    $result = $this->processOptimizeTitle($taskData);
                    break;
                    
                case 'content_polish':
                    $result = $this->processContentPolish($taskData);
                    break;
                    
                case 'content_translate':
                    $result = $this->processContentTranslate($taskData);
                    break;
                    
                case 'ai_generate':
                    $result = $this->processAiGenerate($taskData);
                    break;
                    
                case 'ai_optimize_batch':
                    $result = $this->processOptimizeBatch($taskData);
                    break;
                    
                case 'seo_optimize':
                    $result = $this->processOptimizeBatch($taskData);
                    break;
                    
                default:
                    throw new Exception('未知的任务类型: ' . $taskData['type']);
            }
            
            // 设置任务结果
            $this->queue->setTaskResult($taskId, $result);
            echo "[" . date('Y-m-d H:i:s') . "] 任务完成: {$taskId}\n";
            
        } catch (Exception $e) {
            // 处理任务失败，检查是否需要重试
            $this->handleTaskFailure($taskId, $taskData, $e->getMessage());
        }
        
        // 任务完成后等待10秒，避免频繁请求AI接口
        echo "[" . date('Y-m-d H:i:s') . "] 等待10秒后处理下一个任务...\n";
        sleep(10);
    }
    
    /**
     * 处理标题优化任务
     */
    private function processOptimizeTitle($taskData)
    {
        $data = $taskData['data'];
        $content = $data['content'];
        $aiLanguage = $data['ai_language'] ?? 'english';
        $prompt = "请优化以下标题，使其更具吸引力和SEO友好性，保持原意不变：\n\n{$content}\n\n请以{$aiLanguage}回复，直接返回优化后的标题，不要添加任何解释。";
        
        return $this->callAiApi($prompt);
    }
    
    /**
     * 处理内容润色任务
     */
    private function processContentPolish($taskData)
    {
        $data = $taskData['data'];
        $text = $data['text'];
        $style = $data['style'] ?? 'professional';
        $aiLanguage = $data['ai_language'] ?? 'english';
        $stylePrompts = [
            'professional' => '请将以下内容润色为专业、正式的表达方式',
            'casual' => '请将以下内容润色为轻松、随意的表达方式',
            'academic' => '请将以下内容润色为学术、严谨的表达方式'
        ];
        
        $stylePrompt = $stylePrompts[$style] ?? $stylePrompts['professional'];
        $prompt = "{$stylePrompt}，保持原意不变，提升可读性和表达效果：\n\n{$text}\n\n请以{$aiLanguage}回复，不要解释说明。";
        
        return $this->callAiApi($prompt);
    }
    
    /**
     * 处理内容翻译任务
     */
    private function processContentTranslate($taskData)
    {
        $data = $taskData['data'];
        $text = $data['text'];
        $targetLang = $data['target_lang'] ?? 'en';
        
        $langNames = [
            'en' => '英语',
            'es' => '西班牙语',
            'ru' => '俄语',
            'fr' => '法语',
            'ar' => '阿拉伯语',
            'pt' => '葡萄牙语',
            'zh' => '中文'
        ];
        
        $langName = $langNames[$targetLang] ?? '英语';
        $prompt = "请将以下内容翻译为{$langName}，保持原文的语气和风格：\n\n{$text}";
        
        return $this->callAiApi($prompt);
    }
    
    /**
     * 处理AI代写任务
     */
    private function processAiGenerate($taskData)
    {
        $data = $taskData['data'];
        $topic = $data['topic'];
        $requirements = $data['requirements'] ?? '';
        $aiLanguage = $data['ai_language'] ?? 'english';
        $prompt = "请根据以下主题创作内容：\n\n主题：{$topic}\n";
        if ($requirements) {
            $prompt .= "要求：{$requirements}\n";
        }
        $prompt .= "\n请以{$aiLanguage}回复，创作一篇结构清晰、内容丰富的文章,500字左右。";
        
        return $this->callAiApi($prompt);
    }
    
    /**
     * 调用AI接口
     */
    private function callAiApi($prompt)
    {
        $config = Config::get();
        $aiUrl = $config['AiUrl'] ?? 'https://dashscope.aliyuncs.com/compatible-mode/v1/';
        $aiKey = $config['AiKey'] ?? '';
        $aiModel = $config['AiModel'] ?? 'deepseek-v3.1';
        
        if (empty($aiKey)) {
            throw new Exception('AI接口密钥未配置');
        }
        
        // 处理多模型随机选择
        $models = explode("\n", $aiModel);
        $selectedModel = trim($models[array_rand($models)]);
        
        // 记录选择的模型
        $this->log("使用AI模型: {$selectedModel}", 'DEBUG');
        
        $data = [
            'model' => $selectedModel,
            'messages' => [
                [
                    'role' => 'user',
                    'content' => $prompt
                ]
            ],
            'temperature' => 0.7,
            'max_tokens' => 2000
        ];
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $aiUrl . 'chat/completions',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $aiKey
            ],
            CURLOPT_TIMEOUT => 60,
            CURLOPT_SSL_VERIFYPEER => false
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            throw new Exception('CURL错误: ' . $error);
        }
        
        if ($httpCode !== 200) {
            throw new Exception('AI接口返回错误: HTTP ' . $httpCode . " \n" . $response. " \nurl:". $aiUrl );
        }
        
        // 记录AI接口原始返回内容，用于调试
        // $this->log("AI接口原始返回: " . $response, 'DEBUG');
        
        $result = json_decode($response, true);
        if (!$result || !isset($result['choices'][0]['message']['content'])) {
            throw new Exception('AI接口返回格式错误');
        }
        
        // 记录AI返回的内容
        $aiContent = $result['choices'][0]['message']['content'];
        // $this->log("AI返回内容: " . $aiContent, 'DEBUG');
        
        return $aiContent;
    }
    
    /**
     * 处理AI一键优化批量任务（一次性调用AI接口）
     * 使用SEO专家提示词，一次性获取所有SEO相关内容
     */
    private function processOptimizeBatch($taskData)
    {
        $data = $taskData['data'];
        $content = $data['content'];
        $title = $data['title'] ?? '';
        $language = $data['language'] ?? 'english';
        
        // 构建SEO专家提示词
        $prompt = "你是一位SEO专家，擅长为网站内容生成各种SEO相关的元素。我将提供\n";
        $prompt .= "{language: {$language}, title: {$title}, content: {$content}}，\n";
        $prompt .= "请严格按照以下JSON格式返回结果,不要解释\n";
        $prompt .= "{\n";
        $prompt .= '  "subTitle": "副标题，需要包含一些关键词，副标题是标题的扩展，可以围绕标题展开更多关键词，不超过40个字符",' . "\n";
        $prompt .= '  "description": "SEO友好的描述（120字符）",' . "\n";
        $prompt .= '  "keywords": "基于提供的内容提取5-10个相关的关键词，用英文逗号分隔",' . "\n";
        $prompt .= '  "tags": "基于提供的内容生成3-8个相关标签，用英文逗号分隔，要求足够简短，每个标签最多三个单词",' . "\n";
        $prompt .= '  "url": "基于提供的标题提取2到5个关键英文单词，用连字符连接生成一个简洁的英文URL路径（只包含字母、数字和连字符 不能带空格）。只能使用英语"' . "\n";
        $prompt .= "}";
        
        try {
            // 一次性调用AI接口获取所有SEO内容
            $aiResponse = $this->callAiApi($prompt);
            
            // 清理AI返回的内容，移除可能的多余字符
            $cleanResponse = trim($aiResponse);
            
            // 如果返回内容被包装在代码块中，提取JSON部分
            if (preg_match('/```(?:json)?\s*({.*?})\s*```/s', $cleanResponse, $matches)) {
                $cleanResponse = $matches[1];
            }
            
            // 检查JSON是否完整，如果缺少结尾大括号则补充
            $openBraces = substr_count($cleanResponse, '{');
            $closeBraces = substr_count($cleanResponse, '}');
            if ($openBraces > $closeBraces) {
                $missingBraces = $openBraces - $closeBraces;
                $cleanResponse .= str_repeat('}', $missingBraces);
                // $this->log("检测到JSON缺少{$missingBraces}个结尾大括号，已自动补充", 'DEBUG');
            }
            
            // 记录清理后的内容用于调试
            // $this->log("清理后的AI返回内容: " . $cleanResponse, 'DEBUG');
            
            // 解析AI返回的JSON结果
            $seoData = json_decode($cleanResponse, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \Exception('JSON解析错误: ' . json_last_error_msg() . ', 原始内容: ' . $cleanResponse);
            }
            
            if (!$seoData || !is_array($seoData)) {
                throw new \Exception('AI返回的数据格式不正确，解析结果不是有效数组: ' . var_export($seoData, true));
            }
            
            // 构建返回结果，映射到前端期望的字段名
            $results = [];
            
            // 映射字段名
            $fieldMapping = [
                'subTitle' => 'subtitle',
                'description' => 'description', 
                'keywords' => 'keywords',
                'tags' => 'tags',
                'url' => 'url'
            ];
            
            foreach ($fieldMapping as $aiField => $resultField) {
                if (isset($seoData[$aiField])) {
                    $results[$resultField] = [
                        'success' => true,
                        'data' => $seoData[$aiField]
                    ];
                } else {
                    $results[$resultField] = [
                        'success' => false,
                        'error' => "AI返回结果中缺少{$aiField}字段"
                    ];
                }
            }
            
            return $results;
            
        } catch (\Exception $e) {
            // 如果整个过程出现异常，返回错误
            return [
                'success' => false,
                'error' => 'SEO优化过程出现异常: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * 处理任务失败，实现重试机制
     * @param string $taskId 任务ID
     * @param array $taskData 任务数据
     * @param string $errorMessage 错误消息
     */
    private function handleTaskFailure($taskId, $taskData, $errorMessage)
    {
        $maxRetries = 3; // 最大重试次数
        $currentRetries = $this->queue->getRetryCount($taskId);
        
        echo "[" . date('Y-m-d H:i:s') . "] 任务失败: {$taskId} - {$errorMessage} (重试次数: {$currentRetries}/{$maxRetries})\n";
        
        if ($currentRetries < $maxRetries) {
            // 增加重试次数
            $newRetryCount = $this->queue->incrementRetryCount($taskId);
            
            // 重新将任务加入队列
            if ($this->queue->requeueTask($taskId, $taskData)) {
                echo "[" . date('Y-m-d H:i:s') . "] 任务已重新加入队列: {$taskId} (第{$newRetryCount}次重试)\n";
            } else {
                echo "[" . date('Y-m-d H:i:s') . "] 任务重新加入队列失败: {$taskId}\n";
                $this->queue->markTaskFailed($taskId, $errorMessage . ' (重新加入队列失败)');
            }
        } else {
            // 达到最大重试次数，标记任务最终失败
            $this->queue->markTaskFailed($taskId, $errorMessage . ' (已达到最大重试次数)');
            $this->queue->clearRetryCount($taskId); // 清理重试计数
            echo "[" . date('Y-m-d H:i:s') . "] 任务最终失败: {$taskId} - 已达到最大重试次数\n";
        }
    }
}

// 启动队列处理器
if (php_sapi_name() === 'cli') {
    $processor = new AiQueueProcessor();
    $processor->start();
} else {
    echo "此脚本只能在命令行模式下运行\n";
}