# Bny Admin 基础后台管理

> Bny Admin 是一个基于 ThinkPHP 8 + Workerman 的后台管理系统，用于快速搭建后台管理系统。
> 注解路由（kingbes/annotation）+ 常驻内存（kingbes/tp-worker）+ 声明式前端（bunny.ui / HTMX）。

- [Thinkphp8](https://doc.thinkphp.cn/v8_0/preface.html)   

- [Workerman](https://www.workerman.net/)

- [Bunny-ui](https://github.com/KingBes/bunny.ui)

![](https://github.com/KingBes/tp-worker/blob/master/001.png)

## 环境要求

- PHP >= 8.0
- MySQL 5.7+
- Composer

## 安装

```sh
git clone https://github.com/KingBes/bny-admin
cd bny-admin
composer install
```

启动服务后访问 `/install` 进入安装向导：

1. **环境检查** — 检测 PHP 版本与依赖扩展
2. **注册数据库** — 填写 MySQL 连接信息，自动建表
3. **注册管理员** — 创建超级管理员账号

> 未安装时访问任意页面会自动跳转 `/install`；安装完成后 `/install` 自动锁定，防止重复安装覆盖管理员密码。
> 删除 `.install.lock` 文件可以重新安装。

## workerman 启动/控制

```sh
php think worker                # 前台启动（默认 action 为 start）
php think worker start -d       # 守护进程启动
php think worker stop           # 停止（-g 优雅停止，等待请求处理完成）
php think worker restart -d     # 重启
php think worker reload         # 平滑重启（重载业务代码，不断开连接；-g 优雅模式）
php think worker status         # 查看进程状态（-d 实时刷新）
php think worker connections    # 查看当前连接
```

插件详情 [tp-worker](https://github.com/KingBes/tp-worker)

## 功能特性

- **管理员管理** — 账号增删改、个人中心
- **角色权限** — 角色树（pid → children 嵌套），按菜单节点分配权限
- **菜单管理** — 后台菜单配置，权限节点随注解自动生成
- **附件管理** — 上传、列表、选择器弹层（图片压缩上传 + 附件选择回填）
- **回收站** — 软删除数据统一回收：恢复 / 彻底删除 / 清空
- **操作日志** — 中间件自动记录后台写操作，敏感参数脱敏
- **系统设置** — 站点 logo/名称/关键词/描述、上传大小与类型等基础配置
- **安装向导** — 环境检查、建库建表、注册管理员，安装后自动锁定

## 目录结构

```
app/
├── admin/            # 后台应用（控制器 / 视图）
├── install/          # 安装向导应用（含 database.sql）
├── index/            # 前台应用
├── middleware/       # CheckInstall / Admin / AdminLog
├── model/            # 模型（bny_ 前缀数据表）
└── common.php        # 公共函数（select_tree 等）
public/
├── bunny/            # bunny.ui 静态资源（bunny.js / bunny.css）
├── admin/            # 后台自有 js / css
└── index.php         # 入口
```

## 技术栈

| 层 | 说明 |
|---|---|
| 后端 | ThinkPHP 8（多应用：index / admin / install） |
| 常驻服务 | [kingbes/tp-worker](https://github.com/KingBes/tp-worker)（Workerman 5） |
| 路由 | [kingbes/annotation](https://github.com/KingBes/annotation) — 控制器方法上 `#[Annotation([...])]` 声明权限节点，自动生成路由 |
| 前端 | [bunny.ui](https://github.com/KingBes/bunny.ui) — 基于 HTMX 的声明式 UI 库（bny-table / bny-form / bny-upload 等，`hx-ext` 扩展驱动） |
