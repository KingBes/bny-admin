<?php

namespace app\admin\controller;

use app\BaseController;
use Kingbes\Annotation\Annotation;
use think\Response;
use app\model\Attachment as AttachmentModel;
use think\exception\FileException;

#[Annotation(["title" => "附件管理", "icon" => "icon-attachment"])]
class Attachment extends BaseController
{
    #[Annotation(["title" => "选择附件", "page" => false, "auth" => false])]
    public function select(): string
    {
        $param = request()->param();
        $this->assign([
            // target：回填目标字段 id（必填）
            "target" => $param["target"],
            // max：最大选择数，默认 1
            "max"    => (int) ($param["max"] ?? 1),
            // type：允许的文件类型（逗号分隔扩展名），* 表示全部
            "type"   => (string) ($param["type"] ?? "*"),
        ]);
        return $this->fetch();
    }

    #[Annotation(["title" => "上传附件", "page" => false, "auth" => false])]
    public function upload(): Response
    {
        // 上传文件
        $file = request()->file("file");
        // 文件有效性（区分大小超限 / 未传文件，给出明确提示）
        if (!$file || !$file->isValid()) {
            $err = ($_FILES["file"]["error"] ?? null);
            if ($err === UPLOAD_ERR_INI_SIZE || $err === UPLOAD_ERR_FORM_SIZE) {
                return $this->error("文件大小超出服务器限制");
            }
            if ($err === UPLOAD_ERR_NO_FILE) {
                return $this->error("未接收到文件");
            }
            return $this->error("文件无效");
        }
        // 原始文件名与扩展名
        $filename = $file->getOriginalName();
        $ext      = strtolower($file->getOriginalExtension());
        // 路径文件夹
        $folder = app()->getRootPath() . "files" . DIRECTORY_SEPARATOR . date("Ymd");
        // 文件夹不存在则创建
        if (!file_exists($folder)) {
            mkdir($folder, 0755, true);
        }
        // 保存文件
        $name = uniqid("bny_", true) . "." . $ext;
        try {
            $file->move($folder, $name);
        } catch (FileException $e) {
            return $this->error($e->getMessage());
        }
        // 文件路径
        $path = $folder . DIRECTORY_SEPARATOR . $name;
        // 保存文件信息
        $res = AttachmentModel::create([
            "name" => $filename,
            "ext"  => $ext,
            "path" => $path,
            "mime" => $file->getOriginalMime(),
        ]);
        if ($res) {
            return $this->success([
                "id" => $res->id,
                "url" => url("files.download", ["id" => $res->id, "ext" => $ext]),
            ], "上传成功");
        } else {
            return $this->error("上传信息失败");
        }
    }
}
