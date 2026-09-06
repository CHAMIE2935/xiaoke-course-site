<?php
/**
 * 邮件发送
 *
 * - SMTP 模式：使用 socket 直连 SMTP 服务器发送（支持 SSL/TLS），无需第三方库
 * - log 模式（演示模式）：不实际发信，验证码写入 storage/mail.log 并返回给页面显示
 *
 * 返回 ['ok' => bool, 'debug_code' => string|null]
 */

function send_mail(string $to, string $subject, string $body): array
{
    $mail = $GLOBALS['config']['mail'] ?? ['mode' => 'log'];
    // 后台「站点设置 → 邮箱验证码发信」优先于 config.php
    $override = [
        'mode'     => 'mail_mode',
        'host'     => 'smtp_host',
        'port'     => 'smtp_port',
        'secure'   => 'smtp_secure',
        'username' => 'smtp_username',
        'password' => 'smtp_password',
        'from'     => 'smtp_from',
        'from_name'=> 'smtp_from_name',
    ];
    foreach ($override as $k => $s) {
        $v = setting($s);
        if ($v !== '') {
            $mail[$k] = ($k === 'port') ? (int)$v : $v;
        }
    }
    $code = null;

    if (($mail['mode'] ?? 'log') === 'smtp') {
        $ok = smtp_send($to, $subject, $body, $mail);
        if (!$ok) {
            // SMTP 失败时降级为演示模式，保证流程可走通
            $code = extract_code($body);
            log_mail($to, $subject, $body, 'smtp-failed-fallback');
        }
        return ['ok' => $ok, 'debug_code' => $ok ? null : $code];
    }

    $code = extract_code($body);
    log_mail($to, $subject, $body, 'demo');
    return ['ok' => true, 'debug_code' => $code];
}

function extract_code(string $body): ?string
{
    if (preg_match('/验证码为?[：:]\s*(\d{6})/u', $body, $m)) {
        return $m[1];
    }
    return null;
}

function log_mail(string $to, string $subject, string $body, string $tag): void
{
    $dir = APP_ROOT . '/storage';
    if (!is_dir($dir)) @mkdir($dir, 0777, true);
    $line = sprintf("[%s][%s] TO:%s SUBJECT:%s\n%s\n%s\n", date('Y-m-d H:i:s'), $tag, $to, $subject, $body, str_repeat('-', 60));
    @file_put_contents($dir . '/mail.log', $line, FILE_APPEND);
}

/**
 * 极简 SMTP 客户端（AUTH LOGIN）
 */
function smtp_send(string $to, string $subject, string $body, array $cfg): bool
{
    $host = $cfg['host'] ?? '';
    $port = (int)($cfg['port'] ?? 465);
    $secure = $cfg['secure'] ?? 'ssl';
    if (!$host) return false;

    $remote = ($secure === 'ssl' ? 'ssl://' : '') . $host . ':' . $port;
    $context = stream_context_create(['ssl' => ['verify_peer' => false, 'verify_peer_name' => false]]);
    $fp = @stream_socket_client($remote, $errno, $errstr, 15, STREAM_CLIENT_CONNECT, $context);
    if (!$fp) return false;
    stream_set_timeout($fp, 15);

    $read = function () use ($fp) {
        $data = '';
        while ($line = fgets($fp, 515)) {
            $data .= $line;
            if (isset($line[3]) && $line[3] === ' ') break;
        }
        return $data;
    };
    $cmd = function ($cmdstr) use ($fp, $read) {
        fwrite($fp, $cmdstr . "\r\n");
        return $read();
    };

    $read(); // banner
    $cmd('EHLO xiaoke.local');
    if ($secure === 'tls') {
        $cmd('STARTTLS');
        if (!@stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) { fclose($fp); return false; }
        $cmd('EHLO xiaoke.local');
    }
    $cmd('AUTH LOGIN');
    $cmd(base64_encode($cfg['username'] ?? ''));
    $auth = $cmd(base64_encode($cfg['password'] ?? ''));
    if (strpos($auth, '235') === false) { fclose($fp); return false; }

    $from = $cfg['from'] ?? 'noreply@example.com';
    $cmd('MAIL FROM:<' . $from . '>');
    $cmd('RCPT TO:<' . $to . '>');
    $data = $cmd('DATA');
    if (strpos($data, '354') === false) { fclose($fp); return false; }

    $headers = [
        'From: ' . ($cfg['from_name'] ?? '小课学堂') . ' <' . $from . '>',
        'To: <' . $to . '>',
        'Subject: =?UTF-8?B?' . base64_encode($subject) . '?=',
        'MIME-Version: 1.0',
        'Content-Type: text/plain; charset=UTF-8',
        'Content-Transfer-Encoding: base64',
    ];
    $payload = implode("\r\n", $headers) . "\r\n\r\n" . chunk_split(base64_encode($body));
    $cmd($payload . "\r\n.");
    $cmd('QUIT');
    fclose($fp);
    return true;
}
