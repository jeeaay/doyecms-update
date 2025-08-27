<?php
/**
 * @copyright (C)2016-2099 Hnaoyun Inc.
 * @author XingMeng
 * @email hnxsh@foxmail.com
 * @date 2017年3月13日
 *  默认主页
 */
namespace app\admin\controller;

use core\basic\Controller;
use app\admin\model\IndexModel;

class IndexController extends Controller
{

    private $model;

    public function __construct()
    {
        $this->model = new IndexModel();
    }

    // 登录页面
    public function index()
    {
        if (session('sid')) {
            location(url('admin/Index/home'));
        }
        $this->assign('admin_check_code', $this->config('admin_check_code'));
        $this->display('index.html');
    }

    // 主页面
    public function home()
    {
        // 手动修改数据名称
        if (get('action') == 'moddb') {
            if ($this->modDB()) {
                alert_back('修改成功！');
            } else {
                alert_back('修改失败！');
            }
        }

        // 删除修改后老数据库（上一步无法直接修改删除）
        if (issetSession('deldb')) {
            @unlink(ROOT_PATH . session('deldb'));
            unset($_SESSION['deldb']);
        }

        $dbsecurity = true;
        // 如果是sqlite数据库，并且路径为默认的，则标记为不安全
        if (get_db_type() == 'sqlite') {
            // 数据库配置含有默认名字则进行修改
            if (strpos($this->config('database.dbname'), 'pbootcms') !== false) {
                if (get_user_ip() != '127.0.0.1' && $this->modDB()) { // 非本地测试时尝试自动修改数据库名称
                    $dbsecurity = true;
                } else {
                    $dbsecurity = false;
                }
            } elseif (file_exists(ROOT_PATH . '/data/pbootcms.db')) { // 存在多余的默认数据库文件则改名
                rename(ROOT_PATH . '/data/pbootcms.db', ROOT_PATH . '/data/' . get_uniqid() . '.db');
            }
        } elseif (file_exists(ROOT_PATH . '/data/pbootcms.db')) {
            rename(ROOT_PATH . '/data/pbootcms.db', ROOT_PATH . '/data/' . get_uniqid() . '.db');
        }

        $this->assign('dbsecurity', $dbsecurity);

        if (!session('pwsecurity')) {
            location(url('/admin/Index/ucenter'));
        }

        $this->assign('server', get_server_info());
        $this->assign('branch', $this->config('upgrade_branch') == '3.X.dev' ? '3.X.dev' : '3.X');
        $this->assign('revise', $this->config('revise_version') ?: '0');
        $this->assign('snuser', $this->config('sn_user') ?: '0');
        $this->assign('site', get_http_url());

        $this->assign('user_info', $this->model->getUserInfo(session('ucode')));

        $this->assign('sum_msg', model('admin.content.Message')->getCount());

        // 内容模型菜单
        $model = model('admin.content.Model');
        $models = $model->getModelMenu();
        foreach ($models as $key => $value) {
            $models[$key]->count = $model->getModelCount($value->mcode)->count;
        }

        $this->assign('model_msg', $models);
        $this->display('system/home.html');
    }

