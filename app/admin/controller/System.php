<?php

namespace app\admin\controller;

use app\BaseController;
use Kingbes\Annotation\Annotation;
use app\model\Config;

#[Annotation(["title" => "系统管理", "icon" => "icon-setting"])]
class System extends BaseController
{
    public function users(): string
    {
        $list = [
            ['id' => 1, 'name' => 'kllxs', 'email' => 'hi@kllxs.com', 'role' => '管理员', 'status' => '启用'],
            ['id' => 2, 'name' => 'guest', 'email' => 'guest@example.com', 'role' => '访客', 'status' => '启用'],
        ];
        $this->assign([
            'list' => $list,
        ]);
        return $this->fetch();
    }

    #[Annotation(["title" => "基础设置", "page" => true, "auth" => true])]
    public function base(): string
    {
        return $this->fetch();
    }
}
