<?php
/*
 * @file            apps/admin/controller/content/SpecPageController.php
 * @description     专题单页管理控制器
 * @author          ai-assistant
 * @createTime      2025-12-16 10:30:00
 * @lastModified    2025-12-16 10:30:00
*/

namespace app\admin\controller\content;

use core\basic\Controller;
use app\admin\model\content\SpecPageModel;
use app\admin\model\content\SpecPageStaticService;
use Exception;

class SpecPageController extends Controller
{
    /**
     * @var SpecPageModel
     */
    private $model;

    /**
     * @var SpecPageStaticService
     */
    private $staticService;

    /**
     * 构造函数，初始化模型
     */
    public function __construct()
    {
        $this->model = new SpecPageModel();
        $this->staticService = new SpecPageStaticService();
    }

    /**
     * 获取专题模板列表接口
     */
    public function getTemplateList()
    {
        // Define scanning root: DOC_PATH . '/spec'
        if (!defined('DOC_PATH')) {
             json(0, '系统路径未定义');
        }
        $specRoot = DOC_PATH . '/spec';
        $templates = [];

        if (is_dir($specRoot)) {
            $dirs = scandir($specRoot);
            foreach ($dirs as $dir) {
                if ($dir == '.' || $dir == '..') continue;
                $subDirPath = $specRoot . '/' . $dir;
                if (is_dir($subDirPath)) {
                    $templateDir = $subDirPath . '/template';
                    if (is_dir($templateDir)) {
                        $files = scandir($templateDir);
                        foreach ($files as $file) {
                            if ($file == '.' || $file == '..') continue;
                            if (pathinfo($file, PATHINFO_EXTENSION) == 'html') {
                                // Check if it's a file, not a dir
                                if (is_file($templateDir . '/' . $file)) {
                                    $templates[] = 'spec/' . $dir . '/template/' . $file;
                                }
                            }
                        }
                    }
                }
            }
        }
        json(1, $templates);
    }

    /**
     * 获取专题翻译语言列表（AJAX）
     */
    public function getLanguageList()
    {
        try {
            $languageList = $this->model->getLanguageList();
            json(1, $languageList);
        } catch (Exception $exception) {
            $this->log('获取专题翻译语言列表失败：' . $exception->getMessage());
            json(0, '获取专题翻译语言列表失败');
        }
    }

    /**
     * 新增专题翻译语言（AJAX）
     */
    public function addLanguage()
    {
        if (session('formcheck') != post('formcheck')) {
            json(0, '表单验证失败');
        }

        if (!$_POST) {
            json(0, '请求方式错误');
        }

        $name = post('name');
        $code = post('code', 'var');

        if (! $name) {
            json(0, '语言不能为空');
        }

        if (! $code) {
            json(0, '代码不能为空');
        }

        if (mb_strlen($name) > 50) {
            json(0, '语言长度不能超过50');
        }

        if (! preg_match('/^[a-zA-Z][a-zA-Z0-9\\-_\\.]{1,19}$/', $code)) {
            json(0, '代码格式不正确');
        }

        try {
            $result = $this->model->addLanguage($name, $code);
            if ($result) {
                json(1, array(
                    'name' => $name,
                    'code' => $code
                ));
            }
            json(0, '该代码已存在');
        } catch (Exception $exception) {
            $this->log('新增专题翻译语言失败：' . $exception->getMessage());
            json(0, '新增专题翻译语言失败');
        }
    }

    /**
     * 删除专题翻译语言（AJAX）
     */
    public function delLanguage()
    {
        if (session('formcheck') != post('formcheck')) {
            json(0, '表单验证失败');
        }

        if (!$_POST) {
            json(0, '请求方式错误');
        }

        $id = post('id', 'int');
        if (! $id) {
            json(0, '参数错误');
        }

        try {
            $result = $this->model->delLanguage($id);
            if ($result) {
                json(1, array(
                    'id' => $id
                ));
            }
            json(0, '删除失败');
        } catch (Exception $exception) {
            $this->log('删除专题翻译语言失败：' . $exception->getMessage());
            json(0, '删除专题翻译语言失败');
        }
    }

