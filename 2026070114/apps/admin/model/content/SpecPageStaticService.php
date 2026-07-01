<?php
/**
 * 专题单页静态生成服务
 */

namespace app\admin\model\content;

use Exception;

class SpecPageStaticService
{
    /**
     * @var SpecPageModel
     */
    private $specPageModel;

    /**
     * 构造函数，初始化模型
     */
    public function __construct()
    {
        $this->specPageModel = new SpecPageModel();
    }

    /**
     * 根据专题ID生成静态HTML文件
     *
     * @param int $specId
     * @return array
     * @throws Exception
     */
    public function generateSpecPageHtml($specId)
    {
        $specPage = $this->getSpecPageById($specId);

        $templateFullPath = $this->buildTemplateFullPath($specPage->template_path);
        $templateContent = $this->getRawTemplateContent($templateFullPath);

        $specRootPath = $this->getSpecRootPath();
        $parsedContent = $this->parseTemplateContent($templateContent, $specRootPath, $templateFullPath);

        $outputFilePath = $this->buildOutputFilePath($specPage->output_dir);
        $this->writeOutputFile($outputFilePath, $parsedContent);

        return array(
            'success' => true,
            'output_file' => $outputFilePath
        );
    }

    /**
     * 获取专题数据
     *
     * @param int $specId
     * @return object
     * @throws Exception
     */
    private function getSpecPageById($specId)
    {
        $specPage = $this->specPageModel->getSpecPage($specId);
        if (! $specPage) {
            throw new Exception('专题单页不存在！');
        }
        return $specPage;
    }

    /**
     * 构建模板文件的物理路径
     *
     * @param string $templatePath
     * @return string
     * @throws Exception
     */
    private function buildTemplateFullPath($templatePath)
    {
        if (! defined('DOC_PATH')) {
            throw new Exception('系统未正确初始化站点物理路径常量 DOC_PATH！');
        }

        $relativePath = ltrim($templatePath, '/');
        // 迁移后使用 /www 作为基础路径
        $basePath = defined('WWW_MIGRATED') ? ROOT_PATH . '/www' : DOC_PATH;
        $fullPath = $basePath . '/' . $relativePath;

        if (! is_file($fullPath)) {
            throw new Exception('模板文件不存在：' . $fullPath);
        }

        return $fullPath;
    }

    /**
     * 读取原始模板内容
     *
     * @param string $templateFullPath
     * @return string
     * @throws Exception
     */
    private function getRawTemplateContent($templateFullPath)
    {
        $content = @file_get_contents($templateFullPath);
        if ($content === false) {
            throw new Exception('读取模板文件失败：' . $templateFullPath);
        }

        return $content;
    }

    /**
     * 获取专题根目录物理路径
     *
     * @return string
     * @throws Exception
     */
    private function getSpecRootPath()
    {
        if (! defined('DOC_PATH')) {
            throw new Exception('系统未正确初始化站点物理路径常量 DOC_PATH！');
        }

        return rtrim(DOC_PATH, '/') . '/spec';
    }

    /**
     * 解析模板中的 include 指令
     *
     * @param string $templateContent
     * @param string $specRootPath
     * @param string $currentTemplatePath
     * @param int $depth
     * @return string
     * @throws Exception
     */
    private function parseTemplateContent($templateContent, $specRootPath, $currentTemplatePath, $depth = 0)
    {
        $maxDepth = 5;
        if ($depth > $maxDepth) {
            throw new Exception('模板 include 嵌套层级过深，可能存在循环引用！');
        }

        $pattern = '/\{include\s+file\s*=\s*([^\s\}]+)\}/i';

        if (! preg_match_all($pattern, $templateContent, $matches, PREG_SET_ORDER)) {
            return $templateContent;
        }

        $parsedContent = $templateContent;

        foreach ($matches as $match) {
            $includeRaw = $match[0];
            $includePath = $this->normalizeIncludePath($match[1]);
            $includeFilePath = $this->buildIncludeFilePath($includePath, $specRootPath, $currentTemplatePath);

            if (! is_file($includeFilePath)) {
                throw new Exception('被包含文件不存在：' . $includeFilePath);
            }

            $includeContent = @file_get_contents($includeFilePath);
            if ($includeContent === false) {
                throw new Exception('读取被包含文件失败：' . $includeFilePath);
            }

            $includeContent = $this->parseTemplateContent($includeContent, $specRootPath, $includeFilePath, $depth + 1);

            $parsedContent = str_replace($includeRaw, $includeContent, $parsedContent);
        }

        return $parsedContent;
    }

    /**
     * 规范化 include 路径字符串
     *
     * @param string $path
     * @return string
     */
    private function normalizeIncludePath($path)
    {
        $trimmedPath = trim($path, " \t\n\r\0\x0B'\"");
        return $trimmedPath;
    }

    /**
     * 构建 include 文件物理路径
     *
     * @param string $includePath
     * @param string $specRootPath
     * @param string $currentTemplatePath
     * @return string
     */
    private function buildIncludeFilePath($includePath, $specRootPath, $currentTemplatePath)
    {
        $cleanPath = ltrim($includePath, '/');

        if (strpos($cleanPath, 'spec/') === 0) {
            // 迁移后使用 /www 作为基础路径
            $basePath = defined('WWW_MIGRATED') ? ROOT_PATH . '/www' : DOC_PATH;
            return $basePath . '/' . $cleanPath;
        }

        $relativePath = str_replace(array('\\', '//'), array('/', '/'), dirname($currentTemplatePath) . '/' . $cleanPath);
        if (is_file($relativePath)) {
            return $relativePath;
        }

        return str_replace(array('\\', '//'), array('/', '/'), rtrim($specRootPath, '/\\') . '/' . $cleanPath);
    }

    /**
     * 构建输出文件的物理路径
     *
     * @param string $outputDir
     * @return string
     * @throws Exception
     */
    private function buildOutputFilePath($outputDir)
    {
        if (! defined('DOC_PATH')) {
            throw new Exception('系统未正确初始化站点物理路径常量 DOC_PATH！');
        }

        $relativeDir = trim($outputDir, '/');
        if ($relativeDir === '') {
            throw new Exception('专题输出目录配置不能为空！');
        }

        // 迁移后使用 /www 作为基础路径
        $basePath = defined('WWW_MIGRATED') ? ROOT_PATH . '/www' : DOC_PATH;
        $fullDir = $basePath . '/' . $relativeDir;
        $outputFilePath = rtrim($fullDir, '/') . '/index.html';

        return $outputFilePath;
    }

    /**
     * 将最终HTML内容写入输出文件
     *
     * @param string $outputFilePath
     * @param string $htmlContent
     * @throws Exception
     */
    private function writeOutputFile($outputFilePath, $htmlContent)
    {
        if (! function_exists('check_dir')) {
            throw new Exception('系统未加载目录检查函数，请确认核心函数已正确引入！');
        }

        if (! check_dir(dirname($outputFilePath), true)) {
            throw new Exception('创建输出目录失败：' . dirname($outputFilePath));
        }

        $result = @file_put_contents($outputFilePath, $htmlContent);
        if ($result === false) {
            throw new Exception('写入静态文件失败：' . $outputFilePath);
        }
    }
}

