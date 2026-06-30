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

    // 真实客户端 IP 识别配置。
    // 直连站建议关闭 trusted_proxy_enable，仅使用 REMOTE_ADDR。
    // ESA 站可开启后按 trusted_headers 顺序读取可信头。
    // 若同时配置回源认证头和密钥，则只有认证通过才信任这些头。
    'client_ip' => array(
        'trusted_proxy_enable' => filter_var(getenv('TRUSTED_PROXY_ENABLE') ?: 'false', FILTER_VALIDATE_BOOLEAN),
        'trusted_headers' => getenv('TRUSTED_IP_HEADERS') ?: '',
        'trusted_proxy_auth_header' => getenv('TRUSTED_PROXY_AUTH_HEADER') ?: 'CDN_SRC_SEC',
        'trusted_proxy_auth_secret' => getenv('TRUSTED_PROXY_AUTH_SECRET') ?: ''
    ),

    // 通用缓存驱动，可选 memcache、redis、file
    // 优先读取 .env 中的 CACHE_HANDLER，默认保持 memcache 不变
    'cache' => array(
        'handler' => getenv('CACHE_HANDLER') ?: 'memcache'
    ),

    // Memcache连接配置，优先读取 .env 中的 MEMCACHE_* 变量
    'memcache' => array(
        'host' => getenv('MEMCACHE_HOST') ?: '127.0.0.1',
        'port' => (int) (getenv('MEMCACHE_PORT') ?: 11211)
    ),

    // Redis连接配置，优先读取 .env 中的 REDIS_* 变量
    'redis' => array(
        'host' => getenv('REDIS_HOST') ?: '127.0.0.1',
        'port' => (int) (getenv('REDIS_PORT') ?: 6379),
        'password' => getenv('REDIS_PASSWORD') ?: '',
        'select' => (int) (getenv('REDIS_DB') ?: 0),
        'timeout' => (float) (getenv('REDIS_TIMEOUT') ?: 3600),
        'prefix' => getenv('REDIS_PREFIX') ?: 'doye:'
    ),

    // 文件缓存配置，优先读取 .env 中的 FILE_CACHE_PATH 变量
    'file' => array(
        'path' => getenv('FILE_CACHE_PATH') ?: RUN_PATH . '/cache/data'
    )

);
