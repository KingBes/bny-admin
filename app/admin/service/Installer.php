<?php
declare(strict_types=1);

namespace app\admin\service;

use think\facade\Db;
use Throwable;

/**
 * Web 安装向导服务（静态工具类）.
 *
 * 负责环境检查、数据库连接测试、.env 写入、建表与种子数据、安装锁定。
 * 完整支持安装向导四步：环境检查 → 数据库配置（连接+建表+种子）→ 注册管理员 → 完成。
 */
class Installer
{
    /**
     * 安装锁文件路径（固定为 项目根/.install.lock）.
     *
     * 不放 runtime/——runtime 是缓存清理的常规目标，锁文件会被误删导致
     * 已装系统被重新拉起安装向导；根目录隐藏文件不受清理影响，且 public/
     * 为 web 根，该文件无法被 HTTP 访问。
     */
    public static function lockPath(): string
    {
        return app()->getRootPath() . '.install.lock';
    }

    /**
     * 系统是否已完成安装（以锁文件存在为准）.
     */
    public static function isInstalled(): bool
    {
        return is_file(self::lockPath());
    }

    /**
     * 环境检查：PHP 版本、必需扩展、runtime 目录可写.
     */
    public static function checkEnv(): array
    {
        $items = [];

        $items[] = [
            'name' => 'PHP 版本',
            'ok' => PHP_VERSION_ID >= 80000,
            'msg' => 'PHP ' . PHP_VERSION . (PHP_VERSION_ID >= 80000 ? '（>= 8.0 满足）' : '（需 >= 8.0）'),
        ];

        foreach (['mbstring', 'pdo', 'pdo_mysql', 'openssl', 'curl', 'json'] as $ext) {
            $items[] = [
                'name' => '扩展 ' . $ext,
                'ok' => extension_loaded($ext),
                'msg' => extension_loaded($ext) ? '已加载' : '未加载',
            ];
        }

        $rw = is_writable(app()->getRuntimePath());
        $items[] = [
            'name' => 'run 目录可写',
            'ok' => $rw,
            'msg' => $rw ? '可写' : '不可写',
        ];

        return $items;
    }

