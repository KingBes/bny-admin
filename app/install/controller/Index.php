<?php

declare(strict_types=1);

namespace app\install\controller;

use app\BaseController;
use Kingbes\Annotation\Annotation;
use think\facade\Config;
use think\facade\Db;
use think\Response;

/**
 * Web 安装向导控制器（公共页面，无需登录）
 *
 */
#[Annotation(['title' => '安装系统', 'menu' => false])]
class Index extends BaseController
{
    // sql数据
    protected array $sql_data = [];

    #[Annotation(["title" => "安装向导"])]
    public function index(): string
    {
        return $this->fetch();
    }

    #[Annotation(["title" => "环境检查"])]
    public function checkEnv(): string
    {
        $ok = true;
        $items = [];

        $phpOk = PHP_VERSION_ID >= 80000;
        $items[] = [
            'name' => 'PHP 版本',
            'ok' => $phpOk,
            'msg' => 'PHP ' . PHP_VERSION . ($phpOk ? '（>= 8.0 满足）' : '（需 >= 8.0）'),
        ];
        $ok = $ok && $phpOk;

        foreach (['mbstring', 'pdo', 'pdo_mysql', 'openssl', 'curl', 'gd'] as $ext) {
            $loaded = extension_loaded($ext);
            $items[] = [
                'name' => '扩展 ' . $ext,
                'ok' => $loaded,
                'msg' => $loaded ? '已加载' : '未加载',
            ];
            $ok = $ok && $loaded;
        }

        $rw = is_writable(app()->getRuntimePath());
        $items[] = [
            'name' => 'run 目录可写',
            'ok' => $rw,
            'msg' => $rw ? '可写' : '不可写',
        ];
        $this->assign('envList', ['ok' => $ok, 'items' => $items]);
        return $this->fetch();
    }

    #[Annotation(["title" => "注册数据库"])]
    public function registerSql(): string|Response
    {
        if (request()->isPost()) {
            // 收集数据库配置
            $this->sql_data = request()->param();

            try {
                // 校验并尝试连接数据库
                $this->validateCfg($this->sql_data);
                $this->connectCfg($this->sql_data);
            } catch (\Throwable $e) {
                // 校验/连接失败：回显错误并停留在当前步骤（保留已填写的表单）
                return $this->fetch('register_sql', [
                    'error' => $e->getMessage(),
                    'form'  => $this->sql_data,
                ]);
            }

            // 保存数据库配置到会话，供下一步使用
            session('install_db', $this->sql_data);

            // 成功：返回步骤3（注册管理员）片段，替换当前 #step-2
            return $this->fetch('register_admin', ['form' => [], 'error' => '']);
        }
        // GET：渲染数据库配置表单（form/error 为空，让模板 default 生效）
        return $this->fetch('register_sql', ['form' => [], 'error' => '']);
    }

    #[Annotation(["title" => "注册管理员"])]
    public function registerAdmin(): string|Response
    {
        if (request()->isPost()) {
            // 从上一步会话中取出数据库配置
            $cfg = session('install_db');
            if (!$cfg || !is_array($cfg)) {
                return $this->fetch('register_admin', [
                    'error' => '请先完成上一步数据库配置',
                    'form'  => request()->param(),
                ]);
            }

            // 获取管理员表单数据
            $admin = request()->param();

            try {
                // 写 .env → 建表 → 创建管理员账号 → 创建基础配置信息，顺序执行
                $this->writeEnv($cfg);
                $this->installTables($cfg);
                $this->createAdmin($cfg, $admin);
                $this->createConfig($cfg);
                $this->createMenu($cfg);
            } catch (\Throwable $e) {
                // 任一环节失败：回显错误并停留在当前步骤（保留已填写的表单）
                return $this->fetch('register_admin', [
                    'error' => $e->getMessage(),
                    'form'  => $admin,
                ]);
            }

            // 安装成功，清除临时配置
            session('install_db', null);

            // 成功：返回步骤4（完成）片段，替换当前 #step-3
            return $this->fetch('complete');
        }
        // GET：渲染注册管理员表单（form/error 为空，让模板 default 生效）
        return $this->fetch('register_admin', ['form' => [], 'error' => '']);
    }

    /**
     * 安装锁文件路径（位于项目根目录）
     * @return string
     */
    private function lockPath(): string
    {
        return app()->getRootPath() . '.install.lock';
    }

