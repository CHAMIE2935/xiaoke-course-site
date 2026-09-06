<?php
/** 订单管理 */
require __DIR__ . '/_guard.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $oid = (int)($_POST['id'] ?? 0);
    $do = $_POST['do'] ?? '';
    $order = Db::row('SELECT * FROM orders WHERE id = ?', [$oid]);
    if ($order) {
        if ($do === 'cancel' && $order['status'] === 'pending') {
            Db::run("UPDATE orders SET status = 'cancelled' WHERE id = ?", [$oid]);
            flash('success', '订单已取消');
        } elseif ($do === 'mark_paid' && $order['status'] === 'pending') {
            Db::run("UPDATE orders SET status = 'paid', pay_method = 'manual', paid_at = NOW() WHERE id = ?", [$oid]);
            Db::run('INSERT IGNORE INTO enrollments (user_id, course_id, order_id) VALUES (?, ?, ?)',
                [$order['user_id'], $order['course_id'], $oid]);
            flash('success', '订单已标记为已支付并开通课程');
        }
    }
    redirect('orders.php');
}

$status = $_GET['status'] ?? '';
$sql = "SELECT o.*, u.email, u.nickname FROM orders o LEFT JOIN users u ON u.id = o.user_id";
$params = [];
if (in_array($status, ['pending', 'paid', 'cancelled'], true)) {
    $sql .= " WHERE o.status = ?";
    $params[] = $status;
}
$sql .= " ORDER BY o.id DESC LIMIT 200";
$orders = Db::all($sql, $params);

admin_header('订单管理');
?>
<div class="admin-topbar"><h1>订单管理</h1></div>

<div class="filter-bar" style="margin-bottom:14px">
  <a href="orders.php" class="<?= $status === '' ? 'active' : '' ?>">全部</a>
  <a href="orders.php?status=pending" class="<?= $status === 'pending' ? 'active' : '' ?>">待支付</a>
  <a href="orders.php?status=paid" class="<?= $status === 'paid' ? 'active' : '' ?>">已支付</a>
  <a href="orders.php?status=cancelled" class="<?= $status === 'cancelled' ? 'active' : '' ?>">已取消</a>
</div>

<div class="admin-card">
  <table class="data-table">
    <tr><th>订单号</th><th>用户</th><th>课程</th><th>金额</th><th>状态</th><th>支付方式</th><th>下单时间</th><th>操作</th></tr>
    <?php foreach ($orders as $o): ?>
    <tr>
      <td style="font-size:12px"><?= e($o['order_no']) ?></td>
      <td style="font-size:12px"><?= e($o['email'] ?: '-') ?><br><small style="color:#b6bec9"><?= e($o['nickname'] ?? '') ?></small></td>
      <td style="max-width:260px"><?= e($o['course_title']) ?></td>
      <td><?= $o['amount'] > 0 ? fmt_price((float)$o['amount']) : '—' ?></td>
      <td><span class="badge badge-<?= e($o['status']) ?>"><?= ['pending' => '待支付', 'paid' => '已支付', 'cancelled' => '已取消'][$o['status']] ?></span></td>
      <td><?= ['alipay' => '支付宝', 'wechat' => '微信', 'balance' => '余额', 'free' => '免费', 'manual' => '后台开通', '' => '—'][$o['pay_method']] ?? e($o['pay_method']) ?></td>
      <td style="font-size:12px"><?= e($o['created_at']) ?></td>
      <td>
        <?php if ($o['status'] === 'pending'): ?>
        <div class="table-actions">
          <form method="post" onsubmit="return confirm('确认标记为已支付并开通课程？')">
            <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$o['id'] ?>"><input type="hidden" name="do" value="mark_paid">
            <button type="submit">开通</button>
          </form>
          <form method="post" onsubmit="return confirm('确认取消该订单？')">
            <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$o['id'] ?>"><input type="hidden" name="do" value="cancel">
            <button type="submit" class="danger">取消</button>
          </form>
        </div>
        <?php else: ?>—<?php endif; ?>
      </td>
    </tr>
    <?php endforeach; ?>
  </table>
</div>
<?php require __DIR__ . '/_layout_end.php'; ?>