    /**
     * 专题单页列表
     */
    public function index()
    {
        try {
            $this->assign('list', true);

            $keyword = get('keyword', 'vars');
            if ($keyword) {
                $specPageList = $this->model->findSpecPageByName($keyword);
            } else {
                $specPageList = $this->model->getList();
            }

            if ($specPageList) {
                foreach ($specPageList as $key => $value) {
                    $stats = $this->model->getTranslationStats($value->id);
                    $specPageList[$key]->stats_total = $stats->total;
                    $specPageList[$key]->stats_success = $stats->success;
                    $specPageList[$key]->stats_fail = $stats->fail;
                    // 保留 stats 对象以备他用（如需更详细数据）
                    $specPageList[$key]->stats = $stats;
                }
            }

            $this->assign('spec_pages', $specPageList);
            $this->display('content/specpage.html');
        } catch (Exception $exception) {
            $this->log('加载专题单页列表失败：' . $exception->getMessage());
            error('加载专题单页列表失败，请稍后重试！');
        }
    }

    /**
     * 新增专题单页
     */
    public function add()
    {
        if (!$_POST) {
            $this->assign('add', true);
            $this->display('content/specpage.html');
            return;
        }

        try {
            $specName = post('name');
            $templatePath = post('template_path');
            $outputDir = post('output_dir');
            $baseLanguage = post('base_language');
            $status = post('status', 'int', 1);

            if (!$specName) {
                alert_back('专题名称不能为空！');
            }

            if (!$templatePath) {
                alert_back('模板路径不能为空！');
            }

            if (!$outputDir) {
                alert_back('输出目录不能为空！');
            }

            if (!$baseLanguage) {
                alert_back('模板语种不能为空！');
            }

            $specPageData = array(
                'name' => $specName,
                'template_path' => $templatePath,
                'output_dir' => $outputDir,
                'base_language' => $baseLanguage,
                'status' => $status,
                'create_user' => session('username'),
                'update_user' => session('username')
            );

            if ($this->model->addSpecPage($specPageData)) {
                $this->log('新增专题单页【' . $specName . '】成功！');
                success('新增成功！', url('/admin/SpecPage/index'));
            } else {
                $this->log('新增专题单页【' . $specName . '】失败！');
                error('新增失败，请稍后重试！', -1);
            }
        } catch (Exception $exception) {
            $this->log('新增专题单页失败：' . $exception->getMessage());
            error('新增专题单页失败，请稍后重试！', -1);
        }
    }

    /**
     * 修改专题单页
     */
    public function mod()
    {
        $specId = get('id', 'int');
        if (!$specId) {
            error('传递的参数值错误！', -1);
        }

        if (!$_POST) {
            try {
                $this->assign('mod', true);
                $specPage = $this->model->getSpecPage($specId);
                if (!$specPage) {
                    error('编辑的专题单页已经不存在！', -1);
                }
                $this->assign('spec', $specPage);
                $this->display('content/specpage.html');
                return;
            } catch (Exception $exception) {
                $this->log('加载专题单页编辑数据失败：' . $exception->getMessage());
                error('加载专题单页数据失败，请稍后重试！', -1);
            }
        }

        try {
            $specName = post('name');
            $templatePath = post('template_path');
            $outputDir = post('output_dir');
            $baseLanguage = post('base_language');
            $status = post('status', 'int', 1);

            if (!$specName) {
                alert_back('专题名称不能为空！');
            }

            if (!$templatePath) {
                alert_back('模板路径不能为空！');
            }

            if (!$outputDir) {
                alert_back('输出目录不能为空！');
            }

            if (!$baseLanguage) {
                alert_back('模板语种不能为空！');
            }

            $specPageData = array(
                'name' => $specName,
                'template_path' => $templatePath,
                'output_dir' => $outputDir,
                'base_language' => $baseLanguage,
                'status' => $status,
                'update_user' => session('username')
            );

            if ($this->model->modSpecPage($specId, $specPageData)) {
                $this->log('修改专题单页【' . $specName . '】成功！');
                success('修改成功！', url('/admin/SpecPage/index'));
            } else {
                $this->log('修改专题单页【' . $specName . '】失败！');
                error('修改失败，请稍后重试！', -1);
            }
        } catch (Exception $exception) {
            $this->log('修改专题单页失败：' . $exception->getMessage());
            error('修改专题单页失败，请稍后重试！', -1);
        }
    }

