<?php
/**
 * 课程目录与视频管理：章节 + 课时的增删改 / 排序 / 试看标记
 * 课时视频来源两种：① 本地上传视频文件（存 uploads/videos/） ② 填写外部视频 URL
 */
require __DIR__ . '/_guard.php';

define('UPLOAD_DIR', 'uploads/videos');
define('ALLOWED_VIDEO_EXT', 'mp4,webm,ogv,m4v,mov');

$courseId = (int)($_GET['course_id'] ?? $_POST['course_id'] ?? 0);
$course = Db::row('SELECT id, title FROM courses WHERE id = ?', [$courseId]);
if (!$course) { flash('error', '课程不存在'); redirect('courses.php'); }

/* ================= 工具函数 ================= */

/** 同步课时数到课程表 */
function sync_lesson_count(int $courseId): void
{
    Db::run('UPDATE courses SET lesson_count = (SELECT COUNT(*) FROM lessons WHERE course_id = ?) WHERE id = ?', [$courseId, $courseId]);
}

/**
 * 处理视频上传，成功返回相对路径，失败返回 [null, 错误信息]
 */
function save_video_upload(array $file): array
{
    if (empty($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return [null, null]; // 没有上传文件
    }
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $map = [
            UPLOAD_ERR_INI_SIZE  => '文件超过服务器 upload_max_filesize 限制',
            UPLOAD_ERR_FORM_SIZE => '文件超过表单限制',
            UPLOAD_ERR_PARTIAL   => '文件只上传了一部分',
        ];
        return [null, $map[$file['error']] ?? '上传失败（错误码 ' . $file['error'] . '）'];
    }
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, explode(',', ALLOWED_VIDEO_EXT), true)) {
        return [null, '仅支持视频格式：' . ALLOWED_VIDEO_EXT];
    }
    $dir = APP_ROOT . '/' . UPLOAD_DIR;
    if (!is_dir($dir) && !@mkdir($dir, 0777, true)) {
        return [null, '上传目录创建失败，请检查权限'];
    }
    $name = date('Ymd') . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
    if (!move_uploaded_file($file['tmp_name'], $dir . '/' . $name)) {
        return [null, '文件保存失败，请检查目录权限'];
    }
    return [UPLOAD_DIR . '/' . $name, null];
}

/** 删除本地旧视频（确认无其他课时引用时才删） */
function delete_video_file_if_unused(?string $path, int $excludeLessonId = 0): void
{
    if (!$path || strpos($path, UPLOAD_DIR . '/') !== 0) return;
    $used = Db::value('SELECT COUNT(*) FROM lessons WHERE video_url = ? AND id != ?', [$path, $excludeLessonId]);
    if (!$used) {
        $file = APP_ROOT . '/' . $path;
        if (is_file($file)) @unlink($file);
    }
}

/** 在组内上下移动（通用） */
function move_within(string $table, string $scopeField, int $scopeId, int $id, string $dir): void
{
    $rows = Db::all("SELECT id FROM `$table` WHERE `$scopeField` = ? ORDER BY sort, id", [$scopeId]);
    $ids = array_column($rows, 'id');
    $idx = array_search($id, $ids, true);
    if ($idx === false) return;
    $swap = $dir === 'up' ? $idx - 1 : $idx + 1;
    if ($swap < 0 || $swap >= count($ids)) return;
    [$ids[$idx], $ids[$swap]] = [$ids[$swap], $ids[$idx]];
    foreach ($ids as $i => $rid) {
        Db::run("UPDATE `$table` SET sort = ? WHERE id = ?", [$i + 1, $rid]);
    }
}

/** 课时视频显示：文件名 + 大小，或外链 */
function video_label(?string $url): string
{
    if (!$url) return '<span style="color:#c0c7d0">未设置</span>';
    if (preg_match('#^https?://#i', $url)) {
        return '🔗 外链视频';
    }
    $file = APP_ROOT . '/' . $url;
    $size = is_file($file) ? size_fmt(filesize($file)) : '文件缺失';
    return '🎥 ' . e(basename($url)) . "（{$size}）";
}

