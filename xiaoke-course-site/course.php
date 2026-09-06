<?php
/** 课程详情：信息 + 章节课时 + 购买入口 */
require __DIR__ . '/includes/init.php';

$id = (int)($_GET['id'] ?? 0);
$course = Db::row("SELECT c.*, cat.name AS cat_name FROM courses c LEFT JOIN categories cat ON cat.id = c.category_id WHERE c.id = ? AND c.status = 1", [$id]);
if (!$course) {
    http_response_code(404);
    $pageTitle = '课程不存在';
    require __DIR__ . '/includes/header.php';
    echo '<h1 class="page-title">课程不存在或已下架 <small><a href="' . url('courses.php') . '">返回课程中心</a></small></h1>';
    require __DIR__ . '/includes/footer.php';
    exit;
}

$chapters = Db::all('SELECT * FROM chapters WHERE course_id = ? ORDER BY sort, id', [$id]);
foreach ($chapters as &$ch) {
    $ch['lessons'] = Db::all('SELECT * FROM lessons WHERE chapter_id = ? ORDER BY sort, id', [$ch['id']]);
}
unset($ch);

$user = current_user();
$enrolled = $user ? has_enrolled((int)$user['id'], $id) : false;
$isFree = $course['is_free'] || (float)$course['price'] <= 0;

$pageTitle = $course['title'];
require __DIR__ . '/includes/header.php';
?>

<div class="course-detail">
  <div class="detail-main">
    <div class="detail-hero" style="<?= cover_banner_style($course) ?>">
      <h1><?= e($course['title']) ?></h1>
      <div class="sub"><?= e($course['subtitle']) ?></div>
      <div class="stats">
        <span><?= e($course['teacher']) ?></span>
        <span><?= (int)$course['lesson_count'] ?> 课时</span>
        <span><?= fmt_count($course['student_count']) ?> 人报名</span>
        <span><?= e($course['cat_name'] ?: '综合') ?></span>
      </div>
    </div>

    <div class="detail-desc">
      <h2>课程介绍</h2>
      <p><?= e($course['description'] ?: '暂无介绍') ?></p>
    </div>

    <div class="chapter-list">
      <h2 style="font-size:18px;margin:18px 0 14px">课程目录</h2>
      <?php if (!$chapters): ?>
        <p style="color:#7a8694;padding:20px 0">课程目录更新中…</p>
      <?php endif; ?>
      <?php foreach ($chapters as $ch): ?>
        <div class="chapter">
          <div class="chapter-title">
            <?= e($ch['title']) ?>
            <span class="cnt">共 <?= count($ch['lessons']) ?> 节</span>
          </div>
          <?php foreach ($ch['lessons'] as $ls): ?>
            <?php $canWatch = $enrolled || $ls['is_free_preview']; ?>
            <div class="lesson <?= $canWatch ? '' : 'locked' ?>">
              <span class="icon"><?= $canWatch ? '▶' : '🔒' ?></span>
              <span class="name"><?= e($ls['title']) ?></span>
              <?php if ($ls['is_free_preview'] && !$enrolled): ?><span class="preview-badge">试看</span><?php endif; ?>
              <span class="dur"><?= (int)$ls['duration'] ?> 分钟</span>
              <?php if ($canWatch): ?>
                <a href="<?= url('learn.php') ?>?course=<?= $id ?>&lesson=<?= (int)$ls['id'] ?>"><?= $enrolled ? '学习' : '试看' ?></a>
              <?php endif; ?>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endforeach; ?>
    </div>
  </div>

  <aside class="buy-card">
    <div class="price-line">
      <?php if ($isFree): ?>
        <span class="price"><span class="free">免费</span></span>
      <?php else: ?>
        <span class="price"><?= fmt_price((float)$course['price']) ?></span>
        <?php if ((float)$course['original_price'] > (float)$course['price']): ?>
          <span class="price" style="font-size:14px;color:#c0c7d0;text-decoration:line-through;font-weight:400"><?= fmt_price((float)$course['original_price']) ?></span>
        <?php endif; ?>
      <?php endif; ?>
    </div>
    <ul class="meta-list">
      <li><span>授课老师</span><span><?= e($course['teacher']) ?></span></li>
      <li><span>课时总数</span><span><?= (int)$course['lesson_count'] ?> 节</span></li>
      <li><span>课程有效期</span><span><?= e($course['validity_date'] ?: '长期有效') ?></span></li>
      <li><span>已报名人数</span><span><?= fmt_count($course['student_count']) ?></span></li>
    </ul>
    <?php if ($enrolled): ?>
      <p class="owner-note">✓ 你已拥有该课程，直接开始学习吧</p>
      <div class="actions">
        <a class="btn btn-primary btn-lg btn-block" href="<?= url('learn.php') ?>?course=<?= $id ?>">进入学习</a>
      </div>
    <?php elseif ($isFree): ?>
      <div class="actions">
        <?php if ($user): ?>
          <form method="post" action="<?= url('checkout.php') ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="course_id" value="<?= $id ?>">
            <button class="btn btn-primary btn-lg btn-block" type="submit">免费报名学习</button>
          </form>
        <?php else: ?>
          <a class="btn btn-primary btn-lg btn-block" href="<?= url('login.php') ?>?redirect=<?= urlencode(url('course.php') . '?id=' . $id) ?>">登录后免费学习</a>
        <?php endif; ?>
      </div>
    <?php else: ?>
      <div class="actions">
        <?php if ($user): ?>
          <form method="post" action="<?= url('checkout.php') ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="course_id" value="<?= $id ?>">
            <button class="btn btn-primary btn-lg btn-block" type="submit">立即购买</button>
          </form>
        <?php else: ?>
          <a class="btn btn-primary btn-lg btn-block" href="<?= url('login.php') ?>?redirect=<?= urlencode(url('course.php') . '?id=' . $id) ?>">登录后购买</a>
        <?php endif; ?>
      </div>
    <?php endif; ?>
  </aside>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
