# Checklist

- [x] registerSql POST 能校验必填、库名与前缀格式，非法输入回显中文错误
- [x] 数据库库不存在时，安装流程自动建库（utf8mb4）并成功连接目标库
- [x] 连接失败（账号错/主机不可达/无建库权限）时不产生任何文件，界面给出可读错误
- [x] writeEnv 在根目录生成 `.env`，`DB_*` 键正确且与表单一致；进程内 `Db` 生效
- [x] `.env` 已存在时仅替换/追加 `DB_*`，保留其它行与配置段
- [x] installTables 按提交前缀成功创建 `bny_admin/bny_attachment/bny_config/bny_menu/bny_role`
- [x] createAdmin 插入超级管理员，密码为 `password_hash` 密文，站点名写入 config
- [x] 用户名重复时报错，不产生重复数据
- [x] `.install.lock` 在超级管理员注册成功后写入项目根，`isInstalled()` 返回真
- [x] 安装流程可重复执行（幂等），不产生重复表/重复账号
- [x] 未完成数据库配置直接进入注册管理员时给出"请先完成上一步"的拦截提示