    /**
     * 使用传入配置测试数据库连接（库不存在时自动创建）.
     *
     * 目标数据库不存在时自动创建（utf8mb4）：先连 MySQL 服务器（不选库）
     * 执行 CREATE DATABASE IF NOT EXISTS，再连目标库验证连接可用。
     */
    public static function connect(array $cfg): void
    {
        $database = (string) ($cfg['database'] ?? '');
        if ($database === '') {
            throw new \Exception('请填写数据库名');
        }

        // 1. 原生 PDO 不选库连服务器，尝试自动建库（think-orm 连接必填库名，故绕开）
        if (preg_match('/^[A-Za-z0-9_]{1,64}$/', $database) !== 1) {
            throw new \Exception('数据库名须为 1-64 位字母、数字或下划线');
        }
        try {
            $dsn = sprintf(
                'mysql:host=%s;port=%s',
                (string) ($cfg['hostname'] ?? '127.0.0.1'),
                (string) ($cfg['port'] ?? '3306')
            );
            $pdo = new \PDO($dsn, (string) ($cfg['username'] ?? 'root'), (string) ($cfg['password'] ?? ''), [
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_TIMEOUT => 5,
            ]);
            $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$database}` DEFAULT CHARSET utf8mb4 COLLATE utf8mb4_unicode_ci");
        } catch (Throwable $e) {
            throw new \Exception('自动创建数据库失败（当前账号可能无建库权限）：' . self::friendlyError($e, $database));
        }

        // 2. 连目标库验证连接（建表前置）
        try {
            $connection = Db::connect(self::connectionConfig($cfg));
            $connection->query('SELECT 1');
        } catch (Throwable $e) {
            throw new \Exception(self::friendlyError($e, $database));
        }
    }

    /**
     * 将数据库配置合并写入项目根 .env（保留其它行，只替换/追加顶层 DB_* 键）.
     */
    public static function writeEnv(array $cfg): void
    {
        $map = [
            'DB_TYPE'    => 'mysql',
            'DB_HOST'    => (string) ($cfg['hostname'] ?? '127.0.0.1'),
            'DB_NAME'    => (string) ($cfg['database'] ?? ''),
            'DB_USER'    => (string) ($cfg['username'] ?? 'root'),
            'DB_PASS'    => (string) ($cfg['password'] ?? ''),
            'DB_PORT'    => (string) ($cfg['port'] ?? '3306'),
            'DB_CHARSET' => 'utf8mb4',
            'DB_PREFIX'  => (string) ($cfg['prefix'] ?? 'bny_'),
        ];

        $envFile = app()->getRootPath() . '.env';

        if (is_file($envFile)) {
            $content = self::mergeEnvFile($envFile, $map);
        } else {
            $lines = [];
            foreach ($map as $key => $value) {
                $lines[] = self::envLine($key, $value);
            }
            $lines[] = '';
            $lines[] = '[APP]';
            $lines[] = 'DEFAULT_TIMEZONE = Asia/Shanghai';
            $content = implode("\n", $lines) . "\n";
        }

        if (file_put_contents($envFile, $content) === false) {
            throw new \Exception('无法写入 .env 文件，请检查项目根目录权限');
        }

        // 使配置立即生效
        self::applyConfig($cfg);
    }

    /**
     * 第一步：数据库初始化（连接测试 + 写 .env + 建表 + 种子基础数据）.
     *
     * 幂等：建表用 CREATE TABLE IF NOT EXISTS，种子先查后插重复调用不报错不重复。
     */
    public static function setupDatabase(array $cfg): void
    {
        self::connect($cfg);
        self::writeEnv($cfg);
        self::applyConfig($cfg);

        self::createTables($cfg);
        self::seedBaseData($cfg);
    }

    /**
     * 第二步：注册超级管理员并完成安装（写账号 + 绑角色 + 安装锁）.
     */
    public static function createAdmin(array $cfg, string $username, string $password, string $siteName): void
    {
        // 校验
        if (preg_match('/^[A-Za-z0-9_-]{3,32}$/', $username) !== 1) {
            throw new \Exception('账号须为 3-32 位字母、数字或下划线');
        }
        if (strlen($password) < 6) {
            throw new \Exception('密码至少 6 位');
        }

        self::applyConfig($cfg);
        $prefix = (string) ($cfg['prefix'] ?? 'bny_');
        $now = time();

        // 确保表已建（兜底幂等）
        self::ensureTables($cfg);

        // 超管角色 id
        $roleId = Db::name('role')->where('code', 'super_administrator')->value('id');
        if ($roleId === null) {
            $roleId = Db::name('role')->insertGetId([
                'name' => '超级管理员',
                'code' => 'super_administrator',
                'remark' => '系统内置角色，拥有全部权限',
                'parent_id' => 0,
                'create_time' => $now,
                'update_time' => $now,
            ]);
        }

        // 管理员：存在则更新密码，不存在则插入
        $adminId = (int) Db::name('admin')->where('username', $username)->value('id');
        if ($adminId > 0) {
            Db::name('admin')->where('id', $adminId)->update([
                'password' => password_hash($password, PASSWORD_DEFAULT),
                'status' => 'normal',
                'update_time' => $now,
            ]);
        } else {
            $adminId = (int) Db::name('admin')->insertGetId([
                'username' => $username,
                'password' => password_hash($password, PASSWORD_DEFAULT),
                'nickname' => $username,
                'status' => 'normal',
                'create_time' => $now,
                'update_time' => $now,
            ]);
        }

        // 绑定超管角色（幂等）
        $exists = Db::name('admin_role')
            ->where('user_id', $adminId)
            ->where('role_id', $roleId)
            ->find();
        if (empty($exists)) {
            Db::name('admin_role')->insert([
                'user_id' => $adminId,
                'role_id' => (int) $roleId,
            ]);
        }

        // 站点名
        if ($siteName !== '') {
            $cfgRow = Db::name('config')->where('key', 'site_name')->find();
            if (!empty($cfgRow)) {
                Db::name('config')->where('id', $cfgRow['id'])->update([
                    'value' => $siteName,
                    'update_time' => $now,
                ]);
            } else {
                Db::name('config')->insert([
                    'key' => 'site_name',
                    'value' => $siteName,
                    'remark' => '站点名称',
                    'create_time' => $now,
                    'update_time' => $now,
                ]);
            }
        }

        // 写安装锁
        if (trim((string) ($cfg['prefix'] ?? 'bny_')) !== '') {
            // no-op
        }
        if (file_put_contents(self::lockPath(), date('Y-m-d H:i:s')) === false) {
            throw new \Exception('无法写入安装锁文件，请检查项目目录权限');
        }
    }

    /**
     * 建表（CREATE TABLE IF NOT EXISTS）.
     */
    protected static function createTables(array $cfg): void
    {
        foreach (self::tableSqlList((string) ($cfg['prefix'] ?? 'bny_')) as $sql) {
            Db::execute($sql);
        }
    }

    /**
     * 确保表已建（setupDatabase 兜底，幂等）.
     */
    protected static function ensureTables(array $cfg): void
    {
        $prefix = (string) ($cfg['prefix'] ?? 'bny_');
        $has = Db::query(
            'SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?',
            [$prefix . 'admin']
        );
        if (empty($has)) {
            self::createTables($cfg);
        }
    }

    /**
     * 种子基础数据：config 默认站点配置.
     */
    protected static function seedBaseData(array $cfg): void
    {
        $now = time();
        $defaults = [
            'site_name' => 'BnyAdmin 后台',
            'site_footer' => 'BnyAdmin 后台管理系统',
        ];
        foreach ($defaults as $key => $value) {
            $row = Db::name('config')->where('key', $key)->find();
            if (!empty($row)) {
                Db::name('config')->where('id', $row['id'])->update([
                    'value' => $value,
                    'update_time' => $now,
                ]);
            } else {
                Db::name('config')->insert([
                    'key' => $key,
                    'value' => $value,
                    'remark' => $key,
                    'create_time' => $now,
                    'update_time' => $now,
                ]);
            }
        }
    }

    /**
     * 建表语句列表（幂等）.
     *
     * @return list<string>
     */
    protected static function tableSqlList(string $prefix): array
    {
        $engine = "ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        return [
            // 管理员表
            "CREATE TABLE IF NOT EXISTS `{$prefix}admin` (
                `id` int unsigned NOT NULL AUTO_INCREMENT,
                `username` varchar(32) NOT NULL COMMENT '用户名',
                `password` varchar(255) NOT NULL COMMENT '密码哈希',
                `nickname` varchar(32) DEFAULT '' COMMENT '昵称',
                `status` enum('normal','disabled') DEFAULT 'normal' COMMENT '状态',
                `last_login` int unsigned DEFAULT 0 COMMENT '最后登录时间',
                `last_ip` varchar(46) DEFAULT '' COMMENT '最后登录IP',
                `create_time` int unsigned DEFAULT 0 COMMENT '创建时间',
                `update_time` int unsigned DEFAULT 0 COMMENT '更新时间',
                PRIMARY KEY (`id`),
                UNIQUE KEY `uk_username` (`username`)
            ) {$engine} COMMENT='管理员表'",

            // 角色表
            "CREATE TABLE IF NOT EXISTS `{$prefix}role` (
                `id` int unsigned NOT NULL AUTO_INCREMENT,
                `parent_id` int unsigned NOT NULL DEFAULT 0 COMMENT '上级角色ID',
                `name` varchar(32) NOT NULL COMMENT '角色名',
                `code` varchar(64) NOT NULL COMMENT '角色编码',
                `remark` varchar(255) DEFAULT '' COMMENT '备注',
                `create_time` int unsigned DEFAULT 0,
                `update_time` int unsigned DEFAULT 0,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uk_code` (`code`)
            ) {$engine} COMMENT='角色表'",

