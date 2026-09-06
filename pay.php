<?php
/** 支付页：易支付（已配置时跳转第三方）/ 模拟支付（未配置时直接开通） */
require __DIR__ . '/includes/init.php';

$user = require_login();
$orderNo = trim($_GET['order_no'] ?? ($_POST['order_no'] ?? ''));
$order = Db::row("SELECT o.*, c.cover, c.teacher FROM orders o LEFT JOIN courses c ON c.id = o.course_id
                  WHERE o.order_no = ? AND o.user_id = ?", [$orderNo, $user['id']]);

if (!$order) { flash('error', '订单不存在'); redirect(url('user.php')); }

/* ============ 易支付模式 ============ */
if (epay_enabled()) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        verify_csrf();
        if ($order['status'] === 'paid') {
            redirect(url('learn.php') . '?course=' . (int)$order['course_id']);
        }
        // 支付方式映射：易支付 type
        $typeMap = ['alipay' => 'alipay', 'wechat' => 'wxpay', 'qq' => 'qqpay'];
        $type = $typeMap[$_POST['pay_method'] ?? ''] ?? 'alipay';

        // 组装易支付请求参数并签名
        $params = [
            'pid'          => setting('epay_pid'),
            'type'         => $type,
            'out_trade_no' => $order['order_no'],
            'notify_url'   => site_url(url('notify.php')),
            'return_url'   => site_url(url('pay.php') . '?order_no=' . $order['order_no']),
            'name'         => $order['course_title'],
            'money'        => number_format((float)$order['amount'], 2, '.', ''),
            'sign_type'    => 'MD5',
        ];
        $params['sign'] = epay_sign($params, setting('epay_key'));

        // 输出自动提交表单页，跳转到易支付收银台
        $action = rtrim(setting('epay_api'), '/') . '/submit.php';
        echo '<!DOCTYPE html><html lang="zh-CN"><head><meta charset="utf-8"><title>正在跳转到支付平台…</title></head><body>';
        echo '<form id="epayform" method="post" action="' . e($action) . '">';
        foreach ($params as $k => $v) {
            echo '<input type="hidden" name="' . e($k) . '" value="' . e($v) . '">';
        }
        echo '</form>';
        echo '<p style="font-family:sans-serif;text-align:center;padding:40px">正在跳转到支付平台，请稍候…<br><button type="submit" form="epayform" style="margin-top:14px;padding:8px 24px">如未自动跳转请点击这里</button></p>';
        echo '<script>document.getElementById("epayform").submit();</script>';
        echo '</body></html>';
        exit;
    }

    // 回跳（return_url）：展示支付结果
    $pageTitle = '订单支付';
    require __DIR__ . '/includes/header.php';
    ?>
    <div class="pay-wrap">
      <div class="panel-card">
        <?php if ($order['status'] === 'paid'): ?>
          <h2>支付成功</h2>
          <p style="color:#7a8694;margin:12px 0 20px">课程已开通，祝学习愉快！</p>
          <a class="btn btn-primary btn-lg btn-block" href="<?= url('learn.php') ?>?course=<?= (int)$order['course_id'] ?>">进入学习</a>
        <?php else: ?>
          <h2>确认支付</h2>
          <div style="margin:16px 0;padding:14px;background:#f8fafc;border-radius:10px">
            <div style="font-weight:600;margin-bottom:4px"><?= e($order['course_title']) ?></div>
            <div style="font-size:12px;color:#7a8694">订单号：<?= e($order['order_no']) ?> · 下单时间：<?= e($order['created_at']) ?></div>
          </div>
          <div class="pay-amount"><span class="num"><?= fmt_price((float)$order['amount']) ?></span></div>
          <form method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="order_no" value="<?= e($order['order_no']) ?>">
            <input type="hidden" name="pay_method" value="alipay">
            <div class="pay-methods">
              <div class="pay-method selected" data-method="alipay">支付宝</div>
              <div class="pay-method" data-method="wechat">微信支付</div>
              <div class="pay-method" data-method="qq">QQ钱包</div>
            </div>
            <button class="btn btn-primary btn-lg btn-block" type="submit">前往支付 <?= fmt_price((float)$order['amount']) ?></button>
            <p style="font-size:12px;color:#b6bec9;text-align:center;margin-top:12px">点击后将跳转到第三方支付平台完成付款</p>
          </form>
        <?php endif; ?>
      </div>
    </div>
    <?php
    require __DIR__ . '/includes/footer.php';
    exit;
}

/* ============ 模拟支付模式（未配置易支付时的默认演示流程） ============ */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    if ($order['status'] === 'paid') {
        redirect(url('learn.php') . '?course=' . (int)$order['course_id']);
    }
    $method = in_array($_POST['pay_method'] ?? '', ['alipay', 'wechat', 'balance'], true) ? $_POST['pay_method'] : 'alipay';

    // ===== 模拟支付：直接标记为已支付（真实环境在此对接支付宝/微信支付回调） =====
    Db::run("UPDATE orders SET status = 'paid', pay_method = ?, paid_at = NOW() WHERE id = ?", [$method, $order['id']]);
    Db::run('INSERT INTO enrollments (user_id, course_id, order_id) VALUES (?, ?, ?)',
        [$user['id'], (int)$order['course_id'], $order['id']]);
    Db::run('UPDATE courses SET student_count = student_count + 1 WHERE id = ?', [(int)$order['course_id']]);
    flash('success', '支付成功，课程已开通！');
    redirect(url('learn.php') . '?course=' . (int)$order['course_id']);
}

$pageTitle = '订单支付';
require __DIR__ . '/includes/header.php';
?>

<div class="pay-wrap">
  <div class="panel-card">
    <?php if ($order['status'] === 'paid'): ?>
      <h2>订单已支付</h2>
      <p style="color:#7a8694;margin:12px 0 20px">该订单已完成，可直接进入学习。</p>
      <a class="btn btn-primary btn-lg btn-block" href="<?= url('learn.php') ?>?course=<?= (int)$order['course_id'] ?>">进入学习</a>
    <?php else: ?>
      <h2>确认支付</h2>
      <div style="margin:16px 0;padding:14px;background:#f8fafc;border-radius:10px">
        <div style="font-weight:600;margin-bottom:4px"><?= e($order['course_title']) ?></div>
        <div style="font-size:12px;color:#7a8694">订单号：<?= e($order['order_no']) ?> · 下单时间：<?= e($order['created_at']) ?></div>
      </div>
      <div class="pay-amount"><span class="num"><?= fmt_price((float)$order['amount']) ?></span></div>
      <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="order_no" value="<?= e($order['order_no']) ?>">
        <input type="hidden" name="pay_method" value="alipay">
        <div class="pay-methods">
          <div class="pay-method selected" data-method="alipay">支付宝<small>（模拟）</small></div>
          <div class="pay-method" data-method="wechat">微信支付<small>（模拟）</small></div>
          <div class="pay-method" data-method="balance">余额支付<small>（模拟）</small></div>
        </div>
        <button class="btn btn-primary btn-lg btn-block" type="submit">确认支付 <?= fmt_price((float)$order['amount']) ?></button>
        <p style="font-size:12px;color:#b6bec9;text-align:center;margin-top:12px">演示系统：点击即视为支付成功，不会产生真实扣款</p>
      </form>
    <?php endif; ?>
  </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
