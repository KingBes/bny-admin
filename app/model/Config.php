<?php

declare(strict_types=1);

namespace app\model;

use think\Model;

/**
 * @mixin \think\Model
 */
class Config extends Model
{
    /**
     * 读取配置
     * @param string $type 配置类型
     * @return array
     */
    public static function read(string $type = 'base'): array
    {
        $select = self::where('group', $type)->select();
        $data = [];
        foreach ($select as $item) {
            $data[$item['key']] = $item['value'];
        }
        return $data;
    }

    /**
     * 全量同步配置
     * @param array $data 配置数据
     * @param string $type 配置类型
     * @return mixed
     */
    public static function upToAdd(array $data, string $type = 'base'): mixed
    {
        $res = true;
        $existMap = array_flip(self::where('group', $type)->column('key'));
        foreach ($data as $key => $value) {
            $res = self::where("key", $key)->find();
            if ($res) {
                $res->value = (string) $value;
                $res->save();
            } else {
                $res = self::create([
                    'group' => $type,
                    'key' => $key,
                    'value' => (string) $value,
                ]);
            }
            // 已处理的键从删除队列中移除
            unset($existMap[$key]);
        }

        // 该组中本次未提交的键：删除，防止脏数据/废弃字段残留
        $toDelete = array_keys($existMap);
        if ($toDelete !== []) {
            self::where('group', $type)->where('key', 'in', $toDelete)->delete();
        }
        return $res;
    }
}
