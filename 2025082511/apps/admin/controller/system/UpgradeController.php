<?php
/**
 * @copyright (C)2016-2099 Hnaoyun Inc.
 * @author XingMeng
 * @email hnxsh@foxmail.com
 * @date 2018年8月14日
 * 基于GitHub/Gitee的补丁更新系统
 */
namespace app\admin\controller\system;

use core\basic\Controller;
use core\basic\Model;

class UpgradeController extends Controller
{
    // GitHub仓库地址
    private $github_repo = 'https://raw.githubusercontent.com/jeeaay/doyecms-update/refs/heads/master';
    // Gitee仓库地址
    private $gitee_repo = 'https://gitee.com/jeay/doyecms-update/raw/master';
    
    // 本地测试模式 - 设置为true时使用本地update_list.txt文件
    private $local_test_mode = false;
    
    // 当前使用的仓库
    private $current_repo;
    
    // 已安装补丁列表文件
    private $applied_file;
    
    // 补丁列表文件
    private $update_list_file;

    public function __construct()
    {
        error_reporting(0);
        $this->applied_file = ROOT_PATH . '/update_apply.txt';
        $this->update_list_file = ROOT_PATH . '/update_list.txt';
        
        // 优先使用Gitee，失败时使用GitHub
        $this->current_repo = $this->gitee_repo;
        
        // 确保已安装补丁列表文件存在
        if (!file_exists($this->applied_file)) {
            file_put_contents($this->applied_file, '');
        }
    }

    public function index()
    {
        $patches = $this->getPatchList();
        $this->assign('patches', $patches);
        $this->display('system/upgrade.html');
    }

    /**
     * 检查更新
     */
    public function check()
    {
        try {
            $update_list = null;
            
            // 本地测试模式
            if ($this->local_test_mode) {
                if (file_exists($this->update_list_file)) {
                    $update_list = file_get_contents($this->update_list_file);
                    $this->current_repo = 'local';
                } else {
                    json(0, '本地测试模式：找不到 update/update_list.txt 文件');
                }
            } else {
                // 尝试从GitHub获取更新列表
                $update_list = $this->getRemoteUpdateList($this->gitee_repo);
                if (!$update_list) {
                    // GitHub失败，尝试Gitee
                    $update_list = $this->getRemoteUpdateList($this->github_repo);
                    if ($update_list) {
                        $this->current_repo = $this->github_repo;
                    }
                }
            }
            
            if (!$update_list) {
                json(0, '无法连接到更新服务器，请检查网络连接或仓库配置。调试信息请访问：?p=/Upgrade/debug');
            }
            
            $patches = $this->comparePatchList($update_list);
            
            if (empty($patches)) {
                json(1, '您的系统已是最新版本！');
            } else {
                json(1, $patches);
            }
            
        } catch (Exception $e) {
            json(0, '检查更新失败：' . $e->getMessage());
        }
    }
    
