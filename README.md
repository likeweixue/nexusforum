# NexusForum

基于 [NexusPHP](https://github.com/xiaomlove/nexusphp) 论坛模块提取重构的**独立论坛系统**。

保留了 NexusPHP 作为 PT 站的传统——**邀请注册制度**，同时彻底剥离了种子（Torrent）、考核、H&R 等 PT 功能，
只保留纯粹的论坛功能。系统**零依赖**（无需 Composer、无需数据库、无需 Redis），
帖子与回复以 **TXT 文件**形式存储，上传到网站根目录即可安装使用（类似 DokuWiki）。

## 特性

- 🗂 **纯文件存储**：主题元数据为 JSON，帖子/回复正文为 TXT 文件，无需 MySQL
- 📦 **零依赖**：不需要 Composer install，不需要任何 PHP 框架
- 🚀 **即传即用**：上传到网站根目录，访问 `install.php` 完成安装（DokuWiki 风格向导）
- 🎫 **邀请制度**：保留 PT 站传统，注册必须持有效邀请码；达到等级的用户可自行生成邀请
- 👥 **用户等级**：沿用 NexusPHP 等级体系（用户 → 力量用户 → … → VIP → 版主 → 管理员）
- 💬 **完整论坛功能**：分类/板块/主题/回复、置顶/锁定、BBCode、编辑记录、分页、浏览量统计
- 🛡 **安全设计**：密码哈希、CSRF 防护、XSS 转义、会话安全、文件锁并发控制
- 🎨 **NexusPHP 经典界面**：表格布局、等级配色、紧凑列表风格

## 系统要求

- PHP >= 8.0（需 json、mbstring 扩展）
- Apache（含 mod_rewrite，可选）或 Nginx
- 对 `conf/` 与 `data/` 目录的写权限

## 安装

1. 将本目录所有文件上传到网站根目录（如 `/www/wwwroot/bbs.example.com/`）
2. 确保 `conf/` 和 `data/` 目录可写（755 或 775）
3. 浏览器访问 `https://你的域名/install.php`
4. 按向导填写站点名称、管理员账号，完成安装
5. **删除 `install.php`**（安全要求）
6. 访问首页开始使用

> Nginx 用户请自行配置 `location / { try_files $uri $uri/ /index.php?$args; }`
> 并拒绝 `inc/`、`conf/`、`data/` 目录的外部访问。

## 目录结构

```
nexus-forum/
├── index.php          # 入口/前端控制器（全部路由）
├── install.php        # 安装向导（安装后删除）
├── .htaccess          # Apache 配置
├── LICENSE            # GPL-2.0 许可证
├── conf/              # 配置目录（安装生成 config.php）
├── data/              # 数据目录（安装后生成）
│   ├── users/         #   用户资料 {uid}.json
│   ├── forums/        #   板块定义 {fid}.json
│   ├── overforums/    #   板块分类 {ofid}.json
│   ├── topics/        #   主题元数据 {tid}.json
│   ├── posts/         #   楼层正文 {tid}/{pid}.txt
│   ├── invites/       #   邀请码 {hash}.json
│   └── counters.json  #   自增 ID 计数器
├── inc/               # 核心库
│   ├── bootstrap.php  #   引导/配置/会话/自动加载
│   ├── storage.php    #   文件存储引擎（flock 并发锁）
│   ├── user.php       #   用户系统
│   ├── invite.php     #   邀请系统
│   ├── forum.php      #   论坛逻辑
│   ├── bbcode.php     #   BBCode 解析器
│   ├── template.php   #   模板引擎
│   └── util.php       #   工具函数
├── tpl/               # 页面模板
└── assets/
    └── style.css      # NexusPHP 经典风格样式
```

## 邀请制度说明

- 安装时可选「邀请注册制」（默认开启，PT 传统）或「开放注册」
- 管理员默认获得 99 个邀请名额，可在管理面板生成邀请码
- 普通用户达到「力量用户」(class >= 2) 等级后获得邀请权限（等级/名额均可在后台调整）
- 邀请码默认 7 天有效（可配置 1/7/30 天或永久），支持预绑定受邀人
- 邀请码使用后立即失效，并记录使用者 UID

## 备份与迁移

备份以下两项即可完整迁移：

- `data/` 目录（全部论坛数据）
- `conf/config.php`（站点配置）

## 升级

直接覆盖 `index.php`、`inc/`、`tpl/`、`assets/` 即可，`data/` 与 `conf/` 不受影响。

## 许可证

本项目基于 NexusPHP（GPL-2.0）的论坛模块重构，继承 **GNU General Public License v2.0**。
详见 [LICENSE](LICENSE) 文件。

- 上游项目：[xiaomlove/nexusphp](https://github.com/xiaomlove/nexusphp)
- 原始项目：NexusPHP

## 致谢

感谢 NexusPHP 项目及其贡献者。本系统仅提取其论坛功能并重构为独立轻量实现，
数据模型（overforums/forums/topics/posts/invites）与用户等级体系均沿用 NexusPHP 设计。
