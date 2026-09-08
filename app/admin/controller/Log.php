<?php

namespace app\admin\controller;

use app\BaseController;
use app\model\AdminLog as AdminLogModel;
use Kingbes\Annotation\Annotation;
use think\facade\Db;
use think\response;

#[Annotation(["title" => "操作日记", "auth" => true])]
class Log extends BaseController
{
    /**
     * 允许排序的字段
     */
    private const SORTS = ["id", "create_time"];

    #[Annotation(["title" => "列表", "menu" => true])]
    public function view(): string|response
    {
        if (request()->isPost()) {
            $param = request()->param();
            $where = [];
            $keyword = trim((string) ($param["keyword"] ?? ""));
            if ($keyword !== "") {
                // 账号 / 昵称 / 操作 / 地址 / IP 任一命中
                $where[] = ["username|nickname|title|url|ip", "like", "%{$keyword}%"];
            }
            $data = AdminLogModel::where($where)
                ->order($this->order($param))
                ->paginate([
                    "list_rows" => $param["pageSize"] ?? 10,
                    "page"      => $param["page"] ?? 1,
                ]);
            return $this->success($data->toArray());
        }
        return $this->fetch();
    }

    #[Annotation(["title" => "详情", "menu" => false])]
    public function detail(): string
    {
        $id = (int) request()->param("id");
        $find = AdminLogModel::find($id);
        if (!$find) {
            return "<div class=\"p-12\">日记不存在或已被删除</div>";
        }
        $this->assign("find", $find);
        $this->assign("param", $this->format((string) $find["param"]));
        return $this->fetch();
    }

    #[Annotation(["title" => "删除", "menu" => false])]
    public function delete(): response
    {
        $id = request()->param("id", []);
        if (!is_array($id) || $id === []) {
            return $this->error("请选择要删除的日记");
        }
        Db::startTrans();
        try {
            AdminLogModel::where("id", "in", $id)->delete();
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
            // 日记不进回收站，清空即物理删除全部
            AdminLogModel::where("id", ">", 0)->delete();
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
     * 排序条件：仅允许指定字段，默认按时间倒序
     */
    private function order(array $param): array
    {
        $sort = (string) ($param["sort"] ?? "");
        if (!in_array($sort, self::SORTS, true)) {
            return ["id" => "desc"];
        }
        $order = strtolower((string) ($param["order"] ?? "")) === "asc" ? "asc" : "desc";
        return [$sort => $order];
    }

    /**
     * 请求参数格式化：JSON 美化输出并转义，供详情弹窗展示
     */
    private function format(string $param): string
    {
        if ($param === "") {
            return "";
        }
        $data = json_decode($param, true);
        $text = $data !== null
            ? json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
            : $param;
        return htmlspecialchars((string) $text, ENT_QUOTES, "UTF-8");
    }
}
