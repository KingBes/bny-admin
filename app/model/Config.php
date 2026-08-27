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
     * 更新或添加配置
     * @param array $data 配置数据
     * @param string $type 配置类型
     * @return mixed
     */
    public static function upToAdd(array $data, string $type = 'base'): mixed
    {
        $res = true;
        foreach ($data as $key => $value) {
            $res = self::where("key", $key)->find();
            if ($res) {
                $res->value = $value;
                $res->save();
            } else {
                $res = self::create([
                    'group' => $type,
                    'key' => $key,
                    'value' => $value,
                ]);
            }
        }
        return $res;
    }
}
