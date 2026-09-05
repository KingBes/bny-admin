<?php

namespace app\admin\controller;

use app\BaseController;
use Kingbes\Annotation\Annotation;
use app\model\Config;
use think\Response;

#[Annotation(["title" => "系统管理", "icon" => "icon-setting", "auth" => true])]
class System extends BaseController
{
    #[Annotation(["title" => "基础设置", "menu" => true])]
    public function base(): string|Response
    {
        // 读取基础配置
        $config = Config::read('base');
        // 赋值给模板
        $this->assign($config);
        // 处理POST请求
        if (request()->isPost()) {
            $param = request()->param();
            // 更新配置
            $res = Config::upToAdd($param);
            if ($res) {
                return $this->success();
            } else {
                return $this->error();
            }
        }
        // 渲染模板
        return $this->fetch();
    }
}
