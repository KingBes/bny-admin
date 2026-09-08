<?php
declare (strict_types = 1);

namespace app\model;

use think\Model;

/**
 * 管理员操作日记
 *
 * 由 app\middleware\AdminLog 在请求结束后自动写入。
 *
 * @mixin \think\Model
 */
class AdminLog extends Model
{
    /**
     * 日记只写不改：表内没有 update_time 字段
     */
    protected $updateTime = false;

    /**
     * 读取时追加：操作人显示名（无昵称时取账号，都没有则为未登录）
     */
    protected $append = ["admin"];

    /**
     * 不记录明文的字段（记录时替换为 ******）
     */
    private const SECRET = ["password", "old_password", "confirm_password", "captcha"];

    /**
     * 写入一条操作日记
     *
     * @param string $title  操作标题（取自注解，如 菜单管理 - 删除）
     * @param bool   $result 操作结果，失败时同时记录返回信息
     * @param string $msg    返回信息
     * @param array  $param  请求参数，敏感字段会被脱敏
     * @return void
     */
    public static function record(string $title, bool $result = true, string $msg = "", array $param = []): void
    {
        $request = request();
        $adminId = (int) session("admin_id");
        // 登录失败时会话里没有管理员，账号只能从提交参数里取
        $admin = $adminId > 0 ? Admin::where("id", $adminId)->find() : null;

        try {
            self::create([
                "admin_id"    => $adminId,
                "username"    => $admin["username"] ?? self::text($param["username"] ?? ""),
                "nickname"    => $admin["nickname"] ?? "",
                "title"       => self::text($title, 100),
                "url"         => self::text($request->url(), 255),
                "method"      => $request->method(),
                "ip"          => self::text((string) $request->ip(), 45),
                "param"       => self::param($param),
                "agent"       => self::text((string) $request->server("HTTP_USER_AGENT", ""), 255),
                "result"      => $result ? 1 : 0,
                "msg"         => self::text($msg, 255),
                "create_time" => time(),
            ]);
        } catch (\Throwable) {
            // 日记写入失败不能影响正常业务
        }
    }

    /**
     * 操作人显示名
     */
    public function getAdminAttr(mixed $value, array $data): string
    {
        $nickname = (string) ($data["nickname"] ?? "");
        $username = (string) ($data["username"] ?? "");
        if ($nickname !== "" && $username !== "") {
            return $nickname . "（" . $username . "）";
        }
        if ($nickname !== "") {
            return $nickname;
        }
        return $username !== "" ? $username : "未登录";
    }

    /**
     * 参数转 JSON：敏感字段脱敏
     */
    private static function param(array $param): string
    {
        if ($param === []) {
            return "";
        }
        foreach (self::SECRET as $key) {
            if (array_key_exists($key, $param)) {
                $param[$key] = "******";
            }
        }
        $json = json_encode($param, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        return $json === false ? "" : mb_substr($json, 0, 65535);
    }

    /**
     * 文本截断，避免超长数据入库报错
     */
    private static function text(mixed $value, int $length = 255): string
    {
        return mb_substr((string) $value, 0, $length);
    }
}
