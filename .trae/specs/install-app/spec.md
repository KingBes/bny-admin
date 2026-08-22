# 安装应用完成 Spec

## Why
安装向导目前只有环境检查、表单页面骨架，数据库配置/建表、超级管理员注册、写 `.env` 与 `.install.lock`
等核心逻辑尚未实现（`Installer.php` 已被移除，`registerSql/registerAdmin` 的 POST 分支为空）。
本 spec 补齐安装应用从"验证数据库"到"完成安装"的完整能力。

## What Changes
- 全部安装逻辑落在控制器 `app\install\controller\Index` 内（私有辅助方法或内联实现，**不新增服务类**），承载：库名/前缀校验、连接与自动建库、执行建表 SQL、写 `.env`、写安装锁。
- 实现 `Index::registerSql` 的 POST 分支：校验 + 验证数据库连接（库不存在自动建库 utf8mb4），成功后把数据库配置持久化到 `session('install_db')` 并进入步骤 3；失败回显错误。
  - **BREAKING** `registerSql` 的 POST 不再仅是无脑 `redirect`，改由上面逻辑驱动。
- 实现 `Index::registerAdmin` 的 POST 分支：读管理员表单 + 读取 `session('install_db')`，依次执行【写 `.env` → 安装数据库（执行 `database.sql`）→ 插入超级管理员 → 写 `.install.lock`】。成功进入步骤 4。
- 写 `.env`：项目根生成/合并 `DB_DRIVER/DB_TYPE/DB_HOST/DB_NAME/DB_USER/DB_PASS/DB_PORT/DB_CHARSET/DB_PREFIX`，保留已有非 DB 行，写后进程内应用配置使 `Db` 立即对接目标库。
- 建表：读取 `app/install/database.sql`，把建表语句里的表前缀（反引号内的 `bny_`）替换为表单提交的 `prefix`，分割并逐条执行。SQL 本身用 `DROP TABLE IF EXISTS + CREATE TABLE`，天然可重复执行。
- 安装锁：`app()->getRootPath() . '.install.lock'`，**在超级管理员注册成功之后**才写入（避免未完成注册即锁定安装流程）。

## Impact
- Affected code: `app/install/controller/Index.php`、`app/install/database.sql`（复用现有）、项目根 `.env`（生成）、`.install.lock`（生成）。
- Affected specs: 安装应用（环境检查已实现，不在本次范围）。
- 不涉及：admin 应用、Auth 中间件、后台各业务模块。已安装状态下 `/install` 跳转登录由 Auth 中间件负责，不在本 spec 范围。

## ADDED Requirements

### Requirement: 数据库配置与验证（registerSql POST）
系统 SHALL 接收并校验数据库表单，校验数据库连接，并将库配置持久化供后续步骤使用。

#### Scenario: 成功接入数据库
- **WHEN** 用户提交 `hostname/port/database/username/password/prefix`
- **THEN** 系统校验必填项、库名（`^[A-Za-z0-9_]{1,64}$`）、前缀（`^[a-z][a-z0-9_]{0,19}$`）；连接 MySQL 服务器，库不存在时自动 `CREATE DATABASE IF NOT EXISTS ... utf8mb4`；连接目标库 `SELECT 1` 通过；配置存入 `session('install_db')`；进入步骤 3。

#### Scenario: 数据库接入失败
- **WHEN** 必填缺失、格式非法、账号/权限错误、主机不可达或自动建库失败
- **THEN** 系统不写任何数据，向界面回显清晰的中文错误信息，停留在当前步骤，可修改重试。

### Requirement: 写入 .env
系统 SHALL 在项目根生成或合并 `.env`，写入数据库连接项，并使其立即生效。

#### Scenario: 首次安装
- **WHEN** 项目根无 `.env`
- **THEN** 生成 `.env`，写入 `DB_DRIVER/DB_TYPE/DB_HOST/DB_NAME/DB_USER/DB_PASS/DB_PORT/DB_CHARSET/DB_PREFIX`，随后将配置注入当前进程的 database 配置，使 `Db::` 指向目标库。

#### Scenario: 存在旧 .env
- **WHEN** 项目根已有 `.env`（含其它配置段）
- **THEN** 仅替换/追加顶层 `DB_*` 键，保留其它行与 `[SECTION]`，不破坏既有配置。

### Requirement: 安装数据库
系统 SHALL 按用户前缀执行 `app/install/database.sql` 完成建表，且可多次执行不报错。

#### Scenario: 正常安装
- **WHEN** `.env` 生效后调用建表逻辑
- **THEN** 读 SQL 文件，把反引号内的 `bny_` 前缀替换为实际 `prefix`，分割逐条执行；`bny_admin` 等表全部创建成功。

### Requirement: 注册超级管理员
系统 SHALL 校验并登记超级管理员账号。

#### Scenario: 注册成功
- **WHEN** 用户提交 `username/password/confirm_password/site_name` 且校验通过（用户名 `^[A-Za-z0-9_-]{3,32}$`、密码 ≥6 位、两次密码一致）
- **THEN** 密码经 `password_hash` 密文存储，插入 `{prefix}admin`（含 `rid`、`status`、`nickname`、时间戳），并登记站点名到 `{prefix}config`。

#### Scenario: 账号冲突
- **WHEN** username 已存在（唯一键冲突）
- **THEN** 回显"用户名已存在"类错误，不产生重复数据。

### Requirement: 写入安装锁
系统 SHALL 在超级管理员注册成功后于项目根写入 `.install.lock`。

#### Scenario: 安装完成
- **WHEN** 超管插入成功
- **THEN** 在 `app()->getRootPath() . '.install.lock'` 写入时间戳，完成安装；此后 `isInstalled()` 返回真。

## MODIFIED Requirements

### Requirement: 安装向导步骤导航
现有步骤 1(环境检查)→2(数据库)→3(注册管理员)→4(完成) 的 htmx 片段流程保持不变；仅补齐步骤 2、3 的后端处理逻辑。