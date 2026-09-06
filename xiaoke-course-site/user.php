<?php
/** 个人中心：我的课程 / 我的订单 / 账号资料 */
require __DIR__ . '/includes/init.php';

$user = require_login();
$tab = $_GET['tab'] ?? 'courses';
if (!in_array($tab, ['courses', 'orders', 'profile'], true)) $tab = 'courses';

/* ---------- 资料更新 / 改密 ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $do = $_POST['do'] ?? '';

    if ($do === 'profile') {
        $nickname = mb_substr(trim($_POST['nickname'] ?? ''), 0, 30);
        $phone = trim($_POST['phone'] ?? '');
        if ($nickname === '') $nickname = explode('@', $user['email'])[0];
        Db::run('UPDATE users SET nickname = ?, phone = ? WHERE id = ?', [$nickname, $phone, $user['id']]);
        flash('success', '资料已更新');
        redirect(url('user.php') . '?tab=profile');
    }

    if ($do === 'password') {
        $old = $_POST['old_password'] ?? '';
        $new = $_POST['new_password'] ?? '';
        $new2 = $_POST['new_password2'] ?? '';
        $row = Db::row('SELECT password_hash FROM users WHERE id = ?', [$user['id']]);
        if (!password_verify($old, $row['password_hash'])) {
            flash('error', '原密码不正确');
        } elseif (strlen($new) < 6) {
            flash('error', '新密码至少 6 位');
        } elseif ($new !== $new2) {
            flash('error', '两次输入的新密码不一致');
        } else {
            Db::run('UPDATE users SET password_hash = ? WHERE id = ?', [password_hash($new, PASSWORD_DEFAULT), $user['id']]);
            flash('success', '密码已修改');
        }
        redirect(url('user.php') . '?tab=profile');
    }
}

/* ---------- 数据 ---------- */
$myCourses = Db::all(
    "SELECT c.id, c.title, c.cover, c.teacher, c.lesson_count, c.validity_date, e.created_at AS bought_at
     FROM enrollments e JOIN courses c ON c.id = e.course_id
     WHERE e.user_id = ? ORDER BY e.created_at DESC", [$user['id']]
);
$myOrders = Db::all('SELECT * FROM orders WHERE user_id = ? ORDER BY id DESC LIMIT 100', [$user['id']]);

$displayName = $user['nickname'] ?: explode('@', $user['email'])[0];
$avatarChar = mb_strtoupper(mb_substr($displayName, 0, 1));

$pageTitle = '个人中心';
require __DIR__ . '/includes/header.php';
?>