    /**
     * 校验数据库连接配置
     * @param array $cfg 配置项
     * @return void
     * @throws \Exception
     */
    private function validateCfg(array $cfg): void
    {
        $dbname = trim((string)($cfg['database'] ?? ''));
        $prefix = trim((string)($cfg['prefix'] ?? ''));

        // 数据库名必填，且只能为字母/数字/下划线，长度 1-64
        if ($dbname === '') {
            throw new \Exception('请填写数据库名');
        }
        if (!preg_match('/^[A-Za-z0-9_]{1,64}$/', $dbname)) {
            throw new \Exception('数据库名只能包含字母、数字、下划线，长度 1-64 位');
        }

        // 表前缀可选，给定则必须为小写字母开头，且仅含小写字母/数字/下划线，长度 0-20
        if ($prefix === '') {
            $prefix = 'bny_';
        }
        if (!preg_match('/^[a-z][a-z0-9_]{0,19}$/', $prefix)) {
            throw new \Exception('表前缀须以小写字母开头，且仅含小写字母、数字、下划线，最长 20 位');
        }

        // 关键连接参数缺失检查
        if (empty($cfg['hostname'])) {
            throw new \Exception('请填写数据库主机地址');
        }
        if (!isset($cfg['username']) || $cfg['username'] === '') {
            throw new \Exception('请填写数据库用户名');
        }

        $cfg['prefix'] = $prefix;
        $this->sql_data = $cfg;
    }

