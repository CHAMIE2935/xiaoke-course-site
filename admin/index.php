<?php
/** 后台仪表盘 */
require __DIR__ . '/_guard.php';

$stats = [
    '用户总数'   => (int)Db::value('SELECT COUNT(*) FROM users'),
    '课程总数'   => (int)Db::value('SELECT COUNT(*) FROM courses WHERE status = 1'),
    '订单总数'   => (int)Db::value('SELECT COUNT(*) FROM orders'),
    '销售额(已支付)' => (float)Db::value("SELECT COALESCE(SUM(amount),0) FROM orders WHERE status = 'paid' AND amount > 0"),
];
$recentOrders = Db::all(
    "SELECT o.*, u.email FROM orders o LEFT JOIN users u ON u.id = o.user_id ORDER BY o.id DESC LIMIT 10"
);
$topCourses = Db::all('SELECT id, title, price, student_count FROM courses ORDER BY student_count DESC LIMIT 5');

admin_header('仪表盘');
?>
<div class="admin-topbar"><h1>仪表盘</h1><span style="font-size:13px;color:#7a8694">今日：<?= date('Y-m-d') ?></span></div>

<div class="stat-grid">
  <div class="stat"><div class="num"><?= $stats['用户总数'] ?></div><div class="label">用户总数</div></div>
  <div class="stat"><div class="num"><?= $stats['课程总数'] ?></div><div class="label">在售课程</div></div>
  <div class="stat"><div class="num"><?= $stats['订单总数'] ?></div><div class="label">订单总数</div></div>
  <div class="stat"><div class="num" style="color:#ff4d4f">¥<?= number_format($stats['销售额(已支付)'], 2) ?></div><div class="label">销售额（模拟）</div></div>
</div>

<div class="admin-card" style="margin-bottom:20px">
  <h2 style="font-size:16px;margin-bottom:14px">最新订单</h2>
  <table class="data-table">
    <tr><th>订单号</th><th>用户</th><th>课程</th><th>金额</th><th>状态</th><th>时间</th></tr>
    <?php foreach ($recentOrders as $o): ?>
    <tr>
      <td style="font-size:12px"><?= e($o['order_no']) ?></td>
      <td style="font-size:12px"><?= e($o['email'] ?: '-') ?></td>
      <td><?= e($o['course_title']) ?></td>
      <td><?= $o['amount'] > 0 ? fmt_price((float)$o['amount']) : '—' ?></td>
      <td><span class="badge badge-<?= e($o['status']) ?>"><?= ['pending' => '待支付', 'paid' => '已支付', 'cancelled' => '已取消'][$o['status']] ?></span></td>
      <td style="font-size:12px"><?= e($o['created_at']) ?></td>
    </tr>
    <?php endforeach; ?>
  </table>
</div>

<div class="admin-card">
  <h2 style="font-size:16px;margin-bottom:14px">报名人数 TOP5</h2>
  <table class="data-table">
    <tr><th>课程</th><th>价格</th><th>报名人数</th></tr>
    <?php foreach ($topCourses as $c): ?>
    <tr>
      <td><a href="<?= url('course.php') ?>?id=<?= (int)$c['id'] ?>" target="_blank"><?= e($c['title']) ?></a></td>
      <td><?= $c['price'] > 0 ? fmt_price((float)$c['price']) : '免费' ?></td>
      <td><?= fmt_count($c['student_count']) ?></td>
    </tr>
    <?php endforeach; ?>
  </table>
</div>
<?php require __DIR__ . '/_layout_end.php'; ?>
