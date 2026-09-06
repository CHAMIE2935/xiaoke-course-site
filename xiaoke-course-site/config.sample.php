<?php
/**
 * 配置文件模板 —— 实际的 config.php 由 install.php 安装向导自动生成，请勿手工修改此文件。
 */

return [
    // 数据库
    'db' => [
        'host'     => '127.0.0.1',
        'port'     => 3306,
        'name'     => 'xiaoke_course',
        'user'     => 'root',
        'pass'     => '',
        'charset'  => 'utf8mb4',
    ],
    // 站点
    'site' => [
        'name'     => '小课学堂',
        'slogan'   => '让学习更高效',
        'url'      => '', // 留空则自动探测
    ],
    // 邮件：mode = smtp 时使用下方 SMTP 配置；mode = log 时为演示模式，验证码直接显示在页面（仅建议测试环境）
    'mail' => [
        'mode'       => 'log',
        'from'       => 'noreply@example.com',
        'from_name'  => '小课学堂',
        'host'       => 'smtp.example.com',
        'port'       => 465,
        'username'   => '',
        'password'   => '',
        'secure'     => 'ssl', // ssl / tls / none
    ],
    'installed_at' => null,
];
