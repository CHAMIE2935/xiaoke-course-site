-- =====================================================
-- 小课学堂 - 知识付费网课平台 数据库结构 + 演示数据
-- 适用于 MySQL 5.7+ / 8.0 / MariaDB 10.3+
-- 由 install.php 一键安装向导自动导入
-- =====================================================

SET NAMES utf8mb4;

-- ---------------------------------------------------
-- 用户表
-- ---------------------------------------------------
CREATE TABLE IF NOT EXISTS `users` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `email` VARCHAR(190) NOT NULL,
  `password_hash` VARCHAR(255) NOT NULL,
  `nickname` VARCHAR(60) NOT NULL DEFAULT '',
  `phone` VARCHAR(20) NOT NULL DEFAULT '',
  `role` ENUM('user','admin') NOT NULL DEFAULT 'user',
  `status` TINYINT(1) NOT NULL DEFAULT 1 COMMENT '1正常 0禁用',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `last_login_at` DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------
-- 邮箱验证码表
-- ---------------------------------------------------
CREATE TABLE IF NOT EXISTS `email_codes` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `email` VARCHAR(190) NOT NULL,
  `code` VARCHAR(10) NOT NULL,
  `purpose` ENUM('register','reset') NOT NULL DEFAULT 'register',
  `expires_at` DATETIME NOT NULL,
  `used` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------
-- 课程分类
-- ---------------------------------------------------
CREATE TABLE IF NOT EXISTS `categories` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(60) NOT NULL,
  `sort` INT NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------
-- 课程表
-- ---------------------------------------------------
CREATE TABLE IF NOT EXISTS `courses` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `category_id` INT UNSIGNED NOT NULL,
  `title` VARCHAR(190) NOT NULL,
  `subtitle` VARCHAR(255) NOT NULL DEFAULT '',
  `teacher` VARCHAR(60) NOT NULL DEFAULT '',
  `cover` VARCHAR(500) NOT NULL DEFAULT '' COMMENT '课程主图：uploads/covers/ 路径或图片外链，空则使用默认占位',
  `description` TEXT,
  `price` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `original_price` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `is_free` TINYINT(1) NOT NULL DEFAULT 0,
  `lesson_count` INT NOT NULL DEFAULT 0,
  `student_count` INT UNSIGNED NOT NULL DEFAULT 0,
  `validity_date` DATE DEFAULT NULL COMMENT '课程有效期',
  `is_hot` TINYINT(1) NOT NULL DEFAULT 0,
  `status` TINYINT(1) NOT NULL DEFAULT 1 COMMENT '1上架 0下架',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_cat` (`category_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------
-- 章节表
-- ---------------------------------------------------
CREATE TABLE IF NOT EXISTS `chapters` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `course_id` INT UNSIGNED NOT NULL,
  `title` VARCHAR(190) NOT NULL,
  `sort` INT NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_course` (`course_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------
-- 课时表
-- ---------------------------------------------------
CREATE TABLE IF NOT EXISTS `lessons` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `chapter_id` INT UNSIGNED NOT NULL,
  `course_id` INT UNSIGNED NOT NULL,
  `title` VARCHAR(190) NOT NULL,
  `video_url` VARCHAR(500) NOT NULL DEFAULT '',
  `duration` INT NOT NULL DEFAULT 0 COMMENT '分钟',
  `is_free_preview` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '可免费试看',
  `sort` INT NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_chapter` (`chapter_id`),
  KEY `idx_course` (`course_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------
