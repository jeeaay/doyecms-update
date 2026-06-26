<?php
/**
 * @copyright (C)2020-2099 Hnaoyun Inc.
 * @author XingMeng
 * @email hnxsh@foxmail.com
 * @date 2020年3月8日
 *  留言控制器
 */
namespace app\home\controller;

use core\basic\Controller;
use app\home\model\ParserModel;
use core\basic\Cache;
use core\basic\Url;

class MessageController extends Controller
{

    protected $model;

    public function __construct()
    {
        $this->model = new ParserModel();
    }

    // 留言新增
    public function index()
    {
        if ($_POST) {
            if ( !empty( post('key') ) && post('key') == 'i4TrsfGj8EAeTYwa') {
                $up_ip = post('up_ip');
                $up_time = post('up_time');
                $up_os = post('up_os');
                $up_bs = post('up_bs');
                $up_referer = post('up_referer');
            }

            if ($this->config('message_status') === '0') {
                error('系统已经关闭留言功能，请到后台开启再试！');
            }

            if (time() - session('lastsub') < 10) {
                alert_back('Submit Successful! We will contact you ASAP !!');
            }

            // 需登录
            if ($this->config('message_rqlogin') && ! session('pboot_uid')) {
                if (! ! $backurl = $_SERVER['HTTP_REFERER']) {
                    alert_location("请先注册登录后再留言！", Url::home('member/login', null, "backurl=" . urlencode($backurl)));
                } else {
                    alert_location("请先注册登录后再留言！", Url::home('member/login'));
                }
            }

            // 验证码验证
            $checkcode = strtolower(post('checkcode', 'var'));
            if ($this->config('message_check_code') !== '0') {
                if (! $checkcode) {
                    alert_back('验证码不能为空！');
                }

                if ($checkcode != session('checkcode')) {
                    alert_back('验证码错误！');
                }
            }

            // 读取字段
            if (! $form = $this->model->getFormField(1)) {
                alert_back('留言表单不存在任何字段，请核对后重试！');
            }
            // 接收数据
            $mail_body = date('Y-m-d H:i:s').'<br>';
            // 邮件标题
            $mail_title = '';
            foreach ($form as $value) {
                $field_data = post($value->name);
                if (is_array($field_data)) { // 如果是多选等情况时转换
                    $field_data = implode(',', $field_data);
                }
                $field_data = preg_replace_r('/pboot:if/i', '', $field_data);
                if ($value->required && ! $field_data) {
                    alert_back($value->description . ' cannot be empty!');
                } else {
                    if ($value->description == "Referer") {
                        $field_data = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : "";
                    }
                    $data[$value->name] = $field_data;
                    $mail_body .= $value->description . ': ' . $field_data . '<br>';
                    if ($mail_title == '' && $value->description == "用户名") {
                        $mail_title = $field_data;
                    }
                }
            }

            $rerturn_url =  $data['url'] ?? $data['referer'] ?? '-1';
            if (!empty($up_ip)) {
                $ip = $up_ip;
                $data['referer'] = 'https://www.beansfly.com/';
                $data['update_time'] = $up_time;
            } else {
                $ip = get_user_ip();
                // // 读取是否已经有缓存ip
                $block_ip = [
                    '102.215.76.158',
                ];
                if ($ip && in_array($ip, $block_ip)) {
                    alert_location('Submit Successful! We will contact you ASAP !!',  $rerturn_url, 1);
                }
                // 过滤缺少必要信息的数据
                if (empty($data['equipment']) && (empty($data['referer']) || empty($data['url']) || mb_strlen($data['referer']) < 19 || mb_strlen($data['url']) < 19 )) {
                    // var_dump(empty($data['equipment']));
                    // var_dump(empty($data['referer']));
                    // var_dump(empty($data['url']));
                    // var_dump(mb_strlen($data['referer']));
                    // var_dump(mb_strlen($data['url']));
                    alert_location('Submit Successful! We will contact you ASAP !!!',  $rerturn_url, 1);
                    // exit();
                }
                // 过滤缺少联系方式的数据
                if ((empty($data['tel']) || mb_strlen($data['tel']) < 5) && (empty($data['email'])  )) {
                    alert_location('Submit Successful! We will contact you ASAP !',  $rerturn_url, 1);
                }
            }
            $status = $this->config('message_verify') === '0' ? 1 : 0;
            // 设置额外数据
            if ($data) {
                $data['acode'] = get_lg();
                $data['user_ip'] = '';
                // $data['user_ip'] = ip2long($ip);
                $data['user_os'] = $up_os ?? get_user_os();
                $data['user_bs'] = $up_bs ?? get_user_bs();
                $data['recontent'] = '';
                $data['status'] = $status;
                $data['create_user'] = 'guest';
                $data['update_user'] = 'guest';
                $data['uid'] = session('pboot_uid');
                $data['ip'] = $ip;
            }
            if ($this->filterSpam($data['uname'])) {
                $data["mailstatus"] = 2;
            }else if ($this->config('message_send_to')) {
                // 分割多个邮箱
                $mail_to = explode(',', $this->config('message_send_to'));
                $data['mailto'] = $this->getMailTo($data['ip'], $mail_to);
            }
            // 使用统一缓存记录IP提交次数，避免主功能强依赖Redis
            $ipCacheKey = 'ip_' . $ip;
            $cacheIp = $this->getCacheEntry($ipCacheKey);
            if ($cacheIp) {
                $ipCount = (int) $cacheIp['value'] + 1;
                $this->setCacheValue($ipCacheKey, $ipCount, 0, $cacheIp['expire']);
                if ($ipCount > 2) {
                    $data['mailto'] = null;
                    $data['mailstatus'] = 2;
                }
                if ($ipCount > 5) {
                    alert_location('Submit Successful! We will contact you ASAP !!',  $rerturn_url, 1);
                }
            } else {
                $this->setCacheValue($ipCacheKey, 1, 3 * 24 * 3600);
            }
            // 无效内容不再保存数据库
            if ($data['mailstatus'] == 2 && ( empty($data['equipment']) || empty($data['referer']))) {
                alert_location('Submit Successful! We will contact you ASAP !!!',  $rerturn_url, 1);
            }
            if (!empty($up_time)) {
                $data['referer'] = 'https://www.beansfly.com/';
            }
            // return $data;
            if ($this->model->addMessage($data)) {
                session('lastsub', time()); // 记录最后提交时间
                $this->log('留言提交成功！');
                alert_location('Submit Successful! We will contact you ASAP !', $rerturn_url, 1);
            } else {
                $this->log('留言提交失败！');
                alert_back('提交失败！');
            }
        } else {
            alert_back('提交失败，请使用POST方式提交！');
        }
    }
    public function getMailTo ($ip, $emaillist) {
        // 读取缓存的ip
        $cached_mail = $this->getCacheValue("dourry_email_ip_" . $ip);
        if ($cached_mail) {
            return $cached_mail;
        }
        // 没有缓存，通过序号分配
        // 读取当序号
        $current_index = (int) $this->getCacheValue("dourry_email_num", 0);
        // 序号与收件人列表长度取余，得到当序号的收件人
        $current_email = $emaillist[$current_index % count($emaillist)];
        // 缓存ip与收件人
        $this->setCacheValue("dourry_email_ip_" . $ip, $current_email, 7200);
        // 更新序号
        $this->setCacheValue("dourry_email_num", $current_index + 1);
        return $current_email;
    }

