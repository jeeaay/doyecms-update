<?php
/**
 * @copyright (C)2016-2099 Hnaoyun Inc.
 * @author XingMeng
 * @email hnxsh@foxmail.com
 * @date 2023年01月01日
 *  静态生成控制器
 */
namespace app\admin\controller\system;

use app\common\AdminController;
use app\admin\model\system\StaticService;

class StaticController extends AdminController
{
    private $service;

    public function __construct()
    {
        parent::__construct();
        $this->service = new StaticService();
    }

    public function index()
    {
        // 读取 .env 配置
        $env_config = array(
            'domain_name' => getenv('DOMAIN_NAME') ?: '未配置',
            'static_generate_enable' => getenv('STATIC_GENERATE_ENABLE') ?: 'true',
            'static_generate_dir' => getenv('STATIC_GENERATE_DIR') ?: '/html',
            'static_default_lang_dir' => getenv('STATIC_DEFAULT_LANG_DIR') ?: 'default',
            'static_index_filename' => getenv('STATIC_INDEX_FILENAME') ?: 'index.html'
        );
        $this->assign('env_config', $env_config);
        $this->display('system/static.html');
    }

    public function plan()
    {
        try {
            $cursor = get('cursor', 'int') ?: 0;
            $limit = get('limit', 'int') ?: 100;
            $data = $this->service->getGeneratePlan($cursor, $limit);
            json(1, $data);
        } catch (\Exception $e) {
            json(0, $e->getMessage());
        }
    }

    public function generateBatch()
    {
        try {
            // 获取并解码 items
            if (isset($_POST['items'])) {
                $items = $_POST['items'];
                // 如果是 JSON 字符串，先解码
                if (is_string($items)) {
                    $decoded = json_decode($items, true);
                    if (json_last_error() === JSON_ERROR_NONE) {
                        $items = $decoded;
                    }
                }
            } else {
                $items = null;
            }

            if (!is_array($items)) {
                json(0, 'items 参数错误: ' . gettype($items));
            }

            $success = 0;
            $fail = 0;
            $errors = array();
            $results = array();

            foreach ($items as $item) {
                try {
                    $path = $this->service->generateFromPlanItem($item);
                    $success++;
                    $results[] = array('ok' => 1, 'item' => $item, 'path' => $path);
                } catch (\Exception $e) {
                    $fail++;
                    if (count($errors) < 20) {
                        $errors[] = array('item' => $item, 'error' => $e->getMessage());
                    }
                    $results[] = array('ok' => 0, 'item' => $item, 'error' => $e->getMessage());
                }
            }

            json(1, array(
                'success' => $success,
                'fail' => $fail,
                'errors' => $errors,
                'results' => $results
            ));
        } catch (\Exception $e) {
            json(0, $e->getMessage());
        }
    }
    
    // 生成全站
    public function generateAll()
    {
        try {
            $count = 0;
            
            // 1. 生成首页
            $this->service->generateIndex();
            $count++;
            
            // 2. 生成栏目及分页
            $scodes = $this->service->getAllScodes();
            foreach ($scodes as $scode) {
                $this->service->generateSort($scode);
                $count++;
                
                // 3. 生成内容
                $ids = $this->service->getContentIds($scode);
                foreach ($ids as $id) {
                    $this->service->generateContent($id);
                    $count++;
                }
            }
            
            json(1, '全站生成完成，共处理 ' . $count . ' 个页面');
        } catch (\Exception $e) {
            json(0, '生成失败：' . $e->getMessage());
        }
    }
}
