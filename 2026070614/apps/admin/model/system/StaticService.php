<?php
/**
 * @copyright (C)2016-2099 Hnaoyun Inc.
 * @author XingMeng
 * @email hnxsh@foxmail.com
 * @date 2023年01月01日
 *  静态页面生成服务
 */

namespace app\admin\model\system;

use app\admin\model\content\SiteModel;
use app\home\controller\ParserController;
use app\home\model\ParserModel;
use core\basic\Config;
use core\basic\Controller;
use core\view\Parser as TemplateParser;

class StaticService extends Controller
{
    private $parser;
    private $model;
    private $htmldir;
    private $staticDir;
    private $defaultLang;
    private $siteTheme;
    private $tplThemePath;
    private $tplThemeWebDir;
    
    // 移动端配置
    private $wapThemePath;
    private $wapThemeWebDir;
    private $mobileDir = 'm';
    private $enableMobile = false;

    public function __construct()
    {
        // 检查是否开启静态生成（直接从环境变量读取，避免数据库查询）
        $static_enable = filter_var(getenv('STATIC_GENERATE_ENABLE') ?: 'true', FILTER_VALIDATE_BOOLEAN);
        if (!$static_enable) {
            throw new \Exception('静态生成功能未开启');
        }

        $this->parser = new ParserController();
        $this->model = new ParserModel();

        $this->siteTheme = $this->loadSiteTheme();
        $this->tplThemePath = $this->buildTplThemePath($this->siteTheme);
        $this->tplThemeWebDir = $this->buildTplThemeWebDir($this->siteTheme);
        
        // 加载移动端模板路径
        $this->wapThemePath = $this->buildWapThemePath($this->siteTheme);
        $this->wapThemeWebDir = $this->buildWapThemeWebDir($this->siteTheme);
        
        // 根据系统配置决定是否启用移动端静态生成
        $this->enableMobile = (bool) Config::get('open_wap');
        
        // 模板目录
        $this->htmldir = Config::get('tpl_html_dir') ? Config::get('tpl_html_dir') . '/' : '';
        if ($this->htmldir == '/') {
            $this->htmldir = '';
        }
        
        // 静态生成配置（直接从环境变量读取）
        $this->staticDir = getenv('STATIC_GENERATE_DIR') ?: '/html';
        $this->defaultLang = getenv('STATIC_DEFAULT_LANG_DIR') ?: 'default';
        $this->mobileDir = getenv('STATIC_MOBILE_DIR') ?: 'm';
        
        // 设置副作用隔离标识
        if (!defined('IS_STATIC_GENERATE')) {
            define('IS_STATIC_GENERATE', true);
        }
    }

    /**
     * 生成首页
     */
    public function generateIndex()
    {
        // 生成桌面端首页
        $desktopPath = $this->generateIndexForDevice('desktop');
        
        // 生成移动端首页
        if ($this->enableMobile) {
            $this->generateIndexForDevice('mobile');
        }
        
        return $desktopPath;
    }
    
    /**
     * 按设备类型生成首页
     */
    private function generateIndexForDevice($device = 'desktop')
    {
        // 根据设备类型选择模板和目录
        $tplThemePath = $device === 'mobile' ? $this->wapThemePath : $this->tplThemePath;
        $langDir = $device === 'mobile' ? $this->mobileDir : $this->defaultLang;
        
        // 模拟首页渲染逻辑
        $content = $this->parseFrontendTemplate($this->htmldir . 'index.html', $tplThemePath);
        
        // 解析CMS标签
        $content = $this->parser->parserBefore($content);
        $content = str_replace('{pboot:pagetitle}', Config::get('index_title') ?: '{pboot:sitetitle}-{pboot:sitesubtitle}', $content);
        $content = $this->parser->parserPositionLabel($content, -1, '首页', SITE_INDEX_DIR . '/');
        $content = $this->parser->parserSpecialPageSortLabel($content, 0, '', SITE_INDEX_DIR . '/');
        $content = $this->parser->parserAfter($content);

        // 写入文件
        $path = $this->getStaticPath('/', $langDir);
        $this->writeHtml($path, $content);
        
        return $path;
    }
    
