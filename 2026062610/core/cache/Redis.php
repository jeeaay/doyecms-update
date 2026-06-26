<?php
/**
 * @copyright (C)2016-2099 Hnaoyun Inc.
 * @author AI Assistant
 * @date 2026年6月26日
 * Redis缓存类
 */
namespace core\cache;

use core\basic\Config;

class Redis implements Builder
{

    protected static $redis;

    protected $conn;

    /**
     * 禁止直接实例化。
     */
    private function __construct()
    {}

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
        if (! self::$redis) {
            self::$redis = new self();
        }
        return self::$redis;
    }

    /**
     * 建立Redis连接并应用数据库、认证及前缀配置。
     *
     * @return \Redis
     */
    protected function conn()
    {
        if (! $this->conn) {
            if (! extension_loaded('redis')) {
                error('PHP运行环境未安装redis扩展！');
            }
            $config = Config::get('redis');
            $host = isset($config['host']) ? $config['host'] : '127.0.0.1';
            $port = isset($config['port']) ? (int) $config['port'] : 6379;
            $timeout = isset($config['timeout']) ? (float) $config['timeout'] : 3600;
            $password = isset($config['password']) ? $config['password'] : '';
            $select = isset($config['select']) ? (int) $config['select'] : 0;
            $prefix = isset($config['prefix']) ? $config['prefix'] : '';

            $this->conn = new \Redis();
            $this->conn->connect($host, $port, $timeout);
            if ($password !== '') {
                $this->conn->auth($password);
            }
            if ($select > 0) {
                $this->conn->select($select);
            }
            if ($prefix !== '') {
                $this->conn->setOption(\Redis::OPT_PREFIX, $prefix);
            }
        }
        return $this->conn;
    }

    /**
     * 写入缓存值。
     *
     * @param string $key 缓存键
     * @param mixed $value 缓存值
     * @return bool
     */
    public function set($key, $value)
    {
        $redis = $this->conn();
        return $redis->set($key, serialize($value));
    }

    /**
     * 读取缓存值。
     *
     * @param string $key 缓存键
     * @return mixed
     */
    public function get($key)
    {
        $redis = $this->conn();
        $value = $redis->get($key);
        if ($value === false) {
            return false;
        }
        return unserialize($value);
    }

    /**
     * 删除指定缓存。
     *
     * @param string $key 缓存键
     * @return int
     */
    public function delete($key)
    {
        $redis = $this->conn();
        return $redis->del($key);
    }

    /**
     * 清空当前库缓存。
     *
     * @return bool
     */
    public function flush()
    {
        $redis = $this->conn();
        return $redis->flushDB();
    }

    /**
     * 返回当前Redis服务状态信息。
     *
     * @return array
     */
    public function status()
    {
        $redis = $this->conn();
        return $redis->info();
    }

    /**
     * 关闭Redis连接。
     */
    public function __destruct()
    {
        if ($this->conn instanceof \Redis) {
            $this->conn->close();
        }
    }
}
