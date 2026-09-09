<?php

namespace app\admin\controller;

use app\BaseController;
use app\model\Role as RoleModel;
use Kingbes\Annotation\Annotation;
use Kingbes\Annotation\Data;
use think\Response;
use think\facade\Db;

#[Annotation(["title" => "角色管理", "auth" => true])]
class Role extends BaseController
{
    #[Annotation(["title" => "列表", "menu" => true])]
    public function view(): string|Response
    {
        if (request()->isPost()) {
            $tree = RoleModel::where("is_delete", 0)->select()->toArray();
            return $this->success(["data" => buildTree($tree)]);
        }
        return $this->fetch();
    }

    #[Annotation(["title" => "添加", "menu" => true])]
    public function add(): string|Response
    {
        $list = RoleModel::where("is_delete", 0)->select();
        $this->assign("list", $list); // 获取所有角色
        $this->assign("rule", Data::$data); // 获取所有权限
        if (request()->isPost()) {
            $param = request()->param();
            $res = RoleModel::create($param);
            if ($res) {
                return $this->success();
            } else {
                return $this->error();
            }
        }
        return $this->fetch();
    }

    #[Annotation(["title" => "编辑", "menu" => false])]
    public function edit(): string|Response
    {
        $id = request()->param("id");
        $find = RoleModel::where("id", $id)->find();
        $list = RoleModel::where("is_delete", 0)->where("id", "<>", $id)->select(); // 获取所有角色
        $this->assign("list", $list); // 获取所有角色
        $this->assign("rule", Data::$data); // 获取所有权限
        $this->assign("find", $find); // 获取当前角色
        if (request()->isPost()) {
            $param = request()->param();
            $res = RoleModel::update($param);
            if ($res) {
                return $this->success();
            } else {
                return $this->error();
            }
        }
        return $this->fetch();
    }

    #[Annotation(["title" => "删除", "menu" => false])]
    public function delete(): string|Response
    {
        $id = request()->param("id");
        if (count($id) == 0) {
            return $this->error("请选择要删除的角色");
        }
        Db::startTrans();
        try {
            RoleModel::where("id", "in", $id)->update(["is_delete" => 1]); // 删除角色
            RoleModel::where("pid", "in", $id)->update(["is_delete" => 1]); // 删除角色权限
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
