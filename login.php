<?php
/** 邮箱 + 密码登录 */
require __DIR__ . '/includes/init.php';

if (current_user()) redirect(url('user.php'));

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $email = strtolower(trim($_POST['email'] ?? ''));
    $password = $_POST['password'] ?? '';

    $user = Db::row('SELECT * FROM users WHERE email = ?', [$email]);
    if (!$user || !password_verify($password, $user['password_hash'])) {
        $error = '邮箱或密码错误';
    } elseif (!$user['status']) {
        $error = '账号已被禁用，请联系管理员';
    } else {
        session_regenerate_id(true);
        $_SESSION['user_id'] = (int)$user['id'];
        Db::run('UPDATE users SET last_login_at = NOW() WHERE id = ?', [$user['id']]);
        $redirect = $_GET['redirect'] ?? '';
        // 仅允许站内跳转
        if ($redirect && strpos($redirect, '/') === 0 && strpos($redirect, '//') !== 0) {
            redirect($redirect);
        }
        redirect(url($user['role'] === 'admin' ? 'admin/index.php' : 'user.php'));
    }
}

$pageTitle = '登录';
require __DIR__ . '/includes/header.php';
?>

<div class="auth-wrap">
  <div class="auth-card">
    <h1>登录</h1>
    <?php if ($error): ?><p class="form-error"><?= e($error) ?></p><?php endif; ?>
    <form method="post" autocomplete="off">
      <?= csrf_field() ?>
      <div class="form-item">
        <label>邮箱</label>
        <input type="email" name="email" required placeholder="you@example.com" value="<?= e($_POST['email'] ?? '') ?>">
      </div>
      <div class="form-item">
        <label>密码</label>
        <input type="password" name="password" required placeholder="输入密码">
      </div>
      <button class="btn btn-primary btn-lg btn-block" type="submit">登 录</button>
    </form>
    <p class="auth-foot">还没有账号？<a href="<?= url('register.php') ?>">免费注册</a></p>
  </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
