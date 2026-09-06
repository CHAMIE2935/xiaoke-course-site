<?php
/** 后台登录（管理员账号，与前台用户表共用） */
require dirname(__DIR__) . '/includes/init.php';

if (current_user() && current_user()['role'] === 'admin') redirect('index.php');

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $email = strtolower(trim($_POST['email'] ?? ''));
    $u = Db::row('SELECT * FROM users WHERE email = ? AND role = \'admin\'', [$email]);
    if (!$u || !password_verify($_POST['password'] ?? '', $u['password_hash'])) {
        $error = '管理员账号或密码错误';
    } else {
        session_regenerate_id(true);
        $_SESSION['user_id'] = (int)$u['id'];
        redirect('index.php');
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>后台登录</title>
<link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
<div class="auth-wrap">
  <div class="auth-card">
    <h1>🛠 管理后台登录</h1>
    <?php if ($error): ?><p class="form-error"><?= e($error) ?></p><?php endif; ?>
    <form method="post">
      <?= csrf_field() ?>
      <div class="form-item"><label>管理员邮箱</label><input type="email" name="email" required></div>
      <div class="form-item"><label>密码</label><input type="password" name="password" required></div>
      <button class="btn btn-primary btn-lg btn-block" type="submit">登 录</button>
    </form>
    <p class="auth-foot"><a href="<?= url('index.php') ?>">← 返回前台</a></p>
  </div>
</div>
</body>
</html>
