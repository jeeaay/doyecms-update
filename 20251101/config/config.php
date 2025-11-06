<?php
return array(

    // 定义CMS名称
    'cmsname' => 'DoyeCMS',
    'debug' => filter_var(getenv('DEBUG_MODE') ?: 'true', FILTER_VALIDATE_BOOLEAN),
    // 增加不带www跳转到带www的开关配置
    'enable_domain_redirect' => 1,

    // 增加域名配置
    'domain_name' => 'doye.top',

    // 模板内容输出缓存开关
    'tpl_html_cache' => 1,

    // 模板内容缓存有效时间（秒）
    'tpl_html_cache_time' => 900000000000,

    // 会话文件使用网站路径
    'session_in_sitepath' => 1,
    // 前台URL分隔符
    'url_break_char' => '/list_',
    // 默认分页大小
    'pagesize' => 15,

    // 分页条数字数量
    'pagenum' => 5,

    // 访问页面规则，如禁用浏览器、操作系统类型
    'access_rule' => array(
        'deny_bs' => 'MJ12bot,IE6,IE7'
    ),

    // 上传配置
    'upload' => array(
        'format' => 'jpg,jpeg,png,gif,xls,xlsx,doc,docx,ppt,pptx,rar,zip,pdf,txt,mp4,avi,flv,rmvb,mp3,otf,ttf',
        'max_width' => '1920',
        'max_height' => ''
    ),

    // 缩略图配置
    'ico' => array(
        'max_width' => '1000',
        'max_height' => '1000'
    ),

    // 模块模板路径定义
    'tpl_dir' => array(
        'home' => '/template'
    ),
    'redis' => array(
        'host' => '127.0.0.1',
        'port' => 6379,
        'password' => '',
        'select' => 0,
        'timeout' => 3600,
        'prefix' => 'doye:'
    )

);
