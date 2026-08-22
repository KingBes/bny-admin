# Tasks

- [x] Task 1: 在 `Index` 控制器内实现安装工具辅助方法（不新增服务类）
  - [x] 1.1 `lockPath()` / `isInstalled()`：项目根 `.install.lock`，以锁文件是否存在判断
  - [x] 1.2 `validateCfg(array)`：校验库名 `^[A-Za-z0-9_]{1,64}$`、前缀 `^[a-z][a-z0-9_]{0,19}$`，缺参抛异常
  - [x] 1.3 `connectCfg(array)`：原生 PDO 不带库名连 MySQL，库不存在自动 `CREATE DATABASE IF NOT EXISTS ... utf8mb4`，再连目标库 `SELECT 1`；失败抛友好中文异常
  - [x] 1.4 `writeEnv(array)`：项目根生成/合并 `.env`，写 `DB_DRIVER/DB_TYPE/DB_HOST/DB_NAME/DB_USER/DB_PASS/DB_PORT/DB_CHARSET/DB_PREFIX`，保留其它行；写后把配置注入当前进程 database 配置使 `Db` 生效
  - [x] 1.5 `installTables(string $prefix)`：读 `database.sql`，替换反引号内 `bny_` 为 `$prefix`，分割逐条执行
  - [x] 1.6 `createAdmin(array $cfg, array $admin)`：校验用户名/密码，`password_hash` 插 `{prefix}admin`，登记站点名到 config，成功后写 `.install.lock`（顺序不可颠倒）
  - [x] 1.7 幂等/边界：重复执行不重复建库、不重复插入

- [x] Task 2: 实现 `Index::registerSql` POST：校验 + 验证连接 + 持久化配置
  - [x] 2.1 POST 收集 `request()->param()`，调 `validateCfg + connectCfg`
  - [x] 2.2 成功：`session('install_db', $cfg)` 并进入步骤 3
  - [x] 2.3 失败：回显中文错误，停留在当前步骤
  - [x] 2.4 GET：保持现状渲染步骤 2 表单

- [x] Task 3: 实现 `Index::registerAdmin` POST：完整安装流程
  - [x] 3.1 读取并校验管理员表单（用户名/密码/确认/站点名）
  - [x] 3.2 从 `session('install_db')` 取库配置，缺失则提示"请先完成上一步"
  - [x] 3.3 依次调用 `writeEnv → installTables → createAdmin`（其内部含写锁）
  - [x] 3.4 成功清空 install 相关 session，进入步骤 4
  - [x] 3.5 失败：回显中文错误

- [x] Task 4: 校验与回归
  - [x] 4.1 步骤2填错主机/账号时界面报错且不写任何文件
  - [x] 4.2 库不存在时自动建库成功
  - [x] 4.3 安装完成后 `.env` 存在且 `DB_*` 键正确
  - [x] 4.4 `{prefix}admin` 等 5 张表已建、前缀正确
  - [x] 4.5 超级管理员已插入、密码为哈希、站点名已登记
  - [x] 4.6 根目录 `.install.lock` 存在，`isInstalled()` 为真
  - [x] 4.7 二次提交（如重复走流程）不产生重复表/重复账号

# Task Dependencies
- [Task 2] depends on [Task 1]
- [Task 3] depends on [Task 1]
- [Task 4] depends on [Task 2], [Task 3]