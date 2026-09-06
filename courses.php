<?php
/** 课程列表：分类筛选 + 搜索 */
require __DIR__ . '/includes/init.php';

$cat = (int)($_GET['cat'] ?? 0);
$q = trim($_GET['q'] ?? '');
$sort = $_GET['sort'] ?? 'hot';

$categories = Db::all('SELECT * FROM categories ORDER BY sort, id');

$sql = "SELECT c.*, cat.name AS cat_name FROM courses c
        LEFT JOIN categories cat ON cat.id = c.category_id
        WHERE c.status = 1";
$params = [];
if ($cat > 0) { $sql .= " AND c.category_id = ?"; $params[] = $cat; }
if ($q !== '')  { $sql .= " AND (c.title LIKE ? OR c.teacher LIKE ?)"; $params[] = "%$q%"; $params[] = "%$q%"; }
$sql .= $sort === 'new' ? " ORDER BY c.created_at DESC" : " ORDER BY c.student_count DESC";

$courses = Db::all($sql, $params);

$pageTitle = '全部课程';
require __DIR__ . '/includes/header.php';
?>

<h1 class="page-title">课程中心
  <?php if ($q !== ''): ?><small>搜索“<?= e($q) ?>” 共 <?= count($courses) ?> 个结果</small>
  <?php else: ?><small>共 <?= count($courses) ?> 门课程</small><?php endif; ?>
</h1>

<div class="filter-bar">
  <a href="<?= url('courses.php') ?>" class="<?= $cat === 0 ? 'active' : '' ?>">全部</a>
  <?php foreach ($categories as $c): ?>
    <a href="<?= url('courses.php') ?>?cat=<?= (int)$c['id'] ?>" class="<?= $cat === (int)$c['id'] ? 'active' : '' ?>"><?= e($c['name']) ?></a>
  <?php endforeach; ?>
  <span style="flex:1"></span>
  <a href="<?= url('courses.php') ?>?<?= http_build_query(array_filter(['cat' => $cat ?: null, 'q' => $q ?: null])) ?>&sort=hot">最热</a>
  <a href="<?= url('courses.php') ?>?<?= http_build_query(array_filter(['cat' => $cat ?: null, 'q' => $q ?: null])) ?>&sort=new">最新</a>
</div>

<div class="course-grid" style="grid-template-columns:repeat(4,1fr)">
  <?php if (!$courses): ?>
    <p style="grid-column:1/-1;text-align:center;color:#7a8694;padding:60px 0">没有找到相关课程</p>
  <?php endif; ?>
  <?php foreach ($courses as $c): ?>
  <div class="course-card">
    <a href="<?= url('course.php') ?>?id=<?= (int)$c['id'] ?>">
      <div class="course-cover">
        <?php if ($c['is_free']): ?><span class="tag">免费</span><?php endif; ?>
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
          <?php if ($c['is_free'] || (float)$c['price'] <= 0): ?><span class="free">免费</span>
          <?php else: ?><?= fmt_price((float)$c['price']) ?>
            <?php if ((float)$c['original_price'] > (float)$c['price']): ?><span class="orig"><?= fmt_price((float)$c['original_price']) ?></span><?php endif; ?>
          <?php endif; ?>
        </div>
        <span class="student-num"><?= fmt_count($c['student_count']) ?>人报名</span>
      </div>
    </div>
  </div>
  <?php endforeach; ?>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