    /**
     * 调试连接
     */
    public function debug()
    {
        $debug_info = [];
        
        // 测试GitHub连接
        $github_url = $this->github_repo . '/update_list.txt';
        $debug_info['github'] = [
            'url' => $github_url,
            'status' => 'testing...'
        ];
        
        // 测试GitHub连接
         $github_result = $this->testConnection($github_url);
         $debug_info['github']['status'] = $github_result['status'];
         if ($github_result['success']) {
             $debug_info['github']['content'] = substr($github_result['content'], 0, 200) . '...';
             $debug_info['github']['method'] = $github_result['method'];
         } else {
             $debug_info['github']['error'] = $github_result['error'];
         }
         
         // 测试Gitee连接
         $gitee_url = $this->gitee_repo . '/update_list.txt';
         $debug_info['gitee'] = [
             'url' => $gitee_url,
             'status' => 'testing...'
         ];
         
         $gitee_result = $this->testConnection($gitee_url);
         $debug_info['gitee']['status'] = $gitee_result['status'];
         if ($gitee_result['success']) {
             $debug_info['gitee']['content'] = substr($gitee_result['content'], 0, 200) . '...';
             $debug_info['gitee']['method'] = $gitee_result['method'];
         } else {
             $debug_info['gitee']['error'] = $gitee_result['error'];
         }
        
        // 输出调试信息
        echo '<h2>DoyeCMS 更新系统调试信息</h2>';
        echo '<h3>仓库连接测试</h3>';
        echo '<pre>' . print_r($debug_info, true) . '</pre>';
        
        echo '<h3>配置说明</h3>';
        echo '<p>1. 请在 UpgradeController.php 中配置正确的仓库地址</p>';
        echo '<p>2. 确保仓库中存在 /update_list.txt 文件</p>';
        echo '<p>3. 确保服务器可以访问外网</p>';
        echo '<p>4. 如果使用HTTPS，请确保SSL证书配置正确</p>';
        
        echo '<h3>PHP配置检查</h3>';
        echo '<p>allow_url_fopen: ' . (ini_get('allow_url_fopen') ? '已启用' : '已禁用') . '</p>';
        echo '<p>openssl: ' . (extension_loaded('openssl') ? '已安装' : '未安装') . '</p>';
        echo '<p>curl: ' . (extension_loaded('curl') ? '已安装' : '未安装') . '</p>';
    }

    /**
     * 安装补丁（新版本：支持逐个文件下载）
     */
    public function install()
    {
        if (!$_POST) {
            json(0, '请求方式错误');
        }
        
        $version = post('version');
        if (!$version) {
            json(0, '请指定要安装的补丁版本');
        }
        
        // 检查版本格式
        if (!preg_match('/^\d{10}$/', $version)) {
            json(0, '补丁版本格式错误');
        }
        
        // 检查是否可以安装（版本顺序检查）
        if (!$this->canInstallPatch($version)) {
            json(0, '不能跳版本安装，请按顺序安装补丁');
        }
        
        // 检查补丁是否已安装
        if ($this->isPatchApplied($version)) {
            json(0, '该补丁已经安装');
        }
        
        try {
            // 记录已安装补丁
            $this->recordAppliedPatch($version);
            
            // 清理缓存
            $this->clearCache();
            
            json(1, '补丁安装成功！');
            
        } catch (Exception $e) {
            json(0, '安装失败：' . $e->getMessage());
        }
    }
    
    /**
     * 完成补丁安装（在所有文件下载完成后调用）
     */
    public function finishInstall()
    {
        if (!$_POST) {
            json(0, '请求方式错误');
        }
        
        $version = post('version');
        if (!$version) {
            json(0, '请指定版本号');
        }
        
        // 检查版本格式
        if (!preg_match('/^\d{10}$/', $version)) {
            json(0, '版本号格式错误');
        }
        
        try {
            // 记录已安装补丁
            $this->recordAppliedPatch($version);
            
            // 清理缓存
            $this->clearCache();
            
            json(1, '补丁安装完成！');
            
        } catch (Exception $e) {
            json(0, '完成安装失败：' . $e->getMessage());
        }
    }

    /**
     * 获取补丁列表
     */
    private function getPatchList()
    {
        // 尝试从远程获取更新列表
        $update_list = null;
        
        // 本地测试模式
        if ($this->local_test_mode) {
            if (file_exists($this->update_list_file)) {
                $update_list = file_get_contents($this->update_list_file);
                $remote_list = array_filter(array_map('trim', explode("\n", $update_list)));
            } else {
                $remote_list = array();
            }
        } else {
            // 尝试从GitHub获取更新列表
            $remote_list = $this->getRemoteUpdateList($this->github_repo);
            if (!$remote_list) {
                // GitHub失败，尝试Gitee
                $remote_list = $this->getRemoteUpdateList($this->gitee_repo);
                if ($remote_list) {
                    $this->current_repo = $this->gitee_repo;
                }
            }
            
            // 如果远程获取失败，返回空列表
            if (!$remote_list) {
                $remote_list = array();
            }
        }
        
        // 使用comparePatchList方法处理补丁列表
        return $this->comparePatchList($remote_list);
    }

