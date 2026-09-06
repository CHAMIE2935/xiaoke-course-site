<?php
/**
 * 后台公共守卫与布局
 * 用法：在 admin/*.php 中先 require __DIR__ . '/_guard.php';
 * 然后输出页面内容，最后 require __DIR__ . '/_layout_end.php';
 */
require dirname(__DIR__) . '/includes/init.php';

$user = current_user();
if (!$user) redirect(url('admin/login.php'));
if ($user['role'] !== 'admin') {
    http_response_code(403);
    exit('403 - 无权访问管理后台');
}

$adminPage = basename($_SERVER['SCRIPT_NAME']);

function admin_header(string $title): void
{
    global $adminPage;
    $navs = [
        'index.php'      => ['📊', '仪表盘'],
        'courses.php'    => ['📚', '课程管理'],
        'categories.php' => ['🗂', '分类管理'],
        'orders.php'     => ['🧾', '订单管理'],
        'users.php'      => ['👥', '用户管理'],
        'settings.php'   => ['⚙️', '站点设置'],
    ];
    echo '<!DOCTYPE html><html lang="zh-CN"><head><meta charset="UTF-8">'
       . '<meta name="viewport" content="width=device-width, initial-scale=1.0">'
       . '<title>' . e($title) . ' - 后台管理</title>'
       . '<link rel="stylesheet" href="../assets/css/style.css"></head><body>'
       . '<div class="admin-layout"><aside class="admin-side">'
       . '<div class="brand">🛠 ' . e($GLOBALS['config']['site']['name']) . ' 后台</div><nav>';
    foreach ($navs as $file => [$ico, $label]) {
        $cls = $adminPage === $file ? ' class="active"' : '';
        echo '<a href="' . $file . '"' . $cls . '>' . $ico . ' ' . $label . '</a>';
    }
    echo '</nav></aside><div class="admin-main">';
    // 后台闪存消息
    $flashes = get_flashes();
    if ($flashes) {
        echo '<div class="flash-wrap">';
        foreach ($flashes as $f) {
            echo '<div class="flash flash-' . e($f['type']) . '">' . e($f['message']) . '</div>';
        }
        echo '</div>';
    }
}
