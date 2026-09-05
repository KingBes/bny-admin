<?php

declare(strict_types=1);

namespace app\middleware;

use Closure;
use think\App;
use think\Request;
use think\Response;

/**
 * 安装守护中间件
 *
 * 每次请求进入时检测系统是否已安装（项目根 .install.lock 是否存在）：
 * - 未安装：除 install 应用自身外，其余任意页面一律 302 跳转到 /install；
 * - 已安装：拦截 install 应用，302 跳转到前台首页，防止重跑向导覆盖管理员账号。
 */
class CheckInstall
{
    public function __construct(protected App $app)
    {
    }

    /**
     * 是否已完成安装（以锁文件存在为准）
     */
    protected function isInstalled(): bool
    {
        return is_file($this->app->getRootPath() . '.install.lock');
    }

    /**
     * 当前请求是否属于 install 应用
     */
    protected function isInstallRequest(Request $request): bool
    {
        // 多应用模式下当前应用名
        if ($this->app->http->getName() === 'install') {
            return true;
        }
        // 兜底：按请求路径判断
        return str_starts_with(ltrim($request->pathinfo(), '/'), 'install');
    }

    public function handle(Request $request, Closure $next): Response
    {
        $installed  = $this->isInstalled();
        $isInstall  = $this->isInstallRequest($request);

        // 已安装：禁止再次进入安装向导（重跑会覆盖管理员账号）
        if ($installed && $isInstall) {
            return redirect('/');
        }

        // 未安装：跳转到安装向导
        if (!$installed && !$isInstall) {
            return redirect('/install');
        }

        return $next($request);
    }
}