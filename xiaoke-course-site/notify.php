<?php
/**
 * 易支付异步通知（notify_url）
 *
 * 易支付服务器在用户支付成功后以 GET 方式回调本地址，参数含：
 * pid / trade_no / out_trade_no / type / name / money / trade_status / sign / sign_type
 *
 * 处理规则：验签通过 + 金额一致 + TRADE_SUCCESS → 订单置 paid、开通课程，输出 success
 * 已处理过的订单直接输出 success（幂等），其余情况输出 fail
 */
require __DIR__ . '/includes/init.php';

$params = $_GET ?: $_POST; // 兼容 GET / POST 两种回调方式

header('Content-Type: text/plain; charset=utf-8');
$fail = function () { echo 'fail'; exit; };

if (!epay_enabled()) $fail();

// 1. 验签
if (!epay_verify($params)) $fail();

// 2. 交易状态
if (($params['trade_status'] ?? '') !== 'TRADE_SUCCESS') $fail();

// 3. 商户号核对
if ((string)($params['pid'] ?? '') !== setting('epay_pid')) $fail();

// 4. 订单核对
$orderNo = trim((string)($params['out_trade_no'] ?? ''));
$order = Db::row('SELECT * FROM orders WHERE order_no = ?', [$orderNo]);
if (!$order) $fail();

// 5. 金额核对（误差 0.01 元内视为一致）
if (abs((float)($params['money'] ?? 0) - (float)$order['amount']) > 0.01) $fail();

// 6. 幂等：已支付直接返回 success
if ($order['status'] === 'paid') { echo 'success'; exit; }
if ($order['status'] === 'cancelled') $fail();

// 7. 标记支付成功并开通课程
$tradeNo = trim((string)($params['trade_no'] ?? ''));
$payMethod = 'epay_' . ($params['type'] ?? 'unknown');
Db::run("UPDATE orders SET status = 'paid', pay_method = ?, trade_no = ?, paid_at = NOW() WHERE id = ? AND status = 'pending'",
    [$payMethod, $tradeNo, $order['id']]);
Db::run('INSERT IGNORE INTO enrollments (user_id, course_id, order_id) VALUES (?, ?, ?)',
    [(int)$order['user_id'], (int)$order['course_id'], $order['id']]);
Db::run('UPDATE courses SET student_count = student_count + 1 WHERE id = ?', [(int)$order['course_id']]);

echo 'success';
