<?php
declare(strict_types=1);

namespace app\install\controller;

use app\admin\service\Installer;
use app\BaseController;
use Kingbes\Annotation\Annotation;
use think\response\Json;
use think\response\Redirect;

/**
 * Web 安装向导控制器（公共页面，无需登录）
 *
 * install 为独立应用：think-multi-app 按 pathinfo 首段识别应用，应用内
 * 注解路由以应用内相对路径注册（不含应用段）。控制器名为 Install 时默认
 * 前缀为 /install，会导致访问路径成为 /install/install/xxx，故此处为各
 * 方法显式指定 path 设为应用内相对路径，使最终访问地址为 /install/env 等。
 */
#[Annotation(['title' => '安装向导', 'menu' => false])]
class Install extends BaseController
{
    /**
     * 控制器初始化：已安装状态下禁止再访问安装向导任意接口
     * （重装防护，防外部探测环境/重建库覆盖 .env）。
     *
     * @return void
     */
    protected function initialize(): void
    {
        if (Installer::isInstalled()) {
            abort(302, '', ['Location' => '/admin/passport/login']);
        }
    }

    /**
     * 环境检查
     *
     * @return Json
     */
    #[Annotation(['title' => '环境检查', 'path' => 'env'])]
    public function env(): Json
    {
        return json(['items' => Installer::checkEnv()]);
    }

    /**
     * 测试数据库连接并初始化数据表（通过后写入 .env、建表种子并保存配置到 session）
     *
     * @return Json
     */
    #[Annotation(['title' => '测试数据库', 'request' => 'POST', 'path' => 'testdb'])]
    public function testdb(): Json
    {
        $hostname = (string) $this->request->post('hostname', '127.0.0.1');
        $port     = (string) $this->request->post('port', '3306');
        $database = (string) $this->request->post('database', '');
        $username = (string) $this->request->post('username', 'root');
        $password = (string) $this->request->post('password', '');
        $prefix   = (string) $this->request->post('prefix', 'bny_');

        // 表前缀校验（默认 bny_）
        if ($prefix === '') {
            $prefix = 'bny_';
        }
        if (preg_match('/^[a-z][a-z0-9_]{0,19}$/', $prefix) !== 1) {
            return json(['ok' => false, 'msg' => '表前缀须以小写字母开头，仅含小写字母、数字、下划线，长度 1-20']);
        }
        if ($database === '') {
            return json(['ok' => false, 'msg' => '请填写数据库名']);
        }

        $cfg = [
            'hostname' => $hostname,
            'port'     => $port,
            'database' => $database,
            'username' => $username,
            'password' => $password,
            'prefix'   => $prefix,
        ];

        try {
            // 全程只用传入配置测试连接，不触碰模型
            Installer::connect($cfg);
            // 连接成功：写入 .env 并保存配置
            Installer::writeEnv($cfg);
            session('install_db', $cfg);
            // 建七张表 + 种子基础数据（幂等，可重试）
            Installer::setupDatabase($cfg);
            session('install_db_done', true);
        } catch (\Throwable $e) {
            return json(['ok' => false, 'msg' => $e->getMessage()]);
        }

        return json(['ok' => true, 'msg' => '连接成功，数据表与基础数据已初始化']);
    }

    /**
     * 注册超级管理员并完成安装（写账号 + 绑角色 + 安装锁）
     *
     * @return Json
     */
    #[Annotation(['title' => '完成安装', 'request' => 'POST', 'path' => 'create'])]
    public function create(): Json
    {
        // 跳步防护：数据库配置步骤须已完成
        if (session('install_db_done') !== true) {
            return json(['ok' => false, 'msg' => '请先完成上一步数据库配置']);
        }

        $cfg = session('install_db');
        if (empty($cfg) || !is_array($cfg)) {
            return json(['ok' => false, 'msg' => '未检测到数据库配置，请返回上一步完成数据库配置']);
        }

        $adminUser   = (string) $this->request->post('username', '');
        $adminPass   = (string) $this->request->post('password', '');
        $confirmPass = (string) $this->request->post('confirm_password', '');
        $siteName    = (string) $this->request->post('site_name', '');

        // 两次密码不一致
        if ($adminPass !== $confirmPass) {
            return json(['ok' => false, 'msg' => '两次输入的密码不一致']);
        }

        try {
            // 写管理员账号 + 绑定超管角色 + 写安装锁
            Installer::createAdmin($cfg, $adminUser, $adminPass, $siteName);

            // 清理安装会话标记
            session('install_db', null);
            session('install_db_done', null);

            // 若权限节点同步服务存在则同步（失败不影响安装完成）
            if (class_exists('\\app\\admin\\service\\RuleSync')) {
                try {
                    \app\admin\service\RuleSync::sync();
                } catch (\Throwable $e) {
                    // 同步失败仅忽略，后续可通过命令手动同步
                }
            }
        } catch (\Throwable $e) {
            return json(['ok' => false, 'msg' => $e->getMessage()]);
        }

        return json(['ok' => true, 'msg' => '安装完成']);
    }

    /**
     * 安装向导页
     *
     * @return string|Redirect
     */
    #[Annotation(['title' => '安装向导', 'path' => ['/', 'index']])]
    public function index(): string|Redirect
    {
        // 已安装则跳转登录页，禁止重复安装
        if (Installer::isInstalled()) {
            return redirect('/admin/passport/login');
        }

        return $this->fetch();
    }
}