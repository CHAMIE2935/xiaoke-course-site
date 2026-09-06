<?php
/** 学习页：视频播放 + 课程目录 */
require __DIR__ . '/includes/init.php';

$user = require_login();
$courseId = (int)($_GET['course'] ?? 0);
$lessonId = (int)($_GET['lesson'] ?? 0);

$course = Db::row('SELECT * FROM courses WHERE id = ? AND status = 1', [$courseId]);
if (!$course) { flash('error', '课程不存在'); redirect(url('user.php')); }

$enrolled = has_enrolled((int)$user['id'], $courseId);
$chapters = Db::all('SELECT * FROM chapters WHERE course_id = ? ORDER BY sort, id', [$courseId]);
$allLessons = [];
foreach ($chapters as &$ch) {
    $ch['lessons'] = Db::all('SELECT * FROM lessons WHERE chapter_id = ? ORDER BY sort, id', [$ch['id']]);
    foreach ($ch['lessons'] as $l) $allLessons[$l['id']] = $l;
}
unset($ch);

// 当前课时：优先指定ID，否则第一节
$current = null;
if ($lessonId && isset($allLessons[$lessonId])) $current = $allLessons[$lessonId];
if (!$current && $allLessons) $current = reset($allLessons);

// 权限：已购 或 课时允许试看
$canWatch = false;
if ($current) {
    $canWatch = $enrolled || $current['is_free_preview'];
}

$pageTitle = $current ? $current['title'] . ' - ' . $course['title'] : $course['title'];
require __DIR__ . '/includes/header.php';
?>

<div class="learn-layout">
  <div>
    <div class="player-card">
      <?php if ($current && $canWatch && $current['video_url']): ?>
        <?php $videoSrc = preg_match('#^https?://#i', $current['video_url']) ? $current['video_url'] : url($current['video_url']); ?>
        <video controls preload="metadata" src="<?= e($videoSrc) ?>"></video>
      <?php else: ?>
        <div style="aspect-ratio:16/9;display:flex;align-items:center;justify-content:center;color:#8b95a3;font-size:15px;text-align:center;padding:20px">
          <?= $current ? '🔒 该课时需要购买课程后观看' : '课程目录更新中' ?>
        </div>
      <?php endif; ?>
    </div>
    <div class="player-info">
      <h1><?= e($current ? $current['title'] : $course['title']) ?></h1>
      <p>
        <?php if (!$enrolled): ?>
          <a class="btn btn-primary" href="<?= url('course.php') ?>?id=<?= $courseId ?>" style="margin-right:10px">购买完整课程</a>
          当前为试看模式
        <?php else: ?>
          📖 <?= e($course['title']) ?> · 👨‍🏫 <?= e($course['teacher']) ?> · 课程有效期至 <?= e($course['validity_date'] ?: '长期') ?>
        <?php endif; ?>
      </p>
    </div>
  </div>

  <aside class="lesson-menu">
    <h3>📁 课程目录</h3>
    <?php foreach ($chapters as $ch): ?>
      <div class="chapter">
        <div class="chapter-title"><?= e($ch['title']) ?></div>
        <?php foreach ($ch['lessons'] as $l):
            $ok = $enrolled || $l['is_free_preview'];
            $isCurrent = $current && $current['id'] == $l['id']; ?>
          <div class="lesson <?= $isCurrent ? 'current' : '' ?>">
            <?php if ($ok): ?>
              <a href="<?= url('learn.php') ?>?course=<?= $courseId ?>&lesson=<?= (int)$l['id'] ?>" style="color:inherit;flex:1">▶ <?= e($l['title']) ?></a>
            <?php else: ?>
              <span style="flex:1">🔒 <?= e($l['title']) ?></span>
            <?php endif; ?>
            <span class="dur"><?= (int)$l['duration'] ?>′</span>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endforeach; ?>
  </aside>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