    /**
     * 从远程获取更新列表
     */
    private function getRemoteUpdateList($repo_url)
    {
        $url = $repo_url . '/update_list.txt';
        
        // 优先尝试使用cURL（对HTTPS支持更好）
        if (function_exists('curl_init')) {
            return $this->fetchWithCurl($url);
        }
        
        // 备用方案：使用file_get_contents
        return $this->fetchWithFileGetContents($url);
    }
    
    /**
     * 使用cURL获取内容
     */
    private function fetchWithCurl($url)
    {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_USERAGENT => 'DoyeCMS-Updater/1.0',
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 3,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_SSLVERSION => CURL_SSLVERSION_TLSv1_2,
            CURLOPT_HTTPHEADER => [
                'Accept: text/plain',
                'Cache-Control: no-cache',
                'User-Agent: DoyeCMS-Updater/1.0'
            ]
        ]);
        
        $content = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($content === false || $httpCode !== 200) {
            error_log('cURL failed to fetch: ' . $url . ' HTTP Code: ' . $httpCode . ' Error: ' . $error);
            return false;
        }
        
        return array_filter(array_map('trim', explode("\n", $content)));
    }
    
    /**
     * 使用file_get_contents获取内容
     */
    private function fetchWithFileGetContents($url)
    {
        // 创建增强的HTTP上下文
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => 30,
                'user_agent' => 'DoyeCMS-Updater/1.0',
                'header' => [
                    'Accept: text/plain',
                    'Cache-Control: no-cache',
                    'Connection: close'
                ],
                'ignore_errors' => true
            ],
            'https' => [
                'method' => 'GET',
                'timeout' => 30,
                'user_agent' => 'DoyeCMS-Updater/1.0',
                'header' => [
                    'Accept: text/plain',
                    'Cache-Control: no-cache',
                    'Connection: close'
                ],
                'ignore_errors' => true
            ],
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true,
                'SNI_enabled' => true,
                'ciphers' => 'HIGH:!SSLv2:!SSLv3'
            ]
        ]);
        
        $content = @file_get_contents($url, false, $context);
        
        if ($content === false) {
            // 记录详细错误信息
            $error = error_get_last();
            error_log('file_get_contents failed to fetch: ' . $url . ' Error: ' . ($error['message'] ?? 'Unknown error'));
            return false;
        }
        
        return array_filter(array_map('trim', explode("\n", $content)));
    }
    
    /**
     * 测试连接（用于调试）
     */
    private function testConnection($url)
    {
        $result = [
            'success' => false,
            'status' => '连接失败',
            'content' => '',
            'error' => '',
            'method' => ''
        ];
        
        // 优先尝试cURL
        if (function_exists('curl_init')) {
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 10,
                CURLOPT_CONNECTTIMEOUT => 5,
                CURLOPT_USERAGENT => 'DoyeCMS-Updater/1.0',
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_MAXREDIRS => 3,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => false,
                CURLOPT_SSLVERSION => CURL_SSLVERSION_TLSv1_2
            ]);
            
            $content = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);
            
            if ($content !== false && $httpCode === 200) {
                $result['success'] = true;
                $result['status'] = '连接成功 (cURL)';
                $result['content'] = $content;
                $result['method'] = 'cURL';
                return $result;
            } else {
                $result['error'] = 'cURL Error: ' . $error . ' (HTTP Code: ' . $httpCode . ')';
            }
        }
        
        // 备用方案：file_get_contents
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => 10,
                'user_agent' => 'DoyeCMS-Updater/1.0',
                'ignore_errors' => true
            ],
            'https' => [
                'method' => 'GET',
                'timeout' => 10,
                'user_agent' => 'DoyeCMS-Updater/1.0',
                'ignore_errors' => true
            ],
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            ]
        ]);
        
        $content = @file_get_contents($url, false, $context);
        if ($content !== false) {
            $result['success'] = true;
            $result['status'] = '连接成功 (file_get_contents)';
            $result['content'] = $content;
            $result['method'] = 'file_get_contents';
        } else {
            $error = error_get_last();
            $result['error'] = 'file_get_contents Error: ' . ($error['message'] ?? '未知错误');
        }
        
        return $result;
    }

    /**
     * 比较补丁列表，返回可用更新和已安装补丁
     */
    private function comparePatchList($remote_list)
    {
        $applied_list = $this->getAppliedPatches();
        $all_patches = array();
        
        foreach ($remote_list as $version) {
            $version = trim($version);
            if ($version) {
                $is_applied = in_array($version, $applied_list);
                $all_patches[] = array(
                    'version' => $version,
                    'applied' => $is_applied,
                    'update_time' => $this->formatVersionTime($version),
                    'can_install' => $is_applied ? false : $this->canInstallPatch($version),
                    'status' => $is_applied ? 'installed' : 'new'
                );
            }
        }
        
        // 按版本号排序，最新的在前
        usort($all_patches, function($a, $b) {
            return strcmp($b['version'], $a['version']);
        });
        
        return $all_patches;
    }

    /**
     * 获取已安装补丁列表
     */
    private function getAppliedPatches()
    {
        if (!file_exists($this->applied_file)) {
            return array();
        }
        
        $content = file_get_contents($this->applied_file);
        return array_filter(array_map('trim', explode("\n", $content)));
    }

    /**
     * 检查补丁是否已安装
     */
    private function isPatchApplied($version)
    {
        $applied_list = $this->getAppliedPatches();
        return in_array($version, $applied_list);
    }

    /**
     * 检查是否可以安装补丁（版本顺序检查）
     */
    private function canInstallPatch($version)
    {
        $applied_list = $this->getAppliedPatches();
        
        if (empty($applied_list)) {
            return true; // 没有已安装补丁，可以安装任何版本
        }
        
        // 获取最新已安装版本
        sort($applied_list);
        $latest_applied = end($applied_list);
        
        // 检查是否是下一个版本或已安装版本
        return $version > $latest_applied || in_array($version, $applied_list);
    }

    /**
     * 下载并安装补丁
     */
    private function downloadAndInstallPatch($version)
    {
        // 创建临时目录
        $temp_dir = RUN_PATH . '/temp_patch_' . $version;
        if (!check_dir($temp_dir, true)) {
            throw new Exception('无法创建临时目录');
        }
        
        try {
            // 下载补丁文件
            $patch_dir = $this->current_repo . '/' . $version;
            $this->downloadPatchFiles($patch_dir, $temp_dir, $version);
            
            // 备份现有文件
            $backup_dir = RUN_PATH . '/backup/patch_' . date('YmdHis');
            
            // 应用补丁
            $this->applyPatch($temp_dir, $backup_dir);
            
            // 清理临时文件
            path_delete($temp_dir, true);
            
            return true;
            
        } catch (Exception $e) {
            // 清理临时文件
            if (is_dir($temp_dir)) {
                path_delete($temp_dir, true);
            }
            throw $e;
        }
    }

    /**
     * 下载补丁文件
     */
    private function downloadPatchFiles($patch_url, $temp_dir, $version)
    {
        // 获取补丁文件列表
        $files = $this->getPatchFileList($version);
        
        foreach ($files as $file) {
            $remote_file = $patch_url . '/' . $file;
            $local_file = $temp_dir . '/' . $file;
            
            // 创建目录
            $dir = dirname($local_file);
            if (!check_dir($dir, true)) {
                throw new Exception('无法创建目录：' . $dir);
            }
            
            // 下载文件
            $content = @file_get_contents($remote_file);
            if ($content === false) {
                throw new Exception('下载文件失败：' . $file);
            }
            
            if (!file_put_contents($local_file, $content)) {
                throw new Exception('保存文件失败：' . $file);
            }
        }
    }

    /**
     * 获取补丁文件列表
     */
    private function getPatchFileList($version)
    {
        // 优先从file_list.txt读取文件列表
        $file_list_path = ROOT_PATH . '/update/' . $version . '/file_list.txt';
        if (file_exists($file_list_path)) {
            $content = file_get_contents($file_list_path);
            $files = array_filter(array_map('trim', explode("\n", $content)));
            return $files;
        }
        
        // 备用方案：从本地update目录扫描获取文件列表
        $patch_dir = ROOT_PATH . '/update/' . $version;
        if (!is_dir($patch_dir)) {
            return array();
        }
        
        $files = array();
        $this->scanPatchFiles($patch_dir, $patch_dir, $files);
        return $files;
    }
    
    /**
     * 获取远程补丁文件列表
     */
    public function getFileList()
    {
        if (!$_POST) {
            json(0, '请求方式错误');
        }
        
        $version = post('version');
        if (!$version) {
            json(0, '请指定版本号');
        }
        
        // 检查版本格式
        if (!preg_match('/^\d{10}$/', $version)) {
            json(0, '版本号格式错误');
        }
        
        try {
            // 尝试从远程获取file_list.txt
            $file_list_url = $this->current_repo . '/' . $version . '/file_list.txt';
            $file_list_content = null;
            
            // 使用cURL获取文件列表
            if (function_exists('curl_init')) {
                $ch = curl_init();
                curl_setopt_array($ch, [
                    CURLOPT_URL => $file_list_url,
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_TIMEOUT => 30,
                    CURLOPT_CONNECTTIMEOUT => 10,
                    CURLOPT_USERAGENT => 'DoyeCMS-Updater/1.0',
                    CURLOPT_FOLLOWLOCATION => true,
                    CURLOPT_MAXREDIRS => 3,
                    CURLOPT_SSL_VERIFYPEER => false,
                    CURLOPT_SSL_VERIFYHOST => false,
                    CURLOPT_SSLVERSION => CURL_SSLVERSION_TLSv1_2
                ]);
                
                $file_list_content = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);
                
                if ($file_list_content === false || $httpCode !== 200) {
                    $file_list_content = null;
                }
            }
            
            // 备用方案：使用file_get_contents
            if (!$file_list_content) {
                $file_list_content = @file_get_contents($file_list_url);
            }
            
            if (!$file_list_content) {
                json(0, '无法获取文件列表，请检查网络连接');
            }
            
            $files = array_filter(array_map('trim', explode("\n", $file_list_content)));
            
            if (empty($files)) {
                json(0, '文件列表为空');
            }
            
            json(1, $files);
            
        } catch (Exception $e) {
            json(0, '获取文件列表失败：' . $e->getMessage());
        }
    }
    
    /**
     * 下载单个文件
     */
    public function downloadFile()
    {
        if (!$_POST) {
            json(0, '请求方式错误');
        }
        
        $version = post('version');
        $file_path = post('file');
        
        if (!$version || !$file_path) {
            json(0, '参数不完整');
        }
        
        // 检查版本格式
        if (!preg_match('/^\d{10}$/', $version)) {
            json(0, '版本号格式错误');
        }
        
        // 安全检查：防止路径遍历攻击
        if (strpos($file_path, '..') !== false || strpos($file_path, '\\') !== false) {
            json(0, '文件路径不合法');
        }
        
        try {
            // 构建远程文件URL
            $remote_file_url = $this->current_repo . '/' . $version . '/' . $file_path;
            
            // 下载文件内容
            $file_content = null;
            
            // 使用cURL下载文件
            if (function_exists('curl_init')) {
                $ch = curl_init();
                curl_setopt_array($ch, [
                    CURLOPT_URL => $remote_file_url,
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_TIMEOUT => 60,
                    CURLOPT_CONNECTTIMEOUT => 10,
                    CURLOPT_USERAGENT => 'DoyeCMS-Updater/1.0',
                    CURLOPT_FOLLOWLOCATION => true,
                    CURLOPT_MAXREDIRS => 3,
                    CURLOPT_SSL_VERIFYPEER => false,
                    CURLOPT_SSL_VERIFYHOST => false,
                    CURLOPT_SSLVERSION => CURL_SSLVERSION_TLSv1_2
                ]);
                
                $file_content = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);
                
                if ($file_content === false || $httpCode !== 200) {
                    $file_content = null;
                }
            }
            
            // 备用方案：使用file_get_contents
            if ($file_content === null) {
                $file_content = @file_get_contents($remote_file_url);
            }
            
            if ($file_content === false || $file_content === null) {
                json(0, '下载文件失败：' . $file_path);
            }
            
            // 确定目标文件路径
            $target_file = ROOT_PATH . '/' . $file_path;
            $target_dir = dirname($target_file);
            
            // 创建目标目录
            if (!check_dir($target_dir, true)) {
                json(0, '无法创建目录：' . $target_dir);
            }
            
            // 备份现有文件
            if (file_exists($target_file)) {
                $backup_dir = RUN_PATH . '/backup/patch_' . date('YmdHis');
                $backup_file = $backup_dir . '/' . $file_path;
                $backup_file_dir = dirname($backup_file);
                
                if (!check_dir($backup_file_dir, true)) {
                    json(0, '无法创建备份目录：' . $backup_file_dir);
                }
                
                if (!copy($target_file, $backup_file)) {
                    json(0, '备份文件失败：' . $file_path);
                }
            }
            
            // 保存文件
            if (!file_put_contents($target_file, $file_content)) {
                json(0, '保存文件失败：' . $file_path);
            }
            
            json(1, '文件下载成功：' . $file_path);
            
        } catch (Exception $e) {
            json(0, '下载文件失败：' . $e->getMessage());
        }
    }

    /**
     * 扫描补丁文件
     */
    private function scanPatchFiles($dir, $base_dir, &$files)
    {
        $items = scandir($dir);
        foreach ($items as $item) {
            if ($item == '.' || $item == '..') {
                continue;
            }
            
            $path = $dir . '/' . $item;
            if (is_dir($path)) {
                $this->scanPatchFiles($path, $base_dir, $files);
            } else {
                $relative_path = str_replace($base_dir . '/', '', $path);
                $relative_path = str_replace('\\', '/', $relative_path);
                $files[] = $relative_path;
            }
        }
    }

    /**
     * 应用补丁
     */
    private function applyPatch($temp_dir, $backup_dir)
    {
        $files = array();
        $this->scanPatchFiles($temp_dir, $temp_dir, $files);
        
        foreach ($files as $file) {
            $source_file = $temp_dir . '/' . $file;
            $target_file = ROOT_PATH . '/' . $file;
            $backup_file = $backup_dir . '/' . $file;
            
            // 创建目标目录
            $target_dir = dirname($target_file);
            if (!check_dir($target_dir, true)) {
                throw new Exception('无法创建目标目录：' . $target_dir);
            }
            
            // 备份现有文件
            if (file_exists($target_file)) {
                $backup_file_dir = dirname($backup_file);
                if (!check_dir($backup_file_dir, true)) {
                    throw new Exception('无法创建备份目录：' . $backup_file_dir);
                }
                if (!copy($target_file, $backup_file)) {
                    throw new Exception('备份文件失败：' . $file);
                }
            }
            
            // 复制新文件
            if (!copy($source_file, $target_file)) {
                throw new Exception('复制文件失败：' . $file);
            }
        }
    }

    /**
     * 记录已安装补丁
     */
    private function recordAppliedPatch($version)
    {
        $applied_list = $this->getAppliedPatches();
        if (!in_array($version, $applied_list)) {
            $applied_list[] = $version;
            sort($applied_list);
            file_put_contents($this->applied_file, implode("\n", $applied_list) . "\n");
        }
    }

    /**
     * 格式化版本时间
     */
    private function formatVersionTime($version)
    {
        if (preg_match('/^(\d{4})(\d{2})(\d{2})(\d{2})$/', $version, $matches)) {
            return $matches[1] . '-' . $matches[2] . '-' . $matches[3] . ' ' . $matches[4] . ':00';
        }
        return $version;
    }

    /**
     * 清理缓存
     */
    private function clearCache()
    {
        path_delete(RUN_PATH . '/cache');
        path_delete(RUN_PATH . '/complite');
        path_delete(RUN_PATH . '/config');
    }
}