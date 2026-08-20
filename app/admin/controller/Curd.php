<?php

namespace app\admin\controller;

use app\BaseController;
use Kingbes\Annotation\Annotation;

abstract class Curd extends BaseController
{
    #[Annotation([
        "title" => "读取",
        "page" => true
    ])]
    public function read(): string
    {
        return $this->fetch();
    }

    #[Annotation([
        "title" => "创建",
        "page" => true
    ])]
    public function create(): string
    {
        return $this->fetch();
    }

    #[Annotation([
        "title" => "更新",
        "page" => false
    ])]
    public function update(): string
    {
        return $this->fetch();
    }

    #[Annotation([
        "title" => "删除",
        "page" => false
    ])]
    public function delete(): string
    {
        return $this->fetch();
    }
}
