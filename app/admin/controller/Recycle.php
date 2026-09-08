<?php

namespace app\admin\controller;

use app\BaseController;
use app\model\Admin as AdminModel;
use app\model\Attachment as AttachmentModel;
use app\model\Menu as MenuModel;
use app\model\Role as RoleModel;
use Kingbes\Annotation\Annotation;
use think\facade\Db;
use think\response;

#[Annotation(["title" => "回收站", "auth" => true])]
class Recycle extends BaseController
{
    /**
     * 回收站支持的数据类型
     *
     * @return array [模型类, 类型名称, 名称字段, 标签颜色]
     */
    private function types(): array
    {
        return [
            "admin"      => [AdminModel::class, "管理员", "nickname", "blue"],
            "menu"       => [MenuModel::class, "菜单", "name", "green"],
            "role"       => [RoleModel::class, "角色", "name", "yellow"],
            "attachment" => [AttachmentModel::class, "附件", "name", "red"],
        ];
    }

    #[Annotation(["title" => "列表", "menu" => true])]
    public function view(): string|response
    {
        if (request()->isPost()) {
            $param = request()->param();
            return $this->success(["data" => $this->deleted($param)]);
        }
        return $this->fetch();
    }

    #[Annotation(["title" => "恢复", "menu" => false])]
    public function restore(): response
    {
        $items = $this->items();
        if ($items === []) {
            return $this->error("请选择要恢复的数据");
        }
        Db::startTrans();
        try {
            foreach ($items as $type => $ids) {
                [$model] = $this->types()[$type];
                // 管理员：账号名唯一，恢复前检查是否已被占用
                if ($type === "admin") {
                    $usernames = $model::where("id", "in", $ids)->column("username");
                    $exists = $model::where("is_delete", 0)
                        ->where("username", "in", $usernames)
                        ->column("username");
                    if ($exists !== []) {
                        Db::rollback();
                        return $this->error("账号已存在，无法恢复：" . implode("、", $exists));
                    }
                }
                $model::where("id", "in", $ids)->update(["is_delete" => 0]);
            }
            // 提交事务
            Db::commit();
            return $this->success();
        } catch (\Exception $e) {
            // 回滚事务
            Db::rollback();
            return $this->error($e->getMessage());
        }
    }

    #[Annotation(["title" => "彻底删除", "menu" => false])]
    public function delete(): response
    {
        $items = $this->items();
        if ($items === []) {
            return $this->error("请选择要彻底删除的数据");
        }
        Db::startTrans();
        try {
            $this->purge($items);
            // 提交事务
            Db::commit();
            return $this->success();
        } catch (\Exception $e) {
            // 回滚事务
            Db::rollback();
            return $this->error($e->getMessage());
        }
    }

    #[Annotation(["title" => "清空", "menu" => false])]
    public function clear(): response
    {
        Db::startTrans();
        try {
            $this->purge();
            // 提交事务
            Db::commit();
            return $this->success();
        } catch (\Exception $e) {
            // 回滚事务
            Db::rollback();
            return $this->error($e->getMessage());
        }
    }

    /**
     * 已删除数据列表
     *
     * @param array $param 请求参数（name 名称关键字）
     * @return array 统一格式：[type, type_text, id, title, delete_time]
     */
    private function deleted(array $param = []): array
    {
        $keyword = trim((string) ($param["name"] ?? ""));
        $list = [];
        foreach ($this->types() as $type => [$model, $text, $field, $color]) {
            $rows = $model::where("is_delete", 1)
                ->field("id,{$field},update_time")
                ->select()
                ->toArray();
            foreach ($rows as $row) {
                $title = (string) ($row[$field] ?? "");
                if ($keyword !== "" && stripos($title, $keyword) === false) {
                    continue;
                }
                // 模型读取时 update_time 可能是时间戳或 Y-m-d H:i:s 字符串
                $list[] = [
                    "type"        => $type,
                    "type_text"   => $text,
                    "color"       => $color,
                    "id"          => (int) $row["id"],
                    "title"       => $title,
                    "delete_time" => $this->time((string) ($row["update_time"] ?? "")),
                ];
            }
        }
        // 按删除时间倒序
        usort($list, fn(array $a, array $b): int => strcmp($b["delete_time"], $a["delete_time"]));
        return $list;
    }

    /**
     * 格式化删除时间
     *
     * 支持时间戳与 Y-m-d H:i:s 字符串；无效值统一显示 —
     */
    private function time(string $value): string
    {
        if ($value === "" || $value === "0") {
            return "—";
        }
        if (is_numeric($value)) {
            return (int) $value > 0 ? date("Y-m-d H:i", (int) $value) : "—";
        }
        // 未记录时间的行，模型也会格式化成 1970-01-01 00:00:00，按无效值处理
        return strtotime($value) > 0 ? substr($value, 0, 16) : "—";
    }

    /**
     * 解析勾选的数据
     *
     * 复选框值为 "类型:ID"（跨表 ID 会重复，必须带类型）
     *
     * @return array [类型 => [ID, ...]]
     */
    private function items(): array
    {
        $ids = request()->param("id", []);
        if (!is_array($ids)) {
            $ids = [$ids];
        }
        $items = [];
        foreach ($ids as $val) {
            if (!is_scalar($val) || !str_contains((string) $val, ":")) {
                continue;
            }
            [$type, $id] = explode(":", (string) $val, 2);
            $id = (int) $id;
            if (!isset($this->types()[$type]) || $id <= 0) {
                continue;
            }
            $items[$type][] = $id;
        }
        return $items;
    }

    /**
     * 物理删除
     *
     * @param array|null $items 指定数据；为 null 时清空全部已删除数据
     */
    private function purge(?array $items = null): void
    {
        foreach ($this->types() as $type => [$model]) {
            if ($items !== null && !isset($items[$type])) {
                continue;
            }
            $ids = $items === null ? null : $items[$type];
            // 附件：先删除物理文件，避免留下垃圾
            if ($type === "attachment") {
                $query = $model::where("is_delete", 1);
                if ($ids !== null) {
                    $query->where("id", "in", $ids);
                }
                foreach ($query->column("path") as $path) {
                    $this->unlink((string) $path);
                }
            }
            $query = $model::where("is_delete", 1);
            if ($ids !== null) {
                $query->where("id", "in", $ids);
            }
            $query->delete();
        }
    }

    /**
     * 删除物理文件
     */
    private function unlink(string $path): void
    {
        if ($path === "") {
            return;
        }
        $file = app()->getRootPath() . "files" . DIRECTORY_SEPARATOR . $path;
        if (is_file($file)) {
            unlink($file);
        }
    }
}
