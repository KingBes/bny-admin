<?php

declare(strict_types=1);

namespace app\middleware;

use Closure;
use think\App;
use think\Request;
use think\Response;
use app\model\Admin as AdminModel;
use app\model\Role as RoleModel;
use Kingbes\Annotation\Data;

/**
 * 后台登录校验中间件
 */
class Admin
{
    public function __construct(protected App $app) {}

    /**
     * 当前请求是否属于后台应用
     */
    protected function isAdmin(): bool
    {
        return $this->app->http->getName() === 'admin';
    }

    /**
     * 是否登录页相关（放行它，避免重定向死循环）
     */
    protected function isLoginPage(Request $request): bool
    {
        return strtolower((string) $request->controller()) === 'login'
            && strtolower((string) $request->action()) !== 'logout';
    }

    /**
     * 需要权限的 路由名称
     *
     * @return array
     */
    protected function isRoles(): array
    {
        $data = Data::$data; // 获取所有权限节点
        $arr = [];
        foreach ($data as $key => $value) {
            if (isset($value["auth"]) && $value["auth"] && count($value["methods"]) > 0) {
                foreach ($value["methods"] as $k => $v) {
                    $arr[] = "admin." . $value["name"] . "." . $v["name"];
                }
            }
        }
        return $arr;
    }

    /**
     * 权限验证
     *
     * @return boolean
     */
    protected function roleAuth(): bool
    {
        $admin_id = (int)session('admin_id');
        $rid = AdminModel::where("id", "=", $admin_id)->value("rid");
        // 超级管理员直接放行
        if ($rid === 0) {
            return true;
        }
        $roles = explode(",", RoleModel::where("id", "=", $rid)->value("rule")); // 获取权限
        $rule = request()->rule()->getName(); // 获取当前路由名称
        // 判断当前路由是否需要权限验证
        if (!in_array($rule, $this->isRoles())) {
            return true;
        }
        // 判断当前用户是否拥有权限
        if (in_array($rule, $roles)) {
            return true;
        }
        return false;
    }

    public function handle(Request $request, Closure $next): Response
    {
        // 仅后台应用生效
        if (!$this->isAdmin()) {
            return $next($request);
        }

        // 登录页 / 登录提交：未登录放行；已登录则回到仪表盘
        if ($this->isLoginPage($request)) {
            return (int)session('admin_id') > 0
                ? redirect((string)url("admin"))
                : $next($request);
        }

        // 已登录直接放行
        if ((int)session('admin_id') > 0) {
            if ($this->roleAuth()) {
                return $next($request);
            } else {
                return json(['code' => 3, 'msg' => '权限不足']);
            }
        }

        // 未登录
        $loginUrl = (string)(url("admin.login.index"));
        if ($request->isAjax()) {
            return json(['code' => 3, 'msg' => '请先登录', 'url' => $loginUrl]);
        }
        return redirect($loginUrl);
    }
}