    // 异步登录验证
    public function login()
    {
        if (!$_POST) {
            return;
        }

        // 在安装了gd库时才执行验证码验证
        if (extension_loaded("gd") && $this->config('admin_check_code') && strtolower(post('checkcode', 'var')) != session('checkcode')) {
            json(0, '验证码错误！');
        }

        // 就收数据
        $username = post('username');
        $password = post('password');

        if (!preg_match('/^[\x{4e00}-\x{9fa5}\w\-\.@]+$/u', $username)) {
            json(0, '用户名含有不允许的特殊字符！');
        }

        if (!$username) {
            json(0, '用户名不能为空！');
        }

        if (!$password) {
            json(0, '密码不能为空！');
        }

        if (!!$time = $this->checkLoginBlack()) {
            $this->log('登录锁定!');
            json(0, '您登录失败次数太多已被锁定，请' . $time . '秒后再试！');
        }

        // 执行用户登录
        $where = array(
            'username' => $username,
            'password' => encrypt_string($password)
        );

        // 判断数据库写入权限
        if ((get_db_type() == 'sqlite') && !is_writable(ROOT_PATH . $this->config('database.dbname'))) {
            json(0, '数据库目录写入权限不足！');
        }

        if (!!$login = $this->model->login($where)) {

            session_regenerate_id(true);
            session('sid', encrypt_string(session_id() . $login->id)); // 会话标识
            session('M', M);

            session('id', $login->id); // 用户id
            session('ucode', $login->ucode); // 用户编码
            session('username', $login->username); // 用户名
            session('realname', $login->realname); // 真实名字

            if ($where['password'] != '14e1b600b1fd579f47433b88e8d85291') {
                session('pwsecurity', true);
            }

            session('acodes', $login->acodes); // 用户管理区域
            if ($login->acodes) { // 当前显示区域
                session('acode', $login->acodes[0]);
            } else {
                session('acode', '');
            }

            session('rcodes', $login->rcodes); // 用户角色代码表
            session('levels', $login->levels); // 用户权限URL列表
            session('menu_tree', $login->menus); // 菜单树
            session('area_map', $login->area_map); // 区域代码名称映射表
            session('area_tree', $login->area_tree); // 用户区域树

            $this->log('登录成功!');
            json(1, url('admin/Index/home'));
        } else {
            $this->setLoginBlack();
            $this->log('登录失败!');
            session('checkcode', mt_rand(10000, 99999)); // 登录失败，随机打乱原有验证码
            json(0, '用户名或密码错误！');
        }
    }

    // 退出登录
    public function loginOut()
    {
        session_unset();
        location(url('/admin/Index/index'));
    }

    // 用户中心，修改密码
    public function ucenter()
    {
        if ($_POST) {
            $username = post('username'); // 用户名
            $realname = post('realname'); // 真实姓名
            $cpassword = post('cpassword'); // 现在密码
            $password = post('password'); // 新密码
            $rpassword = post('rpassword'); // 确认密码

            if (!$username) {
                alert_back('用户名不能为空！');
            }
            if (!$cpassword) {
                alert_back('当前密码不能为空！');
            }

            if (!preg_match('/^[\x{4e00}-\x{9fa5}\w\-\.@]+$/u', $username)) {
                alert_back('用户名含有不允许的特殊字符！');
            }

            $data = array(
                'username' => $username,
                'realname' => $realname,
                'update_user' => $username
            );

            // 如果有修改密码，则添加数据
            if ($password) {
                if ($password != $rpassword) {
                    alert_back('确认密码不正确！');
                }
                $data['password'] = encrypt_string($password);
                if ($data['password'] != '14e1b600b1fd579f47433b88e8d85291') {
                    session('pwsecurity', true);
                } else {
                    session('pwsecurity', false);
                }
            }

            // 检查现有密码
            if ($this->model->checkUserPwd(encrypt_string($cpassword))) {
                if ($this->model->modUserInfo($data)) {
                    session('username', post('username'));
                    session('realname', post('realname'));
                    $this->log('用户资料成功！');
                    success('用户资料修改成功！', -1);
                }
            } else {
                $this->log('用户资料修改时当前密码错误！');
                alert_location('当前密码错误！', -1);
            }
        }
        $this->display('system/ucenter.html');
    }

    // 切换显示的数据区域
    public function area()
    {
        if ($_POST) {
            $acode = post('acode');
            if (in_array($acode, session('acodes'))) {
                session('acode', $acode);
                cookie('lg', $acode); // 同步切换前台语言
            }
            location(url('admin/Index/home'));
        }
    }

    // 清理缓存
    public function clearCache()
    {
        if (get('delall')) {
            $rs = path_delete(RUN_PATH);
        } else {
            $rs = (path_delete(RUN_PATH . '/cache') && path_delete(RUN_PATH . '/complile') && path_delete(RUN_PATH . '/config') && path_delete(RUN_PATH . '/upgrade'));
        }
        cache_config(); // 清理缓存后立即生成新的配置
        if ($rs) {
            if (extension_loaded('Zend OPcache')) {
                opcache_reset(); // 在启用了OPcache加速器时同时清理
            }
            $this->log('清理缓存成功！');
            alert_back('清理缓存成功！', 1);
        } else {
            $this->log('清理缓存失败！');
            alert_back('清理缓存失败！', 0);
        }
    }
	
