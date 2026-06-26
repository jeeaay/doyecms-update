<?php
/**
 * @copyright (C)2016-2099 Hnaoyun Inc.
 * @author AI Assistant
 * @date 2026年6月26日
 * 文件缓存类
 */
namespace core\cache;

use core\basic\Config;

class File implements Builder
{

    protected static $file;

    protected $cachePath;

    /**
     * 禁止直接实例化。
     */
    private function __construct()
    {
        $this->cachePath = $this->normalizeCachePath(Config::get('file.path') ?: RUN_PATH . '/cache/data');
        check_dir($this->cachePath, true);
    }

    /**
     * 禁止克隆实例。
     */
    private function __clone()
    {
        error('禁止克隆实例！');
    }

    /**
     * 获取单例实例。
     *
     * @return self
     */
    public static function getInstance()
    {
        if (! self::$file) {
            self::$file = new self();
        }
        return self::$file;
    }

    /**
     * 生成缓存文件路径。
     *
     * @param string $key 缓存键
     * @return string
     */
    protected function getCacheFile($key)
    {
        return $this->cachePath . '/' . md5($key) . '.php';
    }

    /**
     * 规范化缓存目录路径，支持站点相对路径配置。
     *
     * @param string $path 缓存目录
     * @return string
     */
    protected function normalizeCachePath($path)
    {
        if (preg_match('/^[A-Za-z]:[\\\\\\/]/', $path) || strpos($path, '/') === 0 || strpos($path, '\\') === 0) {
            return rtrim(str_replace('\\', '/', $path), '/');
        }
        return rtrim(ROOT_PATH . '/' . ltrim(str_replace('\\', '/', $path), '/'), '/');
    }

    /**
     * 写入缓存值。
     *
     * @param string $key 缓存键
     * @param mixed $value 缓存值
     * @return int|false
     */
    public function set($key, $value)
    {
        $cacheFile = $this->getCacheFile($key);
        $content = "<?php\nreturn unserialize(" . var_export(serialize($value), true) . ");";
        return file_put_contents($cacheFile, $content);
    }

    /**
     * 读取缓存值。
     *
     * @param string $key 缓存键
     * @return mixed
     */
    public function get($key)
    {
        $cacheFile = $this->getCacheFile($key);
        if (! file_exists($cacheFile)) {
            return false;
        }
        return require $cacheFile;
    }

    /**
     * 删除指定缓存。
     *
     * @param string $key 缓存键
     * @return bool
     */
    public function delete($key)
    {
        $cacheFile = $this->getCacheFile($key);
        if (file_exists($cacheFile)) {
            return unlink($cacheFile);
        }
        return true;
    }

    /**
     * 清空全部文件缓存。
     *
     * @return bool
     */
    public function flush()
    {
        if (! is_dir($this->cachePath)) {
            return true;
        }
        $files = glob($this->cachePath . '/*.php');
        if (! $files) {
            return true;
        }
        foreach ($files as $file) {
            if (is_file($file) && ! unlink($file)) {
                return false;
            }
        }
        return true;
    }
}
