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
 * 每次请求进入时检测系统是否已安装（项目根 .install.lock 是否存在）。
 * 未安装时，除 install 应用自身外，其余任意页面一律 302 跳转到 /install；
 * 安装完成后（锁文件存在）则完全放行，不干预任何请求。
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
     * 当前请求是否属于 install 应用（防重定向死循环）
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
        if ($this->isInstalled() || $this->isInstallRequest($request)) {
            return $next($request);
        }

        // 未安装：跳转到安装向导
        return redirect('/install');
    }
}