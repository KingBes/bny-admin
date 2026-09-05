<?php

declare(strict_types=1);

namespace app\model;

use think\Model;

/**
 * @mixin \think\Model
 */
class Admin extends Model
{
    public static function onAfterRead(object $data)
    {
        if ($data->role == null) {
            $data->role = "超级管理员";
        }
    }

    public function role()
    {
        return $this->hasOne(Role::class, 'id', 'rid');
    }
}
