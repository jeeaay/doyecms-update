<?php
/**
 * IP 黑名单模型
 */
namespace app\admin\model\system;

use core\basic\Config;
use core\basic\Model;

/**
 * 负责黑名单、规则和存储表结构的后台数据操作。
 */
class IpBlacklistModel extends Model
{
    /**
     * 确保黑名单相关表存在。
     *
     * @return void
     */
    public function ensureStorage()
    {
        foreach ($this->getSchemaSqlList() as $sql) {
            if (trim($sql) !== '') {
                $this->amd($sql);
            }
        }
    }

    /**
     * 获取黑名单列表。
     *
     * @param string $keyword 搜索关键字
     * @return array
     */
    public function getBlacklistList($keyword = '')
    {
        $query = parent::table('ay_ip_blacklist')->order('id DESC');
        if ($keyword !== '') {
            $query->like('ip,reason,remark,last_uri', $keyword);
        }
        return $query->select();
    }

    /**
     * 获取单条黑名单记录。
     *
     * @param int $id 记录 ID
     * @return object|null
     */
    public function getBlacklist($id)
    {
        return parent::table('ay_ip_blacklist')->where("id=$id")->find();
    }

    /**
     * 新增黑名单记录。
     *
     * @param array $data 记录数据
     * @return bool|int
     */
    public function addBlacklist(array $data)
    {
        return parent::table('ay_ip_blacklist')->autoTime()->insert($data);
    }

    /**
     * 修改黑名单记录。
     *
     * @param int $id 记录 ID
     * @param array|string $data 更新数据
     * @return bool|int
     */
    public function modBlacklist($id, $data)
    {
        return parent::table('ay_ip_blacklist')->where("id=$id")->autoTime()->update($data);
    }

    /**
     * 删除黑名单记录。
     *
     * @param int $id 记录 ID
     * @return bool|int
     */
    public function delBlacklist($id)
    {
        return parent::table('ay_ip_blacklist')->where("id=$id")->delete();
    }

    /**
     * 获取规则列表。
     *
     * @param string $keyword 搜索关键字
     * @return array
     */
    public function getRuleList($keyword = '')
    {
        $query = parent::table('ay_ip_blacklist_rule')->order('id DESC');
        if ($keyword !== '') {
            $query->like('name,pattern,description', $keyword);
        }
        return $query->select();
    }

    /**
     * 获取单条规则记录。
     *
     * @param int $id 规则 ID
     * @return object|null
     */
    public function getRule($id)
    {
        return parent::table('ay_ip_blacklist_rule')->where("id=$id")->find();
    }

    /**
     * 新增规则。
     *
     * @param array $data 规则数据
     * @return bool|int
     */
    public function addRule(array $data)
    {
        return parent::table('ay_ip_blacklist_rule')->autoTime()->insert($data);
    }

    /**
     * 修改规则。
     *
     * @param int $id 规则 ID
     * @param array|string $data 更新数据
     * @return bool|int
     */
    public function modRule($id, $data)
    {
        return parent::table('ay_ip_blacklist_rule')->where("id=$id")->autoTime()->update($data);
    }

    /**
     * 删除规则。
     *
     * @param int $id 规则 ID
     * @return bool|int
     */
    public function delRule($id)
    {
        return parent::table('ay_ip_blacklist_rule')->where("id=$id")->delete();
    }

    /**
     * 根据当前数据库驱动返回建表 SQL。
     *
     * @return array
     */
    protected function getSchemaSqlList()
    {
        $dbType = Config::get('database.type');
        if ($dbType === 'sqlite' || $dbType === 'pdo_sqlite') {
            return $this->getSqliteSchemaSqlList();
        }
        return $this->getMysqlSchemaSqlList();
    }

    /**
     * 返回 SQLite 建表 SQL。
     *
     * @return array
     */
    protected function getSqliteSchemaSqlList()
    {
        return array(
            "CREATE TABLE IF NOT EXISTS `ay_ip_blacklist` (
                `id` INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
                `ip` TEXT(64) NOT NULL,
                `source_type` TEXT(30) NOT NULL,
                `reason` TEXT(255) NOT NULL,
                `status` TEXT(1) NOT NULL DEFAULT '1',
                `expire_time` TEXT(30) NOT NULL,
                `hit_count` INTEGER NOT NULL DEFAULT 0,
                `last_uri` TEXT(255) NOT NULL,
                `remark` TEXT(255) NOT NULL,
                `create_user` TEXT(30) NOT NULL,
                `update_user` TEXT(30) NOT NULL,
                `create_time` TEXT NOT NULL,
                `update_time` TEXT NOT NULL
            )",
            "CREATE INDEX IF NOT EXISTS `ay_ip_blacklist_ip` ON `ay_ip_blacklist` (`ip` ASC)",
            "CREATE INDEX IF NOT EXISTS `ay_ip_blacklist_status` ON `ay_ip_blacklist` (`status` ASC)",
            "CREATE TABLE IF NOT EXISTS `ay_ip_blacklist_rule` (
                `id` INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
                `name` TEXT(100) NOT NULL,
                `match_type` TEXT(20) NOT NULL,
                `pattern` TEXT(255) NOT NULL,
                `risk_level` TEXT(20) NOT NULL,
                `status` TEXT(1) NOT NULL DEFAULT '1',
                `description` TEXT(255) NOT NULL,
                `create_user` TEXT(30) NOT NULL,
                `update_user` TEXT(30) NOT NULL,
                `create_time` TEXT NOT NULL,
                `update_time` TEXT NOT NULL
            )",
            "CREATE INDEX IF NOT EXISTS `ay_ip_blacklist_rule_status` ON `ay_ip_blacklist_rule` (`status` ASC)"
        );
    }

    /**
     * 返回 MySQL 类驱动建表 SQL。
     *
     * @return array
     */
    protected function getMysqlSchemaSqlList()
    {
        return array(
            "CREATE TABLE IF NOT EXISTS `ay_ip_blacklist` (
                `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
                `ip` varchar(64) NOT NULL,
                `source_type` varchar(30) NOT NULL,
                `reason` varchar(255) NOT NULL,
                `status` char(1) NOT NULL DEFAULT '1',
                `expire_time` varchar(30) NOT NULL DEFAULT '',
                `hit_count` int(10) NOT NULL DEFAULT '0',
                `last_uri` varchar(255) NOT NULL DEFAULT '',
                `remark` varchar(255) NOT NULL DEFAULT '',
                `create_user` varchar(30) NOT NULL DEFAULT '',
                `update_user` varchar(30) NOT NULL DEFAULT '',
                `create_time` datetime NOT NULL,
                `update_time` datetime NOT NULL,
                PRIMARY KEY (`id`),
                KEY `ay_ip_blacklist_ip` (`ip`),
                KEY `ay_ip_blacklist_status` (`status`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8",
            "CREATE TABLE IF NOT EXISTS `ay_ip_blacklist_rule` (
                `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
                `name` varchar(100) NOT NULL,
                `match_type` varchar(20) NOT NULL,
                `pattern` varchar(255) NOT NULL,
                `risk_level` varchar(20) NOT NULL,
                `status` char(1) NOT NULL DEFAULT '1',
                `description` varchar(255) NOT NULL DEFAULT '',
                `create_user` varchar(30) NOT NULL DEFAULT '',
                `update_user` varchar(30) NOT NULL DEFAULT '',
                `create_time` datetime NOT NULL,
                `update_time` datetime NOT NULL,
                PRIMARY KEY (`id`),
                KEY `ay_ip_blacklist_rule_status` (`status`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8"
        );
    }
}
