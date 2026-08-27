<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006~2018 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: liu21st <liu21st@gmail.com>
// +----------------------------------------------------------------------
use think\facade\Route;
use \app\model\Attachment;

// 文件下载
Route::get("/files/:id.:ext", function () {
    $id = request()->param("id");
    $ext = request()->param("ext");
    $file = Attachment::where(["id" => $id, "ext" => $ext])->find();
    if ($file) {
        return response(file_get_contents($file->path), 200, ["Content-Type" => $file->mime]);
    } else {
        return response("file not found", 404);
    }
})->name("files.download");
