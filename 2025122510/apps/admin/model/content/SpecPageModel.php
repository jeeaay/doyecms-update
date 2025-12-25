<?php
/**
 * 专题单页模型
 */
namespace app\admin\model\content;

use core\basic\Model;
use core\basic\Config;

class SpecPageModel extends Model
{
    /**
     * 获取专题单页列表
     */
    public function getList()
    {
        return parent::table('ay_spec_page')
            ->order('id DESC')
            ->select();
    }

    /**
     * 根据ID获取专题单页
     */
    public function getSpecPage($id)
    {
        return parent::table('ay_spec_page')
            ->where("id=$id")
            ->find();
    }

    /**
     * 新增专题单页
     */
    public function addSpecPage(array $specPageData)
    {
        return parent::table('ay_spec_page')
            ->autoTime()
            ->insert($specPageData);
    }

    /**
     * 修改专题单页
     */
    public function modSpecPage($id, array $specPageData)
    {
        return parent::table('ay_spec_page')
            ->autoTime()
            ->where("id=$id")
            ->update($specPageData);
    }

    /**
     * 删除专题单页
     */
    public function delSpecPage($id)
    {
        return parent::table('ay_spec_page')
            ->where("id=$id")
            ->delete();
    }

    /**
     * 通过名称模糊查询专题单页
     */
    public function findSpecPageByName($keyword)
    {
        return parent::table('ay_spec_page')
            ->like('name', $keyword)
            ->order('id DESC')
            ->select();
    }

    /**
     * 获取某个专题的翻译记录列表
     */
    public function getTranslationsBySpecId($specId)
    {
        return parent::table('ay_spec_translation')
            ->where("spec_id=$specId")
            ->order('id ASC')
            ->select();
    }

    /**
     * 根据专题与语种获取单条翻译记录
     */
    public function getTranslation($specId, $targetLanguage)
    {
        return parent::table('ay_spec_translation')
            ->where("spec_id=$specId")
            ->where("target_language='$targetLanguage'")
            ->find();
    }

    /**
     * 新增或更新专题翻译记录
     *
     * @param int $specId
     * @param string $targetLanguage
     * @param string $outputFilename
     * @param string $status
     * @param string $lastError
     * @return bool|int
     */
    public function upsertTranslationRecord($specId, $targetLanguage, $outputFilename, $status, $lastError)
    {
        $translation = $this->getTranslation($specId, $targetLanguage);

        $data = array(
            'spec_id' => $specId,
            'target_language' => $targetLanguage,
            'output_filename' => $outputFilename,
            'status' => $status,
            'last_error' => $lastError
        );

        if ($translation && isset($translation->id)) {
            return parent::table('ay_spec_translation')
                ->autoTime()
                ->where("id={$translation->id}")
                ->update($data);
        }

        return parent::table('ay_spec_translation')
            ->autoTime()
            ->insert($data);
    }

    /**
     * 更新专题单页生成时间
     *
     * @param int $specId
     * @return bool|int
     */
    public function updateSpecPageGenerateTime($specId)
    {
        $now = date('Y-m-d H:i:s');

        return parent::table('ay_spec_page')
            ->where("id=$specId")
            ->update(array(
                'update_time' => $now
            ));
    }

    /**
     * 获取专题翻译统计
     * @param int $specId
     * @return array
     */
    public function getTranslationStats($specId)
    {
        $stats = array(
            'total' => 0,
            'success' => 0,
            'fail' => 0,
            'languages' => array()
        );

        $list = parent::table('ay_spec_translation')
            ->where("spec_id=$specId")
            ->select();

        if ($list) {
            foreach ($list as $item) {
                $stats['total']++;
                if ($item->status == '1') {
                    $stats['success']++;
                    $stats['languages'][] = $item->target_language;
                } elseif ($item->status == '2') {
                    $stats['fail']++;
                }
            }
        }
        return (object) $stats;
    }

    /**
     * 获取专题翻译语言列表
     *
     * @return array|false
     */
    public function getLanguageList()
    {
        return parent::table('ay_spec_language')
            ->order('id ASC')
            ->select();
    }

    /**
     * 获取语言键值对（code => name），用于表单渲染
     *
     * @return array
     */
    public function getLanguagePairs()
    {
        $languageList = $this->getLanguageList();
        $pairs = array();

        if ($languageList) {
            foreach ($languageList as $language) {
                if (isset($language->code) && isset($language->name)) {
                    $pairs[$language->code] = $language->name;
                }
            }
        }

        return $pairs;
    }

    /**
     * 新增语言（按 code 唯一约束，重复时忽略）
     *
     * @param string $name 语言名称
     * @param string $code 语言代码
     * @return int|bool
     */
    public function addLanguage($name, $code)
    {
        $name = trim($name);
        $code = trim($code);

        if (! $name || ! $code) {
            return 0;
        }

        $escapedName = escape_string($name);
        $escapedCode = escape_string($code);

        $dbType = Config::get('database.type');

        if ($dbType === 'sqlite') {
            $sql = "INSERT OR IGNORE INTO `ay_spec_language` (`name`,`code`,`create_time`,`update_time`) VALUES ('$escapedName','$escapedCode',datetime('now','localtime'),datetime('now','localtime'))";
        } elseif ($dbType === 'mysqli') {
            $sql = "INSERT IGNORE INTO `ay_spec_language` (`name`,`code`,`create_time`,`update_time`) VALUES ('$escapedName','$escapedCode',NOW(),NOW())";
        } else {
            return parent::table('ay_spec_language')
                ->autoTime()
                ->insert(array(
                    'name' => $name,
                    'code' => $code
                ));
        }

        return $this->amd($sql);
    }

    /**
     * 删除语言
     *
     * @param int $id 语言ID
     * @return int|bool
     */
    public function delLanguage($id)
    {
        return parent::table('ay_spec_language')
            ->where("id=$id")
            ->delete();
    }
}