    /**
     * 获取所有需要生成的栏目编码（排除外链）
     */
    public function getAllScodes()
    {
        return $this->model->getScodes(array(1, 2)); // 1=单页, 2=列表
    }

    /**
     * 获取栏目内容ID列表
     */
    public function getContentIds($scode)
    {
        return $this->model->getContentIds(array($scode));
    }

    public function getGeneratePlan($cursor = 0, $limit = 100)
    {
        $cursor = intval($cursor);
        $limit = intval($limit);
        if ($cursor < 0) $cursor = 0;
        if ($limit < 1) $limit = 1;
        if ($limit > 500) $limit = 500;

        $items = array();
        
        // 阶段 1: 首页 (cursor = 0)
        if ($cursor == 0) {
            $items[] = array('type' => 'index');
            $cursor++;

            // 计算总任务数：1(首页) + 栏目总数 + 内容总数
            $total_sorts = count($this->getAllScodes());
            $total_content = $this->model->getAllContentCount();
            $total = 1 + $total_sorts + $total_content;

            return array(
                'total' => $total, 
                'cursor' => 0,
                'limit' => $limit,
                'next_cursor' => $cursor,
                'has_more' => true,
                'items' => $items
            );
        }

        // 阶段 2: 栏目
        // 假设栏目总数不超过 10000，使用 1-10000 范围表示栏目 cursor
        // 实际 cursor 偏移量：cursor - 1
        $sort_offset = $cursor - 1;
        
        $scodes = $this->getAllScodes();
        $total_sorts = count($scodes);
        
        if ($sort_offset < $total_sorts) {
            $slice = array_slice($scodes, $sort_offset, $limit);
            foreach ($slice as $scode) {
                if ($scode) {
                    $items[] = array('type' => 'sort', 'scode' => $scode);
                }
            }
            $cursor += count($items);
            return array(
                'total' => -1,
                'cursor' => $sort_offset + 1,
                'limit' => $limit,
                'next_cursor' => $cursor,
                'has_more' => true,
                'items' => $items
            );
        }

        // 阶段 3: 内容
        // 栏目生成完毕，进入内容生成阶段
        // 内容 cursor 起始点：total_sorts + 1
        // 实际内容 offset：cursor - 1 - total_sorts
        $content_offset = $cursor - 1 - $total_sorts;
        
        // 获取所有内容 ID (分页获取以优化性能)
        // 这里的 limit 可以直接传给数据库查询
        $content_items = $this->model->getAllContentIds($content_offset, $limit);
        
        if ($content_items) {
            foreach ($content_items as $id) {
                $items[] = array('type' => 'content', 'id' => $id);
            }
            $cursor += count($items);
            $has_more = count($items) >= $limit; // 如果取满 limit，假设还有更多
        } else {
            $has_more = false;
        }

        return array(
            'total' => -1,
            'cursor' => $cursor - count($items),
            'limit' => $limit,
            'next_cursor' => $cursor,
            'has_more' => $has_more,
            'items' => $items
        );
    }

    public function generateFromPlanItem($item)
    {
        if (!is_array($item) || !isset($item['type'])) {
            throw new \Exception('生成项格式错误');
        }
        $type = $item['type'];
        if ($type === 'index') {
            return $this->generateIndex();
        }
        if ($type === 'sort') {
            if (!isset($item['scode']) || !$item['scode']) {
                throw new \Exception('栏目生成项缺少 scode');
            }
            return $this->generateSort($item['scode']);
        }
        if ($type === 'content') {
            if (!isset($item['id']) || !is_numeric($item['id'])) {
                throw new \Exception('内容生成项缺少 id');
            }
            return $this->generateContent(intval($item['id']));
        }
        throw new \Exception('未知生成类型：' . $type);
    }

