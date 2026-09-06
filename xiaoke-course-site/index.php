<?php
/** 首页：Banner + 热门课程 + 分类专区 */
require __DIR__ . '/includes/init.php';

$hotCourses = Db::all(
    "SELECT c.*, cat.name AS cat_name FROM courses c
     LEFT JOIN categories cat ON cat.id = c.category_id
     WHERE c.status = 1 AND c.is_hot = 1 ORDER BY c.student_count DESC LIMIT 3"
);
$categories = Db::all('SELECT * FROM categories ORDER BY sort, id');
$sections = [];
foreach ($categories as $cat) {
    $courses = Db::all(
        "SELECT * FROM courses WHERE status = 1 AND category_id = ? ORDER BY student_count DESC LIMIT 4",
        [$cat['id']]
    );
    if ($courses) $sections[] = ['cat' => $cat, 'courses' => $courses];
}

$pageTitle = '首页';

$heroImage = setting('hero_image');
$heroTitle = setting('hero_title', '好课不贵，学即所用');
$heroSubtitle = setting('hero_subtitle', '计算机等级考试 · 期末冲刺 · 办公技能，一站式备考平台');
$heroBtnText = setting('hero_btn_text', '浏览全部课程 →');
$heroBtnLink = setting('hero_btn_link') ?: url('courses.php');

require __DIR__ . '/includes/header.php';
?>

<section class="hero <?= $heroImage ? 'hero-with-img' : '' ?>"
         <?= $heroImage ? 'style="background-image:url(\'' . e(cover_url($heroImage)) . '\')"' : '' ?>>
  <div class="hero-content">
    <h1><?= e($heroTitle) ?></h1>
    <p><?= e($heroSubtitle) ?></p>
    <a class="btn btn-lg" href="<?= e($heroBtnLink) ?>"><?= e($heroBtnText) ?></a>
  </div>
</section>

<section class="section">
  <div class="section-head">
    <h2><span class="bar"></span>热门课程</h2>
  </div>
  <div class="hot-strip">
    <?php foreach ($hotCourses as $c): ?>
    <div class="hot-card">
      <?php if (cover_is_image($c['cover'])): ?>
        <img class="hot-thumb" src="<?= e(cover_url($c['cover'])) ?>" alt="<?= e($c['title']) ?>" loading="lazy">
      <?php else: ?>
        <div class="hot-badge"><?= e(mb_substr($c['cat_name'] ?: $c['title'], 0, 2)) ?></div>
      <?php endif; ?>
      <div class="info">
        <h3><a href="<?= url('course.php') ?>?id=<?= (int)$c['id'] ?>"><?= e($c['title']) ?></a></h3>
        <p><?= fmt_count($c['student_count']) ?> 人报名 · <?= e($c['teacher']) ?></p>
      </div>
      <a class="btn btn-outline buy" href="<?= url('course.php') ?>?id=<?= (int)$c['id'] ?>">
        <?= $c['is_free'] ? '免费学' : fmt_price((float)$c['price']) ?>
      </a>
    </div>
    <?php endforeach; ?>
  </div>
</section>

<?php foreach ($sections as $sec): ?>
<section class="section">
  <div class="section-head">
    <h2><span class="bar"></span><?= e($sec['cat']['name']) ?></h2>
    <a href="<?= url('courses.php') ?>?cat=<?= (int)$sec['cat']['id'] ?>">查看更多 →</a>
  </div>
  <div class="course-grid">
    <?php foreach ($sec['courses'] as $c): ?>
    <div class="course-card">
      <a href="<?= url('course.php') ?>?id=<?= (int)$c['id'] ?>">
        <div class="course-cover">
          <?php if ($c['is_free']): ?><span class="tag">免费</span>
          <?php elseif ($c['original_price'] > $c['price']): ?><span class="tag">优惠</span><?php endif; ?>
          <?= course_cover_html($c) ?>
        </div>
      </a>
      <div class="course-body">
        <h3><a href="<?= url('course.php') ?>?id=<?= (int)$c['id'] ?>"><?= e($c['title']) ?></a></h3>
        <div class="course-meta">
          <span>有效期：<?= e($c['validity_date'] ?: '长期') ?></span>
          <span>课时：<?= (int)$c['lesson_count'] ?>节</span>
        </div>
        <div class="course-meta"><span class="teacher"><?= e($c['teacher']) ?></span></div>
        <div class="course-foot">
          <div class="price">
            <?php if ($c['is_free'] || (float)$c['price'] <= 0): ?>
              <span class="free">免费</span>
            <?php else: ?>
              <?= fmt_price((float)$c['price']) ?>
              <?php if ((float)$c['original_price'] > (float)$c['price']): ?>
                <span class="orig"><?= fmt_price((float)$c['original_price']) ?></span>
              <?php endif; ?>
            <?php endif; ?>
          </div>
          <span class="student-num"><?= fmt_count($c['student_count']) ?>人报名</span>
        </div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
</section>
<?php endforeach; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
