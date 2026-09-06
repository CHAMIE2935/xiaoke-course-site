<?php
/**
 * 公共函数：转义、CSRF、闪存消息、跳转、格式化、当前用户
 */

function e($str): string
{
    return htmlspecialchars((string)$str, ENT_QUOTES, 'UTF-8');
}

function redirect(string $url): void
{
    header('Location: ' . $url);
    exit;
}

function url(string $path = ''): string
{
    $base = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
    // admin/ 子目录下的页面回根路径
    if (strpos($_SERVER['SCRIPT_NAME'], '/admin/') !== false) {
        $base = substr($base, 0, strrpos($base, '/'));
    }
    $base = str_replace('\\', '/', $base);
    return $base . '/' . ltrim($path, '/');
}

/* ---------------- CSRF ---------------- */
function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="_token" value="' . e(csrf_token()) . '">';
}

function verify_csrf(): void
{
    $token = $_POST['_token'] ?? '';
    if (!$token || !hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        http_response_code(419);
        exit('页面已过期，请返回刷新后重试（CSRF 校验失败）。');
    }
}

/* ---------------- 闪存消息 ---------------- */
function flash(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function get_flashes(): array
{
    $flashes = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $flashes;
}

/* ---------------- 格式化 ---------------- */

/** 1622000 -> "162.20w" */
function fmt_count($n): string
{
    $n = (int)$n;
    if ($n >= 10000) {
        return number_format($n / 10000, 2) . 'w';
    }
    return (string)$n;
}

/** 价格显示：免费 / ¥39.90（划线原价） */
function fmt_price(float $price, float $original = 0): string
{
    if ($price <= 0) {
        return '免费';
    }
    return '¥ ' . number_format($price, 2, '.', '');
}

/* ---------------- 当前用户 ---------------- */
function current_user(): ?array
{
    static $user = null;
    if ($user !== null) return $user;
    if (empty($_SESSION['user_id'])) return null;

    $user = Db::row('SELECT id, email, nickname, phone, role, status, created_at FROM users WHERE id = ? AND status = 1', [$_SESSION['user_id']]);
    if (!$user) {
        unset($_SESSION['user_id']);
        return null;
    }
    return $user;
}

function require_login(): array
{
    $user = current_user();
    if (!$user) {
        flash('warning', '请先登录');
        redirect(url('login.php') . '?redirect=' . urlencode($_SERVER['REQUEST_URI'] ?? ''));
    }
    return $user;
}

function is_admin(): bool
{
    $u = current_user();
    return $u && $u['role'] === 'admin';
}

/* ---------------- 业务辅助 ---------------- */

/** 用户是否已购/已报名某课程 */
function has_enrolled(int $userId, int $courseId): bool
{
    return (bool) Db::value('SELECT COUNT(*) FROM enrollments WHERE user_id = ? AND course_id = ?', [$userId, $courseId]);
}

/** 生成订单号 */
function make_order_no(): string
{
    return date('YmdHis') . strtoupper(bin2hex(random_bytes(4)));
}

/* ---------------- 站点设置 ---------------- */

/** 读取站点设置项（带静态缓存），不存在返回默认值 */
function setting(string $key, string $default = ''): string
{
    static $cache = null;
    if ($cache === null) {
        $cache = [];
        try {
            foreach (Db::all('SELECT skey, svalue FROM settings') as $row) {
                $cache[$row['skey']] = (string)$row['svalue'];
            }
        } catch (Throwable $e) { /* settings 表未就绪时使用默认值 */ }
    }
    $val = $cache[$key] ?? '';
    return $val !== '' ? $val : $default; // 空值视为未设置，使用默认值
}

/** 写入站点设置项 */
function setting_set(string $key, string $value): void
{
    Db::run('INSERT INTO settings (skey, svalue) VALUES (?, ?) ON DUPLICATE KEY UPDATE svalue = VALUES(svalue)', [$key, $value]);
}

/**
 * 后台图片上传（课程主图 / 站点图片），存入 uploads/{子目录}
 * 返回 [相对路径|null, 错误信息|null]；未选择文件时返回 [null, null]
 */
function save_image_upload(array $file, string $subDir = 'covers', array $allowed = ['jpg','jpeg','png','webp','gif','svg','ico']): array
{
    if (empty($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return [null, null];
    }
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return [null, $file['error'] === UPLOAD_ERR_INI_SIZE ? '图片超过服务器大小限制' : '上传失败（错误码 ' . $file['error'] . '）'];
    }
    if (($file['size'] ?? 0) > 5 * 1024 * 1024) {
        return [null, '图片不能超过 5MB'];
    }
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowed, true)) {
        return [null, '仅支持格式：' . implode('/', $allowed)];
    }
    // svg 仅允许站点图片目录（课程主图不建议 svg）
    $dir = APP_ROOT . '/uploads/' . $subDir;
    if (!is_dir($dir) && !@mkdir($dir, 0777, true)) {
        return [null, '上传目录创建失败，请检查权限'];
    }
    $name = date('Ymd') . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
    if (!move_uploaded_file($file['tmp_name'], $dir . '/' . $name)) {
        return [null, '图片保存失败，请检查目录权限'];
    }
    return ['uploads/' . $subDir . '/' . $name, null];
}