    /**
     * 生成指定栏目首页及分页
     * @param string $scode 栏目编码
     * @param bool $allPages 是否生成所有分页
     */
    public function generateSort($scode, $allPages = true)
    {
        $sort = $this->model->getSort($scode);
        if (!$sort) return false;
        
        // 生成桌面端
        $desktopPath = $sort->type == 1 
            ? $this->generateAbout($sort, $allPages, 'desktop')
            : $this->generateList($sort, $allPages, 'desktop');
        
        // 生成移动端
        if ($this->enableMobile) {
            if ($sort->type == 1) {
                $this->generateAbout($sort, $allPages, 'mobile');
            } else {
                $this->generateList($sort, $allPages, 'mobile');
            }
        }
        
        return $desktopPath;
    }
    
    /**
     * 生成内容详情页
     * @param int $id 内容ID
     */
    public function generateContent($id)
    {
        $data = $this->model->getContent($id);
        if (!$data) return false;
        
        $sort = $this->model->getSort($data->scode);
        if (!$sort) return false;
        
        // 生成桌面端
        $desktopPath = $this->generateContentForDevice($sort, $data, 'desktop');
        
        // 生成移动端
        if ($this->enableMobile) {
            $this->generateContentForDevice($sort, $data, 'mobile');
        }
        
        return $desktopPath;
    }
    
    /**
     * 按设备类型生成内容详情页
     */
    private function generateContentForDevice($sort, $data, $device = 'desktop')
    {
        if (!$sort->contenttpl) {
            throw new \Exception('请到后台设置分类栏目内容页模板！');
        }
        
        $tplThemePath = $device === 'mobile' ? $this->wapThemePath : $this->tplThemePath;
        $langDir = $device === 'mobile' ? $this->mobileDir : $this->defaultLang;
        
        $tpl = $sort->contenttpl;
        $content = $this->parseFrontendTemplate($this->htmldir . $tpl, $tplThemePath);
        $content = $this->parser->parserBefore($content);
        $content = str_replace('{pboot:pagetitle}', Config::get('content_title') ?: '{content:title}-{sort:name}-{pboot:sitetitle}-{pboot:sitesubtitle}', $content);
        $content = str_replace('{pboot:pagekeywords}', '{content:keywords}', $content);
        $content = str_replace('{pboot:pagedescription}', '{content:description}', $content);
        $content = $this->parser->parserPositionLabel($content, $sort->scode);
        $content = $this->parser->parserSortLabel($content, $sort);
        $content = $this->parser->parserCurrentContentLabel($content, $sort, $data);
        $content = $this->parser->parserCommentLabel($content);
        $content = $this->parser->parserAfter($content);

        $url = $this->buildContentUrl($sort, $data);
        $path = $this->getStaticPath($url, $langDir);
        $this->writeHtml($path, $content);
        
        return $path;
    }

    // --- 私有辅助方法 ---

    /**
     * 生成栏目封面页；若模板内包含分页列表，则同步生成各分页静态文件。
     *
     * @param object $sort 栏目对象
     * @param bool $allPages 是否生成所有分页
     * @param string $device 设备类型 desktop/mobile
     * @return string|false
     */
    private function generateAbout($sort, $allPages = true, $device = 'desktop')
    {
        // 渲染单页
        // 参考 IndexController::getAboutPage
        $data = $this->model->getAbout($sort->scode);
        if (!$data) return false;
        
        if (!$sort->contenttpl) {
            throw new \Exception('请到后台设置分类栏目内容页模板！');
        }
        
        $tplThemePath = $device === 'mobile' ? $this->wapThemePath : $this->tplThemePath;
        $langDir = $device === 'mobile' ? $this->mobileDir : $this->defaultLang;
        
        $firstPage = $this->renderAboutPage($sort, $data, 1, $tplThemePath);
        $pageCount = $allPages ? max(1, intval($firstPage['page_count'])) : 1;
        $firstPath = '';

        for ($page = 1; $page <= $pageCount; $page++) {
            $pageData = $page === 1 ? $firstPage : $this->renderAboutPage($sort, $data, $page, $tplThemePath);
            $path = $this->getStaticPath($pageData['url'], $langDir);
            $this->writeHtml($path, $pageData['content']);
            if (!$firstPath) {
                $firstPath = $path;
            }
        }

        return $firstPath;
    }

