<?php
// 应用公共函数文件

use Kingbes\Annotation\Url;

if (!function_exists('url')) {
    function url(string $url = '', array $vars = [], $suffix = true, $domain = false): string
    {
        return Url::build($url, $vars, $suffix, $domain);
    }
}

/**
 * 构建无限层级树形结构
 * @param array $data     原始平面数组，每条包含 id、pid
 * @param int   $rootPid  根节点的父ID（通常为0）
 * @param string $childKey 子节点字段名
 * @return array 树形数组
 */
function buildTree(array $data, int $rootPid = 0, string $childKey = 'children'): array
{
    $tree = [];

    foreach ($data as &$item) {
        // 根节点判断：松散比较，自动兼容字符串/数字类型的 pid
        if ($item['pid'] == $rootPid) {
            $tree[] = &$item;
        } else {
            // 遍历查找对应父节点
            foreach ($data as &$parent) {
                if ($parent['id'] == $item['pid']) {
                    // 仅当节点有子节点时，才创建 children 字段
                    if (!isset($parent[$childKey])) {
                        $parent[$childKey] = [];
                    }
                    $parent[$childKey][] = &$item;
                    break;
                }
            }
        }
    }
    // 强制解除所有引用，避免后续操作污染原数组
    unset($item, $parent);

    return $tree;
}

/**
 * 树形菜单html
 *
 * @param array $data
 * @return string
 */
function menuTree(array $data): string
{
    $html = "";
    foreach ($data as $k => $v) {

        $a = "javascript:void(0);";
        if ($v["route"] !== "0") {
            $a = url($v["route"]);
        }

        $icon_son = "";
        $is_tab = "admin-tab";
        if (!empty($v['children'])) {
            $icon_son = "<i class='bny-icon icon-arrowright'></i>";
            $is_tab = "";
        }

        $html .= "<li>";
        $html .= "<a class='trigger' tip='{$v['name']}' data-url='{$a}' data-title='{$v['name']}' {$is_tab}>";
        $html .= "<i class='bny-icon {$v['icon']}'></i>";
        $html .= "<span>{$v['name']}</span>";
        $html .= $icon_son;
        $html .= "</a>";

        if (!empty($v['children'])) {
            $html .= "<ul class='sub-menu'>";
            $html .= menuTree($v['children']);
            $html .= "</ul>";
        }
        $html .= "</li>";
    }
    return $html;
}
