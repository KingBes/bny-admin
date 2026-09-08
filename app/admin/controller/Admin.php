<?php

namespace app\admin\controller;

use app\BaseController;
use think\Response;
use Kingbes\Annotation\Annotation;
use app\model\Admin as AdminModel;
use app\model\Role;

#[Annotation(["title" => "管理员", "auth" => true])]
class Admin extends BaseController
{
    #[Annotation(["title" => "列表", "menu" => true, "auth" => true])]
    public function view(): Response|string
    {
        if (request()->isPost()) {
            $param = request()->param();
            if (isset($param["sort"]) && isset($param["order"])) {
                $order[$param["sort"]] = $param["order"];
            } else {
                $order = ["id" => "desc"];
            }
            $where = [];
            $where[] = ["admin.is_delete", "=", 0];
            if (isset($param["nickname"])) {
                $where[] = ["nickname", "like", "%{$param["nickname"]}"];
            }
            $data = AdminModel::where($where)
                ->withJoin(['role' => ['role.name' => 'role']], 'left')
                ->order($order)
                ->paginate([
                    "list_rows" => $param["pageSize"] ?? 10,
                    "page"      => $param["page"] ?? 1,
                ]);
            return $this->success($data->toArray());
        }
        return $this->fetch();
    }

    #[Annotation(["title" => "添加", "menu" => true, "auth" => true])]
    public function add(): Response|string
    {
        $role = Role::where("is_delete", "=", 0)->select();
        $this->assign("role", $role);
        if (request()->isPost()) {
            $param = request()->param();
            if (mb_strlen($param["password"]) < 6) {
                return $this->error("新密码长度不能少于 6 位");
            }
            if ($param["confirm_password"] !== $param["password"]) {
                return $this->error("两次输入的新密码不一致");
            }
            $param["password"] = password_hash($param["password"], PASSWORD_DEFAULT);
            $result = AdminModel::create($param);
            if ($result) {
                return $this->success();
            }
            return $this->error();
        }
        return $this->fetch();
    }

    #[Annotation(["title" => "编辑", "menu" => false, "auth" => true])]
    public function edit(): Response|string
    {
        $id = request()->param("id");
        $find = AdminModel::find($id);
        $role = Role::where("is_delete", "=", 0)->select();
        $this->assign("role", $role);
        $this->assign("find", $find);
        if (request()->isPost()) {
            $param = request()->param();
            if (mb_strlen($param["password"]) < 6) {
                return $this->error("新密码长度不能少于 6 位");
            }
            if ($param["confirm_password"] !== $param["password"]) {
                return $this->error("两次输入的新密码不一致");
            }
            $param["password"] = password_hash($param["password"], PASSWORD_DEFAULT);
            $res = $find->save($param);
            if ($res) {
                return $this->success();
            }
            return $this->error();
        }
        return $this->fetch();
    }

    #[Annotation(["title" => "删除", "menu" => false, "auth" => true])]
    public function delete(): Response
    {
        $id = request()->param("id");
        $res = AdminModel::where("id", "in", $id)->update(["is_delete" => 1, "update_time" => time()]);
        if ($res) {
            return $this->success();
        }
        return $this->error();
    }

    #[Annotation(["title" => "个人中心", "menu" => true, "auth" => false])]
    public function mine(): Response|string
    {
        // 修改密码
        if (request()->isPost()) {
            $account = AdminModel::find((int) session("admin_id"));
            if (!$account) {
                return $this->error("管理员不存在");
            }
            $param   = request()->param();
            $old     = (string) ($param["old_password"] ?? "");
            $password = (string) ($param["password"] ?? "");
            $confirm = (string) ($param["confirm_password"] ?? "");

            if ($old === "" || $password === "" || $confirm === "") {
                return $this->error("请填写完整的密码信息");
            }
            if (!password_verify($old, $account["password"])) {
                return $this->error("旧密码错误");
            }
            if (mb_strlen($password) < 6) {
                return $this->error("新密码长度不能少于 6 位");
            }
            if ($password !== $confirm) {
                return $this->error("两次输入的新密码不一致");
            }

            $account->save(["password" => password_hash($password, PASSWORD_DEFAULT)]);
            return $this->success();
        }
        return $this->fetch();
    }
}
