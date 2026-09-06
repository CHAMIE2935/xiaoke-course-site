<?php
/** 后台布局结尾 */
$user = current_user();
echo '</div></div>'; // .admin-main .admin-layout
echo '<div style="text-align:center;padding:16px;font-size:12px;color:#b6bec9">'
   . e($GLOBALS['config']['site']['name']) . ' 后台 · '
   . '<a href="' . url('index.php') . '" style="color:#7a8694">前台首页</a> · '
   . '<a href="' . url('logout.php') . '" style="color:#7a8694">退出登录（' . e($user['nickname'] ?: $user['email']) . '）</a>'
   . '</div></body></html>';