function size_fmt($bytes): string
{
    if ($bytes >= 1073741824) return round($bytes / 1073741824, 2) . ' GB';
    if ($bytes >= 1048576) return round($bytes / 1048576, 1) . ' MB';
    return round($bytes / 1024, 1) . ' KB';
}

/* ================= 动作处理 ================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';

    // ---- 章节 ----
    if ($action === 'chapter_add') {
        $title = trim($_POST['title'] ?? '');
        if ($title === '') { flash('error', '章节标题不能为空'); }
        else {
            $maxSort = (int)Db::value('SELECT COALESCE(MAX(sort),0) FROM chapters WHERE course_id = ?', [$courseId]);
            Db::run('INSERT INTO chapters (course_id, title, sort) VALUES (?, ?, ?)', [$courseId, $title, $maxSort + 1]);
            flash('success', '章节已添加');
        }
    }
    if ($action === 'chapter_rename') {
        $title = trim($_POST['title'] ?? '');
        $cid = (int)($_POST['chapter_id'] ?? 0);
        if ($title !== '') Db::run('UPDATE chapters SET title = ? WHERE id = ? AND course_id = ?', [$title, $cid, $courseId]);
        flash('success', '章节已重命名');
    }
    if ($action === 'chapter_del') {
        $cid = (int)($_POST['chapter_id'] ?? 0);
        $oldVideos = Db::all('SELECT video_url FROM lessons WHERE chapter_id = ?', [$cid]);
        Db::run('DELETE FROM lessons WHERE chapter_id = ?', [$cid]);
        Db::run('DELETE FROM chapters WHERE id = ? AND course_id = ?', [$cid, $courseId]);
        foreach ($oldVideos as $v) delete_video_file_if_unused($v['video_url']);
        sync_lesson_count($courseId);
        flash('success', '章节及其课时已删除');
    }
    if ($action === 'chapter_move') {
        move_within('chapters', 'course_id', $courseId, (int)$_POST['chapter_id'], $_POST['dir'] ?? 'up');
    }

    // ---- 课时 ----
    if ($action === 'lesson_add') {
        $chapterId = (int)($_POST['chapter_id'] ?? 0);
        $title = trim($_POST['title'] ?? '');
        $duration = max(0, (int)($_POST['duration'] ?? 0));
        $free = isset($_POST['is_free_preview']) ? 1 : 0;
        $url = trim($_POST['video_url'] ?? '');
        [$uploaded, $err] = save_video_upload($_FILES['video_file'] ?? []);
        if ($err) { flash('error', $err); }
        elseif ($title === '') { flash('error', '课时标题不能为空'); }
        elseif (!$uploaded && $url === '') { flash('error', '请上传视频文件或填写视频地址'); }
        else {
            $video = $uploaded ?: $url;
            $chapter = Db::row('SELECT id FROM chapters WHERE id = ? AND course_id = ?', [$chapterId, $courseId]);
            if (!$chapter) { flash('error', '请选择有效章节'); }
            else {
                $maxSort = (int)Db::value('SELECT COALESCE(MAX(sort),0) FROM lessons WHERE chapter_id = ?', [$chapterId]);
                Db::run('INSERT INTO lessons (chapter_id, course_id, title, video_url, duration, is_free_preview, sort) VALUES (?, ?, ?, ?, ?, ?, ?)',
                    [$chapterId, $courseId, $title, $video, $duration, $free, $maxSort + 1]);
                sync_lesson_count($courseId);
                flash('success', '课时已添加' . ($uploaded ? '（视频已上传）' : ''));
            }
        }
    }
    if ($action === 'lesson_edit') {
        $lid = (int)($_POST['lesson_id'] ?? 0);
        $lesson = Db::row('SELECT * FROM lessons WHERE id = ? AND course_id = ?', [$lid, $courseId]);
        $title = trim($_POST['title'] ?? '');
        $duration = max(0, (int)($_POST['duration'] ?? 0));
        $free = isset($_POST['is_free_preview']) ? 1 : 0;
        $newUrl = trim($_POST['video_url'] ?? '');
        [$uploaded, $err] = save_video_upload($_FILES['video_file'] ?? []);
        if (!$lesson) { flash('error', '课时不存在'); }
        elseif ($err) { flash('error', $err); }
        elseif ($title === '') { flash('error', '课时标题不能为空'); }
        else {
            $video = $uploaded ?: ($newUrl !== '' ? $newUrl : $lesson['video_url']);
            if ($video !== $lesson['video_url']) delete_video_file_if_unused($lesson['video_url'], $lid);
            Db::run('UPDATE lessons SET title = ?, video_url = ?, duration = ?, is_free_preview = ? WHERE id = ?',
                [$title, $video, $duration, $free, $lid]);
            sync_lesson_count($courseId);
            flash('success', '课时已更新');
        }
        redirect('lessons.php?course_id=' . $courseId);
    }
    if ($action === 'lesson_del') {
        $lid = (int)($_POST['lesson_id'] ?? 0);
        $lesson = Db::row('SELECT * FROM lessons WHERE id = ? AND course_id = ?', [$lid, $courseId]);
        if ($lesson) {
            delete_video_file_if_unused($lesson['video_url'], $lid);
            Db::run('DELETE FROM lessons WHERE id = ?', [$lid]);
            sync_lesson_count($courseId);
            flash('success', '课时已删除');
        }
    }
    if ($action === 'lesson_move') {
        $lid = (int)($_POST['lesson_id'] ?? 0);
        $lesson = Db::row('SELECT chapter_id FROM lessons WHERE id = ? AND course_id = ?', [$lid, $courseId]);
        if ($lesson) move_within('lessons', 'chapter_id', (int)$lesson['chapter_id'], $lid, $_POST['dir'] ?? 'up');
    }

    redirect('lessons.php?course_id=' . $courseId);
}

/* ================= 页面数据 ================= */
$editLesson = null;
if (($_GET['action'] ?? '') === 'edit_lesson') {
    $editLesson = Db::row('SELECT * FROM lessons WHERE id = ? AND course_id = ?', [(int)($_GET['id'] ?? 0), $courseId]);
}
$chapters = Db::all('SELECT * FROM chapters WHERE course_id = ? ORDER BY sort, id', [$courseId]);
foreach ($chapters as &$ch) {
    $ch['lessons'] = Db::all('SELECT * FROM lessons WHERE chapter_id = ? ORDER BY sort, id', [$ch['id']]);
}
unset($ch);

