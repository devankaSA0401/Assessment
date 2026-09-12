<?php

$config = require __DIR__ . '/../config/config.php';

$isHttps = (
    (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || strtolower($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https'
);

session_name($config['session_name']);

session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'secure' => $isHttps,
    'httponly' => true,
    'samesite' => 'Lax',
]);

session_start();

require_once __DIR__ . '/../app/helpers.php';
require_once __DIR__ . '/../app/Database.php';
require_once __DIR__ . '/../app/Auth.php';

$path = trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');

$base = trim(dirname($_SERVER['SCRIPT_NAME']), '/');

if ($base && str_starts_with($path, $base)) {
    $path = trim(substr($path, strlen($base)), '/');
}

$method = $_SERVER['REQUEST_METHOD'];

if ($path === '' || $path === 'dashboard') {
    require_auth();
    require __DIR__ . '/../app/controllers/DashboardController.php';
    exit;
}

if ($path === 'login') {
    require __DIR__ . '/../app/controllers/AuthController.php';
    exit;
}

if ($path === 'logout') {
    require_auth();
    logout_user();
    redirect('login');
}

if (preg_match('#^assessments/(\d+)/?$#', $path, $m)) {
    require_auth();
    require __DIR__ . '/../app/controllers/AssessmentController.php';
    show_assessment((int) $m[1]);
    exit;
}

if (preg_match('#^assessments/(\d+)/save/?$#', $path, $m) && $method === 'POST') {
    require_auth();
    require __DIR__ . '/../app/controllers/AssessmentController.php';
    save_assessment((int) $m[1]);
    exit;
}

if (preg_match('#^assessments/(\d+)/report/?$#', $path, $m)) {
    require_auth();
    require __DIR__ . '/../app/controllers/AssessmentController.php';
    show_report((int) $m[1]);
    exit;
}

if ($path === 'assessments/new' && $method === 'POST') {
    require_auth();
    require __DIR__ . '/../app/controllers/AssessmentController.php';
    create_assessment();
    exit;
}

if ($path === 'assessments/new') {
    require_auth();
    require __DIR__ . '/../app/controllers/AssessmentController.php';
    new_assessment();
    exit;
}

http_response_code(404);
echo '404 - Page not found';
