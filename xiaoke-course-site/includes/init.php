<?php
/**
 * 应用引导文件 —— 所有前台页面通过 require 'includes/init.php' 启动
 */

declare(strict_types=1);
error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE);
ini_set('display_errors', '1');
date_default_timezone_set('Asia/Shanghai');

session_start();

define('APP_ROOT', dirname(__DIR__));

// 未安装则跳转到安装向导
if (!file_exists(APP_ROOT . '/config.php')) {
    if (basename($_SERVER['SCRIPT_NAME']) !== 'install.php') {
        header('Location: install.php');
        exit;
    }
    return;
}

$GLOBALS['config'] = require APP_ROOT . '/config.php';

require __DIR__ . '/db.php';
require __DIR__ . '/functions.php';
require __DIR__ . '/mailer.php';

// ---- 自动升级：settings 表（老版本部署补充建表，幂等） ----
Db::run("CREATE TABLE IF NOT EXISTS `settings` (
  `skey` VARCHAR(60) NOT NULL,
  `svalue` TEXT,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`skey`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

// ---- 自动升级：orders 表补 trade_no 列（易支付流水号，幂等） ----
try {
    $col = Db::row("SHOW COLUMNS FROM `orders` LIKE 'trade_no'");
    if (!$col) {
        Db::run("ALTER TABLE `orders` ADD COLUMN `trade_no` VARCHAR(64) NOT NULL DEFAULT '' COMMENT '第三方支付平台流水号（易支付）' AFTER `pay_method`, ADD KEY `idx_trade_no` (`trade_no`)");
    }
} catch (Throwable $e) { /* orders 表不存在（未安装）时忽略 */ }

// ---- 自动升级：courses.cover 扩容（旧版 VARCHAR(30) 只存渐变编号，需容纳图片路径，幂等） ----
try {
    $col = Db::row("SHOW COLUMNS FROM `courses` LIKE 'cover'");
    if ($col && stripos((string)$col['Type'], 'varchar') === 0) {
        $len = (int)filter_var($col['Type'], FILTER_SANITIZE_NUMBER_INT);
        if ($len < 500) {
            Db::run("ALTER TABLE `courses` MODIFY `cover` VARCHAR(500) NOT NULL DEFAULT '' COMMENT '课程主图：uploads/covers/ 路径或图片外链，空则使用默认占位'");
            Db::run("UPDATE `courses` SET `cover` = '' WHERE `cover` LIKE 'g%' AND `cover` NOT LIKE '%/%' AND `cover` NOT LIKE '%.%'");
        }
    }
} catch (Throwable $e) {
    // 升级失败不阻断站点运行
}
