<?php

namespace app\admin\controller;

use app\BaseController;
use app\model\Menu as MenuModel;
use think\response;
use Kingbes\Annotation\Annotation;
use Kingbes\Annotation\Data;
use think\facade\Db;

#[Annotation(["title" => "菜单管理", "auth" => true])]
class Menu extends BaseController
{
    #[Annotation(["title" => "列表", "menu" => true])]
    public function view(): string|response
    {
        if (request()->isPost()) {
            $list = MenuModel::where("is_delete", 0)->select()
                ->toArray();
            $tree = buildTree($list);
            return $this->success(["data" => $tree]);
        }
        return $this->fetch();
    }

    #[Annotation(["title" => "添加", "menu" => true])]
    public function add(): string|response
    {
        $list = MenuModel::where("is_delete", 0)->select();
        $this->assign("list", $list);
        $this->assign("menus", Data::$data);
        if (request()->isPost()) {
            $param = request()->param();
            $res = MenuModel::create($param);
            if ($res) {
                return $this->success();
            } else {
                return $this->error();
            }
        }
        return $this->fetch();
    }

    #[Annotation(["title" => "编辑", "menu" => false])]
    public function edit(): string|response
    {
        $id = request()->param("id");
        $find  = MenuModel::where("id", $id)->find();
        $list = MenuModel::where("is_delete", 0)->where("id", "<>", $id)->select();
        $this->assign("list", $list);
        $this->assign("menus", Data::$data);
        $this->assign("find", $find);
        if (request()->isPost()) {
            $param = request()->param();
            $res = MenuModel::update($param);
            if ($res) {
                return $this->success();
            } else {
                return $this->error();
            }
        }
        return $this->fetch();
    }

    #[Annotation(["title" => "删除", "menu" => false])]
    public function delete(): response
    {
        $id = request()->param("id");
        if (count($id) == 0) {
            return $this->error("请选择要删除的菜单");
        }
        Db::startTrans();
        try {
            MenuModel::where("id", "in", $id)->update(["is_delete" => 1, "update_time" => time()]); // 删除菜单
            MenuModel::where("pid", "in", $id)->update(["is_delete" => 1, "update_time" => time()]); // 删除菜单子项
            // 提交事务
            Db::commit();
            return $this->success();
        } catch (\Exception $e) {
            // 回滚事务
            Db::rollback();
            return $this->error($e->getMessage());
        }
    }
}
