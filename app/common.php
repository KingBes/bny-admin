<?php
// 应用公共函数文件

use think\route\Url as UrlBuild;
use Kingbes\Annotation\Url;

if (!function_exists('url')) {
    function url(string $url = '', array $vars = [], $suffix = true, $domain = false): UrlBuild
    {
        return Url::build($url, $vars, $suffix, $domain);
    }
}