    private function generateList($sort, $allPages, $device = 'desktop')
    {
        // 渲染列表页
        // 参考 IndexController::getListPage
        
        if (!$sort->listtpl) {
            return false;
        }
        
        $tplThemePath = $device === 'mobile' ? $this->wapThemePath : $this->tplThemePath;
        $langDir = $device === 'mobile' ? $this->mobileDir : $this->defaultLang;

        $firstPage = $this->renderListPage($sort, 1, $tplThemePath);
        $pageCount = $allPages ? max(1, intval($firstPage['page_count'])) : 1;
        $firstPath = '';

        for ($page = 1; $page <= $pageCount; $page++) {
            $pageData = $page === 1 ? $firstPage : $this->renderListPage($sort, $page, $tplThemePath);
            $path = $this->getStaticPath($pageData['url'], $langDir);
            $this->writeHtml($path, $pageData['content']);
            if (!$firstPath) {
                $firstPath = $path;
            }
        }

        return $firstPath;
    }

    private function buildSortUrl($sort)
    {
        // 简单模拟，实际需参考 Url::sort
        if ($sort->filename) {
            return '/' . $sort->filename . '/';
        } else {
            return '/list/' . $sort->scode . '/'; // 默认动态兼容，静态化需强制规则
        }
    }

    /**
     * 构建列表分页 URL，第 1 页保留栏目首页，后续页输出静态分页地址。
     *
     * @param object $sort 栏目对象
     * @param int $page 页码
     * @return string
     */
    private function buildSortPageUrl($sort, $page)
    {
        $baseUrl = $this->buildSortUrl($sort);
        if ($page <= 1) {
            return $baseUrl;
        }

        $url_break_char = Config::get('url_break_char') ?: '_';
        $url_rule_suffix = Config::get('url_rule_suffix') ?: '.html';
        $baseUrl = rtrim($baseUrl, '/');
        if (!$baseUrl) {
            return '.' . $url_break_char . intval($page) . $url_rule_suffix;
        }

        return $baseUrl . $url_break_char . intval($page) . $url_rule_suffix;
    }

    /**
     * 渲染列表页指定分页，并临时注入静态生成所需的前台分页上下文。
     *
     * @param object $sort 栏目对象
     * @param int $page 页码
     * @param string $tplThemePath 模板主题路径
     * @return array
     */
    private function renderListPage($sort, $page, $tplThemePath = null)
    {
        $snapshot = $this->applyStaticPagingContext($sort, $page);
        try {
            $this->resetPagingViewVars();
            $tpl = $sort->listtpl;
            $content = $this->parseFrontendTemplate($this->htmldir . $tpl, $tplThemePath);
            $content = $this->parser->parserBefore($content);
            $pagetitle = $sort->title ? "{sort:title}" : "{sort:name}";
            $content = str_replace('{pboot:pagetitle}', Config::get('list_title') ?: ($pagetitle . '-{pboot:sitetitle}-{pboot:sitesubtitle}'), $content);
            $content = str_replace('{pboot:pagekeywords}', '{sort:keywords}', $content);
            $content = str_replace('{pboot:pagedescription}', '{sort:description}', $content);
            $content = $this->parser->parserPositionLabel($content, $sort->scode);
            $content = $this->parser->parserSortLabel($content, $sort);
            $content = $this->parser->parserListLabel($content, $sort->scode);
            $content = $this->parser->parserAfter($content);

            return array(
                'content' => $content,
                'page_count' => intval($this->getVar('pagecount')) ?: 1,
                'url' => $this->buildSortPageUrl($sort, $page)
            );
        } finally {
            $this->restoreStaticPagingContext($snapshot);
        }
    }

