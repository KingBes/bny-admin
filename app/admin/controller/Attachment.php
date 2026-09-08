<?php

namespace app\admin\controller;

use Kingbes\Annotation\Annotation;
use think\Response;
use app\model\Attachment as AttachmentModel;
use think\exception\FileException;
use app\model\Config;
use app\BaseController;

#[Annotation(["title" => "附件管理", "auth" => true])]
class Attachment extends BaseController
{
    #[Annotation(["title" => "列表", "menu" => true, "auth" => true])]
    public function view(): string|Response
    {
        // 获取附件列表
        if (request()->isPost()) {
            $param = request()->param();
            if (isset($param["sort"]) && isset($param["order"])) {
                $order[$param["sort"]] = $param["order"];
            } else {
                $order = ["id" => "desc"];
            }
            $where = [];
            $where[] = ["is_delete", "=", 0];
            if (isset($param["name"]) && $param["name"] !== "") {
                $where[] = ["name", "like", "%{$param["name"]}%"];
            }
            $data = AttachmentModel::where($where)->order($order)->paginate([
                "list_rows" => $param["pageSize"] ?? 10,
                "page"      => $param["page"] ?? 1,
            ]);
            return $this->success($data->toArray());
        }
        return $this->fetch();
    }

    #[Annotation(["title" => "删除", "menu" => false, "auth" => true])]
    public function delete(): Response
    {
        $param = request()->param();
        if (!isset($param["id"]) || $param["id"] <= 0) {
            return $this->error("请选择要删除的附件");
        }
        $res = AttachmentModel::where("id", "in", $param["id"])->update(["is_delete" => 1, "update_time" => time()]);
        if ($res) {
            return $this->success();
        }
        return $this->error();
    }

    #[Annotation(["title" => "选择附件", "page" => false, "auth" => false])]
    public function select(): string|Response
    {
        $param = request()->param();
        $this->assign([
            "target" => $param["target"] ?? "",
            "max"    => (int) ($param["max"] ?? 1),
            "type"   => (string) ($param["type"] ?? "*"),
        ]);
        // 获取附件列表
        if (request()->isPost()) {
            $name = request()->post("name", ""); // 搜索关键字
            $page = request()->post("page", 1); // 当前页码
            $list = AttachmentModel::where("is_delete", 0)
                ->where("name", "like", "%{$name}%")
                ->order("id", "desc")
                ->paginate([
                    "list_rows" => 8,
                    "page"      => $page,
                ]);
            return $this->success($list->toArray());
        }
        return $this->fetch();
    }

    #[Annotation(["title" => "上传附件", "menu" => false, "auth" => false])]
    public function upload(): Response
    {
        // 上传文件
        $file = request()->file("file");
        // 当前日期
        $date = date("Ymd");
        // 上传配置
        $is_size = Config::where("group", "base")
            ->where("key", "upload_size")
            ->value("value");
        // 上传配置
        $in_exts = Config::where("group", "base")
            ->where("key", "upload_ext")
            ->value("value");
        // 文件有效性（区分大小超限 / 未传文件，给出明确提示）
        if (!$file || !$file->isValid()) {
            $err = ($_FILES["file"]["error"] ?? null); // 获取上传错误码
            if (
                $err === UPLOAD_ERR_INI_SIZE
                || $err === UPLOAD_ERR_FORM_SIZE
            ) {
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
        // 验证文件扩展名
        if (!in_array($ext, explode(",", $in_exts))) {
            return $this->error("文件扩展名错误");
        }
        // 验证文件大小是否超出限制
        if ($file->getSize() > (int)$is_size * 1024 * 1024) {
            return $this->error("文件大小超出限制");
        }
        // 路径文件夹
        $folder = app()->getRootPath() . "files" . DIRECTORY_SEPARATOR . $date;
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
        $path = $date . DIRECTORY_SEPARATOR . $name;
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
                "url" => url("files.download", ["name" => $res->id . "." . $ext], false),
            ], "上传成功");
        } else {
            return $this->error("上传信息失败");
        }
    }
}
