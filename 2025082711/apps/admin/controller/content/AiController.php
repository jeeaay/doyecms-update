<?php
/**
 * @copyright (C)2016-2099 Hnaoyun Inc.
 * @author XingMeng
 * @email hnxsh@foxmail.com
 * @date 2024年1月15日
 * AI助手控制器
 */
namespace app\admin\controller\content;

use core\basic\Controller;

class AiController extends Controller
{
    public function __construct()
    {
        // 继承父类构造函数
        // parent::__construct();
    }
    public function index()
    {
        json(0, 'index');
    }

    /**
     * AI队列 - 提交任务到队列
     * @return void
     */
    public function aiQueueSubmit()
    {
        if (session('formcheck') != post('formcheck')) {
            json(0, '表单验证失败');
        }

        if (!$_POST) {
            json(0, '请求方式错误');
        }

        $type = post('type', 'var'); // optimize_title, content_polish, content_translate, ai_generate
        $data = post('data'); // 任务数据

        if (empty($type)) {
            json(0, '请指定任务类型');
        }

        if (empty($data)) {
            json(0, '请提供任务数据');
        }

        try {
            // 引入Redis队列类
            require_once CORE_PATH . '/basic/RedisQueue.php';
            $queue = new \core\basic\RedisQueue('ai_queue');

            // 准备任务数据
            $taskData = [
                'type' => $type,
                'data' => $data,
                'created_at' => time(),
                'user_id' => session('uid'),
                'user_ip' => get_user_ip()
            ];

            // 根据任务类型验证数据
            $this->validateTaskData($type, $data);

            // 提交任务到队列
            $taskId = $queue->pushTask($taskData);

            if ($taskId) {
                json(1, ['task_id' => $taskId], '任务已提交到队列');
            } else {
                json(0, '任务提交失败');
            }

        } catch (\Exception $e) {
            json(0, '队列操作失败：' . $e->getMessage());
        }
    }

    /**
     * AI队列 - 查询任务状态
     * @return void
     */
    public function aiQueueStatus()
    {
        $taskId = get('task_id', 'var');

        if (empty($taskId)) {
            json(0, '请提供任务ID');
        }

        try {
            // 引入Redis队列类
            require_once CORE_PATH . '/basic/RedisQueue.php';
            $queue = new \core\basic\RedisQueue('ai_queue');

            // 检查任务是否存在
            if (!$queue->taskExists($taskId)) {
                // 使用特殊状态码2表示任务不存在，前端需要清理任务并关闭弹窗
                json(2, ['task_status' => 'not_exists'], '任务不存在或已过期');
            }

            // 获取任务状态
            $status = $queue->getTaskStatus($taskId);

            // 获取重试次数
            $retryCount = $queue->getRetryCount($taskId);
            
            $response = [
                'task_id' => $taskId,
                'status' => $status,
                'retry_count' => $retryCount
            ];

            // 如果任务完成，获取结果
            if ($status && $status['status'] === 'completed') {
                $result = $queue->getTaskResult($taskId);
                $response['result'] = $result;
                $response['task_status'] = 'completed';
                
                // 使用特殊状态码3表示任务已完成，前端需要处理结果并清理任务
                json(3, $response, '任务已完成');
            } elseif ($status && $status['status'] === 'failed') {
                $result = $queue->getTaskResult($taskId);
                $response['error'] = $result;
                $response['task_status'] = 'failed';
                
                // 使用特殊状态码4表示任务失败，前端需要显示错误并清理任务
                json(4, $response, '任务执行失败');
            } elseif ($status && $status['status'] === 'cancelled') {
                $response['task_status'] = 'cancelled';
                
                // 使用特殊状态码5表示任务已取消，前端需要清理任务并关闭弹窗
                json(5, $response, '任务已取消');
            }

            // 任务进行中（pending或processing状态）
            $response['task_status'] = $status['status'] ?? 'pending';
            json(1, $response, '查询成功');

        } catch (\Exception $e) {
            json(0, '查询失败：' . $e->getMessage());
        }
    }

    /**
     * 调试接口：直接获取任务结果
     */
    public function aiQueueDebug()
    {
        $taskId = input('task_id');
        
        if (!$taskId) {
            json(0, '', '请提供任务ID');
            return;
        }
        
        try {
            $queue = new \core\basic\RedisQueue();
            
            // 获取任务状态
            $status = $queue->getTaskStatus($taskId);
            
            // 获取任务结果
            $result = $queue->getTaskResult($taskId);
            
            $debugInfo = [
                'task_id' => $taskId,
                'status' => $status,
                'result' => $result,
                'result_type' => gettype($result),
                'result_length' => is_string($result) ? strlen($result) : 'N/A'
            ];
            
            json(1, $debugInfo, '调试信息获取成功');
            
        } catch (\Exception $e) {
            json(0, '', '调试失败：' . $e->getMessage());
        }
    }

    /**
     * AI队列 - 取消任务
     * @return void
     */
    public function aiQueueCancel()
    {
        if (session('formcheck') != post('formcheck')) {
            json(0, '表单验证失败');
        }

        $taskId = post('task_id', 'var');

        if (empty($taskId)) {
            json(0, '请提供任务ID');
        }

        try {
            // 引入Redis队列类
            require_once CORE_PATH . '/basic/RedisQueue.php';
            $queue = new \core\basic\RedisQueue('ai_queue');

            // 检查任务是否存在
            if (!$queue->taskExists($taskId)) {
                json(0, '任务不存在或已过期');
            }

            // 获取任务状态
            $status = $queue->getTaskStatus($taskId);

            if ($status === 'completed') {
                json(0, '任务已完成，无法取消');
            }

            if ($status === 'processing') {
                json(0, '任务正在处理中，无法取消');
            }

            // 删除任务
            $queue->deleteTask($taskId);
            json(1, '', '任务已取消');

        } catch (\Exception $e) {
            json(0, '取消失败：' . $e->getMessage());
        }
    }

    /**
     * 验证任务数据
     * @param string $type 任务类型
     * @param array $data 任务数据
     * @throws \Exception
     */
    private function validateTaskData($type, $data)
    {
        switch ($type) {
            case 'optimize_title':
                if (empty($data['content'])) {
                    throw new \Exception('请提供要优化的标题内容');
                }
                break;

            case 'content_polish':
                if (empty($data['text'])) {
                    throw new \Exception('请提供要润色的文本内容');
                }
                break;

            case 'content_translate':
                if (empty($data['text'])) {
                    throw new \Exception('请提供要翻译的文本内容');
                }
                if (empty($data['target_lang'])) {
                    throw new \Exception('请指定目标语言');
                }
                break;

            case 'ai_generate':
                if (empty($data['topic'])) {
                    throw new \Exception('请提供写作主题');
                }
                break;
                
            case 'ai_optimize_batch':
                if (empty($data['content'])) {
                    throw new \Exception('请提供要优化的内容');
                }
                if (empty($data['tasks']) || !is_array($data['tasks'])) {
                    throw new \Exception('请提供有效的任务列表');
                }
                break;
                
            case 'seo_optimize':
                if (empty($data['content'])) {
                    throw new \Exception('请提供要优化的内容');
                }
                if (empty($data['tasks']) || !is_array($data['tasks'])) {
                    throw new \Exception('请提供有效的任务列表');
                }
                break;

            default:
                throw new \Exception('不支持的任务类型：' . $type);
        }
    }
}