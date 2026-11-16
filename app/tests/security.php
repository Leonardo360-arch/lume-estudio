<?php

declare(strict_types=1);

// No database is required: exercise the real bootstrap over local HTTP.
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once dirname(__DIR__) . '/src/helpers.php';
require_once dirname(__DIR__) . '/src/Security.php';

$checks = 0;
function check_security(bool $condition, string $message): void
{
    global $checks;
    if (!$condition) {
        throw new RuntimeException($message);
    }
    $checks++;
}

$savedEnv = $_ENV;
try {
    $_ENV['APP_ENV'] = 'production';
    foreach (['http://example.test', 'https://user:pass@example.test', 'https://example.test?x=1', 'https://example.test/#fragment', 'javascript:alert(1)'] as $invalid) {
        $_ENV['APP_URL'] = $invalid;
        $rejected = false;
        try {
            canonical_app_url();
        } catch (RuntimeException) {
            $rejected = true;
        }
        check_security($rejected, 'Unsafe canonical URL accepted.');
    }
    $_ENV['APP_URL'] = 'https://example.test/studio/';
    check_security(canonical_app_url() === 'https://example.test/studio', 'Canonical base path lost.');
    $_ENV['APP_ENV'] = 'local';
    $_ENV['APP_URL'] = '';
    $_SERVER['HTTP_HOST'] = 'untrusted.invalid';
    check_security(canonical_app_url() === 'http://localhost:8080', 'Recovery trusted HTTP_HOST.');
} finally {
    $_ENV = $savedEnv;
    unset($_SERVER['HTTP_HOST']);
}

$socket = stream_socket_server('tcp://127.0.0.1:0', $errno, $error);
if ($socket === false) {
    throw new RuntimeException('Cannot reserve a local test port: ' . $error);
}
$address = stream_socket_get_name($socket, false);
fclose($socket);
$temp = sys_get_temp_dir() . '/lume-security-' . bin2hex(random_bytes(8));
mkdir($temp, 0700, true);
$app = dirname(__DIR__);
$router = '<?php $app = ' . var_export($app, true) . ';' . <<<'PHP'

$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$_SERVER['SCRIPT_FILENAME'] = $app . $path;
if ($path === '/secure-cookie') { $_ENV['APP_ENV'] = 'production'; }
require $app . '/src/bootstrap.php';
if ($path === '/admin-only') { require_admin(); }
if ($path === '/client-only') { require_client(); }
header('Content-Type: application/json');
if ($path === '/token') {
    echo json_encode(['token' => csrf_token(), 'id' => session_id(), 'strict' => ini_get('session.use_strict_mode')]);
} elseif ($path === '/rotate') {
    $_SESSION['admin_id'] = 1;
    $_SESSION['admin_autorizado'] = true;
    $_SESSION['cliente_id'] = 2;
    renew_authenticated_session();
    echo json_encode(['token' => csrf_token(), 'id' => session_id(), 'old_identity' => isset($_SESSION['admin_id']) || isset($_SESSION['cliente_id'])]);
} else {
    echo json_encode(['accepted' => true]);
}
PHP;
file_put_contents($temp . '/router.php', $router);
$environment = array_merge(getenv(), ['APP_ENV' => 'local', 'APP_URL' => 'http://' . $address]);
$process = proc_open(
    [PHP_BINARY, '-d', 'session.save_path=' . $temp, '-S', $address, $temp . '/router.php'],
    [0 => ['pipe', 'r'], 1 => ['file', $temp . '/server.log', 'a'], 2 => ['file', $temp . '/server.log', 'a']],
    $pipes,
    $app,
    $environment
);
if (!is_resource($process)) {
    throw new RuntimeException('Cannot start local PHP security test server.');
}
fclose($pipes[0]);

