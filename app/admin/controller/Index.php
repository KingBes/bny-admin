<?php

namespace app\admin\controller;

use app\BaseController;
use Kingbes\Annotation\Annotation;
use app\model\Menu as MenuModel;
use app\model\Admin as AdminModel;
use app\model\Attachment as AttachmentModel;

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
        $id = (int) session("admin_id");
        $admin = AdminModel::where("id", $id)->find();
        $attachment_count = AttachmentModel::where("is_delete", 0)->count(); //附件数量
        $this->assign([
            'title' => '仪表盘',
            'welcome' => '欢迎回来,' . $admin["nickname"],
            'article_count' => 0,
            'attachment_count' => $attachment_count,
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
        $menu = MenuModel::where("is_delete", 0)
            ->where("status", 1)
            ->select();
        $this->assign("menu", buildTree($menu->toArray()));
        return $this->fetch();
    }
}