    /**
     * 渲染栏目封面指定分页，兼容封面模板内嵌分页列表的静态输出。
     *
     * @param object $sort 栏目对象
     * @param object $data 单页内容对象
     * @param int $page 页码
     * @param string $tplThemePath 模板主题路径
     * @return array
     */
    private function renderAboutPage($sort, $data, $page, $tplThemePath = null)
    {
        $snapshot = $this->applyStaticPagingContext($sort, $page);
        try {
            $this->resetPagingViewVars();
            $tpl = $sort->contenttpl;
            $content = $this->parseFrontendTemplate($this->htmldir . $tpl, $tplThemePath);
            $content = $this->parser->parserBefore($content);
            $pagetitle = $sort->title ? "{sort:title}" : "{content:title}";
            $content = str_replace('{pboot:pagetitle}', Config::get('about_title') ?: ($pagetitle . '-{pboot:sitetitle}-{pboot:sitesubtitle}'), $content);
            $content = str_replace('{pboot:pagekeywords}', '{content:keywords}', $content);
            $content = str_replace('{pboot:pagedescription}', '{content:description}', $content);
            $content = $this->parser->parserPositionLabel($content, $sort->scode);
            $content = $this->parser->parserSortLabel($content, $sort);
            $content = $this->parser->parserCurrentContentLabel($content, $sort, $data);
            $content = $this->parser->parserCommentLabel($content);
            $content = $this->parser->parserAfter($content);

            return array(
                'content' => $content,
                'page_count' => intval($this->getVar('pagecount')) ?: 1,
                'url' => $this->buildSortPageUrl($sort, $page)
            );
        } finally {
            $this->restoreStaticPagingContext($snapshot);
        }
    }

    /**
     * 注入静态分页生成上下文，避免分页链接错误指向后台请求地址。
     *
     * @param object $sort 栏目对象
     * @param int $page 页码
     * @return array
     */
    private function applyStaticPagingContext($sort, $page)
    {
        $snapshot = array(
            'get_page_exists' => array_key_exists('page', $_GET),
            'get_page' => array_key_exists('page', $_GET) ? $_GET['page'] : null,
            'static_base_exists' => isset($_SERVER['STATIC_GENERATE_PAGING_BASE_URL']),
            'static_base' => isset($_SERVER['STATIC_GENERATE_PAGING_BASE_URL']) ? $_SERVER['STATIC_GENERATE_PAGING_BASE_URL'] : null,
            'static_url_exists' => isset($_SERVER['STATIC_GENERATE_PAGING_URL']),
            'static_url' => isset($_SERVER['STATIC_GENERATE_PAGING_URL']) ? $_SERVER['STATIC_GENERATE_PAGING_URL'] : null,
            'static_qs_exists' => isset($_SERVER['STATIC_GENERATE_QUERY_STRING']),
            'static_qs' => isset($_SERVER['STATIC_GENERATE_QUERY_STRING']) ? $_SERVER['STATIC_GENERATE_QUERY_STRING'] : null,
        );

        if ($page > 1) {
            $_GET['page'] = intval($page);
        } else {
            unset($_GET['page']);
        }

        $_SERVER['STATIC_GENERATE_PAGING_BASE_URL'] = rtrim($this->buildSortUrl($sort), '/');
        $_SERVER['STATIC_GENERATE_PAGING_URL'] = $this->buildSortPageUrl($sort, $page);
        $_SERVER['STATIC_GENERATE_QUERY_STRING'] = '';

        return $snapshot;
    }

    /**
     * 恢复静态分页生成前的请求上下文，避免污染后续批次。
     *
     * @param array $snapshot 上下文快照
     * @return void
     */
    private function restoreStaticPagingContext(array $snapshot)
    {
        if ($snapshot['get_page_exists']) {
            $_GET['page'] = $snapshot['get_page'];
        } else {
            unset($_GET['page']);
        }

        if ($snapshot['static_base_exists']) {
            $_SERVER['STATIC_GENERATE_PAGING_BASE_URL'] = $snapshot['static_base'];
        } else {
            unset($_SERVER['STATIC_GENERATE_PAGING_BASE_URL']);
        }

        if ($snapshot['static_url_exists']) {
            $_SERVER['STATIC_GENERATE_PAGING_URL'] = $snapshot['static_url'];
        } else {
            unset($_SERVER['STATIC_GENERATE_PAGING_URL']);
        }

        if ($snapshot['static_qs_exists']) {
            $_SERVER['STATIC_GENERATE_QUERY_STRING'] = $snapshot['static_qs'];
        } else {
            unset($_SERVER['STATIC_GENERATE_QUERY_STRING']);
        }
    }