function security_http(string $address, string $path, string $method = 'GET', array $fields = [], string $cookie = '', array $extraHeaders = []): array
{
    $headers = array_merge(['Content-Type: application/x-www-form-urlencoded'], $extraHeaders);
    if ($cookie !== '') {
        $headers[] = 'Cookie: ' . $cookie;
    }
    $context = stream_context_create(['http' => [
        'method' => $method,
        'header' => implode("\r\n", $headers),
        'content' => $method === 'GET' ? '' : http_build_query($fields),
        'ignore_errors' => true,
        'follow_location' => 0,
        'timeout' => 2,
    ]]);
    $body = @file_get_contents('http://' . $address . $path, false, $context);
    $responseHeaders = $http_response_header ?? [];
    preg_match('/\s(\d{3})\s/', $responseHeaders[0] ?? '', $match);
    $cookieHeader = '';
    foreach ($responseHeaders as $header) {
        if (stripos($header, 'Set-Cookie:') === 0) {
            $cookieHeader = trim(substr($header, 11));
        }
    }
    return [
        'status' => (int) ($match[1] ?? 0),
        'json' => is_string($body) ? json_decode($body, true) : null,
        'body' => $body,
        'cookie' => explode(';', $cookieHeader, 2)[0],
        'cookie_header' => strtolower($cookieHeader),
    ];
}

try {
    $initial = [];
    for ($attempt = 0; $attempt < 30; $attempt++) {
        $initial = security_http($address, '/token', cookie: 'LUMESESSID=' . str_repeat('a', 32));
        if ($initial['status'] === 200) {
            break;
        }
        usleep(50000);
    }
    check_security(($initial['status'] ?? 0) === 200, 'Bootstrap did not start.');
    check_security(is_array($initial['json']), 'Bootstrap returned invalid JSON: ' . (string) $initial['body']);
    check_security($initial['json']['strict'] === '1', 'Session strict mode disabled.');
    check_security($initial['json']['id'] !== str_repeat('a', 32), 'Chosen session ID accepted.');
    check_security(str_contains($initial['cookie_header'], 'httponly'), 'Session cookie lacks HttpOnly.');
    check_security(str_contains($initial['cookie_header'], 'samesite=lax'), 'Session cookie lacks SameSite=Lax.');
    $production = security_http($address, '/secure-cookie');
    check_security(str_contains($production['cookie_header'], 'secure'), 'Production session cookie lacks Secure.');
    check_security(security_http($address, '/admin-only')['status'] === 302, 'Anonymous admin access accepted.');
    check_security(security_http($address, '/client-only')['status'] === 302, 'Anonymous client access accepted.');
    $cookie = $initial['cookie'];
    $token = $initial['json']['token'];
    check_security(strlen($token) === 64 && ctype_xdigit($token), 'CSRF token malformed.');
    check_security(security_http($address, '/mutate', 'POST', cookie: $cookie)['status'] === 403, 'Missing CSRF accepted.');
    check_security(security_http($address, '/mutate', 'POST', ['_csrf' => 'wrong'], $cookie)['status'] === 403, 'Wrong CSRF accepted.');
    check_security(security_http($address, '/mutate', 'POST', ['_csrf' => ['array']], $cookie)['status'] === 403, 'Array CSRF accepted.');
    check_security(security_http($address, '/mutate', 'POST', ['_csrf' => $token])['status'] === 403, 'CSRF accepted without originating session.');
    check_security(security_http($address, '/mutate', 'POST', ['_csrf' => $token], $cookie)['status'] === 200, 'Valid CSRF rejected.');
    check_security(security_http($address, '/mutate', 'POST', [], $cookie, ['X-CSRF-Token: ' . $token])['status'] === 200, 'Header CSRF rejected.');
    check_security(security_http($address, '/actions/probe.php', cookie: $cookie)['status'] === 405, 'Mutation action accepted GET.');
    $rotated = security_http($address, '/rotate', 'POST', ['_csrf' => $token], $cookie);
    check_security($rotated['status'] === 200 && $rotated['json']['id'] !== $initial['json']['id'], 'Authentication did not rotate session.');
    check_security($rotated['json']['token'] !== $token && !$rotated['json']['old_identity'], 'Previous identity or CSRF survived authentication.');
    check_security(security_http($address, '/mutate', 'POST', ['_csrf' => $token], $rotated['cookie'])['status'] === 403, 'Old CSRF survived rotation.');
    echo "Security checks passed: {$checks}\n";
} finally {
    proc_terminate($process);
    proc_close($process);
    foreach (glob($temp . '/*') ?: [] as $file) {
        unlink($file);
    }
    rmdir($temp);
}
