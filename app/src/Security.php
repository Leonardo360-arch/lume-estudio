<?php

declare(strict_types=1);

/** Shared HTTP security, loaded before output by bootstrap. */
function secure_session_start(): void
{
    if (PHP_SAPI === 'cli' || session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    $production = strtolower((string) env('APP_ENV', 'local')) === 'production';
    $https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    session_name('LUMESESSID');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => $production || $https,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    if (!session_start()) {
        throw new RuntimeException('Não foi possível iniciar a sessão.');
    }
}

function csrf_token(): string
{
    if (!isset($_SESSION['_csrf']) || !is_string($_SESSION['_csrf']) || strlen($_SESSION['_csrf']) !== 64) {
        $_SESSION['_csrf'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['_csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="'
        . htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') . '">';
}

function require_csrf(): void
{
    if (PHP_SAPI === 'cli') {
        return;
    }

    $expected = $_SESSION['_csrf'] ?? null;
    $received = $_POST['_csrf'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
    if (!is_string($expected) || !is_string($received) || !hash_equals($expected, $received)) {
        http_response_code(403);
        header('Content-Type: text/plain; charset=UTF-8');
        header('Cache-Control: no-store');
        exit('Solicitação inválida ou expirada. Atualize a página e tente novamente.');
    }
}

/** Successful login rotates the session and clears the previous identity. */
function renew_authenticated_session(): void
{
    if (PHP_SAPI !== 'cli' && !session_regenerate_id(true)) {
        throw new RuntimeException('Não foi possível renovar a sessão.');
    }

    unset(
        $_SESSION['admin_id'], $_SESSION['admin_nome'], $_SESSION['admin_email'],
        $_SESSION['admin_autorizado'], $_SESSION['cliente_id'],
        $_SESSION['cliente_nome'], $_SESSION['cliente_email'], $_SESSION['_csrf']
    );
    csrf_token();
}

function require_admin(): int
{
    if (empty($_SESSION['admin_autorizado']) || (int) ($_SESSION['admin_id'] ?? 0) <= 0) {
        header('Location: /login.php');
        exit;
    }

    return (int) $_SESSION['admin_id'];
}

function require_client(): int
{
    if ((int) ($_SESSION['cliente_id'] ?? 0) <= 0) {
        header('Location: /login-cliente.php');
        exit;
    }

    return (int) $_SESSION['cliente_id'];
}

/** The first explicitly approved administrator manages access requests, regardless of numeric ID. */
function can_manage_admin_access(): bool
{
    if (empty($_SESSION['admin_autorizado'])) { return false; }
    $owner = Danny\Database::connection()->query("SELECT MIN(id) FROM admins WHERE tipo='ADMIN' AND status_admin='APROVADO'")->fetchColumn();
    return $owner !== false && (int)$owner > 0 && (int)$owner === (int)($_SESSION['admin_id'] ?? 0);
}

/** Recovery URLs never trust HTTP_HOST or forwarded headers. */
function canonical_app_url(): string
{
    $production = strtolower((string) env('APP_ENV', 'local')) === 'production';
    $url = rtrim((string) env('APP_URL', $production ? '' : 'http://localhost:8080'), '/');
    $parts = parse_url($url);
    if (
        filter_var($url, FILTER_VALIDATE_URL) === false
        || !is_array($parts)
        || empty($parts['host'])
        || !in_array(strtolower($parts['scheme'] ?? ''), ['http', 'https'], true)
        || isset($parts['user']) || isset($parts['pass'])
        || isset($parts['query']) || isset($parts['fragment'])
        || ($production && strtolower($parts['scheme']) !== 'https')
    ) {
        throw new RuntimeException('Configure APP_URL com a URL pública canônica; produção exige HTTPS.');
    }

    return $url;
}

function protect_http_request(): void
{
    if (PHP_SAPI === 'cli') {
        return;
    }

    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: same-origin');
    header('X-Frame-Options: SAMEORIGIN');
    $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
    $script = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_FILENAME'] ?? ''));
    $actionsDirectory = str_replace('\\', '/', dirname(__DIR__)) . '/actions/';

    // Every existing actions/ endpoint mutates state or sends e-mail.
    if (str_starts_with($script, $actionsDirectory) && $method !== 'POST') {
        http_response_code(405);
        header('Allow: POST');
        header('Content-Type: text/plain; charset=UTF-8');
        exit('Método não permitido.');
    }

    if (!in_array($method, ['GET', 'HEAD', 'OPTIONS'], true)) {
        require_csrf();
    }
}
