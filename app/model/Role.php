<?php

declare(strict_types=1);

namespace app\model;

use think\Model;

/**
 * 角色
 *
 * 树形结构：pid 指向父级角色，children 字段嵌套子角色。
 */
class Role extends Model
{

    /**
     * 获取角色树
     *
     * @param string|null $keyword 按名称过滤：命中节点保留全部子孙，未命中的节点仅在
     *                             其子孙命中时作为祖先链保留
     * @return array 树形数组，每个节点带 children
     */
    public static function tree(?string $keyword = null): array
    {
        $rows = self::where("is_delete", 0)
            ->order("pid asc, id asc")
            ->select()
            ->toArray();
        $tree = self::buildTree($rows);
        if ($keyword !== null && $keyword !== "") {
            $tree = self::filterTree($tree, $keyword);
        }
        return $tree;
    }

    /**
     * 平铺行构建树
     *
     * pid 指向的父节点存在则挂到其 children 下；
     * pid 为 0、指向自身或父节点不存在（含父级已被软删）时作为根节点。
     */
    protected static function buildTree(array $rows): array
    {
        $items = [];
        foreach ($rows as $row) {
            $row["children"] = [];
            $items[(int) $row["id"]] = $row;
        }
        $tree = [];
        foreach ($items as $id => &$node) {
            $pid = (int) $node["pid"];
            if ($pid > 0 && $pid !== $id && isset($items[$pid])) {
                $items[$pid]["children"][] = &$node;
            } else {
                $tree[] = &$node;
            }
        }
        return $tree;
    }

    /**
     * 树形过滤
     *
     * 名称命中关键字的节点保留（含全部子孙）；
     * 未命中的节点，若子孙中有命中则作为祖先链保留。
     */
    protected static function filterTree(array $nodes, string $keyword): array
    {
        $result = [];
        foreach ($nodes as $node) {
            $selfMatch = stripos((string) $node["name"], $keyword) !== false;
            $children  = self::filterTree($node["children"] ?? [], $keyword);
            if ($selfMatch) {
                $result[] = $node; // 命中：原样保留整个子树
            } elseif ($children !== []) {
                $node["children"] = $children;
                $result[] = $node; // 未命中但子孙命中：保留祖先链
            }
        }
        return $result;
    }
}
