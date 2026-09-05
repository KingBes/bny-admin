<?php

namespace app\admin\controller;

use app\BaseController;
use Kingbes\Annotation\Annotation;

#[Annotation(["title" => "首页管理", "auth" => false, "menu" => false])]
class Index extends BaseController
{
    #[Annotation(["title" => "首页"])]
    public function index(): string
    {
        return $this->fetch();
    }

    #[Annotation(["title" => "仪表盘"])]
    public function home(): string
    {
        $this->assign([
            'title' => '仪表盘',
            'welcome' => '欢迎回来,KLLXS',
            'diary_count' => 0,
            'photo_count' => 0,
            'visit_count' => 0,
            'comment_count' => 0,
        ]);
        return $this->fetch();
    }

    #[Annotation(["title" => "菜单"])]
    public function menu(): string
    {
        $toggle = "";
        if (request()->get("toggle")) {
            $toggle = "nav-toggle";
        }
        $this->assign([
            'toggle' => $toggle,
        ]);
        return $this->fetch();
    }
}
