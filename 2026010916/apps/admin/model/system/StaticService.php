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

    public function __construct()
    {
        // 检查是否开启静态生成
        if (!Config::get('static_generate_enable')) {
            throw new \Exception('静态生成功能未开启');
        }

        $this->parser = new ParserController();
        $this->model = new ParserModel();

        $this->siteTheme = $this->loadSiteTheme();
        $this->tplThemePath = $this->buildTplThemePath($this->siteTheme);
        $this->tplThemeWebDir = $this->buildTplThemeWebDir($this->siteTheme);
        
        // 模板目录
        $this->htmldir = Config::get('tpl_html_dir') ? Config::get('tpl_html_dir') . '/' : '';
        if ($this->htmldir == '/') {
            $this->htmldir = '';
        }
        
        // 静态生成配置
        $this->staticDir = Config::get('static_generate_dir') ?: '/html';
        $this->defaultLang = Config::get('static_generate_default_lang_dir') ?: 'default';
        
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
        // 模拟首页渲染逻辑
        // 参考 IndexController::getIndexPage
        
        // 1. 获取模板内容
        $content = $this->parseFrontendTemplate($this->htmldir . 'index.html'); // 框架标签解析
        
        // 2. 解析CMS标签
        $content = $this->parser->parserBefore($content); 
        $content = str_replace('{pboot:pagetitle}', Config::get('index_title') ?: '{pboot:sitetitle}-{pboot:sitesubtitle}', $content);
        $content = $this->parser->parserPositionLabel($content, -1, '首页', SITE_INDEX_DIR . '/'); 
        $content = $this->parser->parserSpecialPageSortLabel($content, 0, '', SITE_INDEX_DIR . '/'); 
        $content = $this->parser->parserAfter($content); 

        // 3. 写入文件
        $path = $this->getStaticPath('/');
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
        
        // 单页模式
        if ($sort->type == 1) {
            return $this->generateAbout($sort);
        } 
        // 列表模式
        else {
            return $this->generateList($sort, $allPages);
        }
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

        // 渲染详情页
        // 参考 IndexController::getContentPage
        
        // 权限检查跳过（静态生成默认由管理员触发，假设生成公开内容）
        
        $tpl = $sort->contenttpl ?: $sort->def_contenttpl;
        if (!$tpl && $sort->filename) {
            $tpl = $sort->filename . '.html';
        }

        $content = $this->parseFrontendTemplate($this->htmldir . $tpl); 
        $content = $this->parser->parserBefore($content);
        $content = str_replace('{pboot:pagetitle}', Config::get('content_title') ?: '{content:title}-{sort:name}-{pboot:sitetitle}-{pboot:sitesubtitle}', $content);
        $content = str_replace('{pboot:pagekeywords}', '{content:keywords}', $content);
        $content = str_replace('{pboot:pagedescription}', '{content:description}', $content);
        $content = $this->parser->parserPositionLabel($content, $sort->scode); 
        $content = $this->parser->parserSortLabel($content, $sort); 
        $content = $this->parser->parserCurrentContentLabel($content, $sort, $data); 
        $content = $this->parser->parserCommentLabel($content); 
        $content = $this->parser->parserAfter($content); 

        // 计算静态路径
        // 根据 URL 规则生成
        // 这里需要复用 IndexController 或者 ParserModel 中的 URL 生成逻辑，但为了静态化，我们需要确定的路径
        // 假设使用 canonical URL 规则
        
        $url = $this->buildContentUrl($sort, $data);
        $path = $this->getStaticPath($url);
        $this->writeHtml($path, $content);
        
        return $path;
    }

    // --- 私有辅助方法 ---

    private function generateAbout($sort)
    {
        // 渲染单页
        // 参考 IndexController::getAboutPage
        $data = $this->model->getAbout($sort->scode);
        if (!$data) return false;
        
        $tpl = $sort->contenttpl ?: $sort->def_contenttpl;
        if (!$tpl) {
            $tpl = ($sort->filename ?: 'about') . '.html';
        }
        
        $content = $this->parseFrontendTemplate($this->htmldir . $tpl);
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

        // 单页 URL 通常是 /about/ 或者 /about.html
        $url = $this->buildSortUrl($sort);
        $path = $this->getStaticPath($url);
        $this->writeHtml($path, $content);
        
        return $path;
    }

    private function generateList($sort, $allPages)
    {
        // 渲染列表页
        // 参考 IndexController::getListPage
        
        // 分页处理逻辑比较复杂，需要知道总页数
        // 暂时只生成第一页
        
        $tpl = $sort->listtpl ?: $sort->def_listtpl;
        if (!$tpl && $sort->filename) {
            $tpl = $sort->filename . 'list.html';
        }
        
        $content = $this->parseFrontendTemplate($this->htmldir . $tpl);
        $content = $this->parser->parserBefore($content);
        $pagetitle = $sort->title ? "{sort:title}" : "{sort:name}";
        $content = str_replace('{pboot:pagetitle}', Config::get('list_title') ?: ($pagetitle . '-{pboot:sitetitle}-{pboot:sitesubtitle}'), $content);
        $content = str_replace('{pboot:pagekeywords}', '{sort:keywords}', $content);
        $content = str_replace('{pboot:pagedescription}', '{sort:description}', $content);
        $content = $this->parser->parserPositionLabel($content, $sort->scode); 
        $content = $this->parser->parserSortLabel($content, $sort); 
        $content = $this->parser->parserListLabel($content, $sort->scode); 
        $content = $this->parser->parserAfter($content); 
        
        $url = $this->buildSortUrl($sort);
        $path = $this->getStaticPath($url);
        $this->writeHtml($path, $content);
        
        return $path;
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

    private function getStaticPath($url)
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
        
        // 拼接完整物理路径
        // 格式：DOC_PATH + static_dir + default_lang + url
        return DOC_PATH . $this->staticDir . '/' . $this->defaultLang . $url;
    }

    private function parseFrontendTemplate($file)
    {
        $file = ltrim((string) $file, "/\\");
        
        // 兼容处理：如果传入的文件名以 .html 结尾且包含目录，确保不重复拼接
        // 但这里更根本的是确保 htmldir 不干扰
        
        $tpl_file = rtrim($this->tplThemePath, "/\\") . '/' . $file;
        
        // 尝试修复路径中可能出现的双斜杠问题
        $tpl_file = str_replace(array('//', '\\\\'), array('/', '\\'), $tpl_file);

        if (!is_file($tpl_file)) {
            // 尝试去掉 htmldir 再找一次（容错）
            if ($this->htmldir && strpos($file, $this->htmldir) === 0) {
                $file_retry = substr($file, strlen($this->htmldir));
                $tpl_file_retry = rtrim($this->tplThemePath, "/\\") . '/' . $file_retry;
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
            $content = TemplateParser::compile($this->tplThemePath, $tpl_file);
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
