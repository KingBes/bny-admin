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
Route::get("/files/:name", function () {
    $name = request()->param("name");
    [$id, $ext] = explode(".", $name);
    $file = Attachment::where(["id" => $id, "ext" => $ext])->find();
    if ($file) {
        $path = app()->getRootPath() . "files" . DIRECTORY_SEPARATOR . $file->path;
        if (!file_exists($path)) {
            return response("文件不存在: $path", 404);
        }
        return response(file_get_contents($path), 200, ["Content-Type" => $file->mime]);
    } else {
        return response("没有该文件数据", 404);
    }
})->name("files.download");
