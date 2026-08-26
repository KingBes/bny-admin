<?php

declare(strict_types=1);

namespace app\middleware;

use Closure;
use think\App;
use think\Request;
use think\Response;

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
     * 是否已登录（会话中存在管理员身份）
     */
    protected function isLoggedIn(): bool
    {
        return (int) session('admin_id') > 0;
    }

    public function handle(Request $request, Closure $next): Response
    {
        // 仅后台应用生效
        if (!$this->isAdmin()) {
            return $next($request);
        }

        // 登录页 / 登录提交：未登录放行；已登录则回到仪表盘
        if ($this->isLoginPage($request)) {
            return $this->isLoggedIn()
                ? redirect((string)url("admin"))
                : $next($request);
        }

        // 已登录直接放行
        if ($this->isLoggedIn()) {
            return $next($request);
        }

        // 未登录
        $loginUrl = (string)(url("admin.login.index"));
        if ($request->isAjax()) {
            return json(['code' => 3, 'msg' => '请先登录', 'url' => $loginUrl]);
        }
        return redirect($loginUrl);
    }
}
