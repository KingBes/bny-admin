<?php

declare(strict_types=1);

namespace app\model;

use think\Model;

/**
 * @mixin \think\Model
 */
class Attachment extends Model
{
    public static function onAfterRead(object $data)
    {
        $data->url = url("files.download", ["name" => $data->id . "." . $data->ext], false);
    }
}