<div class="user-layout">
  <div class="side-card">
    <div class="profile">
      <div class="avatar"><?= e($avatarChar) ?></div>
      <div style="font-weight:600;margin-bottom:2px"><?= e($displayName) ?></div>
      <div class="email"><?= e($user['email']) ?></div>
    </div>
    <nav class="side-nav">
      <a href="?tab=courses" class="<?= $tab === 'courses' ? 'active' : '' ?>">🎓 我的课程</a>
      <a href="?tab=orders" class="<?= $tab === 'orders' ? 'active' : '' ?>">🧾 我的订单</a>
      <a href="?tab=profile" class="<?= $tab === 'profile' ? 'active' : '' ?>">⚙️ 账号资料</a>
      <?php if ($user['role'] === 'admin'): ?>
        <a href="<?= url('admin/index.php') ?>">🛠 管理后台</a>
      <?php endif; ?>
    </nav>
  </div>

  <div>
    <?php if ($tab === 'courses'): ?>
    <div class="panel-card">
      <h2>我的课程（<?= count($myCourses) ?>）</h2>
      <?php if (!$myCourses): ?>
        <p style="color:#7a8694;padding:20px 0">还没有课程，去<a href="<?= url('courses.php') ?>" style="color:#ff4d4f">课程中心</a>逛逛吧～</p>
      <?php else: ?>
        <div class="my-course-grid">
          <?php foreach ($myCourses as $c): ?>
          <div class="my-course">
            <a class="mini-cover" href="<?= url('learn.php') ?>?course=<?= (int)$c['id'] ?>">
              <?php if (cover_is_image($c['cover'])): ?>
                <img src="<?= e(cover_url($c['cover'])) ?>" alt="<?= e($c['title']) ?>" loading="lazy">
              <?php else: ?>进入学习<?php endif; ?>
            </a>
            <div style="flex:1;min-width:0">
              <div style="font-weight:600;font-size:13px;line-height:1.4;margin-bottom:6px"><?= e($c['title']) ?></div>
              <div style="font-size:12px;color:#7a8694"><?= (int)$c['lesson_count'] ?> 节 · <?= e($c['teacher']) ?></div>
              <div style="font-size:11px;color:#b6bec9;margin-top:4px"><?= e(substr($c['bought_at'], 0, 10)) ?> 开通 · 有效期至 <?= e($c['validity_date'] ?: '长期') ?></div>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>

    <?php elseif ($tab === 'orders'): ?>
    <div class="panel-card">
      <h2>我的订单</h2>
      <?php if (!$myOrders): ?>
        <p style="color:#7a8694;padding:20px 0">暂无订单</p>
      <?php else: ?>
        <table class="data-table">
          <tr><th>订单号</th><th>课程</th><th>金额</th><th>状态</th><th>支付方式</th><th>时间</th><th></th></tr>
          <?php foreach ($myOrders as $o): ?>
          <tr>
            <td style="font-size:12px"><?= e($o['order_no']) ?></td>
            <td><?= e($o['course_title']) ?></td>
            <td><?= $o['amount'] > 0 ? fmt_price((float)$o['amount']) : '—' ?></td>
            <td><span class="badge badge-<?= e($o['status']) ?>"><?= ['pending' => '待支付', 'paid' => '已支付', 'cancelled' => '已取消'][$o['status']] ?></span></td>
            <td><?= ['alipay' => '支付宝', 'wechat' => '微信', 'balance' => '余额', 'free' => '免费报名', '' => '—'][$o['pay_method']] ?? e($o['pay_method']) ?></td>
            <td style="font-size:12px"><?= e($o['created_at']) ?></td>
            <td>
              <?php if ($o['status'] === 'pending'): ?>
                <a class="btn btn-outline" style="padding:3px 12px;font-size:12px" href="<?= url('pay.php') ?>?order_no=<?= e($o['order_no']) ?>">去支付</a>
              <?php elseif ($o['status'] === 'paid'): ?>
                <a class="btn btn-ghost" style="padding:3px 12px;font-size:12px" href="<?= url('learn.php') ?>?course=<?= (int)$o['course_id'] ?>">学习</a>
              <?php endif; ?>
            </td>
          </tr>
          <?php endforeach; ?>
        </table>
      <?php endif; ?>
    </div>

    <?php else: ?>
    <div class="panel-card">
      <h2>账号资料</h2>
      <form method="post" style="max-width:420px">
        <?= csrf_field() ?>
        <input type="hidden" name="do" value="profile">
        <div class="form-item"><label>邮箱（不可修改）</label><input type="text" value="<?= e($user['email']) ?>" disabled></div>
        <div class="form-item"><label>昵称</label><input type="text" name="nickname" maxlength="30" value="<?= e($user['nickname']) ?>"></div>
        <div class="form-item"><label>手机号</label><input type="text" name="phone" maxlength="20" value="<?= e($user['phone']) ?>"></div>
        <button class="btn btn-primary" type="submit">保存资料</button>
      </form>
    </div>
    <div class="panel-card">
      <h2>修改密码</h2>
      <form method="post" style="max-width:420px">
        <?= csrf_field() ?>
        <input type="hidden" name="do" value="password">
        <div class="form-item"><label>原密码</label><input type="password" name="old_password" required></div>
        <div class="form-item"><label>新密码</label><input type="password" name="new_password" minlength="6" required></div>
        <div class="form-item"><label>确认新密码</label><input type="password" name="new_password2" minlength="6" required></div>
        <button class="btn btn-outline" type="submit">修改密码</button>
      </form>
    </div>
    <?php endif; ?>
  </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
