<?php
// 应用公共函数文件

use Kingbes\Annotation\Url;

if (!function_exists('url')) {
    function url(string $url = '', array $vars = [], $suffix = true, $domain = false): string
    {
        return Url::build($url, $vars, $suffix, $domain);
    }
}
