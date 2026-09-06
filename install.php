<?php
/**
 * =====================================================
 *  小课学堂 · 一键安装向导
 *  访问 http://你的域名/install.php 按提示 3 步完成部署：
 *    1. 环境检测（PHP 版本 / PDO / MySQL 扩展 / 目录可写）
 *    2. 填写数据库与管理员信息
 *    3. 自动建库建表、导入演示数据、生成 config.php
 *  安装完成后请删除本文件（或保留，已安装状态下会被锁定）。
 * =====================================================
 */
error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE);
ini_set('display_errors', '1');
date_default_timezone_set('Asia/Shanghai');
session_start();

define('APP_ROOT', __DIR__);
$installed = file_exists(APP_ROOT . '/config.php');

/* ---------- 环境检测 ---------- */
$checks = [
    'PHP 版本 >= 7.4'            => version_compare(PHP_VERSION, '7.4.0', '>='),
    'PDO 扩展'                    => extension_loaded('PDO'),
    'PDO MySQL 驱动'              => extension_loaded('pdo_mysql'),
    'openssl 扩展（验证码/安全随机）' => extension_loaded('openssl'),
    'mbstring 扩展'               => extension_loaded('mbstring'),
    '根目录可写（生成配置）'        => is_writable(APP_ROOT),
    'storage 目录可写（邮件日志）'  => is_writable(APP_ROOT . '/storage') || @mkdir(APP_ROOT . '/storage', 0777, true),
];
$allPassed = !in_array(false, $checks, true);

