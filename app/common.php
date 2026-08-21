<?php
// 应用公共函数文件

use think\facade\Session;

if (!function_exists('bny_token')) {
    /**
     * 获取 CSRF 令牌（session 中不存在则生成并存储）.
     *
     * @return string 32 位十六进制令牌
     */
    function bny_token(): string
    {
        $token = (string) Session::get('_bny_token', '');
        if ($token === '') {
            $token = bin2hex(random_bytes(16));
            Session::set('_bny_token', $token);
        }

        return $token;
    }
}

if (!function_exists('bny_config')) {
    /**
     * 读取站点配置项（请求级静态缓存）.
     *
     * @param string $key     配置键
     * @param string $default 默认值
     */
    function bny_config(string $key, string $default = ''): string
    {
        static $cache = null;
        if ($cache === null) {
            $cache = [];
            try {
                foreach (\think\facade\Db::name('config')->column('value', 'key') as $k => $v) {
                    $cache[(string) $k] = (string) $v;
                }
            } catch (\Throwable) {
                // 未安装/未建表时返回默认值
            }
        }

        return $cache[$key] ?? $default;
    }
}