admin_header('课程目录管理');
?>
<div class="admin-topbar">
  <h1>📁 课程目录：<?= e($course['title']) ?></h1>
  <div style="display:flex;gap:8px">
    <a class="btn btn-ghost" href="<?= url('course.php') ?>?id=<?= $courseId ?>" target="_blank">前台预览</a>
    <a class="btn btn-outline" href="courses.php">← 返回课程列表</a>
  </div>
</div>

<!-- 编辑课时 -->
<?php if ($editLesson): ?>
<div class="admin-card" style="margin-bottom:18px;border-left:4px solid #ff4d4f">
  <h2 style="font-size:16px;margin-bottom:14px">编辑课时 #<?= (int)$editLesson['id'] ?></h2>
  <form method="post" enctype="multipart/form-data">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="lesson_edit">
    <input type="hidden" name="lesson_id" value="<?= (int)$editLesson['id'] ?>">
    <div style="display:grid;grid-template-columns:2fr 1fr 1fr;gap:14px">
      <div class="form-item"><label>课时标题</label><input type="text" name="title" required value="<?= e($editLesson['title']) ?>"></div>
      <div class="form-item"><label>时长（分钟）</label><input type="number" name="duration" min="0" value="<?= (int)$editLesson['duration'] ?>"></div>
      <div class="form-item" style="display:flex;align-items:flex-end">
        <label style="margin:0"><input type="checkbox" name="is_free_preview" <?= $editLesson['is_free_preview'] ? 'checked' : '' ?> style="width:auto;margin-right:6px">允许免费试看</label>
      </div>
    </div>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px">
      <div class="form-item">
        <label>替换视频文件（<?= ALLOWED_VIDEO_EXT ?>，留空则保持不变）</label>
        <input type="file" name="video_file" accept=".mp4,.webm,.ogv,.m4v,.mov,video/*">
        <p class="hint">当前：<?= video_label($editLesson['video_url']) ?></p>
      </div>
      <div class="form-item">
        <label>视频地址（外链，优先级低于上传文件）</label>
        <input type="text" name="video_url" value="<?= preg_match('#^https?://#i', $editLesson['video_url'] ?? '') ? e($editLesson['video_url']) : '' ?>" placeholder="https://...">
        <p class="hint">填写后将以该地址替换现有视频</p>
      </div>
    </div>
    <div style="display:flex;gap:10px">
      <button class="btn btn-primary" type="submit">保存课时</button>
      <a class="btn btn-ghost" href="lessons.php?course_id=<?= $courseId ?>">取消</a>
    </div>
  </form>
