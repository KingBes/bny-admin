<?php

namespace app\admin\controller;

use app\BaseController;
use app\model\Role as RoleModel;
use Kingbes\Annotation\Annotation;
use Kingbes\Annotation\Data;
use think\Response;
use think\facade\Db;

#[Annotation(["title" => "角色管理", "icon" => "icon-shopping-fill", "auth" => true])]
class Role extends BaseController
{
    #[Annotation(["title" => "列表", "menu" => true])]
    public function view(): string|Response
    {
        if (request()->isPost()) {
            $param = request()->param();
            // 树形输出：pid 层级嵌套 children，支持按名称过滤
            $tree = RoleModel::tree(isset($param["name"]) ? (string) $param["name"] : null);
            return $this->success(["data" => $tree]);
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
        Db::startTrans();
        try {
            RoleModel::where("id", "in", $id)->update(["is_delete" => 1]); // 删除角色
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