    /**
     * 删除专题单页
     */
    public function del()
    {
        $specId = get('id', 'int');
        if (!$specId) {
            error('传递的参数值错误！', -1);
        }

        try {
            if ($this->model->delSpecPage($specId)) {
                $this->log('删除专题单页ID【' . $specId . '】成功！');
                success('删除成功！', -1);
            } else {
                $this->log('删除专题单页ID【' . $specId . '】失败！');
                error('删除失败，请稍后重试！', -1);
            }
        } catch (Exception $exception) {
            $this->log('删除专题单页失败：' . $exception->getMessage());
            error('删除专题单页失败，请稍后重试！', -1);
        }
    }

    /**
     * 生成专题单页静态HTML文件
     */
    public function generate()
    {
        $specId = get('id', 'int');
        if (! $specId) {
            error('传递的参数值错误！', -1);
        }

        try {
            $result = $this->staticService->generateSpecPageHtml($specId);
            $outputFile = isset($result['output_file']) ? $result['output_file'] : '';

            $this->model->updateSpecPageGenerateTime($specId);
            $this->log('生成专题单页静态文件成功，ID【' . $specId . '】，文件：' . $outputFile);
            success('静态文件生成成功！', -1);
        } catch (Exception $exception) {
            $this->log('生成专题单页静态文件失败：' . $exception->getMessage());
            error('生成静态文件失败：' . $exception->getMessage(), -1);
        }
    }

    /**
     * 提交专题单页多语种翻译任务到AI队列
     */
    public function translate()
    {
        $specId = get('id', 'int');
        if (! $specId) {
            error('传递的参数值错误！', -1);
        }

        $specPage = $this->model->getSpecPage($specId);
        if (! $specPage) {
            error('要翻译的专题单页已经不存在！', -1);
        }

        if (!$_POST) {
             $this->assign('translate', true);
             $this->assign('spec', $specPage);
             $languagePairs = $this->model->getLanguagePairs();
             if (! $languagePairs) {
                 $languagePairs = array(
                     'ru' => '俄语',
                     'zh-TW' => '繁体中文',
                     'en' => '英语',
                     'es' => '西班牙语',
                     'fr' => '法语',
                     'ar' => '阿拉伯语',
                     'pt' => '葡萄牙语',
                     'ja' => '日语',
                     'ko' => '韩语',
                     'de' => '德语'
                 );
             }
             $this->assign('languages', $languagePairs);
             $this->display('content/specpage.html');
             return;
        }

        try {
            if (! defined('DOC_PATH')) {
                error('系统未正确初始化站点物理路径！', -1);
            }

            $relativeOutputDir = trim($specPage->output_dir, '/');
            $sourceFile = DOC_PATH . '/' . $relativeOutputDir . '/index.html';

            if (! is_file($sourceFile)) {
                $this->staticService->generateSpecPageHtml($specId);
            }

            $targetLanguages = post('target_languages');
            if (! $targetLanguages || ! is_array($targetLanguages)) {
                error('请选择至少一种目标语言！', -1);
            }

            require_once CORE_PATH . '/basic/RedisQueue.php';
            $queue = new \core\basic\RedisQueue('ai_queue');

            $submittedCount = 0;

            foreach ($targetLanguages as $targetLanguage) {
                $outputFilename = $targetLanguage . '.html';
                $outputFile = DOC_PATH . '/' . $relativeOutputDir . '/' . $outputFilename;

                $this->model->upsertTranslationRecord($specId, $targetLanguage, $outputFilename, '0', '');

                $taskData = array(
                    'type' => 'spec_translate',
                    'data' => array(
                        'spec_id' => $specId,
                        'source_file' => $sourceFile,
                        'output_file' => $outputFile,
                        'target_lang' => $targetLanguage,
                        'base_language' => $specPage->base_language
                    ),
                    'created_at' => time(),
                    'user_id' => session('uid'),
                    'user_ip' => get_user_ip()
                );

                $taskId = $queue->pushTask($taskData);
                if ($taskId) {
                    $submittedCount++;
                }
            }

            $this->log('为专题单页ID【' . $specId . '】提交翻译任务数量：' . $submittedCount);
            success('已提交 ' . $submittedCount . ' 条翻译任务到AI队列！', url('/admin/SpecPage/index'));
        } catch (Exception $exception) {
            $this->log('提交专题单页翻译任务失败：' . $exception->getMessage());
            error('提交翻译任务失败：' . $exception->getMessage(), -1);
        }
    }
}
