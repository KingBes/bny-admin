<?php

declare(strict_types=1);

namespace app\middleware;

use app\model\AdminLog as AdminLogModel;
use Closure;
use Kingbes\Annotation\Data;
use think\App;
use think\Request;
use think\Response;

/**
 * 管理员操作日记中间件
 *
 * 在请求处理完成后记录后台的写操作，操作结果取自响应的 code 字段。
 * 登录/登出同样记录；日记自身与列表查询、页面渲染不记录。
 */
class AdminLog
{
    /**
     * 需要记录的操作，形如 admin.menu.delete
     *
     * 其余写操作按「非 GET 请求」判定，新增接口无需在此登记
     */
    private const ACTIONS = ["add", "edit", "delete", "restore", "clear", "mine", "login", "logout", "upload"];

    /**
     * 不记录的控制器：日记自身（查看与删除日记不再产生日记）
     */
    private const SKIP_CONTROLLERS = ["log"];

    /**
     * 不记录的操作：列表查询、页面渲染、验证码
     */
    private const SKIP_ACTIONS = ["view", "index", "menu", "captcha", "detail"];

    public function __construct(protected App $app) {}

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // 仅后台应用的写操作记账
        if (!$this->isAdmin() || !$this->recordable($request)) {
            return $response;
        }

        [$result, $msg] = $this->outcome($response);
        AdminLogModel::record($this->title($request), $result, $msg, $request->param());

        return $response;
    }

    /**
     * 当前请求是否属于后台应用
     */
    protected function isAdmin(): bool
    {
        return $this->app->http->getName() === "admin";
    }

    /**
     * 是否需要记录
     */
    protected function recordable(Request $request): bool
    {
        $controller = strtolower((string) $request->controller());
        $action     = $this->snake((string) $request->action());

        if (in_array($controller, self::SKIP_CONTROLLERS, true)) {
            return false;
        }
        if (in_array($action, self::SKIP_ACTIONS, true)) {
            return false;
        }
        return in_array($action, self::ACTIONS, true) || !$request->isGet();
    }

    /**
     * 操作标题：控制器注解标题 + 方法注解标题，如 菜单管理 - 删除
     */
    protected function title(Request $request): string
    {
        $controller = strtolower((string) $request->controller());
        $action     = $this->snake((string) $request->action());

        foreach (Data::$data as $item) {
            if (($item["app"] ?? "") !== "admin" || (string) ($item["name"] ?? "") !== $controller) {
                continue;
            }
            foreach ((array) ($item["methods"] ?? []) as $method) {
                if ($this->snake((string) ($method["name"] ?? "")) !== $action) {
                    continue;
                }
                $title = (string) ($method["title"] ?? "");
                $group = (string) ($item["title"] ?? "");
                return $group !== "" && $group !== $title ? $group . " - " . $title : $title;
            }
        }
        // 注解未命中时退回 控制器 - 操作
        return $controller . " - " . $action;
    }

    /**
     * 解析操作结果：响应为 JSON 时按 code 判定，其余按成功处理
     *
     * @return array [是否成功, 返回信息]
     */
    protected function outcome(Response $response): array
    {
        $json = json_decode($response->getContent(), true);
        if (!is_array($json) || !array_key_exists("code", $json)) {
            return [true, ""];
        }
        return [(int) $json["code"] === 0, (string) ($json["msg"] ?? "")];
    }

    /**
     * 大驼峰转下划线，兼容已是下划线的方法名
     */
    protected function snake(string $value): string
    {
        return strtolower((string) preg_replace("/(?<!^)[A-Z]/", "_$0", $value));
    }
}