            // 权限节点表（权限点数据源）
            "CREATE TABLE IF NOT EXISTS `{$prefix}rule` (
                `id` int unsigned NOT NULL AUTO_INCREMENT,
                `parent_id` int unsigned DEFAULT 0 COMMENT '父节点ID',
                `title` varchar(64) NOT NULL COMMENT '节点标题',
                `name` varchar(128) DEFAULT '' COMMENT '路由标识',
                `type` enum('catalog','menu','button') DEFAULT 'button' COMMENT '节点类型',
                `icon` varchar(64) DEFAULT '' COMMENT '图标',
                `sort` int DEFAULT 100 COMMENT '排序',
                `status` enum('normal','hidden','orphan') DEFAULT 'normal' COMMENT '状态',
                `is_page` tinyint(1) DEFAULT 0 COMMENT '是否页面',
                `is_auth` tinyint(1) DEFAULT 0 COMMENT '是否受保护',
                `create_time` int unsigned DEFAULT 0,
                `update_time` int unsigned DEFAULT 0,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uk_name` (`name`)
            ) {$engine} COMMENT='权限节点表'",

            // 管理员角色关联表
            "CREATE TABLE IF NOT EXISTS `{$prefix}admin_role` (
                `user_id` int unsigned NOT NULL DEFAULT 0,
                `role_id` int unsigned NOT NULL DEFAULT 0,
                PRIMARY KEY (`user_id`,`role_id`)
            ) {$engine} COMMENT='管理员角色关联'",

            // 角色规则关联表
            "CREATE TABLE IF NOT EXISTS `{$prefix}role_rule` (
                `role_id` int unsigned NOT NULL DEFAULT 0,
                `rule_id` int unsigned NOT NULL DEFAULT 0,
                PRIMARY KEY (`role_id`,`rule_id`)
            ) {$engine} COMMENT='角色规则关联'",

            // 配置表
            "CREATE TABLE IF NOT EXISTS `{$prefix}config` (
                `id` int unsigned NOT NULL AUTO_INCREMENT,
                `key` varchar(64) NOT NULL COMMENT '配置键',
                `value` text COMMENT '配置值',
                `remark` varchar(255) DEFAULT '',
                `create_time` int unsigned DEFAULT 0,
                `update_time` int unsigned DEFAULT 0,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uk_key` (`key`)
            ) {$engine} COMMENT='配置表'",

            // 菜单表
            "CREATE TABLE IF NOT EXISTS `{$prefix}menu` (
                `id` int unsigned NOT NULL AUTO_INCREMENT,
                `parent_id` int unsigned DEFAULT 0 COMMENT '父菜单ID',
                `title` varchar(64) NOT NULL COMMENT '菜单标题',
                `icon` varchar(64) DEFAULT '' COMMENT '图标',
                `type` enum('catalog','menu','link') DEFAULT 'menu' COMMENT '类型',
                `rule_id` int unsigned DEFAULT 0 COMMENT '关联权限点ID',
                `url` varchar(255) DEFAULT '' COMMENT '链接地址',
                `sort` int DEFAULT 100 COMMENT '排序',
                `status` enum('normal','hidden') DEFAULT 'normal' COMMENT '状态',
                `is_auth` tinyint(1) DEFAULT 0,
                `create_time` int unsigned DEFAULT 0,
                `update_time` int unsigned DEFAULT 0,
                PRIMARY KEY (`id`)
            ) {$engine} COMMENT='后台菜单表'",

            // 操作日志表
            "CREATE TABLE IF NOT EXISTS `{$prefix}log` (
                `id` int unsigned NOT NULL AUTO_INCREMENT,
                `user_id` int unsigned DEFAULT 0,
                `username` varchar(32) DEFAULT '',
                `title` varchar(64) DEFAULT '',
                `url` varchar(255) DEFAULT '',
                `method` varchar(8) DEFAULT '',
                `ip` varchar(46) DEFAULT '',
                `create_time` int unsigned DEFAULT 0,
                PRIMARY KEY (`id`)
            ) {$engine} COMMENT='操作日志表'",
        ];
    }

    /**
     * 组装连接配置.
     */
    protected static function connectionConfig(array $cfg): array
    {
        return [
            'type'     => 'mysql',
            'hostname' => (string) ($cfg['hostname'] ?? '127.0.0.1'),
            'database' => (string) ($cfg['database'] ?? ''),
            'username' => (string) ($cfg['username'] ?? 'root'),
            'password' => (string) ($cfg['password'] ?? ''),
            'hostport' => (string) ($cfg['port'] ?? '3306'),
            'charset'  => 'utf8mb4',
            'prefix'   => (string) ($cfg['prefix'] ?? 'bny_'),
        ];
    }

    /**
     * 向当前进程注入数据库配置，使 Db:: 门面对接目标库立即生效.
     *
     * 安装期进程内覆写全局 database 配置（default 连接 + connections.mysql），
     * 保证随后的 Db::name/table 均作用于目标库。
     */
    protected static function applyConfig(array $cfg): void
    {
        $config = self::connectionConfig($cfg);
        \think\facade\Config::set([
            'default' => 'mysql',
            'connections' => ['mysql' => $config],
        ], 'database');
        Db::setConfig([
            'default' => 'mysql',
            'connections' => ['mysql' => $config],
        ]);
    }

    /**
     * 友好化错误信息.
     */
    protected static function friendlyError(Throwable $e, string $database): string
    {
        $message = $e->getMessage();

        if (stripos($message, 'unknown database') !== false) {
            return '数据库不存在：' . ($database !== '' ? $database : '(未填写)');
        }
        if (stripos($message, 'access denied') !== false) {
            return '数据库连接被拒绝：用户名或密码错误';
        }
        if (stripos($message, 'connection refused') !== false
            || stripos($message, 'no route to host') !== false
            || stripos($message, 'network is unreachable') !== false
            || stripos($message, 'getaddrinfo failed') !== false
            || stripos($message, 'php_network_getaddresses') !== false) {
            return '无法连接数据库服务器，请检查主机地址与端口';
        }
        if (stripos($message, 'could not find driver') !== false) {
            return '缺少 PDO MySQL 驱动，请先安装 php-mysql 扩展';
        }

        return '数据库连接失败：' . $message;
    }

    /**
     * 合并写入 .env：保留其它行，只替换/追加顶层键（置于任何 [SECTION] 之前）.
     */
    protected static function mergeEnvFile(string $envFile, array $map): string
    {
        $lines = file($envFile, FILE_IGNORE_NEW_LINES);
        $out = [];
        $sectionStarted = false;
        $written = [];

        foreach ($lines as $line) {
            $trim = trim($line);
            if ($trim !== '' && $trim !== '#' && str_starts_with($trim, '[')) {
                $sectionStarted = true;
            }
            if (!$sectionStarted && !str_starts_with(trim($line), '#') && trim($line) !== '') {
                $key = strtok(trim($line), ' =');
                if ($key !== false && isset($map[$key]) && !isset($written[$key])) {
                    $out[] = self::envLine($key, (string) $map[$key]);
                    $written[$key] = true;
                    continue;
                }
            }
            $out[] = $line;
        }

        // 追加未出现的顶层键
        foreach ($map as $key => $value) {
            if (!isset($written[$key])) {
                $out[] = self::envLine($key, (string) $value);
            }
        }

        return implode("\n", $out) . "\n";
    }

    /**
     * 生成单行 key = value.
     */
    protected static function envLine(string $key, string $value): string
    {
        return $key . ' = ' . $value;
    }
}