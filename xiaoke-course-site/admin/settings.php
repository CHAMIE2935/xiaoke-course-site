<?php
/**
 * 站点设置：Logo / 网站图标(favicon) / 首页 Banner 图与文案
 *          邮箱验证码发信（SMTP）/ 支付（易支付 API）
 * 全部可后台配置，未设置时使用内置默认
 */
require __DIR__ . '/_guard.php';

$textKeys = ['hero_title', 'hero_subtitle', 'hero_btn_text', 'hero_btn_link'];
$imageKeys = ['site_logo', 'site_favicon', 'hero_image'];
$mailKeys = ['mail_mode', 'smtp_host', 'smtp_port', 'smtp_secure', 'smtp_username', 'smtp_from', 'smtp_from_name'];
$epayKeys = ['epay_api', 'epay_pid'];

/* ---------- 发送测试邮件（先保存页面上的 SMTP 配置再测试） ---------- */
if (($_POST['action'] ?? '') === 'test_mail') {
    verify_csrf();
    foreach ($mailKeys as $k) {
        setting_set($k, trim($_POST[$k] ?? ''));
    }
    if (!empty($_POST['smtp_password'])) {
        setting_set('smtp_password', $_POST['smtp_password']);
    }
    $to = trim($_POST['test_to'] ?? '');
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
        flash('error', '请输入正确的测试收件邮箱');
    } else {
        $r = send_mail($to, '【' . ($GLOBALS['config']['site']['name'] ?? '小课学堂') . '】发信测试', "这是一封来自后台「站点设置」的测试邮件。\n验证码为：888888\n如收到本邮件说明 SMTP 配置正确。");
        if ($r['ok']) {
            flash('success', "测试邮件已发送至 {$to}，请查收（若未收到请检查 SMTP 配置与垃圾箱）");
        } else {
            flash('error', "测试邮件发送失败（{$to}），已降级记录到 storage/mail.log，请检查 SMTP 配置");
        }
    }
    redirect('settings.php');
}

