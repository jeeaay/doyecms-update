<?php
/**
 * @copyright (C)2016-2099 Hnaoyun Inc.
 * @author XingMeng
 * @email hnxsh@foxmail.com
 * @date 2017年2月7日
 *  验证码控制器
 */
namespace app\admin\controller\system;

use core\basic\Controller;
use core\extend\code\Code;

class CodeController extends Controller
{
    public function index()
    {
        // 检查 Referer
        $referer = $_SERVER['HTTP_REFERER'] ?? '';
        $host = $_SERVER['HTTP_HOST'] ?? '';
        if (empty($referer) || (strpos($referer, '://' . $host) !== 4 && strpos($referer, '://' . $host) !== 5)) {
            die('非法调用验证码！');
        }

        // 生成验证码
        $code = new Code();
        $code->height = 45;
        $code->width = 120;
        $code->fontsize = 18;
        $code->charset = 'abcdefghkmnprtuvwxy23456789ABCDEFGHKMNPRTUVWXY';
        $code->doimg();
        session('checkcode', $code->getCode());
    }
}
