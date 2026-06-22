<?php
/**
 * @copyright (C)2016-2099 Hnaoyun Inc.
 * @author XingMeng
 * @email hnxsh@foxmail.com
 * @date 2016年11月5日
 *  内核启动文件，请使用入口文件对本文件进行引用即可
 */
// 全局参数安全过滤
function filterDangerousParams(&$params) {
    // 处理REQUEST_URI中的路径参数
    if (isset($_SERVER['REQUEST_URI'])) {
        $uriParts = explode('?', $_SERVER['REQUEST_URI']);
        $path = $uriParts[0];
        $pathParts = explode('/', $path);
        foreach ($pathParts as $part) {
            $decodedValue = $part;
            do {
                $lastValue = $decodedValue;
                $decodedValue = urldecode($decodedValue);
            } while ($decodedValue != $lastValue);

            if (preg_match('/(\x22|eval|base64_decode|file_put_contents|passthru|\{pboot:if\()/i', $decodedValue)) {
                exit('404 Not Found');
            }
        }
    }

    // 单独处理POST参数
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        foreach ($params as $key => $value) {
            // 递归处理数组参数
            if (is_array($value)) {
                filterDangerousParams($value);
                continue;
            }
            
            // 递归解码URL编码参数
            $decodedValue = $value;
            do {
                $lastValue = $decodedValue;
                $decodedValue = urldecode($decodedValue);
            } while ($decodedValue != $lastValue);

            // 过滤特殊字符和危险函数
            if (preg_match('/(\x22|eval|base64_decode|file_put_contents|passthru|\{pboot:if\()/i', $decodedValue)) {
                exit('404 Not Found!');
            }
        }
    }
}

// 统一处理所有请求参数
if (!empty($_POST) || !empty($_GET) || (isset($_SERVER['PATH_INFO']) && strlen($_SERVER['PATH_INFO']) > 1)) {
    filterDangerousParams($_REQUEST);
}
// 引入初始化文件
require dirname(__FILE__) . '/init.php';

// 入口检测
defined('IS_INDEX') ?: die('不允许直接访问框架内核启动文件！');

// 启动内核
core\basic\Kernel::run();






