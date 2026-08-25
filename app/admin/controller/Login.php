<?php

namespace app\admin\controller;

use app\BaseController;
use think\Response;
use think\captcha\facade\Captcha;
use Kingbes\Annotation\Annotation;

#[Annotation(["title" => "登录", "auth" => false])]
class Login extends BaseController
{
    public function index(): string
    {
        $this->assign(
            "form",
            session("form") ?? []
        ); // 获取表单数据
        return $this->fetch();
    }

    public function login(): Response
    {
        $param = request()->param();
    }

    public function captcha(): Response
    {
        return Captcha::create();
    }
}