/** 删除后台上传的旧图片（仅限 uploads/ 内且未被引用时） */
function delete_uploaded_image(?string $path): void
{
    if (!$path || strpos($path, 'uploads/') !== 0) return;
    $file = APP_ROOT . '/' . $path;
    if (is_file($file)) @unlink($file);
}

/* ---------------- 课程封面 ---------------- */

/** 封面是否为真实图片（上传文件或外链） */
function cover_is_image(?string $cover): bool
{
    return (bool) preg_match('#^(https?://|uploads/)#i', (string)$cover);
}

/** 封面 URL（相对路径转站点绝对地址） */
function cover_url(string $cover): string
{
    if (preg_match('#^https?://#i', $cover)) return $cover;
    return url($cover);
}

/**
 * 课程封面 HTML（卡片用）：
 * 有图显示图片；无图显示中性纯色占位（课程标题文字），不再使用花哨渐变
 */
function course_cover_html(array $course): string
{
    $cover = (string)($course['cover'] ?? '');
    if (cover_is_image($cover)) {
        return '<img class="cover-img" src="' . e(cover_url($cover)) . '" alt="' . e($course['title']) . '" loading="lazy">';
    }
    return '<div class="cover-fallback"><span>' . e($course['title']) . '</span></div>';
}

/** 详情页头图背景样式：有图用图，无图用中性深色 */
function cover_banner_style(array $course): string
{
    $cover = (string)($course['cover'] ?? '');
    if (cover_is_image($cover)) {
        return 'background:#2b3440 url(\'' . e(cover_url($cover)) . '\') center/cover no-repeat;';
    }
    return 'background:#2b3440;';
}

/* ---------------- 站点绝对地址 ---------------- */

/** 生成带协议与域名的完整 URL（支付回调等外部场景使用） */
function site_url(string $path = ''): string
{
    $base = trim((string)($GLOBALS['config']['site']['url'] ?? ''));
    if ($base !== '') {
        return rtrim($base, '/') . '/' . ltrim($path, '/');
    }
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? '127.0.0.1';
    return $scheme . '://' . $host . '/' . ltrim($path, '/');
}

/* ---------------- 易支付 ---------------- */

/** 易支付是否已启用（配置完整才视为启用） */
function epay_enabled(): bool
{
    return setting('epay_enabled') === '1'
        && setting('epay_api') !== ''
        && setting('epay_pid') !== ''
        && setting('epay_key') !== '';
}

/** 易支付签名：参数按键名升序原样拼接 a=b&c=d 后直接接密钥取 MD5 */
function epay_sign(array $params, string $key): string
{
    unset($params['sign'], $params['sign_type']);
    $params = array_filter($params, fn($v) => $v !== '' && $v !== null);
    ksort($params);
    $pairs = [];
    foreach ($params as $k => $v) $pairs[] = $k . '=' . $v;
    return md5(implode('&', $pairs) . $key);
}

/** 校验易支付回调签名 */
function epay_verify(array $params): bool
{
    $sign = (string)($params['sign'] ?? '');
    if ($sign === '') return false;
    return hash_equals($sign, epay_sign($params, setting('epay_key')));
}

