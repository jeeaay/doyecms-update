<?php
/**
 * Redis队列处理基础类
 * @copyright (C)2016-2099 Hnaoyun Inc.
 * @author AI Assistant
 * @date 2024年1月
 * Redis队列任务处理核心类
 */

namespace core\basic;

class RedisQueue
{
    private $redis;
    private $queueName;
    private $taskPrefix = 'task:';
    private $statusPrefix = 'status:';
    private $resultPrefix = 'result:';
    private $retryPrefix = 'retry:';
    
    /**
     * 构造函数 - 初始化Redis连接
     * @param string $queueName 队列名称
     */
    public function __construct($queueName = 'ai_queue')
    {
        $this->queueName = $queueName;
        $this->initRedis();
    }
    
    /**
     * 初始化Redis连接
     * @throws \Exception 连接失败时抛出异常
     */
    private function initRedis()
    {
        try {
            if (! extension_loaded('redis')) {
                throw new \Exception('PHP运行环境未安装redis扩展！');
            }
            $config = Config::get('redis');
            $host = isset($config['host']) ? $config['host'] : '127.0.0.1';
            $port = isset($config['port']) ? (int) $config['port'] : 6379;
            $timeout = isset($config['timeout']) ? (float) $config['timeout'] : 3600;
            $password = isset($config['password']) ? $config['password'] : '';
            $select = isset($config['select']) ? (int) $config['select'] : 0;
            $prefix = isset($config['prefix']) ? $config['prefix'] : '';

            $this->redis = new \Redis();
            $this->redis->connect($host, $port, $timeout);
            if ($password !== '') {
                $this->redis->auth($password);
            }
            if ($select > 0) {
                $this->redis->select($select);
            }
            if ($prefix !== '') {
                $this->redis->setOption(\Redis::OPT_PREFIX, $prefix);
            }
        } catch (\Exception $e) {
            throw new \Exception('Redis连接失败: ' . $e->getMessage());
        }
    }
    
    /**
     * 生成唯一任务ID
     * @return string 任务ID
     */
    private function generateTaskId()
    {
        return uniqid('task_', true) . '_' . time();
    }
    
    /**
     * 将任务加入队列
     * @param array $taskData 任务数据
     * @return string 任务ID
     */
    public function pushTask($taskData)
    {
        $taskId = $this->generateTaskId();
        
        // 存储任务数据
        $taskKey = $this->taskPrefix . $taskId;
        $this->redis->setex($taskKey, 3600, json_encode($taskData)); // 1小时过期
        
        // 设置任务状态为等待中
        $this->setTaskStatus($taskId, 'pending');
        
        // 将任务ID推入队列
        $this->redis->lpush($this->queueName, $taskId);
        
        return $taskId;
    }
    
    /**
     * 从队列中获取任务
     * @param int $timeout 阻塞超时时间（秒）
     * @return array|null 任务数据或null
     */
    public function popTask($timeout = 0)
    {
        $result = $this->redis->brpop([$this->queueName], $timeout);
        
        if (!$result) {
            return null;
        }
        
        $taskId = $result[1];
        $taskKey = $this->taskPrefix . $taskId;
        $taskData = $this->redis->get($taskKey);
        
        if (!$taskData) {
            return null;
        }
        
        // 设置任务状态为处理中
        $this->setTaskStatus($taskId, 'processing');
        
        return [
            'task_id' => $taskId,
            'data' => json_decode($taskData, true)
        ];
    }
    
    /**
     * 设置任务状态
     * @param string $taskId 任务ID
     * @param string $status 状态（pending/processing/completed/failed）
     * @param string $message 状态消息
     */
    public function setTaskStatus($taskId, $status, $message = '')
    {
        $statusKey = $this->statusPrefix . $taskId;
        $statusData = [
            'status' => $status,
            'message' => $message,
            'updated_at' => time()
        ];
        
        $this->redis->setex($statusKey, 3600, json_encode($statusData)); // 1小时过期
    }
    