    /**
     * 重置分页相关模板变量，避免上一轮渲染残留影响当前页面。
     *
     * @return void
     */
    private function resetPagingViewVars()
    {
        $this->assign('pagebar', '');
        $this->assign('pagecurrent', 0);
        $this->assign('pagecount', 0);
        $this->assign('pagerows', 0);
        $this->assign('pageindex', '');
        $this->assign('pagepre', '');
        $this->assign('pagenext', '');
        $this->assign('pagelast', '');
        $this->assign('pagestatus', '');
        $this->assign('pagenumbar', '');
        $this->assign('pageselectbar', '');
    }
    
    private function buildContentUrl($sort, $data)
    {
         // 简单模拟，实际需参考 Url::content
        if ($data->filename) {
            if ($sort->filename) {
                return '/' . $sort->filename . '/' . $data->filename . '.html';
            } else {
                return '/' . $data->filename . '.html';
            }
        } else {
             return '/' . $sort->filename . '/' . $data->id . '.html';
        }
    }

    private function getStaticPath($url, $langDir = null)
    {
        // 移除 URL 中的 query string
        $url = strtok($url, '?');
        
        // 确保以 / 开头
        if (strpos($url, '/') !== 0) {
            $url = '/' . $url;
        }
        
        // 如果以 / 结尾，追加 index.html
        if (substr($url, -1) == '/') {
            $url .= Config::get('static_index_filename') ?: 'index.html';
        }
        
        // 使用指定的语言目录或默认语言目录
        $lang = $langDir ?: $this->defaultLang;
        
        // 拼接完整物理路径
        // 格式：基础路径 + static_dir + lang + url
        // 迁移后使用 /www 作为基础路径，未迁移使用 DOC_PATH
        $basePath = defined('WWW_MIGRATED') ? ROOT_PATH . '/www' : DOC_PATH;
        return $basePath . $this->staticDir . '/' . $lang . $url;
    }

    private function parseFrontendTemplate($file, $tplThemePath = null)
    {
        $file = ltrim((string) $file, "/\\");
        
        // 使用指定的模板路径或默认路径
        $themePath = $tplThemePath ?: $this->tplThemePath;
        
        $tpl_file = rtrim($themePath, "/\\") . '/' . $file;
        
        // 尝试修复路径中可能出现的双斜杠问题
        $tpl_file = str_replace(array('//', '\\\\'), array('/', '\\'), $tpl_file);

        if (!is_file($tpl_file)) {
            // 尝试去掉 htmldir 再找一次（容错）
            if ($this->htmldir && strpos($file, $this->htmldir) === 0) {
                $file_retry = substr($file, strlen($this->htmldir));
                $tpl_file_retry = rtrim($themePath, "/\\") . '/' . $file_retry;
                if (is_file($tpl_file_retry)) {
                    $tpl_file = $tpl_file_retry;
                } else {
                     throw new \Exception('模板文件读取错误！' . $tpl_file);
                }
            } else {
                 throw new \Exception('模板文件读取错误！' . $tpl_file);
            }
        }

        if (!defined('APP_THEME_DIR')) {
            define('APP_THEME_DIR', $this->tplThemeWebDir);
        } elseif (APP_THEME_DIR !== $this->tplThemeWebDir) {
            throw new \Exception('模板主题目录常量冲突：APP_THEME_DIR=' . APP_THEME_DIR . '，期望=' . $this->tplThemeWebDir);
        }

        $tpl_c_dir = RUN_PATH . '/complile';
        check_dir($tpl_c_dir, true);
        $tpl_c_file = $tpl_c_dir . '/' . md5($tpl_file) . '.php';

        if (!file_exists($tpl_c_file) || filemtime($tpl_c_file) < filemtime($tpl_file) || !Config::get('tpl_parser_cache')) {
            $content = TemplateParser::compile($themePath, $tpl_file);
            file_put_contents($tpl_c_file, $content) ?: error('编译文件' . $tpl_c_file . '生成出错！请检查目录是否有可写权限！');
            $compile = true;
        }

        ob_start();
        $rs = include $tpl_c_file;
        if (!isset($compile)) {
            if (!is_array($rs)) {
                throw new \Exception('模板编译文件返回值异常：' . $tpl_c_file);
            }
            foreach ($rs as $value) {
                if (!file_exists($value) || filemtime($tpl_c_file) < filemtime($value) || !Config::get('tpl_parser_cache')) {
                    $content = TemplateParser::compile($this->tplThemePath, $tpl_file);
                    file_put_contents($tpl_c_file, $content) ?: error('编译文件' . $tpl_c_file . '生成出错！请检查目录是否有可写权限！');
                    ob_clean();
                    include $tpl_c_file;
                    break;
                }
            }
        }
        $content = ob_get_contents();
        ob_end_clean();
        return $content;
    }