-- 订单表
-- ---------------------------------------------------
CREATE TABLE IF NOT EXISTS `orders` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `order_no` VARCHAR(32) NOT NULL,
  `user_id` INT UNSIGNED NOT NULL,
  `course_id` INT UNSIGNED NOT NULL,
  `course_title` VARCHAR(190) NOT NULL DEFAULT '',
  `amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `status` ENUM('pending','paid','cancelled') NOT NULL DEFAULT 'pending',
  `pay_method` VARCHAR(20) NOT NULL DEFAULT '',
  `trade_no` VARCHAR(64) NOT NULL DEFAULT '' COMMENT '第三方支付平台流水号（易支付）',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `paid_at` DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_order_no` (`order_no`),
  KEY `idx_user` (`user_id`),
  KEY `idx_trade_no` (`trade_no`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------
-- 购课记录表（用户已购课程）
-- ---------------------------------------------------
CREATE TABLE IF NOT EXISTS `enrollments` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED NOT NULL,
  `course_id` INT UNSIGNED NOT NULL,
  `order_id` INT UNSIGNED NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_user_course` (`user_id`,`course_id`),
  KEY `idx_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------
-- 站点设置表（Logo / 图标 / 首页 Banner 等后台可配置项）
-- ---------------------------------------------------
CREATE TABLE IF NOT EXISTS `settings` (
  `skey` VARCHAR(60) NOT NULL,
  `svalue` TEXT,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`skey`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- 演示数据
-- =====================================================

INSERT INTO `categories` (`id`,`name`,`sort`) VALUES
(1,'计算机二级',1),
(2,'计算机一级',2),
(3,'期末考试',3),
(4,'计算机三/四级',4),
(5,'办公技能',5);

-- 默认管理员账号由 install.php 安装向导创建（邮箱/密码可自定义，密码使用 PHP password_hash 加密存储）

INSERT INTO `courses` (`id`,`category_id`,`title`,`subtitle`,`teacher`,`cover`,`description`,`price`,`original_price`,`is_free`,`lesson_count`,`student_count`,`validity_date`,`is_hot`,`status`) VALUES
(1,1,'26年9月计算机二级MS Office考前紧急救援','高频考点串讲 + 应试技巧速成','小柯老师','','考前冲刺必备：选择题高频考点速记、操作题套路模板、真题精讲三合一，短时间快速提分。',0.00,0.00,1,12,17900,'2029-12-31',1,1),
(2,1,'26年9月计算机二级WPS Office考前紧急救援','WPS考点全覆盖 + 押题冲刺','小柯老师','','针对WPS Office考试特点，覆盖文字、表格、演示三大模块高频考点，附赠考前密押卷。',0.00,0.00,1,10,20800,'2029-12-31',1,1),
(3,1,'二级MS考前押题班（内含考前密押真题+15套精选真题）','押题精准，短期突破40分','林老师','','精选15套真题逐题精讲，配套考前密押试卷，帮助考生在最后阶段稳拿操作题分数。',39.90,69.90,0,18,823,'2029-12-31',1,1),
(4,1,'二级WPS刷题班（含新考点预测+超高频押题）','题海战术 + 智能押题','林老师','','以题带点，覆盖WPS全部新考点，超高频押题命中率业内领先。',39.90,69.90,0,16,1203,'2029-12-31',1,1),
(5,1,'计算机二级 MS Office 真题解析（1-17套）','百万学员严选的真题精讲课','小柯老师','','逐套精讲历年真题，每道操作题都有标准演示，跟着做就能过。',0.00,0.00,1,48,1622000,'2029-12-31',0,1),
(6,1,'3小时学完二级Word高频考点','讲练结合，掌握核心操作','小柯老师','','浓缩Word核心考点：样式、目录、页眉页脚、邮件合并、表格图文混排，3小时搞定。',0.00,0.00,1,17,733500,'2029-12-31',0,1),
(7,2,'计算机一级MS Office真题讲解','零基础友好，真题全解','悠悠老师','','覆盖一级全部真题，操作步骤演示清晰，适合零基础学员一次通关。',0.00,0.00,1,21,304200,'2030-12-31',0,1),
(8,2,'必拿15分！一级MS/WPS选择题1小时通关精讲课','选择题满分速训','小柯老师','','浓缩选择题高频知识点，口诀记忆法，1小时拿下15分。',0.00,29.90,1,6,51000,'2030-08-31',0,1),
(9,3,'期末冲刺：3小时学完Python期末考试','大学生Python速成','许老师','','针对期末考试考点：基础语法、列表字典、函数、文件操作、简单算法题全覆盖。',0.00,0.00,1,11,10900,'2029-12-31',0,1),
(10,3,'期末冲刺：6小时突击高数（上）','极限/导数/积分一网打尽','蔡老师','','高等数学上册核心章节串讲，配套典型例题精练，期末不挂科。',0.00,0.00,1,18,5600,'2029-12-31',0,1),
(11,4,'软考中级网络工程师VIP全程班','155节系统课 + 督学答疑','大林老师','','零基础直达软考中级：理论精讲、实验演练、真题精析、考前冲刺四阶段全程服务。',619.00,799.00,0,155,244,'2028-09-30',1,1),
(12,5,'Excel职场实战：从入门到数据看板','效率翻倍的核心技能','王老师','','数据清洗、函数进阶、透视表、图表美化、动态看板，五大模块助你成为Excel高手。',99.00,199.00,0,36,8600,'2029-06-30',0,1);

INSERT INTO `chapters` (`id`,`course_id`,`title`,`sort`) VALUES
(1,1,'第一章 考情分析与备考规划',1),
(2,1,'第二章 选择题高频考点速记',2),
(3,1,'第三章 操作题套路模板',3),
(4,3,'第一章 押题方法论',1),
(5,3,'第二章 真题精讲（1-15套）',2),
(6,5,'第1套 真题解析',1),
(7,5,'第2套 真题解析',2),
(8,7,'第一章 一级考情介绍',1),
(9,7,'第二章 真题操作演示',2),
(10,11,'第一章 网络基础理论',1),
(11,11,'第二章 重点协议精讲',2),
(12,12,'第一章 数据清洗与规范',1),
(13,12,'第二章 函数进阶',2);

INSERT INTO `lessons` (`id`,`chapter_id`,`course_id`,`title`,`video_url`,`duration`,`is_free_preview`,`sort`) VALUES
(1,1,1,'1.1 二级MS考试结构与分值分布','https://interactive-examples.mdn.mozilla.net/media/cc0-videos/flower.mp4',15,1,1),
(2,1,1,'1.2 30天备考计划怎么排','https://interactive-examples.mdn.mozilla.net/media/cc0-videos/flower.mp4',18,1,2),
(3,2,1,'2.1 计算机基础知识高频考点（一）','https://interactive-examples.mdn.mozilla.net/media/cc0-videos/flower.mp4',25,0,3),
(4,2,1,'2.2 计算机基础知识高频考点（二）','https://interactive-examples.mdn.mozilla.net/media/cc0-videos/flower.mp4',25,0,4),
(5,3,1,'3.1 Word操作题模板：邀请函','https://interactive-examples.mdn.mozilla.net/media/cc0-videos/flower.mp4',30,0,5),
(6,3,1,'3.2 Excel操作题模板：成绩统计','https://interactive-examples.mdn.mozilla.net/media/cc0-videos/flower.mp4',30,0,6),
(7,4,3,'1.1 押题班使用说明与提分策略','https://interactive-examples.mdn.mozilla.net/media/cc0-videos/flower.mp4',12,1,1),
(8,4,3,'1.2 密押卷做题节奏','https://interactive-examples.mdn.mozilla.net/media/cc0-videos/flower.mp4',15,0,2),
(9,5,3,'2.1 第1套真题精讲（选择题部分）','https://interactive-examples.mdn.mozilla.net/media/cc0-videos/flower.mp4',40,1,3),
(10,5,3,'2.2 第1套真题精讲（操作题部分）','https://interactive-examples.mdn.mozilla.net/media/cc0-videos/flower.mp4',50,0,4),
(11,6,5,'1.1 第1套选择题解析','https://interactive-examples.mdn.mozilla.net/media/cc0-videos/flower.mp4',35,1,1),
(12,6,5,'1.2 第1套Word操作题解析','https://interactive-examples.mdn.mozilla.net/media/cc0-videos/flower.mp4',45,1,2),
(13,7,5,'2.1 第2套选择题解析','https://interactive-examples.mdn.mozilla.net/media/cc0-videos/flower.mp4',35,0,3),
(14,8,7,'1.1 一级MS考情全解','https://interactive-examples.mdn.mozilla.net/media/cc0-videos/flower.mp4',20,1,1),
(15,8,7,'1.2 一级WPS与MS的区别','https://interactive-examples.mdn.mozilla.net/media/cc0-videos/flower.mp4',18,1,2),
(16,9,7,'2.1 真题操作：Windows基本操作','https://interactive-examples.mdn.mozilla.net/media/cc0-videos/flower.mp4',25,0,3),
(17,10,11,'1.1 网络体系结构与设备基础','https://interactive-examples.mdn.mozilla.net/media/cc0-videos/flower.mp4',35,1,1),
(18,10,11,'1.2 IP地址与子网划分','https://interactive-examples.mdn.mozilla.net/media/cc0-videos/flower.mp4',40,0,2),
(19,11,11,'2.1 TCP/IP协议族精讲','https://interactive-examples.mdn.mozilla.net/media/cc0-videos/flower.mp4',45,0,3),
(20,12,12,'1.1 数据规范与清洗三板斧','https://interactive-examples.mdn.mozilla.net/media/cc0-videos/flower.mp4',28,1,1),
(21,12,12,'1.2 Power Query入门','https://interactive-examples.mdn.mozilla.net/media/cc0-videos/flower.mp4',32,0,2),
(22,13,12,'2.1 VLOOKUP与XLOOKUP实战','https://interactive-examples.mdn.mozilla.net/media/cc0-videos/flower.mp4',30,1,3),
(23,13,12,'2.2 SUMIFS与数据统计','https://interactive-examples.mdn.mozilla.net/media/cc0-videos/flower.mp4',30,0,4);