	// 清理系统缓存
    public function clearOnlySysCache()
    {
        if (get('delall')) {
            $rs = path_delete(RUN_PATH);
        } else {
            $rs = (path_delete(RUN_PATH . '/complile') && path_delete(RUN_PATH . '/config') && path_delete(RUN_PATH . '/upgrade'));
        }
        cache_config(); // 清理缓存后立即生成新的配置
        if ($rs) {
            if (extension_loaded('Zend OPcache')) {
                opcache_reset(); // 在启用了OPcache加速器时同时清理
            }
            $this->log('清理缓存成功！');
            alert_back('清理缓存成功！', 1);
        } else {
            $this->log('清理缓存失败！');
            alert_back('清理缓存失败！', 0);
        }
    }
	
    // 清理会话
    public function clearSession()
    {
        ignore_user_abort(true); // 后台运行
        set_time_limit(7200);
        ob_start();
        $output['code'] = 1;
        $output['data'] = '执行成功，后台自动清理中!';
        $output['tourl'] = '';
        echo json_encode($output);
        ob_end_flush();
        flush();
        $rs = path_delete(RUN_PATH . '/session', false, array(
            'sess_' . session_id()
        ));
    }

    // 文件上传方法
    public function upload()
    {
        $upload = upload('upload');
        if (is_array($upload)) {
            json(1, $upload);
        } else {
            json(0, $upload);
        }
    }

    // 检查是否在黑名单
    private function checkLoginBlack()
    {
        // 读取黑名单
        $ip_black = RUN_PATH . '/data/' . md5('login_black') . '.php';
        if (file_exists($ip_black)) {
            $data = require $ip_black;
            $user_ip = get_user_ip();
            $lock_time = $this->config('lock_time') ?: 900;
            $lock_count = $this->config('lock_count') ?: 5;
            if (isset($data[$user_ip]) && $data[$user_ip]['count'] >= $lock_count && time() - $data[$user_ip]['time'] < $lock_time) {
                return $lock_time - (time() - $data[$user_ip]['time']); // 返回剩余秒数
            }
        }
        return false;
    }

    // 添加登录黑名单
    private function setLoginBlack()
    {
        // 读取黑名单
        $ip_black = RUN_PATH . '/data/' . md5('login_black') . '.php';
        if (file_exists($ip_black)) {
            $data = require $ip_black;
        } else {
            $data = array();
        }

        // 添加IP
        $user_ip = get_user_ip();
        $lock_time = $this->config('lock_time') ?: 900;
        $lock_count = $this->config('lock_count') ?: 5;
        if (isset($data[$user_ip]) && $data[$user_ip]['count'] < $lock_count && time() - $data[$user_ip]['time'] < $lock_time) {
            $data[$user_ip] = array(
                'time' => time(),
                'count' => $data[get_user_ip()]['count'] + 1
            );
        } else {
            $data[$user_ip] = array(
                'time' => time(),
                'count' => 1
            );
        }

        // 写入黑名单
        check_file($ip_black, true);
        return file_put_contents($ip_black, "<?php\nreturn " . var_export($data, true) . ";");
    }

    // 修改数据库名称
    private function modDB()
    {
        $file = CONF_PATH . '/database.php';
        $sname = $this->config('database.dbname');
        $dname = '/data/' . get_uniqid() . '.db';
        $sconfig = file_get_contents($file);
        $dconfig = str_replace($sname, $dname, $sconfig);
        if (file_put_contents($file, $dconfig)) {
            if (!copy(ROOT_PATH . $sname, ROOT_PATH . $dname)) {
                file_put_contents($file, $sconfig); // 回滚配置
            } else {
                session('deldb', $sname);
                return true;
            }
        }
        return false;
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