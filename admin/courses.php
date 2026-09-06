<?php
/** 课程管理：列表 + 新增/编辑 + 删除 + 上下架 */
require __DIR__ . '/_guard.php';

$action = $_GET['action'] ?? 'list';
$id = (int)($_GET['id'] ?? 0);
$categories = Db::all('SELECT * FROM categories ORDER BY sort, id');

/* ---------- 删除 ---------- */
if ($action === 'delete' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    Db::run('DELETE FROM lessons WHERE course_id = ?', [$id]);
    Db::run('DELETE FROM chapters WHERE course_id = ?', [$id]);
    Db::run('DELETE FROM courses WHERE id = ?', [$id]);
    flash('success', '课程已删除');
    redirect('courses.php');
}

/* ---------- 上下架 ---------- */
if ($action === 'toggle' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    Db::run('UPDATE courses SET status = 1 - status WHERE id = ?', [$id]);
    flash('success', '状态已更新');
    redirect('courses.php');
}

/* ---------- 保存（新增/编辑） ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($action === 'edit' || $action === 'new')) {
    verify_csrf();
    $old = $id ? Db::row('SELECT cover FROM courses WHERE id = ?', [$id]) : null;

    // 课程主图上传（可选）；也可填写外链
    $coverUrl = trim($_POST['cover_url'] ?? '');
    if (!empty($_POST['clear_cover'])) {
        $cover = '';
    } else {
        [$uploaded, $err] = save_image_upload($_FILES['cover_file'] ?? [], 'covers', ['jpg', 'jpeg', 'png', 'webp', 'gif']);
        if ($err) flash('error', '课程主图上传失败：' . $err);
        $cover = $uploaded ?: ($coverUrl !== '' ? $coverUrl : ($old['cover'] ?? ''));
    }

    $data = [
        'category_id'    => (int)($_POST['category_id'] ?? 1),
        'title'          => trim($_POST['title'] ?? ''),
        'subtitle'       => trim($_POST['subtitle'] ?? ''),
        'teacher'        => trim($_POST['teacher'] ?? ''),
        'cover'          => $cover,
        'description'    => trim($_POST['description'] ?? ''),
        'price'          => max(0, (float)($_POST['price'] ?? 0)),
        'original_price' => max(0, (float)($_POST['original_price'] ?? 0)),
        'is_free'        => (float)($_POST['price'] ?? 0) <= 0 ? 1 : 0,
        'lesson_count'   => max(0, (int)($_POST['lesson_count'] ?? 0)),
        'validity_date'  => $_POST['validity_date'] ?: null,
        'is_hot'         => isset($_POST['is_hot']) ? 1 : 0,
    ];
    if ($data['title'] === '') {
        flash('error', '课程标题不能为空');
        redirect('courses.php?action=' . ($id ? "edit&id=$id" : 'new'));
    }
    if ($id) {
        $set = implode(', ', array_map(fn($k) => "`$k` = ?", array_keys($data)));
        $params = array_values($data);
        $params[] = $id;
        Db::run("UPDATE courses SET $set WHERE id = ?", $params);
        // 旧图为本地文件且被替换时清理
        if ($old && $old['cover'] !== $data['cover']) delete_uploaded_image($old['cover']);
        flash('success', '课程已更新');
    } else {
        $cols = implode(', ', array_map(fn($k) => "`$k`", array_keys($data)));
        $marks = implode(', ', array_fill(0, count($data), '?'));
        Db::run("INSERT INTO courses ($cols) VALUES ($marks)", array_values($data));
        flash('success', '课程已创建');
    }
    redirect('courses.php');
}

/* ---------- 列表 ---------- */
if ($action === 'list') {
    $courses = Db::all(
        "SELECT c.*, cat.name AS cat_name FROM courses c
         LEFT JOIN categories cat ON cat.id = c.category_id
         ORDER BY c.id DESC"
    );
    admin_header('课程管理');
    ?>
    <div class="admin-topbar"><h1>课程管理</h1><a class="btn btn-primary" href="courses.php?action=new">+ 新增课程</a></div>
    <div class="admin-card">
      <table class="data-table">
        <tr><th>ID</th><th>课程</th><th>分类</th><th>价格</th><th>报名</th><th>状态</th><th>操作</th></tr>
        <?php foreach ($courses as $c): ?>
        <tr>
          <td><?= (int)$c['id'] ?></td>
          <td style="max-width:320px">
            <div style="display:flex;gap:10px;align-items:center">
              <?php if (cover_is_image($c['cover'])): ?>
                <img src="<?= e(cover_url($c['cover'])) ?>" alt="" style="width:64px;height:40px;object-fit:cover;border-radius:5px;flex-shrink:0">
              <?php endif; ?>
              <div>
                <a href="<?= url('course.php') ?>?id=<?= (int)$c['id'] ?>" target="_blank"><?= e($c['title']) ?></a>
                <?php if ($c['is_hot']): ?><span class="badge badge-charge">热</span><?php endif; ?>
              </div>
            </div>
          </td>
          <td><?= e($c['cat_name'] ?: '-') ?></td>
          <td><?= $c['price'] > 0 ? fmt_price((float)$c['price']) : '免费' ?></td>
          <td><?= fmt_count($c['student_count']) ?></td>
          <td><span class="badge badge-<?= $c['status'] ? 'on' : 'off' ?>"><?= $c['status'] ? '上架' : '下架' ?></span></td>
          <td>
            <div class="table-actions">
              <a href="lessons.php?course_id=<?= (int)$c['id'] ?>">目录/视频</a>
              <a href="courses.php?action=edit&id=<?= (int)$c['id'] ?>">编辑</a>
              <form method="post" action="courses.php?action=toggle&id=<?= (int)$c['id'] ?>" onsubmit="return confirm('确认切换上下架状态？')">
                <?= csrf_field() ?><button type="submit"><?= $c['status'] ? '下架' : '上架' ?></button>
              </form>
              <form method="post" action="courses.php?action=delete&id=<?= (int)$c['id'] ?>" onsubmit="return confirm('删除课程将同时删除其章节与课时，不可恢复，确认删除？')">
                <?= csrf_field() ?><button type="submit" class="danger">删除</button>
              </form>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
      </table>
    </div>
    <?php
    require __DIR__ . '/_layout_end.php';
    exit;
}

