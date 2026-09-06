<?php
/** 用户管理 */
require __DIR__ . '/_guard.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $uid = (int)($_POST['id'] ?? 0);
    $do = $_POST['do'] ?? '';
    $target = Db::row('SELECT * FROM users WHERE id = ?', [$uid]);
    if ($target && $target['role'] !== 'admin') { // 不允许操作管理员
        if ($do === 'toggle') {
            Db::run('UPDATE users SET status = 1 - status WHERE id = ?', [$uid]);
            flash('success', '用户状态已更新');
        } elseif ($do === 'reset_pwd') {
            $newPwd = 'xk' . random_int(100000, 999999);
            Db::run('UPDATE users SET password_hash = ? WHERE id = ?', [password_hash($newPwd, PASSWORD_DEFAULT), $uid]);
            flash('success', "已重置 {$target['email']} 的密码为：{$newPwd}（请及时告知用户）");
        }
    } elseif ($target) {
        flash('error', '不能对管理员账号执行该操作');
    }
    redirect('users.php');
}

$users = Db::all(
    "SELECT u.*,
        (SELECT COUNT(*) FROM enrollments e WHERE e.user_id = u.id) AS course_count,
        (SELECT COALESCE(SUM(o.amount),0) FROM orders o WHERE o.user_id = u.id AND o.status = 'paid') AS paid_total
     FROM users u ORDER BY u.id DESC LIMIT 300"
);

admin_header('用户管理');
?>
<div class="admin-topbar"><h1>用户管理</h1></div>

<div class="admin-card">
  <table class="data-table">
    <tr><th>ID</th><th>邮箱</th><th>昵称</th><th>角色</th><th>已购课程</th><th>消费金额</th><th>注册时间</th><th>状态</th><th>操作</th></tr>
    <?php foreach ($users as $u): ?>
    <tr>
      <td><?= (int)$u['id'] ?></td>
      <td><?= e($u['email']) ?></td>
      <td><?= e($u['nickname'] ?: '-') ?></td>
      <td><span class="badge badge-<?= $u['role'] ?>"><?= $u['role'] === 'admin' ? '管理员' : '用户' ?></span></td>
      <td><?= (int)$u['course_count'] ?></td>
      <td><?= number_format((float)$u['paid_total'], 2) ?></td>
      <td style="font-size:12px"><?= e($u['created_at']) ?></td>
      <td><span class="badge badge-<?= $u['status'] ? 'on' : 'off' ?>"><?= $u['status'] ? '正常' : '禁用' ?></span></td>
      <td>
        <?php if ($u['role'] !== 'admin'): ?>
        <div class="table-actions">
          <form method="post" onsubmit="return confirm('确认<?= $u['status'] ? '禁用' : '启用' ?>该用户？')">
            <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$u['id'] ?>"><input type="hidden" name="do" value="toggle">
            <button type="submit"><?= $u['status'] ? '禁用' : '启用' ?></button>
          </form>
          <form method="post" onsubmit="return confirm('确认重置该用户密码为随机密码？')">
            <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$u['id'] ?>"><input type="hidden" name="do" value="reset_pwd">
            <button type="submit">重置密码</button>
          </form>
        </div>
        <?php else: ?>—<?php endif; ?>
      </td>
    </tr>
    <?php endforeach; ?>
  </table>
</div>
<?php require __DIR__ . '/_layout_end.php'; ?>
