# 小课学堂 — 知识付费网课平台

仿「小黑课堂」风格的知识付费网站，基于 **原生 PHP + MySQL**（无框架、无 Composer 依赖），开箱即用。

## 功能一览

### 前台
- **首页**：Banner（背景图与文案后台可配）、热门课程、按分类专区展示课程卡片（价格/原价/报名人数/课时/有效期/授课老师）
- **课程中心**：分类筛选、关键词搜索（课程/老师）、最热/最新排序
- **课程详情**：课程介绍、章节-课时目录、免费试看标记、购买/报名入口
- **学习页**：视频播放器 + 课时目录，已购课程全部解锁，未购仅可试看
- **用户系统**：邮箱验证码注册（演示模式免 SMTP / 正式模式 SMTP 真实发信）、密码登录、退出
- **个人中心**：我的课程、我的订单（含待支付订单去支付）、账号资料、修改密码
- **支付**：下单 → 订单 → 模拟支付（支付宝/微信/余额，代码留有真实支付对接位置）→ 自动开通课程

### 后台（/admin）
- 仪表盘：用户/课程/订单/销售额统计、最新订单、报名 TOP5
- 课程管理：新增/编辑/上下架/删除（删除联动章节课时）
  - **课程主图**：支持本地上传（jpg/jpeg/png/webp/gif，存 `uploads/covers/`）或填写图片外链；列表显示缩略图；替换/清除时自动清理旧图文件；未设置时前台显示简洁中性文字占位（无花哨渐变）
- **站点设置**（后台 → 站点设置）：
  - **站点 Logo**：上传后顶部导航显示图片 Logo，未设置显示默认文字标
  - **网站图标 Favicon**：支持 ico/png/gif/jpg/svg，输出到浏览器标签页
  - **首页 Banner**：背景图（1600×500+）+ 主标题/副标题/按钮文字/按钮链接，全部后台可配
  - **邮箱验证码发信（SMTP）**：发信模式、SMTP 服务器/端口/加密/账号/授权码后台配置，支持发送测试邮件，优先于 config.php
  - **在线支付（易支付）**：网关地址/商户 PID/密钥后台配置，启用后跳转易支付收银台，异步回调自动开课
  - 清除图片后自动恢复内置默认样式，被替换旧图自动删除；密码/密钥不回显
- **课程目录与视频管理**（课程管理 → 目录/视频）：
  - 章节：添加、重命名、排序（↑↓）、删除（联动删除课时与已上传视频文件）
  - 课时：添加、编辑、排序、删除、免费试看开关
  - 视频来源两种：**本地上传视频文件**（mp4/webm/ogv/m4v/mov，存 `uploads/videos/`，自动唯一命名，删除课时自动清理无引用文件）或 **填写外部视频 URL**
  - 课时数自动同步到课程卡片显示
- 分类管理：增删改、排序
- 订单管理：筛选、后台开通、取消
- 用户管理：禁用/启用、重置密码

### 大视频上传调优
代码与配置已将上传上限放宽到 **1GB**：
- Apache + mod_php：根目录 `.htaccess` 已含 `php_value upload_max_filesize 1024M`
- php-fpm / FastCGI：根目录 `.user.ini` 已含同项配置
- **Nginx**：还需在 server 块添加 `client_max_body_size 1024m;`
- `uploads/videos/.htaccess` 已禁止脚本执行、关闭目录列表
- 超大视频（>1GB）建议放对象存储（OSS/COS）后以「外部视频 URL」方式录入

### 安全
- PDO 预处理防 SQL 注入；`password_hash` 加密存储密码
- 全表单 CSRF Token；登录后 `session_regenerate_id` 防会话固定
- `.htaccess` 禁止访问 `config.php` / `includes/` / `database/` / `storage/`
- 站内 redirect 白名单校验

## 环境要求

| 组件 | 要求 |
|------|------|
| PHP | ≥ 7.4（需 pdo_mysql、openssl、mbstring 扩展）|
| MySQL | ≥ 5.7（或 MariaDB ≥ 10.3）|
| Web | Apache / Nginx / PHP 内置服务器均可 |

## 一键部署（三种方式任选）

### 方式一：Windows 一键脚本
```
双击 deploy.bat
```
自动检测 Docker / 本机 PHP：有 Docker 则容器化部署（http://localhost:8080），否则用 PHP 内置服务器启动（http://localhost:8000）。

### 方式二：Linux / macOS
```bash
chmod +x deploy.sh && ./deploy.sh
```

### 方式三：Docker Compose（推荐生产体验）
```bash
docker compose up -d --build
# 前台 http://localhost:8080  MySQL 数据持久化在 xiaoke-mysql 卷
```