/* ---------- 编辑/新增表单 ---------- */
$course = $id ? Db::row('SELECT * FROM courses WHERE id = ?', [$id]) : null;
admin_header($id ? '编辑课程' : '新增课程');
?>
<div class="admin-topbar">
  <h1><?= $id ? '编辑课程 #' . $id : '新增课程' ?></h1>
  <a class="btn btn-ghost" href="courses.php">← 返回列表</a>
</div>
<div class="admin-card" style="max-width:720px">
  <form method="post" enctype="multipart/form-data">
    <?= csrf_field() ?>
    <div class="form-item"><label>课程标题 *</label><input type="text" name="title" required value="<?= e($course['title'] ?? '') ?>"></div>
    <div class="form-item"><label>副标题</label><input type="text" name="subtitle" value="<?= e($course['subtitle'] ?? '') ?>"></div>

    <div class="form-item">
      <label>课程主图（建议 800×450 JPG/PNG/WebP，列表与详情页头图使用）</label>
      <input type="file" name="cover_file" accept=".png,.jpg,.jpeg,.webp,.gif,image/*">
      <div class="cover-preview-box">
        <?php if (cover_is_image($course['cover'] ?? '')): ?>
          <img src="<?= e(cover_url($course['cover'])) ?>?t=<?= time() ?>" alt="主图预览">
          <label style="font-size:12px;color:#c45656;display:flex;align-items:center;gap:4px">
            <input type="checkbox" name="clear_cover" value="1" style="width:auto"> 清除图片（改用默认占位样式）
          </label>
        <?php else: ?>
          <span style="color:#b6bec9;font-size:13px">当前未设置图片，前台将显示简洁文字占位样式</span>
        <?php endif; ?>
      </div>
      <input type="text" name="cover_url" value="<?= cover_is_image($course['cover'] ?? '') && preg_match('#^https?://#i', $course['cover']) ? e($course['cover']) : '' ?>" placeholder="或填写图片外链 https://...（与上传二选一）" style="margin-top:8px">
    </div>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px">
      <div class="form-item"><label>分类</label>
        <select name="category_id">
          <?php foreach ($categories as $cat): ?>
          <option value="<?= (int)$cat['id'] ?>" <?= ($course['category_id'] ?? 0) == $cat['id'] ? 'selected' : '' ?>><?= e($cat['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-item"><label>授课老师</label><input type="text" name="teacher" value="<?= e($course['teacher'] ?? '') ?>"></div>
      <div class="form-item"><label>价格（元，0 = 免费）</label><input type="number" step="0.01" min="0" name="price" value="<?= e($course['price'] ?? '0') ?>"></div>
      <div class="form-item"><label>原价（元）</label><input type="number" step="0.01" min="0" name="original_price" value="<?= e($course['original_price'] ?? '0') ?>"></div>
      <div class="form-item"><label>课时数</label><input type="number" min="0" name="lesson_count" value="<?= e($course['lesson_count'] ?? '0') ?>"></div>
      <div class="form-item"><label>有效期至</label><input type="date" name="validity_date" value="<?= e($course['validity_date'] ?? '') ?>"></div>
      <div class="form-item" style="display:flex;align-items:flex-end">
        <label style="margin:0"><input type="checkbox" name="is_hot" <?= !empty($course['is_hot']) ? 'checked' : '' ?> style="width:auto;margin-right:6px">标记为热门课程</label>
      </div>
    </div>
    <div class="form-item"><label>课程介绍</label><textarea name="description" rows="6"><?= e($course['description'] ?? '') ?></textarea></div>
    <button class="btn btn-primary" type="submit">保存</button>
  </form>
</div>
<style>
.cover-preview-box { display:flex; align-items:center; gap:14px; margin-top:8px; padding:10px 14px; background:#f8fafc; border-radius:8px }
.cover-preview-box img { max-height:90px; max-width:180px; border-radius:6px; border:1px solid #e8ecf1 }
</style>
<?php require __DIR__ . '/_layout_end.php'; ?>