/* ---------- 保存设置 ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    // ---- 文案保存 ----
    foreach ($textKeys as $k) {
        setting_set($k, trim($_POST[$k] ?? ''));
    }

    // ---- 图片上传/清除 ----
    foreach ($imageKeys as $k) {
        if (!empty($_POST['clear_' . $k])) {
            delete_uploaded_image(setting($k));
            setting_set($k, '');
        }
        [$path, $err] = save_image_upload($_FILES[$k] ?? [], 'site', $k === 'site_favicon' ? ['ico', 'png', 'gif', 'jpg', 'svg'] : ['jpg', 'jpeg', 'png', 'webp', 'gif', 'svg']);
        if ($err) {
            flash('error', "「{$k}」上传失败：{$err}");
        } elseif ($path) {
            delete_uploaded_image(setting($k)); // 清掉被替换的旧文件
            setting_set($k, $path);
            flash('success', '图片已更新');
        }
    }

    // ---- 邮箱验证码发信（SMTP）----
    foreach ($mailKeys as $k) {
        setting_set($k, trim($_POST[$k] ?? ''));
    }
    if (!empty($_POST['smtp_password'])) {
        setting_set('smtp_password', $_POST['smtp_password']); // 留空保持不变
    }

    // ---- 易支付 ----
    setting_set('epay_enabled', !empty($_POST['epay_enabled']) ? '1' : '0');
    foreach ($epayKeys as $k) {
        setting_set($k, trim($_POST[$k] ?? ''));
    }
    if (!empty($_POST['epay_key'])) {
        setting_set('epay_key', $_POST['epay_key']); // 留空保持不变
    }

    flash('success', '站点设置已保存');
    redirect('settings.php');
}

$values = [];
foreach (array_merge($textKeys, $imageKeys, $mailKeys, $epayKeys, ['epay_enabled']) as $k) $values[$k] = setting($k);
$smtpPasswordSet = setting('smtp_password') !== '';
$epayKeySet = setting('epay_key') !== '';

admin_header('站点设置');
?>
<div class="admin-topbar"><h1>站点设置</h1></div>

<form method="post" enctype="multipart/form-data">
  <?= csrf_field() ?>

  <div class="admin-card" style="margin-bottom:18px">
    <h2 style="font-size:16px;margin-bottom:14px">网站标识</h2>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:24px">
      <div>
        <div class="form-item">
          <label>站点 Logo（顶部导航左侧，建议透明底 PNG/SVG，高度 ≥ 40px）</label>
          <input type="file" name="site_logo" accept=".png,.jpg,.jpeg,.webp,.gif,.svg,image/*">
        </div>
        <div class="preview-box">
          <span class="preview-label">当前：</span>
          <?php if ($values['site_logo']): ?>
            <img class="preview-logo" src="<?= e(cover_url($values['site_logo'])) ?>?t=<?= time() ?>" alt="logo">
            <label class="clear-check"><input type="checkbox" name="clear_site_logo" value="1"> 清除图片（恢复默认文字标）</label>
          <?php else: ?>
            <span style="color:#b6bec9;font-size:13px">未设置（使用默认文字标）</span>
          <?php endif; ?>
        </div>
      </div>
      <div>
        <div class="form-item">
          <label>网站图标 Favicon（浏览器标签页图标，建议 32×32 ICO/PNG/SVG）</label>
          <input type="file" name="site_favicon" accept=".ico,.png,.gif,.jpg,.svg,image/*">
        </div>
        <div class="preview-box">
          <span class="preview-label">当前：</span>
          <?php if ($values['site_favicon']): ?>
            <img class="preview-favicon" src="<?= e(cover_url($values['site_favicon'])) ?>?t=<?= time() ?>" alt="favicon">
            <label class="clear-check"><input type="checkbox" name="clear_site_favicon" value="1"> 清除图标</label>
          <?php else: ?>
            <span style="color:#b6bec9;font-size:13px">未设置（使用浏览器默认）</span>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>

  <div class="admin-card" style="margin-bottom:18px">
    <h2 style="font-size:16px;margin-bottom:14px">首页 Banner</h2>
    <div class="form-item">
      <label>Banner 背景图（建议 1600×500 以上 JPG/PNG/WebP，不传则使用默认纯色样式）</label>
      <input type="file" name="hero_image" accept=".png,.jpg,.jpeg,.webp,.gif,image/*">
    </div>
    <div class="preview-box" style="margin-bottom:16px">
      <span class="preview-label">当前：</span>
      <?php if ($values['hero_image']): ?>
        <img class="preview-hero" src="<?= e(cover_url($values['hero_image'])) ?>?t=<?= time() ?>" alt="hero">
        <label class="clear-check"><input type="checkbox" name="clear_hero_image" value="1"> 清除图片（恢复默认样式）</label>
      <?php else: ?>
        <span style="color:#b6bec9;font-size:13px">未设置（默认纯色样式）</span>
      <?php endif; ?>
    </div>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px">
      <div class="form-item"><label>主标题</label><input type="text" name="hero_title" value="<?= e($values['hero_title']) ?>" placeholder="好课不贵，学即所用"></div>
      <div class="form-item"><label>副标题</label><input type="text" name="hero_subtitle" value="<?= e($values['hero_subtitle']) ?>" placeholder="一句话介绍平台定位"></div>
      <div class="form-item"><label>按钮文字</label><input type="text" name="hero_btn_text" value="<?= e($values['hero_btn_text']) ?>" placeholder="浏览全部课程 →"></div>
      <div class="form-item"><label>按钮链接</label><input type="text" name="hero_btn_link" value="<?= e($values['hero_btn_link']) ?>" placeholder="留空默认为课程中心页"></div>
    </div>
  </div>

  <div class="admin-card" style="margin-bottom:18px">
    <h2 style="font-size:16px;margin-bottom:14px">邮箱验证码发信（SMTP）</h2>
    <p style="font-size:12px;color:#7a8694;margin-bottom:16px">注册邮箱验证码的发送邮箱。选择「演示模式」验证码直接显示在注册页（仅测试用）；选择「SMTP 真实发信」并填写邮箱服务商的 SMTP 信息后，验证码将真实发送到用户邮箱。此处配置优先于 config.php。</p>
    <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:14px">
      <div class="form-item">
        <label>发信模式 *</label>
        <select name="mail_mode">
          <option value="log" <?= $values['mail_mode'] !== 'smtp' ? 'selected' : '' ?>>演示模式（验证码直接显示，测试用）</option>
          <option value="smtp" <?= $values['mail_mode'] === 'smtp' ? 'selected' : '' ?>>SMTP 真实发信</option>
        </select>
      </div>
      <div class="form-item"><label>SMTP 服务器</label><input type="text" name="smtp_host" value="<?= e($values['smtp_host']) ?>" placeholder="如 smtp.qq.com / smtp.163.com"></div>
      <div class="form-item">
        <label>加密方式</label>
        <select name="smtp_secure">
          <option value="ssl" <?= $values['smtp_secure'] !== 'tls' && $values['smtp_secure'] !== 'none' ? 'selected' : '' ?>>SSL（465 端口常用）</option>
          <option value="tls" <?= $values['smtp_secure'] === 'tls' ? 'selected' : '' ?>>STARTTLS（587 端口常用）</option>
          <option value="none" <?= $values['smtp_secure'] === 'none' ? 'selected' : '' ?>>不加密（25 端口）</option>
        </select>
      </div>
      <div class="form-item"><label>端口</label><input type="number" name="smtp_port" value="<?= e($values['smtp_port'] ?: '465') ?>" placeholder="465"></div>
      <div class="form-item"><label>SMTP 账号（发信邮箱）</label><input type="text" name="smtp_username" value="<?= e($values['smtp_username']) ?>" placeholder="如 123456@qq.com"></div>
      <div class="form-item"><label>SMTP 密码 / 授权码<?= $smtpPasswordSet ? '（已设置，留空保持不变）' : '' ?></label><input type="password" name="smtp_password" placeholder="<?= $smtpPasswordSet ? '••••••••' : '邮箱的 SMTP 授权码' ?>"></div>
      <div class="form-item"><label>发件人地址（Reply 地址）</label><input type="text" name="smtp_from" value="<?= e($values['smtp_from']) ?>" placeholder="留空默认同 SMTP 账号"></div>
      <div class="form-item"><label>发件人名称</label><input type="text" name="smtp_from_name" value="<?= e($values['smtp_from_name']) ?>" placeholder="如 小课学堂"></div>
    </div>
    <div class="form-item" style="display:flex;gap:10px;align-items:flex-end;margin-top:6px">
      <div style="flex:1">
        <label>发送测试邮件（先保存上方配置，再填收件邮箱测试）</label>
        <input type="email" name="test_to" placeholder="收件邮箱，如 someone@example.com">
      </div>
      <button class="btn btn-outline" type="submit" name="action" value="test_mail">发送测试</button>
    </div>
  </div>

  <div class="admin-card" style="margin-bottom:18px">
    <h2 style="font-size:16px;margin-bottom:14px">在线支付（易支付 API）</h2>
    <p style="font-size:12px;color:#7a8694;margin-bottom:16px">配置后用户购买课程将跳转到易支付平台（支持支付宝 / 微信 / QQ 钱包），支付成功由平台异步回调自动开通课程；不启用时保持内置的模拟支付演示流程。此处配置优先于 config.php。</p>
    <div class="form-item" style="display:flex;align-items:center;gap:8px;margin-bottom:14px">
      <label style="margin:0"><input type="checkbox" name="epay_enabled" value="1" <?= $values['epay_enabled'] === '1' ? 'checked' : '' ?> style="width:auto;margin-right:6px">启用易支付（三项信息均填写后才生效）</label>
    </div>
    <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:14px">
      <div class="form-item"><label>易支付网关地址</label><input type="text" name="epay_api" value="<?= e($values['epay_api']) ?>" placeholder="如 https://pay.example.com（不带 /submit.php）"></div>
      <div class="form-item"><label>商户 ID（PID）</label><input type="text" name="epay_pid" value="<?= e($values['epay_pid']) ?>" placeholder="易支付后台的商户编号"></div>
      <div class="form-item"><label>商户密钥（KEY）<?= $epayKeySet ? '（已设置，留空保持不变）' : '' ?></label><input type="password" name="epay_key" placeholder="<?= $epayKeySet ? '••••••••' : '易支付后台的通信密钥' ?>"></div>
    </div>
    <p style="font-size:12px;color:#b6bec9">回调地址（notify_url）与回跳地址（return_url）由系统自动生成，无需在易支付后台另外填写。</p>
  </div>

  <button class="btn btn-primary btn-lg" type="submit">保存全部设置</button>
</form>

<style>
.preview-box { display:flex; align-items:center; gap:14px; flex-wrap:wrap; padding:10px 14px; background:#f8fafc; border-radius:8px; margin-top:4px }
.preview-label { font-size:12px; color:#7a8694 }
.preview-logo { max-height:44px; max-width:220px; background:#fff; border:1px solid #e8ecf1; border-radius:6px; padding:4px 8px }
.preview-favicon { width:32px; height:32px; background:#fff; border:1px solid #e8ecf1; border-radius:6px; padding:2px }
.preview-hero { max-height:90px; max-width:320px; border-radius:8px; border:1px solid #e8ecf1 }
.clear-check { font-size:12px; color:#c45656; display:flex; align-items:center; gap:4px }
</style>

<?php require __DIR__ . '/_layout_end.php'; ?>
