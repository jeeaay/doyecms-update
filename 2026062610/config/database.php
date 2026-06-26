<?php
/*
 * @file            config/database.php
 * @description     如果修改为mysql数据库，请同时修改type和dbname两个参数
 * @author          jeay <wrjie@msn.cn>
 * @createTime      2025-06-10 09:07:07
 * @lastModified    2025-12-16 09:11:30
 * Copyright ©YourCompanyName All rights reserved
*/

return array(

    'database' => array(

        'type' => 'sqlite', // 数据库连接驱动类型: mysqli,sqlite,pdo_mysql,pdo_sqlite

        'host' => '127.0.0.1', // 数据库服务器

        'user' => 'doye', // 数据库连接用户名

        'passwd' => '123456', // 数据库连接密码

        'port' => '3306', // 数据库端口

        //'dbname' => getenv('DB_NAME') ?: 'doyecms' // 去掉注释，启用mysql数据库，注意修改前面的连接信息及type为mysqli

        'dbname' => getenv('DB_NAME') ?: '/data/doye_tvOqSSX02XCKyYPI.db' // 优先读取 .env 中的 DB_NAME，未设置时使用当前默认值
    )

);