</div>
<?php endif; ?>

<!-- 新增章节 -->
<div class="admin-card" style="margin-bottom:18px;display:flex;gap:12px;align-items:flex-end;flex-wrap:wrap">
  <form method="post" style="display:flex;gap:12px;align-items:flex-end;flex:1;min-width:320px">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="chapter_add">
    <div class="form-item" style="flex:1;margin:0">
      <label>新增章节</label>
      <input type="text" name="title" required placeholder="例如：第一章 考情分析与备考规划">
    </div>
    <button class="btn btn-primary" type="submit">+ 添加章节</button>
  </form>
  <span style="font-size:12px;color:#7a8694">当前共 <?= count($chapters) ?> 章 / <?= (int)$course['lesson_count'] ?> 课时</span>
</div>

<?php if (!$chapters): ?>
<div class="admin-card" style="text-align:center;color:#7a8694;padding:40px">还没有章节，先添加第一章吧</div>
<?php endif; ?>

<?php foreach ($chapters as $idx => $ch): ?>
<div class="admin-card" style="margin-bottom:16px">
  <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;margin-bottom:6px">
    <h2 style="font-size:16px;flex:1"><?= e($ch['title']) ?></h2>
    <div class="table-actions">
      <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="chapter_move"><input type="hidden" name="chapter_id" value="<?= (int)$ch['id'] ?>"><input type="hidden" name="course_id" value="<?= $courseId ?>"><button type="submit" name="dir" value="up" <?= $idx === 0 ? 'disabled' : '' ?>>↑</button></form>
      <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="chapter_move"><input type="hidden" name="chapter_id" value="<?= (int)$ch['id'] ?>"><input type="hidden" name="course_id" value="<?= $courseId ?>"><button type="submit" name="dir" value="down" <?= $idx === count($chapters) - 1 ? 'disabled' : '' ?>>↓</button></form>
      <form method="post" style="display:flex;gap:4px" onsubmit="this.querySelector('input[name=title]').value = prompt('修改章节标题：', '<?= e($ch['title']) ?>') ?? ''; return this.querySelector('input[name=title]').value !== '';">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="chapter_rename"><input type="hidden" name="chapter_id" value="<?= (int)$ch['id'] ?>"><input type="hidden" name="course_id" value="<?= $courseId ?>">
        <input type="hidden" name="title" value="">
        <button type="submit">重命名</button>
      </form>
      <form method="post" onsubmit="return confirm('删除章节将同时删除其中 <?= count($ch['lessons']) ?> 个课时及已上传视频，确认？')">
        <?= csrf_field() ?><input type="hidden" name="action" value="chapter_del"><input type="hidden" name="chapter_id" value="<?= (int)$ch['id'] ?>"><input type="hidden" name="course_id" value="<?= $courseId ?>">
        <button type="submit" class="danger">删除章节</button>
      </form>
    </div>
  </div>

  <table class="data-table">
    <tr><th style="width:34px"></th><th>课时</th><th>视频</th><th>时长</th><th>试看</th><th style="width:200px">操作</th></tr>
    <?php foreach ($ch['lessons'] as $li => $l): ?>
    <tr>
      <td style="color:#b6bec9"><?= $li + 1 ?></td>
      <td><?= e($l['title']) ?></td>
      <td style="font-size:12px"><?= video_label($l['video_url']) ?></td>
      <td><?= (int)$l['duration'] ?> 分钟</td>
      <td><?= $l['is_free_preview'] ? '✅' : '—' ?></td>
      <td>
        <div class="table-actions">
          <a href="lessons.php?course_id=<?= $courseId ?>&action=edit_lesson&id=<?= (int)$l['id'] ?>">编辑</a>
          <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="lesson_move"><input type="hidden" name="lesson_id" value="<?= (int)$l['id'] ?>"><input type="hidden" name="course_id" value="<?= $courseId ?>"><button type="submit" name="dir" value="up" <?= $li === 0 ? 'disabled' : '' ?>>↑</button></form>
          <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="lesson_move"><input type="hidden" name="lesson_id" value="<?= (int)$l['id'] ?>"><input type="hidden" name="course_id" value="<?= $courseId ?>"><button type="submit" name="dir" value="down" <?= $li === count($ch['lessons']) - 1 ? 'disabled' : '' ?>>↓</button></form>
          <form method="post" onsubmit="return confirm('确认删除该课时？已上传的视频文件将一并删除')">
            <?= csrf_field() ?><input type="hidden" name="action" value="lesson_del"><input type="hidden" name="lesson_id" value="<?= (int)$l['id'] ?>"><input type="hidden" name="course_id" value="<?= $courseId ?>">
            <button type="submit" class="danger">删除</button>
          </form>
        </div>
      </td>
    </tr>
    <?php endforeach; ?>
    <?php if (!$ch['lessons']): ?>
    <tr><td colspan="6" style="color:#b6bec9;text-align:center;padding:14px">本章暂无课时</td></tr>
    <?php endif; ?>
  </table>

  <details style="margin-top:12px">
    <summary style="cursor:pointer;color:#ff4d4f;font-size:13px;font-weight:600">＋ 在本章添加课时</summary>
    <form method="post" enctype="multipart/form-data" style="margin-top:14px;padding:16px;background:#f8fafc;border-radius:10px">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="lesson_add">
      <input type="hidden" name="chapter_id" value="<?= (int)$ch['id'] ?>">
      <input type="hidden" name="course_id" value="<?= $courseId ?>">
      <div style="display:grid;grid-template-columns:2fr 1fr 1fr;gap:14px">
        <div class="form-item"><label>课时标题 *</label><input type="text" name="title" required placeholder="例如：1.1 考试结构分析"></div>
        <div class="form-item"><label>时长（分钟）</label><input type="number" name="duration" min="0" value="0"></div>
        <div class="form-item" style="display:flex;align-items:flex-end">
          <label style="margin:0"><input type="checkbox" name="is_free_preview" style="width:auto;margin-right:6px">允许免费试看</label>
        </div>
      </div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px">
        <div class="form-item">
          <label>上传视频文件（<?= ALLOWED_VIDEO_EXT ?>）</label>
          <input type="file" name="video_file" accept=".mp4,.webm,.ogv,.m4v,.mov,video/*">
        </div>
        <div class="form-item">
          <label>或填写视频外链地址</label>
          <input type="text" name="video_url" placeholder="https://...">
        </div>
      </div>
      <p class="hint" style="margin-bottom:12px">二者填其一即可；同时填写时优先使用上传的文件。大文件上传受服务器 upload_max_filesize / post_max_size 限制（见 README 调优说明）。</p>
      <button class="btn btn-primary" type="submit">添加课时</button>
    </form>
  </details>
</div>
<?php endforeach; ?>

<?php require __DIR__ . '/_layout_end.php'; ?>
