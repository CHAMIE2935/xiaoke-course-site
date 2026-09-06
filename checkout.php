<?php
/** 下单：免费课直接报名；付费课创建待支付订单 */
require __DIR__ . '/includes/init.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect(url('courses.php'));
verify_csrf();

$user = require_login();
$courseId = (int)($_POST['course_id'] ?? 0);
$course = Db::row('SELECT * FROM courses WHERE id = ? AND status = 1', [$courseId]);
if (!$course) { flash('error', '课程不存在'); redirect(url('courses.php')); }

if (has_enrolled((int)$user['id'], $courseId)) {
    redirect(url('learn.php') . '?course=' . $courseId);
}

$isFree = $course['is_free'] || (float)$course['price'] <= 0;

if ($isFree) {
    // 免费课：直接报名（order 记录金额 0、状态 paid）
    $orderNo = make_order_no();
    Db::run("INSERT INTO orders (order_no, user_id, course_id, course_title, amount, status, pay_method, paid_at)
             VALUES (?, ?, ?, ?, 0, 'paid', 'free', NOW())",
        [$orderNo, $user['id'], $courseId, $course['title']]);
    $orderId = (int)Db::conn()->lastInsertId();
    Db::run('INSERT INTO enrollments (user_id, course_id, order_id) VALUES (?, ?, ?)', [$user['id'], $courseId, $orderId]);
    Db::run('UPDATE courses SET student_count = student_count + 1 WHERE id = ?', [$courseId]);
    flash('success', '报名成功，开始学习吧！');
    redirect(url('learn.php') . '?course=' . $courseId);
}

// 付费课：创建待支付订单
$orderNo = make_order_no();
Db::run("INSERT INTO orders (order_no, user_id, course_id, course_title, amount, status)
         VALUES (?, ?, ?, ?, ?, 'pending')",
    [$orderNo, $user['id'], $courseId, $course['title'], $course['price']]);
redirect(url('pay.php') . '?order_no=' . $orderNo);
