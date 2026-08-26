<?php

namespace app\admin\controller;

use app\BaseController;
use think\Response;
use think\captcha\facade\Captcha;
use Kingbes\Annotation\Annotation;
use app\model\Admin;

#[Annotation(["title" => "登录", "auth" => false])]
class Login extends BaseController
{
    /**
     * 登录首页
     *
     * @return string
     */
    public function index(): string
    {
        $this->assign(
            "form",
            session("form") ?? []
        ); // 获取表单数据
        return $this->fetch();
    }

    /**
     * 登录
     *
     * @return Response
     */
    public function login(): Response
    {
        $param = request()->param();
        // 验证码验证
        if (!captcha_check($param["captcha"])) {
            return $this->error("验证码错误");
        };
        // 验证用户名密码
        $find = Admin::where("username", $param["username"])->find();
        if (!$find) {
            return $this->error("用户名不存在");
        }
        if (!password_verify($param["password"], $find["password"])) {
            return $this->error("密码错误");
        }
        // 登录成功
        session("admin_id", $find["id"]);
        if (isset($param["remember"])) {
            session("form", $param);
        }
        return $this->success(url: "admin");
    }

    /**
     * 验证码
     *
     * @return Response
     */
    public function captcha(): Response
    {
        return Captcha::create();
    }

    /**
     * 退出登录
     *
     * @return Response
     */
    public function logout(): Response
    {
        session("admin_id", null);
        return $this->success(url: "admin.login.index");
    }
}