    private function loadSiteTheme()
    {
        try {
            $site = (new SiteModel())->getList();
            $theme = $site && !empty($site->theme) ? basename($site->theme) : 'default';
            return $theme ?: 'default';
        } catch (\Throwable $e) {
            return 'default';
        }
    }

    private function buildTplThemePath($theme)
    {
        $tpl_dir = Config::get('tpl_dir');
        $base = is_array($tpl_dir) ? current($tpl_dir) : '/template';
        $base = '/' . trim((string) $base, "/\\");
        $theme = basename((string) $theme) ?: 'default';
        return rtrim(ROOT_PATH, "/\\") . $base . '/' . $theme;
    }

    private function buildTplThemeWebDir($theme)
    {
        $tpl_dir = Config::get('tpl_dir');
        $base = is_array($tpl_dir) ? current($tpl_dir) : '/template';
        $base = '/' . trim((string) $base, "/\\");
        $theme = basename((string) $theme) ?: 'default';
        return $base . '/' . $theme;
    }

    private function buildWapThemePath($theme)
    {
        $tpl_dir = Config::get('tpl_dir');
        $base = is_array($tpl_dir) ? current($tpl_dir) : '/template';
        $base = '/' . trim((string) $base, "/\\");
        $theme = basename((string) $theme) ?: 'default';
        return rtrim(ROOT_PATH, "/\\") . $base . '/' . $theme . '/wap';
    }

    private function buildWapThemeWebDir($theme)
    {
        $tpl_dir = Config::get('tpl_dir');
        $base = is_array($tpl_dir) ? current($tpl_dir) : '/template';
        $base = '/' . trim((string) $base, "/\\");
        $theme = basename((string) $theme) ?: 'default';
        return $base . '/' . $theme . '/wap';
    }

    private function writeHtml($path, $content)
    {
        if (! check_dir(dirname($path), true)) {
            throw new \Exception('创建目录失败：' . dirname($path));
        }
        if (! file_put_contents($path, $content)) {
            throw new \Exception('写入文件失败：' . $path);
        }
    }

    private function iterateGenerateItems()
    {
        yield array('type' => 'index');

        $scodes = $this->getAllScodes();
        $seenContentIds = array();

        foreach ($scodes as $scode) {
            if (!$scode) {
                continue;
            }
            yield array('type' => 'sort', 'scode' => $scode);

            $ids = $this->getContentIds($scode);
            if (!$ids) {
                continue;
            }
            foreach ($ids as $id) {
                $id = intval($id);
                if ($id < 1) {
                    continue;
                }
                if (isset($seenContentIds[$id])) {
                    continue;
                }
                $seenContentIds[$id] = true;
                yield array('type' => 'content', 'id' => $id);
            }
        }
    }
}