/* ---------- 执行安装 ---------- */
$errors = [];
$done = false;
if (!$installed && $_SERVER['REQUEST_METHOD'] === 'POST' && $allPassed) {
    $dbHost = trim($_POST['db_host'] ?? '127.0.0.1');
    $dbPort = (int)($_POST['db_port'] ?? 3306);
    $dbName = trim($_POST['db_name'] ?? 'xiaoke_course');
    $dbUser = trim($_POST['db_user'] ?? 'root');
    $dbPass = (string)($_POST['db_pass'] ?? '');
    $siteName = trim($_POST['site_name'] ?? '小课学堂') ?: '小课学堂';
    $adminEmail = strtolower(trim($_POST['admin_email'] ?? ''));
    $adminPass = (string)($_POST['admin_pass'] ?? '');
    $mailMode = ($_POST['mail_mode'] ?? 'log') === 'smtp' ? 'smtp' : 'log';

    if (!preg_match('/^[A-Za-z0-9_]+$/', $dbName)) $errors[] = '数据库名只能包含字母、数字和下划线';
    if (!filter_var($adminEmail, FILTER_VALIDATE_EMAIL)) $errors[] = '管理员邮箱格式不正确';
    if (strlen($adminPass) < 6) $errors[] = '管理员密码至少 6 位';

    if (!$errors) {
        try {
            // 1. 连接 MySQL（不带库名），不存在则建库
            $pdo = new PDO(
                "mysql:host={$dbHost};port={$dbPort};charset=utf8mb4",
                $dbUser, $dbPass,
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
            );
            $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$dbName}` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            $pdo->exec("USE `{$dbName}`");

            // 2. 导入 schema.sql（逐条执行）
            $sqlFile = APP_ROOT . '/database/schema.sql';
            if (!is_file($sqlFile)) throw new Exception('database/schema.sql 文件缺失');
            $sql = file_get_contents($sqlFile);
            // 去掉注释行后按分号切分
            $sql = preg_replace('/^\s*--.*$/m', '', $sql);
            $statements = array_filter(array_map('trim', preg_split('/;\s*[\r\n]+/', $sql)));
            foreach ($statements as $stmt) {
                if ($stmt !== '') $pdo->exec($stmt);
            }

            // 3. 创建管理员（密码哈希实时生成）
            $hash = password_hash($adminPass, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO users (email, password_hash, nickname, role, status)
                                   VALUES (?, ?, '站点管理员', 'admin', 1)
                                   ON DUPLICATE KEY UPDATE password_hash = VALUES(password_hash), role = 'admin', status = 1");
            $stmt->execute([$adminEmail, $hash]);

            // 4. 生成 config.php
            $configPhp = <<<PHP
<?php
// 本文件由 install.php 生成于 {DATE}
return [
    'db' => [
        'host'    => {DB_HOST},
        'port'    => {DB_PORT},
        'name'    => {DB_NAME},
        'user'    => {DB_USER},
        'pass'    => {DB_PASS},
        'charset' => 'utf8mb4',
    ],
    'site' => [
        'name'   => {SITE_NAME},
        'slogan' => '让学习更高效',
        'url'    => '',
    ],
    'mail' => [
        'mode'      => {MAIL_MODE},
        'from'      => 'noreply@example.com',
        'from_name' => {SITE_NAME},
        'host'      => 'smtp.example.com',
        'port'      => 465,
        'username'  => '',
        'password'  => '',
        'secure'    => 'ssl',
    ],
    'installed_at' => {DATE_STR},
];
PHP;
            $configPhp = strtr($configPhp, [
                '{DATE}'      => var_export(date('Y-m-d H:i:s'), true),
                '{DATE_STR}'  => var_export(date('Y-m-d H:i:s'), true),
                '{DB_HOST}'   => var_export($dbHost, true),
                '{DB_PORT}'   => var_export($dbPort, true),
                '{DB_NAME}'   => var_export($dbName, true),
                '{DB_USER}'   => var_export($dbUser, true),
                '{DB_PASS}'   => var_export($dbPass, true),
                '{SITE_NAME}' => var_export($siteName, true),
                '{MAIL_MODE}' => var_export($mailMode, true),
            ]);
            if (file_put_contents(APP_ROOT . '/config.php', $configPhp) === false) {
                throw new Exception('config.php 写入失败，请检查根目录权限');
            }
            @file_put_contents(APP_ROOT . '/storage/.installed', date('c'));

            $done = true;
        } catch (PDOException $e) {
            $errors[] = '数据库错误：' . $e->getMessage();
        } catch (Exception $e) {
            $errors[] = $e->getMessage();
        }
    }
}

function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>安装向导 - 小课学堂</title>
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<script>
document.addEventListener('DOMContentLoaded', function () {
    alert('本文件只在GitHub上开源，如您在其他网站下载，请联系客服QQ2935609989');
});
</script>

<div class="install-wrap">
  <div class="auth-card">

    <?php if ($installed): ?>
      <h1>✅ 已完成安装</h1>
      <p style="color:#7a8694;text-align:center;margin:14px 0 22px">系统已安装。如需重新安装，请先删除根目录下的 <code>config.php</code>。</p>
      <div style="display:flex;gap:12px">
        <a class="btn btn-primary btn-lg btn-block" href="index.php">进入前台</a>
        <a class="btn btn-outline btn-lg btn-block" href="admin/login.php">进入后台</a>
      </div>

    <?php elseif ($done): ?>
      <h1>🎉 安装成功！</h1>
      <div class="code-tip" style="margin-top:16px">
        站点名称：<?= h($_POST['site_name']) ?><br>
        管理员邮箱：<?= h($_POST['admin_email']) ?><br>
        数据库：<?= h($_POST['db_name']) ?> @ <?= h($_POST['db_host']) ?>
      </div>
      <div style="display:flex;gap:12px">
        <a class="btn btn-primary btn-lg btn-block" href="index.php">进入前台</a>
        <a class="btn btn-outline btn-lg btn-block" href="admin/login.php">进入后台</a>
      </div>
      <p style="font-size:12px;color:#b6bec9;text-align:center;margin-top:14px">安全提示：请尽快删除 install.php 文件</p>

    <?php else: ?>
      <h1>🚀 小课学堂 · 一键安装</h1>
      <div class="step-indicator">
        <span class="<?= $allPassed ? 'done' : 'on' ?>">① 环境检测</span>
        <span>② 填写信息</span>
        <span>③ 完成</span>
      </div>

      <h2 style="font-size:15px;margin-bottom:12px">环境检测</h2>
      <table class="data-table" style="margin-bottom:20px">
        <?php foreach ($checks as $name => $ok): ?>
        <tr><td><?= h($name) ?></td><td style="text-align:right"><span class="badge badge-<?= $ok ? 'paid' : 'cancelled' ?>"><?= $ok ? '通过' : '未通过' ?></span></td></tr>
        <?php endforeach; ?>
      </table>

      <?php if (!$allPassed): ?>
        <p class="form-error" style="text-align:center">环境检测未全部通过，请先解决上述问题后刷新本页</p>

      <?php else: ?>
        <?php foreach ($errors as $err): ?><p class="form-error"><?= h($err) ?></p><?php endforeach; ?>
        <form method="post">
          <h2 style="font-size:15px;margin-bottom:12px">数据库信息</h2>
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
            <div class="form-item"><label>MySQL 主机</label><input type="text" name="db_host" value="<?= h($_POST['db_host'] ?? '127.0.0.1') ?>" required></div>
            <div class="form-item"><label>端口</label><input type="number" name="db_port" value="<?= h($_POST['db_port'] ?? '3306') ?>" required></div>
          </div>
          <div class="form-item"><label>数据库名（不存在会自动创建）</label><input type="text" name="db_name" value="<?= h($_POST['db_name'] ?? 'xiaoke_course') ?>" required></div>
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
            <div class="form-item"><label>数据库用户名</label><input type="text" name="db_user" value="<?= h($_POST['db_user'] ?? 'root') ?>" required></div>
            <div class="form-item"><label>数据库密码</label><input type="password" name="db_pass" value="<?= h($_POST['db_pass'] ?? '') ?>"></div>
          </div>

          <h2 style="font-size:15px;margin:8px 0 12px">站点与管理员</h2>
          <div class="form-item"><label>站点名称</label><input type="text" name="site_name" value="<?= h($_POST['site_name'] ?? '小课学堂') ?>"></div>
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
            <div class="form-item"><label>管理员邮箱（后台登录用）</label><input type="email" name="admin_email" value="<?= h($_POST['admin_email'] ?? 'admin@demo.com') ?>" required></div>
            <div class="form-item"><label>管理员密码</label><input type="text" name="admin_pass" value="<?= h($_POST['admin_pass'] ?? 'admin123456') ?>" required></div>
          </div>
          <div class="form-item"><label>邮箱验证码发送方式</label>
            <select name="mail_mode">
              <option value="log" <?= ($_POST['mail_mode'] ?? '') === 'log' ? 'selected' : '' ?>>演示模式（验证码直接显示，无需SMTP，可稍后在 config.php 修改）</option>
              <option value="smtp" <?= ($_POST['mail_mode'] ?? '') === 'smtp' ? 'selected' : '' ?>>SMTP 模式（需稍后在 config.php 填写 SMTP 账号）</option>
            </select>
          </div>
          <button class="btn btn-primary btn-lg btn-block" type="submit">开始安装（自动建库 + 导入演示数据）</button>
          <p style="font-size:12px;color:#b6bec9;text-align:center;margin-top:12px">重复安装不会覆盖已有数据（CREATE TABLE IF NOT EXISTS）</p>
        </form>
      <?php endif; ?>
    <?php endif; ?>

  </div>
</div>
</body>
</html>