    /**
     * 获取任务状态
     * @param string $taskId 任务ID
     * @return array|null 状态信息或null
     */
    public function getTaskStatus($taskId)
    {
        $statusKey = $this->statusPrefix . $taskId;
        $statusData = $this->redis->get($statusKey);
        
        if (!$statusData) {
            return null;
        }
        
        return json_decode($statusData, true);
    }
    
    /**
     * 设置任务结果
     * @param string $taskId 任务ID
     * @param mixed $result 任务结果
     */
    public function setTaskResult($taskId, $result)
    {
        $resultKey = $this->resultPrefix . $taskId;
        $this->redis->setex($resultKey, 3600, json_encode($result)); // 1小时过期
        
        // 同时更新状态为已完成
        $this->setTaskStatus($taskId, 'completed', '任务执行成功');
    }
    
    /**
     * 获取任务结果
     * @param string $taskId 任务ID
     * @return mixed|null 任务结果或null
     */
    public function getTaskResult($taskId)
    {
        $resultKey = $this->resultPrefix . $taskId;
        $resultData = $this->redis->get($resultKey);
        
        if (!$resultData) {
            return null;
        }
        
        return json_decode($resultData, true);
    }
    
    /**
     * 标记任务失败
     * @param string $taskId 任务ID
     * @param string $errorMessage 错误消息
     */
    public function markTaskFailed($taskId, $errorMessage)
    {
        $this->setTaskStatus($taskId, 'failed', $errorMessage);
    }
    
    /**
     * 删除任务相关数据
     * @param string $taskId 任务ID
     */
    public function deleteTask($taskId)
    {
        $taskKey = $this->taskPrefix . $taskId;
        $statusKey = $this->statusPrefix . $taskId;
        $resultKey = $this->resultPrefix . $taskId;
        $retryKey = $this->retryPrefix . $taskId;
        
        $this->redis->del([$taskKey, $statusKey, $resultKey, $retryKey]);
    }
    
    /**
     * 获取队列长度
     * @return int 队列中等待的任务数量
     */
    public function getQueueLength()
    {
        return $this->redis->llen($this->queueName);
    }
    
    /**
     * 清空队列
     */
    public function clearQueue()
    {
        $this->redis->del($this->queueName);
    }
    
    /**
     * 检查任务是否存在
     * @param string $taskId 任务ID
     * @return bool 任务是否存在
     */
    public function taskExists($taskId)
    {
        $taskKey = $this->taskPrefix . $taskId;
        return $this->redis->exists($taskKey);
    }
    
    /**
     * 获取任务重试次数
     * @param string $taskId 任务ID
     * @return int 重试次数
     */
    public function getRetryCount($taskId)
    {
        $retryKey = $this->retryPrefix . $taskId;
        $count = $this->redis->get($retryKey);
        return $count ? (int)$count : 0;
    }
    
    /**
     * 增加任务重试次数
     * @param string $taskId 任务ID
     * @return int 新的重试次数
     */
    public function incrementRetryCount($taskId)
    {
        $retryKey = $this->retryPrefix . $taskId;
        $count = $this->redis->incr($retryKey);
        // 设置过期时间为1小时，避免数据堆积
        $this->redis->expire($retryKey, 3600);
        return $count;
    }
    
    /**
     * 重新将任务加入队列（用于重试）
     * @param string $taskId 任务ID
     * @param array $taskData 任务数据
     * @return bool 是否成功
     */
    public function requeueTask($taskId, $taskData)
    {
        $task = [
            'task_id' => $taskId,
            'data' => $taskData,
            'created_at' => time()
        ];
        
        // 将任务重新加入队列头部（优先处理重试任务）
        return $this->redis->lpush($this->queueName, json_encode($task)) > 0;
    }
    
    /**
     * 清理任务的重试计数
     * @param string $taskId 任务ID
     */
    public function clearRetryCount($taskId)
    {
        $retryKey = $this->retryPrefix . $taskId;
        $this->redis->del($retryKey);
    }
    
    /**
     * 析构函数 - 关闭Redis连接
     */
    public function __destruct()
    {
        if ($this->redis) {
            $this->redis->close();
        }
    }
}
