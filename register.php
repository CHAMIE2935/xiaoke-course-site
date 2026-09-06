<?php
/**
 * 邮箱注册：三步流程（同页完成）
 *  1. 输入邮箱 -> 发送验证码
 *  2. 输入验证码 + 昵称 + 密码 -> 校验
 *  3. 注册成功自动登录
 *
 * 邮件为 SMTP 模式时真实发送；演示模式(log)时验证码直接显示在页面上，方便本地测试。
 */
require __DIR__ . '/includes/init.php';

if (current_user()) redirect(url('user.php'));

$step = 1;                 // 1=填邮箱 2=填验证码与资料
$email = $_SESSION['reg_email'] ?? '';
$codeTip = null;           // 演示模式下的验证码提示
$error = '';

// 动作处理
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';

    // ---- 第一步：发送验证码 ----
    if ($action === 'send_code') {
        $email = strtolower(trim($_POST['email'] ?? ''));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = '请输入正确的邮箱地址';
        } elseif (Db::value('SELECT COUNT(*) FROM users WHERE email = ?', [$email])) {
            $error = '该邮箱已注册，请直接登录';
        } else {
            // 同一邮箱 60 秒内不重复发送
            $last = Db::row("SELECT created_at FROM email_codes WHERE email = ? AND purpose = 'register' ORDER BY id DESC LIMIT 1", [$email]);
            if ($last && strtotime($last['created_at']) > time() - 60) {
                $error = '发送太频繁，请 60 秒后再试';
            } else {
                $code = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
                Db::run("INSERT INTO email_codes (email, code, purpose, expires_at) VALUES (?, ?, 'register', DATE_ADD(NOW(), INTERVAL 10 MINUTE))", [$email, $code]);
                $result = send_mail($email, '小课学堂注册验证码', "你好！\n\n你的注册验证码为：{$code}，10 分钟内有效。\n\n如非本人操作，请忽略本邮件。");
                if ($result['debug_code']) $codeTip = $result['debug_code'];
                $_SESSION['reg_email'] = $email;
                $step = 2;
                if (!$codeTip) flash('success', '验证码已发送到你的邮箱，10 分钟内有效');
            }
        }
    }

    // ---- 第二步：提交注册 ----
    if ($action === 'register') {
        $email = strtolower(trim($_POST['email'] ?? $_SESSION['reg_email'] ?? ''));
        $code = trim($_POST['code'] ?? '');
        $nickname = trim($_POST['nickname'] ?? '');
        $password = $_POST['password'] ?? '';
        $password2 = $_POST['password2'] ?? '';
        $step = 2;

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = '邮箱地址无效';
        } elseif ($code === '' || !preg_match('/^\d{6}$/', $code)) {
            $error = '请输入 6 位数字验证码';
        } elseif ($password === '' || strlen($password) < 6) {
            $error = '密码至少 6 位';
        } elseif ($password !== $password2) {
            $error = '两次输入的密码不一致';
        } elseif (Db::value('SELECT COUNT(*) FROM users WHERE email = ?', [$email])) {
            $error = '该邮箱已注册，请直接登录';
        } else {
            $row = Db::row(
                "SELECT * FROM email_codes WHERE email = ? AND purpose = 'register' AND used = 0 AND code = ? AND expires_at > NOW() ORDER BY id DESC LIMIT 1",
                [$email, $code]
            );
            if (!$row) {
                $error = '验证码错误或已过期，请重新获取';
            } else {
                Db::run('UPDATE email_codes SET used = 1 WHERE id = ?', [$row['id']]);
                $nickname = $nickname !== '' ? mb_substr($nickname, 0, 30) : explode('@', $email)[0];
                Db::run('INSERT INTO users (email, password_hash, nickname) VALUES (?, ?, ?)',
                    [$email, password_hash($password, PASSWORD_DEFAULT), $nickname]);
                $uid = (int)Db::conn()->lastInsertId();
                $_SESSION['user_id'] = $uid;
                unset($_SESSION['reg_email']);
                flash('success', '注册成功，欢迎加入！');
                redirect(url('index.php'));
            }
        }
    }
}

$pageTitle = '注册';
require __DIR__ . '/includes/header.php';
?>

<div class="auth-wrap">
  <div class="auth-card">
    <h1>注册账号</h1>

    <?php if ($error): ?><p class="form-error"><?= e($error) ?></p><?php endif; ?>
    <?php if ($codeTip): ?>
      <div class="code-tip">📮 本次验证码为：<b><?= e($codeTip) ?></b><br>
      <small>（未配置或发信失败时直接显示；正式使用请在后台「站点设置」完成 SMTP 配置）</small></div>
    <?php endif; ?>

    <?php if ($step === 1): ?>
    <form method="post" autocomplete="off">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="send_code">
      <div class="form-item">
        <label>邮箱地址</label>
        <input type="email" name="email" required placeholder="you@example.com" value="<?= e($email) ?>">
        <p class="hint">将向该邮箱发送 6 位验证码完成注册</p>
      </div>
      <button class="btn btn-primary btn-lg btn-block" type="submit">发送验证码</button>
    </form>

    <?php else: ?>
    <form method="post" autocomplete="off">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="register">
      <div class="form-item">
        <label>邮箱</label>
        <input type="email" name="email" value="<?= e($email) ?>" readonly>
        <p class="hint"><a href="<?= url('register.php') ?>" style="color:#ff4d4f">换一个邮箱</a></p>
      </div>
      <div class="form-item">
        <label>邮箱验证码</label>
        <div class="input-group">
          <input type="text" name="code" maxlength="6" required placeholder="6 位数字">
          <button class="btn btn-outline" type="submit" formaction="<?= url('register.php') ?>" name="action" value="send_code"
                  onclick="startCodeCooldown(this,60)">重新发送</button>
        </div>
      </div>
      <div class="form-item">
        <label>昵称（选填）</label>
        <input type="text" name="nickname" maxlength="30" placeholder="怎么称呼你">
      </div>
      <div class="form-item">
        <label>设置密码</label>
        <input type="password" name="password" minlength="6" required placeholder="至少 6 位">
      </div>
      <div class="form-item">
        <label>确认密码</label>
        <input type="password" name="password2" minlength="6" required placeholder="再次输入密码">
      </div>
      <button class="btn btn-primary btn-lg btn-block" type="submit">完成注册</button>
    </form>
    <?php endif; ?>

    <p class="auth-foot">已有账号？<a href="<?= url('login.php') ?>">直接登录</a></p>
  </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
