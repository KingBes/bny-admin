<?php

namespace app\admin\controller;

use app\BaseController;

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

    public function settings(): string
    {
        return $this->fetch();
    }
}
