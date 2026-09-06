<?php
/** 分类管理 */
require __DIR__ . '/_guard.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $do = $_POST['do'] ?? '';

    if ($do === 'create') {
        $name = trim($_POST['name'] ?? '');
        if ($name === '') {
            flash('error', '分类名不能为空');
        } else {
            Db::run('INSERT INTO categories (name, sort) VALUES (?, ?)', [mb_substr($name, 0, 60), (int)($_POST['sort'] ?? 0)]);
            flash('success', '分类已创建');
        }
    } elseif ($do === 'update') {
        $name = trim($_POST['name'] ?? '');
        if ($name !== '') {
            Db::run('UPDATE categories SET name = ?, sort = ? WHERE id = ?', [mb_substr($name, 0, 60), (int)($_POST['sort'] ?? 0), (int)($_POST['id'] ?? 0)]);
            flash('success', '分类已更新');
        }
    } elseif ($do === 'delete') {
        $cid = (int)($_POST['id'] ?? 0);
        $used = (int)Db::value('SELECT COUNT(*) FROM courses WHERE category_id = ?', [$cid]);
        if ($used > 0) {
            flash('error', "该分类下还有 {$used} 门课程，请先移除或更换课程分类");
        } else {
            Db::run('DELETE FROM categories WHERE id = ?', [$cid]);
            flash('success', '分类已删除');
        }
    }
    redirect('categories.php');
}

$cats = Db::all(
    "SELECT c.*, (SELECT COUNT(*) FROM courses co WHERE co.category_id = c.id) AS course_count
     FROM categories c ORDER BY c.sort, c.id"
);

admin_header('分类管理');
?>
<div class="admin-topbar"><h1>分类管理</h1></div>

<div class="admin-card" style="margin-bottom:20px;max-width:560px">
  <h2 style="font-size:15px;margin-bottom:14px">新增分类</h2>
  <form method="post" style="display:flex;gap:10px;align-items:flex-end">
    <?= csrf_field() ?>
    <input type="hidden" name="do" value="create">
    <div class="form-item" style="flex:1;margin:0"><label>分类名</label><input type="text" name="name" required></div>
    <div class="form-item" style="width:90px;margin:0"><label>排序</label><input type="number" name="sort" value="0"></div>
    <button class="btn btn-primary" type="submit">添加</button>
  </form>
</div>

<div class="admin-card">
  <table class="data-table">
    <tr><th>ID</th><th>分类名</th><th>排序</th><th>课程数</th><th style="width:180px">操作</th></tr>
    <?php foreach ($cats as $c): ?>
    <tr>
      <td><?= (int)$c['id'] ?></td>
      <td>
        <form method="post" style="display:flex;gap:8px;align-items:center">
          <?= csrf_field() ?>
          <input type="hidden" name="do" value="update">
          <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
          <input type="text" name="name" value="<?= e($c['name']) ?>" style="border:1px solid #e8ecf1;border-radius:6px;padding:5px 10px">
          <input type="number" name="sort" value="<?= (int)$c['sort'] ?>" style="width:60px;border:1px solid #e8ecf1;border-radius:6px;padding:5px 8px">
          <button class="btn btn-outline" type="submit" style="padding:4px 12px;font-size:12px">保存</button>
        </form>
      </td>
      <td><?= (int)$c['sort'] ?></td>
      <td><?= (int)$c['course_count'] ?></td>
      <td>
        <form method="post" onsubmit="return confirm('确认删除该分类？')">
          <?= csrf_field() ?>
          <input type="hidden" name="do" value="delete">
          <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
          <button type="submit" class="danger" style="font-size:12px;padding:3px 10px;border-radius:6px;border:1px solid #fde2e2;background:#fff;color:#c45656;cursor:pointer">删除</button>
        </form>
      </td>
    </tr>
    <?php endforeach; ?>
  </table>
</div>
<?php require __DIR__ . '/_layout_end.php'; ?>