    /**
     * 尝试连接数据库；库不存在则自动创建
     * @param array $cfg 配置项
     * @return void
     * @throws \Exception
     */
    private function connectCfg(array $cfg): void
    {
        $host   = $cfg['hostname'];
        $port   = $cfg['port'] ?? '3306';
        $dbname = $cfg['database'];
        $user   = $cfg['username'];
        $pass   = (string)($cfg['password'] ?? '');
        $prefix = $cfg['prefix'];

        // 先不选库，连接 MySQL 服务器
        try {
            new \PDO("mysql:host={$host};port={$port}", $user, $pass, [
                \PDO::ATTR_TIMEOUT    => 5,
                \PDO::ATTR_ERRMODE    => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_PERSISTENT => false,
            ]);
        } catch (\PDOException $e) {
            throw new \Exception('连接数据库服务器失败：' . $this->dbFriendly($e->getMessage()));
        }

        // 库不存在则自动创建
        try {
            $pdo = new \PDO("mysql:host={$host};port={$port}", $user, $pass, [
                \PDO::ATTR_TIMEOUT => 5,
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            ]);
            $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$dbname}` DEFAULT CHARSET utf8mb4 COLLATE utf8mb4_unicode_ci");
        } catch (\PDOException $e) {
            throw new \Exception('创建数据库失败：' . $this->dbFriendly($e->getMessage()));
        }

        // 使用完整连接配置验证目标库连通性
        $config = [
            'type'     => 'mysql',
            'hostname' => $host,
            'database' => $dbname,
            'username' => $user,
            'password' => $pass,
            'hostport' => $port,
            'charset'  => 'utf8mb4',
            'prefix'   => $prefix,
        ];

        try {
            Db::connect($config)->query('SELECT 1');
        } catch (\Throwable $e) {
            throw new \Exception('连接目标数据库失败：' . $e->getMessage());
        }
    }

    /**
     * 将友好的数据库错误信息转换为可读中文提示
     * @param string $message 原始错误信息
     * @return string
     */
    private function dbFriendly(string $message): string
    {
        $m = strtolower($message);
        if (str_contains($m, 'access denied')) {
            return '用户名或密码错误（Access denied）';
        }
        if (str_contains($m, 'unknown database')) {
            return '数据库不存在且无法自动创建';
        }
        if (str_contains($m, 'connection refused')) {
            return '无法连接数据库服务器（连接被拒绝，请检查主机与端口）';
        }
        if (str_contains($m, 'no connection could be made')) {
            return '无法连接数据库服务器，请检查主机与端口';
        }
        if (str_contains($m, 'could not find driver')) {
            return '缺少 pdo_mysql 扩展';
        }
        if (str_contains($m, 'you have an error in your sql syntax')) {
            return 'SQL 语法错误';
        }
        return $message;
    }

    /**
     * 将数据库配置写入项目根目录 .env，并注入当前进程使后续 Db 生效
     * @param array $cfg 配置项
     * @return void
     */
    private function writeEnv(array $cfg): void
    {
        $file   = app()->getRootPath() . '.env';
        $prefix = $cfg['prefix'];

        // 待写入的顶层 DB_* 键
        $new = [
            'DB_TYPE'    => 'mysql',
            'DB_HOST'    => $cfg['hostname'],
            'DB_NAME'    => $cfg['database'],
            'DB_USER'    => $cfg['username'],
            'DB_PASS'    => (string)($cfg['password'] ?? ''),
            'DB_PORT'    => $cfg['port'] ?? '3306',
            'DB_CHARSET' => 'utf8mb4',
            'DB_PREFIX'  => $prefix,
            'DEFAULT_LANG' => 'zh-cn',
            'APP_DEBUG'    => 'false',
        ];

        // 读取现有内容（若已存在则逐行合并，仅替换/追加顶层 DB_* 键，保留其它行与 [SECTION]）
        $lines = is_file($file) ? preg_split('/(\r\n|\n|\r)/', (string)file_get_contents($file)) : [];
        $keep  = [];
        if ($lines) {
            foreach ($lines as $line) {
                $trim = trim($line);
                if ($trim !== '' && !str_starts_with($trim, '#') && !str_starts_with($trim, '[') && str_contains($line, '=')) {
                    $key = trim(explode('=', $line, 2)[0]);
                    if (isset($new[$key])) {
                        // 已存在的顶层 DB_* 键：用新值覆盖该行
                        $keep[] = $key . ' = ' . $new[$key];
                        unset($new[$key]);
                        continue;
                    }
                }
                // 其它行（空行/注释/节/未变更键）原样保留
                $keep[] = $line;
            }
        }
        // 追加尚未出现的新键
        foreach ($new as $k => $v) {
            $keep[] = $k . ' = ' . $v;
        }

        // 写入 .env
        file_put_contents($file, implode(PHP_EOL, $keep) . PHP_EOL);

        // 把连接配置注入当前进程，使后续 Db 操作直接生效
        $dbConfig = [
            'default'          => 'mysql',
            'time_query_rule'  => [],
            'auto_timestamp'   => true,
            'datetime_format'  => 'Y-m-d H:i:s',
            'datetime_field'   => '',
            'connections'      => [
                'mysql' => [
                    'type'            => 'mysql',
                    'hostname'        => $cfg['hostname'],
                    'database'        => $cfg['database'],
                    'username'        => $cfg['username'],
                    'password'        => (string)($cfg['password'] ?? ''),
                    'hostport'        => $cfg['port'] ?? '3306',
                    'params'          => [],
                    'charset'         => 'utf8mb4',
                    'prefix'          => $prefix,
                    'deploy'          => 0,
                    'rw_separate'     => false,
                    'master_num'      => 1,
                    'slave_no'        => '',
                    'fields_strict'   => true,
                    'break_reconnect' => false,
                    'fields_cache'    => false,
                ],
            ],
        ];
        Config::set($dbConfig, 'database');
        Db::setConfig($dbConfig);
    }

    /**
     * 打开目标库的原生 PDO 连接（用于执行建表等 DDL，避免依赖 think-orm 连接）
     * @param array $cfg 数据库配置
     * @return \PDO
     */
    private function pdo(array $cfg): \PDO
    {
        return new \PDO(
            sprintf(
                'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
                $cfg['hostname'],
                $cfg['port'] ?? '3306',
                $cfg['database']
            ),
            $cfg['username'],
            (string) ($cfg['password'] ?? ''),
            [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]
        );
    }

    /**
     * 读取并执行安装用 SQL，创建业务数据表（幂等，可重复执行）
     * @param array $cfg 数据库配置（含 prefix）
     * @return void
     * @throws \Exception
     */
    private function installTables(array $cfg): void
    {
        $sqlFile = app()->getRootPath() . 'app' . DIRECTORY_SEPARATOR . 'install' . DIRECTORY_SEPARATOR . 'database.sql';
        $sql     = @file_get_contents($sqlFile);
        if ($sql === false) {
            throw new \Exception('未找到数据库初始化文件 database.sql');
        }

        // 仅替换反引号内的表前缀标识符 `bny_` → `{prefix}`，避免误改注释等文本
        $sql = str_replace('`bny_', '`' . $cfg['prefix'], $sql);

        // 移除注释内容：块注释 /* ... */ 与行注释 -- 开头
        $sql = preg_replace('#/\*.*?\*/#s', '', $sql);
        $sql = preg_replace('/^\s*--.*$/m', '', $sql);

        // 按分号拆分，仅执行建表/删表语句（跳过 SET NAMES 等会话级语句）
        $statements = array_filter(array_map('trim', explode(';', $sql)));
        $pdo        = $this->pdo($cfg);
        foreach ($statements as $stmt) {
            if ($stmt === '') {
                continue;
            }
            $head = strtoupper(ltrim($stmt));
            if (!str_starts_with($head, 'CREATE ') && !str_starts_with($head, 'DROP ')) {
                continue;
            }
            try {
                $pdo->exec($stmt);
            } catch (\Throwable $e) {
                throw new \Exception('执行数据库脚本出错：' . $e->getMessage() . '，脚本：' . substr($stmt, 0, 80));
            }
        }
    }

    /**
     * 创建超级管理员账号与站点配置，并在最后写入安装锁
     * @param array $cfg   数据库配置（含 prefix）
     * @param array $admin 管理员表单数据（username/password/confirm_password/site_name）
     * @return void
     * @throws \Exception
     */
    private function createAdmin(array $cfg, array $admin): void
    {
        $username = trim((string)($admin['username'] ?? ''));
        $password = (string)($admin['password'] ?? '');
        $confirm  = (string)($admin['confirm_password'] ?? '');
        // 昵称未填写时回退为用户名
        $nickname = trim((string)($admin['nickname'] ?? ''));

        // 校验管理员信息
        if (!preg_match('/^[^\s]{2,20}$/', $username)) {
            throw new \Exception('管理员用户名长度为 2-20 位，且不能包含空格');
        }
        if ($nickname === '') {
            $nickname = $username;
        }
        if (mb_strlen($nickname) > 50) {
            throw new \Exception('管理员昵称长度不能超过 50 位');
        }
        if (strlen($password) < 6) {
            throw new \Exception('管理员密码长度不能少于 6 位');
        }
        if ($confirm !== $password) {
            throw new \Exception('两次输入的密码不一致');
        }

        $prefix = $cfg['prefix'];
        $time   = time();
        $hash   = password_hash($password, PASSWORD_DEFAULT);
        $pdo    = $this->pdo($cfg);

        // 覆盖式写入管理员：已存在则更新密码/昵称/状态（重复安装不报错），否则插入
        $sel = $pdo->prepare("SELECT `id` FROM `{$prefix}admin` WHERE `username` = ? LIMIT 1");
        $sel->execute([$username]);
        $row = $sel->fetch();
        if ($row) {
            $pdo->prepare("UPDATE `{$prefix}admin` SET `password` = ?, `nickname` = ?, `status` = 1, `update_time` = ? WHERE `id` = ?")
                ->execute([$hash, $nickname, $time, $row['id']]);
        } else {
            $stmt = $pdo->prepare(
                "INSERT INTO `{$prefix}admin` (`username`,`password`,`nickname`,`rid`,`status`,`create_time`,`update_time`) VALUES (?,?,?,?,?,?,?)"
            );
            $stmt->execute([$username, $hash, $nickname, 0, 1, $time, $time]);
        }

        // 最后一步：写入安装锁
        @file_put_contents($this->lockPath(), date('Y-m-d H:i:s'));
    }

    /**
     * 创建基础配置信息
     *
     * @return void
     */
    private function createConfig(array $cfg): void
    {
        $pdo = $this->pdo($cfg);
        $data = [
            ["key" => "upload_size", "value" => "20"],
            ["key" => "upload_ext", "value" => "jpg,jpeg,png,gif,mp4"],
        ];
        foreach ($data as $item) {
            $pdo->prepare("INSERT INTO `{$cfg['prefix']}config` (`key`,`value`,`group`,`create_time`,`update_time`) VALUES (?,?,?,?,?)")
                ->execute([$item['key'], $item['value'], 'base', time(), time()]);
        }
    }

    /**
     * 创建菜单
     *
     * @return void
     */
    private function createMenu(array $cfg): void
    {
        $pdo = $this->pdo($cfg);
        $data = [
            ["id" => 1, "pid" => 0, "name" => "系统设置", "icon" => "icon-setting", "route" => "0", "note" => "系统设置"],
            ["id" => 2, "pid" => 1, "name" => "基础设置", "icon" => "", "route" => "admin.system.base", "note" => "基础设置"],
            ["id" => 3, "pid" => 1, "name" => "附件管理", "icon" => "", "route" => "admin.attachment.view", "note" => "附件管理"],
            ["id" => 4, "pid" => 1, "name" => "菜单管理", "icon" => "", "route" => "admin.menu.view", "note" => "菜单管理"],
            ["id" => 5, "pid" => 0, "name" => "系统管理", "icon" => "icon-crown", "route" => "0", "note" => "系统管理"],
            ["id" => 6, "pid" => 5, "name" => "管理员", "icon" => "", "route" => "admin.admin.view", "note" => "管理员"],
            ["id" => 7, "pid" => 5, "name" => "角色管理", "icon" => "", "route" => "admin.role.view", "note" => "角色管理"],
            ["id" => 8, "pid" => 5, "name" => "操作日记", "icon" => "", "route" => "admin.log.view", "note" => "操作日记"],
            ["id" => 9, "pid" => 0, "name" => "回收站", "icon" => "icon-delete", "route" => "admin.recycle.view", "note" => "回收站"],
        ];
        foreach ($data as $item) {
            $pdo->prepare("INSERT INTO `{$cfg['prefix']}menu` (`id`,`pid`,`name`,`icon`,`route`,`note`) VALUES (?,?,?,?,?,?)")
                ->execute([$item['id'], $item['pid'], $item['name'], $item['icon'], $item['route'], $item['note']]);
        }
    }
}
