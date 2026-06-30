## 安装依赖

```bash
pnpm i
```

## 创建项目目录

例如新的模板叫`demo`，请将`demo`替换为你的模板名称，名目规范为`项目名+年` 如 `abc2025`

在`static`下创建`demo`目录

在`demo`目录下创建`src`和`css`目录，`src`是将来`tailwindcss`的输入目录，`css`是输出目录。

为了规范引用 其他静态资源也都放入`static/demo/`目录下，例如`js`、`img`、`font`等目录。

```
static/
    demo/
        src/
        css/
        js/
        img/
        font/
```

现在前端目录创建好了，开始创建后端目录

在`templates`下创建目录`demo`，PC端模板文件放入`demo/html`目录下，移动端在`demo/wap/html`目录下
```
templates/
    demo/
        html/
            index.html
        wap/
            html/
                index.html
```

## 修改package.json

在`package.json`中已经写好了`dev`和`build`脚本，`dev`是开发模式，`build`是生产模式。

现在需要将两个脚本修改为上一步的项目名称，修改后的`package.json`如下：

```json
"scripts": {
    "dev": "tailwindcss -i ./static/项目名/src/app.css -o ./static/项目名/css/app.css --watch",
    "build": "tailwindcss -m -i ./static/项目名/src/app.css -o ./static/项目名/css/app.css"
}
```

实测在同样的`tailwindcss v4.1.11`,Max下可以写通配符，但是在Windows下必须写具体文件名。

```javascript
# 以下使用通配符的命令在win下报错找不到./src/\*.css
"scripts": {
    "dev": "tailwindcss -i ./src/*.css -o ./public/css/* --watch",
    "build": "tailwindcss -m -i ./src/*.css -o ./public/css/*"
},
```
## 创建样式文件并引用

在`static/项目名/src/`目录下创建`app.css`(文件名可以自己定义)文件，内容如下：

```css
@import "tailwindcss";
```

在后端模板中引用`app.css`文件，例如在`templates/项目名/html/index.html`中引用`app.css`文件，内容如下：

```html
<link rel="stylesheet" href="/static/项目名/css/app.css">
```

## 开发模式

```bash
pnpm dev
```

## 生产模式

```bash
pnpm build
```

## 开发环境的后台配置

在本地开发时 需要使用Nginx + PHP, 还需要在Nginx中配置伪静态规则：

```conf
location / {
    if (!-e $request_filename){
        rewrite ^/index.php(.*)$ /index.php?p=$1 last;
            rewrite ^(.*)$ /index.php?s=$1 last;
    }
}
```

识别CDN传入的客户端IP需要转发到PHP脚本中

```conf
# fastcgi_doyecms_headers.conf
fastcgi_param  HTTP_ALI_REAL_CLIENT_IP  $http_ali_real_client_ip;
fastcgi_param  HTTP_X_DCMS_REAL_IP      $http_x_dcms_real_ip;
fastcgi_param  HTTP_CDN_SRC_SEC         $http_cdn_src_sec;
```

```conf
# 将上面的fastcgi_doyecms_headers.conf包含到Nginx配置中 一般写在转发PHP脚本的位置
include fastcgi_doyecms_headers.conf;
# 或者写到fastcgi_params文件中 如fastcgi.conf中
```

cdn_src_sec设置位置是：CDN 域名 回源配置 修改入站请求，添加cdn-src-sec头，值为32位随机字符串（需要和本站的TRUSTED_PROXY_AUTH_SECRET一致）。

## 迁移脚本

项目初始化时会使用模板数据库开始创建。但对于一些老项目的数据库，需要使用迁移脚本来补充一些表结构。

```bash
php data/run_migrations.php
```

## 后台账户

默认后台账户为`doye`，密码为`123456789`。

## logo替换

后台logo在`/static/images/logo.png`中，可以直接覆盖

## 上线前待办

[ ] 搜索替换https://www.beansfly.com/
[ ] 修改script/domain_list.py
[ ] 修改数据库名 为确保安全使用16位随机字符串
[ ] 修改config/database.php 里的数据库名
[ ] 修改config/config.php 里的debug为false
[ ] 修改预设尺寸 apps/admin/view/default/js/cropper-integration.js