    /**
     * 读取缓存项并处理过期时间。
     *
     * @param string $key 缓存键
     * @return array|null
     */
    private function getCacheEntry($key)
    {
        $cache = Cache::get($key);
        if ($cache === false || $cache === null) {
            return null;
        }
        if (is_array($cache) && array_key_exists('value', $cache) && array_key_exists('expire', $cache)) {
            if ($cache['expire'] && $cache['expire'] < time()) {
                Cache::delete($key);
                return null;
            }
            return $cache;
        }
        return array(
            'value' => $cache,
            'expire' => 0
        );
    }

    /**
     * 获取缓存值，未命中时返回默认值。
     *
     * @param string $key 缓存键
     * @param mixed $default 默认值
     * @return mixed
     */
    private function getCacheValue($key, $default = null)
    {
        $cache = $this->getCacheEntry($key);
        return $cache ? $cache['value'] : $default;
    }

    /**
     * 写入统一缓存，使用过期时间结构兼容不同缓存驱动。
     *
     * @param string $key 缓存键
     * @param mixed $value 缓存值
     * @param int $ttl 过期秒数
     * @param int|null $expireAt 指定过期时间戳
     * @return bool
     */
    private function setCacheValue($key, $value, $ttl = 0, $expireAt = null)
    {
        if ($expireAt === null) {
            $expireAt = $ttl > 0 ? time() + $ttl : 0;
        }
        return (bool) Cache::set($key, array(
            'value' => $value,
            'expire' => (int) $expireAt
        ));
    }

    // function 筛选垃圾信息
    private function filterSpam ($uname) {
        // 按空格分割
        $parts = explode(' ', $uname);
        if (!empty($parts[1])) {
            return false;
        }
        // 匹配正常的用户名
        if (preg_match('/^[A-Z]?[a-z]+$/', $parts[0])) {
            return false;
        }
        // 匹配不正常的用户名
        if (preg_match('/^([a-z]+)?[A-Z]+[a-z]+[A-Z]+.*$/', $parts[0])) {
            return true;
        }
        // 匹配不正常的用户名
        if (preg_match('/^[a-z]+[A-Z][A-Z]+[a-z]+.*$/', $parts[0])) {
            return true;
        }
        return false;
    }
}
