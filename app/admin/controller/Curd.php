<?php

namespace app\admin\controller;

use app\BaseController;
use Kingbes\Annotation\Annotation;

abstract class Curd extends BaseController
{
    #[Annotation([
        "title" => "查询",
        "page" => false,
        "auth" => true,
    ])]
    public function query(): string
    {
        return $this->fetch();
    }

    #[Annotation([
        "title" => "创建",
        "page" => false,
        "auth" => true,
    ])]
    public function create(): string
    {
        return $this->fetch();
    }

    #[Annotation([
        "title" => "更新",
        "page" => false,
        "auth" => true,
    ])]
    public function update(): string
    {
        return $this->fetch();
    }

    #[Annotation([
        "title" => "删除",
        "page" => false,
        "auth" => true,
    ])]
    public function delete(): string
    {
        return $this->fetch();
    }
}