### 安装向导（部署后必做一次）
浏览器访问 **`/install.php`**：
1. 自动环境检测（PHP 版本/扩展/目录权限）
2. 填写数据库信息（库不存在会自动创建）+ 管理员邮箱密码 + 站点名称
3. 自动建表、导入 12 门演示课程、生成 `config.php` —— 完成

> 重新安装：删除根目录 `config.php` 后再访问 `install.php` 即可（已存在的表不会被破坏）。

### 生产环境 Nginx 参考配置
```nginx
server {
    listen 80;
    server_name yourdomain.com;
    root /var/www/xiaoke-course-site;
    index index.php;

    location ~ ^/(includes|database|storage)/ { return 404; }
    location ~ /config\.php { return 404; }
    client_max_body_size 1024m;   # 允许上传大视频

    location / { try_files $uri $uri/ /index.php?$query_string; }

    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }
}
```

## 目录结构
```
├── index.php            首页          ├── checkout.php     下单
├── courses.php          课程列表      ├── pay.php          模拟支付
├── course.php           课程详情      ├── learn.php        学习页
├── login/register/logout.php 用户系统  ├── user.php         个人中心
├── install.php          一键安装向导
├── config.sample.php    配置模板（实际 config.php 由安装器生成）
├── database/schema.sql  建表 + 演示数据
├── includes/            框架（DB/函数/邮件/布局）
├── admin/               管理后台（lessons.php = 目录/视频管理）
├── assets/              CSS / JS
├── uploads/covers/      后台上传的课程主图（禁止脚本执行）
├── uploads/site/        后台上传的 Logo / Favicon / Banner 图
├── uploads/videos/      后台上传的视频文件（禁止脚本执行）
├── deploy.bat / deploy.sh / docker-compose.yml / Dockerfile
```

> 老版本升级：`includes/init.php` 会自动完成数据库升级（补建 `settings` 表、`courses.cover` 列扩容至 VARCHAR(500)），无需手工执行 SQL。

## 邮箱验证码说明
- **演示模式（默认）**：验证码不真实发送，直接显示在注册页并记录到 `storage/mail.log`，方便本地联调
- **SMTP 模式（推荐在后台配置）**：后台「站点设置 → 邮箱验证码发信」选择 SMTP 真实发信，填写邮箱服务商的 SMTP 服务器/端口/加密方式/账号/授权码（QQ 邮箱、163 邮箱等均可），并可一键发送测试邮件验证；此处配置优先于 `config.php`
- SMTP 发送失败时自动降级为演示模式（验证码显示 + 记录日志），保证注册流程始终可用

## 真实支付对接（易支付）
系统**已内置易支付（Epay）标准 API 对接**，后台「站点设置 → 在线支付」填入三项信息即可启用：
- 易支付网关地址（如 `https://pay.example.com`，不带 `/submit.php`）
- 商户 ID（PID）、商户密钥（KEY）

启用后购买流程变为：下单 → 跳转易支付收银台（支付宝/微信/QQ 钱包）→ 支付成功由平台异步回调 `notify.php` 自动开通课程（MD5 验签 + 金额核对 + 幂等防重复开通），流水号记录在 `orders.trade_no`。不启用时保持内置模拟支付演示流程。如需接入支付宝/微信官方直连，替换 `pay.php` 中易支付跳转代码块与 `notify.php` 的回调验签即可。

## 演示数据账号
安装时自行设置的管理员账号即后台登录账号（前台 `/admin/login.php`）。

## 已通过的自动化测试（119 项）
基础流程 26 项：邮箱注册(验证码)→登录→改资料→免费报名→付费下单→模拟支付→开通→学习页播放→订单核对→已购识别→权限拦截→管理员后台全页面→后台新增课程→UTF-8 中文入库校验。
目录/视频管理 19 项：新增/重命名/删除章节、上传 mp4 新增课时、外链方式新增课时、视频落盘与前台播放（字节级校验）、课时排序、编辑保留视频、删除课时自动清理视频文件、课时数自动同步。
图片配置 39 项：后台上传课程主图→前台卡片/列表/详情页显示、外链主图、清除主图恢复中性占位并清理旧文件、php 伪装图片拒绝、站点 Logo/Favicon/Banner 上传与前台生效、文案配置、清除恢复默认样式、老库 cover 列扩容升级。
SMTP/易支付 35 项：SMTP 后台配置入库与密码不回显、SMTP 失败自动降级仍可注册、测试邮件发送、易支付配置入库、下单跳转收银台参数与 MD5 签名校验、异步回调验签/金额/状态校验、自动开课、幂等防重、回跳页展示、关闭易支付恢复模拟支付。
