<?php
/** 公共头部：导航、分类、搜索、用户区、闪存消息 */
$siteName = $GLOBALS['config']['site']['name'] ?? '小课学堂';
$user = current_user();
$navCategories = Db::all('SELECT id, name FROM categories ORDER BY sort, id');
$flashes = get_flashes();
$siteLogo = setting('site_logo');
$siteFavicon = setting('site_favicon');
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= isset($pageTitle) ? e($pageTitle) . ' - ' : '' ?><?= e($siteName) ?> - <?= e($GLOBALS['config']['site']['slogan'] ?? '让学习更高效') ?></title>
<?php if ($siteFavicon): ?>
<link rel="icon" href="<?= e(cover_url($siteFavicon)) ?>">
<?php endif; ?>
<link rel="stylesheet" href="<?= url('assets/css/style.css') ?>">
</head>
<body>
<header class="site-header">
  <div class="container header-inner">
    <a class="logo" href="<?= url('index.php') ?>">
      <?php if ($siteLogo): ?>
        <img class="logo-img" src="<?= e(cover_url($siteLogo)) ?>" alt="<?= e($siteName) ?>">
      <?php else: ?>
        <span class="logo-mark"><?= e(mb_substr($siteName, 0, 1)) ?></span>
      <?php endif; ?>
      <span class="logo-text"><?= e($siteName) ?></span>
    </a>
    <nav class="main-nav">
      <a href="<?= url('index.php') ?>">首页</a>
      <a href="<?= url('courses.php') ?>">全部课程</a>
      <?php foreach (array_slice($navCategories, 0, 4) as $c): ?>
        <a href="<?= url('courses.php') ?>?cat=<?= (int)$c['id'] ?>"><?= e($c['name']) ?></a>
      <?php endforeach; ?>
    </nav>
    <form class="search-box" action="<?= url('courses.php') ?>" method="get">
      <input type="text" name="q" placeholder="搜索课程 / 老师" value="<?= e($_GET['q'] ?? '') ?>">
      <button type="submit">搜索</button>
    </form>
    <div class="user-area">
      <?php if ($user): ?>
        <a class="btn btn-ghost" href="<?= url('user.php') ?>"><?= e($user['nickname'] ?: mb_substr($user['email'], 0, strpos($user['email'], '@'))) ?></a>
        <a class="btn btn-ghost" href="<?= url('logout.php') ?>">退出</a>
      <?php else: ?>
        <a class="btn btn-ghost" href="<?= url('login.php') ?>">登录</a>
        <a class="btn btn-primary" href="<?= url('register.php') ?>">注册</a>
      <?php endif; ?>
    </div>
  </div>
</header>

<?php if ($flashes): ?>
<div class="container flash-wrap">
  <?php foreach ($flashes as $f): ?>
    <div class="flash flash-<?= e($f['type']) ?>"><?= e($f['message']) ?></div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<main class="container">
