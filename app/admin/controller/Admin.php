<?php

namespace app\admin\controller;

use app\BaseController;
use think\Response;
use Kingbes\Annotation\Annotation;
use app\model\Admin as AdminModel;

#[Annotation(["title" => "登录"])]
class Admin extends BaseController
{
    #[Annotation(["title" => "个人中心", "auth" => false])]
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
