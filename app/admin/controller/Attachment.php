<?php

namespace app\admin\controller;

use app\BaseController;
use Kingbes\Annotation\Annotation;

#[Annotation(["title" => "附件管理", "icon" => "icon-attachment"])]
class Attachment extends BaseController
{
    #[Annotation(["title" => "选择附件", "page" => false, "auth" => false])]
    public function select(): string
    {
        $param = request()->param();

        $this->assign($param);
        return $this->fetch();
    }
}
