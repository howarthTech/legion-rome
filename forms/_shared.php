<?php
/**
 * Shared helpers for romelegion.org form endpoints.
 *
 * Each form endpoint (contact.php, application.php) require()s this file
 * and uses these helpers. We keep the helpers separate so we don't repeat
 * Resend / validation / rate-limit logic across endpoints.
 *
 * Deploy layout on the VPS:
 *   /srv/www/romelegion.org/_form/contact.php       (this file's siblings)
 *   /srv/www/romelegion.org/_form/_shared.php       (this file)
 *   /srv/secrets/romelegion.org.env                 (env file, 600 root-owned)
 *
 * The shared websites tenant's PHP-FPM (port 9000) executes these via Caddy.
 */

declare(strict_types=1);

// ---------- Secrets ----------------------------------------------------------

/**
 * Load secrets from /srv/secrets/romelegion.org.env into the environment
 * exactly once per request. The file is mode 600 root-owned; PHP-FPM runs as
 * a non-root user, so the file is exposed to the tenant via the systemd
 * EnvironmentFile= directive or via being explicitly readable by the fpm user.
 *
 * For local dev (`make dev-forms`) the same file path works because we mount
 * `./dev/secrets/romelegion.org.env` into the container at the same path.
 */
function rl_load_secrets(): void {
    static $loaded = false;
    if ($loaded) return;
    $loaded = true;

    $envFile = '/srv/secrets/romelegion.org.env';
    if (!is_readable($envFile)) {
        // In production this is a hard error; in dev fall through and let
        // missing-value errors surface in the per-key getters.
        error_log("rl_load_secrets: $envFile not readable");
        return;
    }
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if (str_starts_with($line, '#')) continue;
        if (!str_contains($line, '=')) continue;
        [$k, $v] = explode('=', $line, 2);
        $k = trim($k);
        $v = trim($v, " \t\n\r\0\x0B\"'");
        if ($k !== '') {
            putenv("$k=$v");
            $_ENV[$k] = $v;
        }
    }
}

function rl_secret(string $key): string {
    rl_load_secrets();
    $v = getenv($key);
    if ($v === false || $v === '') {
        rl_fail(500, "Server misconfigured (missing $key)");
    }
    return $v;
}

// ---------- Input validation -------------------------------------------------

/** Read a required POST field, length-limited, trimmed. */
function rl_required(string $field, int $maxLen = 500): string {
    $v = trim((string)($_POST[$field] ?? ''));
    if ($v === '') rl_fail(400, "Missing required field: $field");
    if (mb_strlen($v) > $maxLen) rl_fail(400, "Field too long: $field");
    return $v;
}

/** Read an optional POST field. */
function rl_optional(string $field, int $maxLen = 500): string {
    $v = trim((string)($_POST[$field] ?? ''));
    if (mb_strlen($v) > $maxLen) rl_fail(400, "Field too long: $field");
    return $v;
}

/** Require a syntactically valid email. */
function rl_required_email(string $field): string {
    $v = rl_required($field, 320);
    if (!filter_var($v, FILTER_VALIDATE_EMAIL)) {
        rl_fail(400, "Invalid email address");
    }
    return $v;
}

/** Reject if the honeypot field is filled. Silently 200 — bots don't get error feedback. */
function rl_check_honeypot(string $field = 'website'): void {
    if (trim((string)($_POST[$field] ?? '')) !== '') {
        // Pretend success; the bot moves on and we don't burn API quota.
        header('Location: /thanks/');
        exit;
    }
}

// ---------- Rate limit (1 submission per IP per 60 seconds) -----------------

function rl_rate_limit(string $endpointKey, int $windowSec = 60): void {
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $dir = sys_get_temp_dir() . '/romelegion-rl';
    @mkdir($dir, 0700, true);
    $hash = hash('sha256', "$endpointKey:$ip");
    $file = "$dir/$hash";

    $now = time();
    if (is_file($file) && ($mtime = filemtime($file)) && ($now - $mtime) < $windowSec) {
        rl_fail(429, "Please wait a moment before submitting again.");
    }
    @touch($file);

    // Periodically prune old entries
    if (mt_rand(1, 100) === 1) {
        foreach (glob("$dir/*") ?: [] as $f) {
            if (is_file($f) && (filemtime($f) ?: 0) < $now - 3600) @unlink($f);
        }
    }
}

// ---------- Resend send ------------------------------------------------------

/**
 * Send an email via Resend's HTTP API.
 *
 * @param string|array $to       Recipient address(es)
 * @param string       $subject  Subject line
 * @param string       $textBody Plain-text body
 * @param string|null  $replyTo  Optional reply-to (typically the form submitter)
 */
function rl_send_email($to, string $subject, string $textBody, ?string $replyTo = null): void {
    $apiKey = rl_secret('RESEND_API_KEY');
    $from   = rl_secret('EMAIL_FROM');  // e.g. "Post 5 Web <noreply@romelegion.org>"

    $payload = [
        'from'    => $from,
        'to'      => is_array($to) ? $to : [$to],
        'subject' => $subject,
        'text'    => $textBody,
    ];
    if ($replyTo) $payload['reply_to'] = $replyTo;

    $ch = curl_init('https://api.resend.com/emails');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($payload, JSON_THROW_ON_ERROR),
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer ' . $apiKey,
            'Content-Type: application/json',
        ],
    ]);
    $response = curl_exec($ch);
    $status   = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err      = curl_error($ch);
    curl_close($ch);

    if ($response === false || $status >= 400) {
        error_log("rl_send_email: Resend returned $status: $err | body=$response");
        rl_fail(502, "We couldn't send your message right now. Please call the post directly.");
    }
}

// ---------- Response helpers -------------------------------------------------

/**
 * Fail with a message. If HTTP_REFERER is present (i.e. the user came from
 * a form on our own site), redirect back to that form with the error in the
 * URL — the form's <form-flash> shortcode will surface it in the page's
 * role="alert" region. Otherwise fall back to a plain-text response that
 * names the issue and offers a way back.
 *
 * Why redirect-back instead of inline error rendering: keeps the PHP handler
 * stateless and avoids duplicating Hugo's site chrome in PHP — the styled
 * error appears in the same page the user just submitted.
 */
function rl_fail(int $status, string $message): never {
    $referer = $_SERVER['HTTP_REFERER'] ?? '';
    $host = $_SERVER['HTTP_HOST'] ?? '';
    // Only redirect back if the referer is on our own host — never to an
    // attacker-controlled URL.
    if ($referer && $host && str_contains($referer, $host)) {
        $sep = str_contains($referer, '?') ? '&' : '?';
        $url = $referer . $sep . 'err=' . urlencode($message);
        header("Location: $url", true, 303);
        exit;
    }
    // Fallback: minimal HTML so the response is at least semantically marked
    // up rather than a bare text/plain dump. role="alert" announces it.
    http_response_code($status);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8">';
    echo '<title>Form error · American Legion Post 5</title></head><body>';
    echo '<main><h1>We couldn\'t process that submission</h1>';
    echo '<p role="alert">' . htmlspecialchars($message, ENT_QUOTES, 'UTF-8') . '</p>';
    echo '<p><a href="/">Return to the home page</a></p></main></body></html>';
    exit;
}

function rl_success_redirect(string $location = '/thanks/'): never {
    header("Location: $location");
    http_response_code(303);
    exit;
}

// ---------- Method guard -----------------------------------------------------

function rl_require_post(): void {
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        rl_fail(405, "Method not allowed");
    }
}
