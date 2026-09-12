<?php
function e(?string $value): string { return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8'); }
function url(string $path = ''): string {
    $base = rtrim((require __DIR__ . '/../config/config.php')['base_url'], '/');
    return $base . '/' . ltrim($path, '/');
}
function redirect(string $path): never { header('Location: ' . url($path)); exit; }
function flash(string $key, ?string $value = null): ?string {
    if ($value !== null) { $_SESSION['_flash'][$key] = $value; return null; }
    $v = $_SESSION['_flash'][$key] ?? null; unset($_SESSION['_flash'][$key]); return $v;
}
function csrf_token(): string { if (empty($_SESSION['_csrf'])) $_SESSION['_csrf'] = bin2hex(random_bytes(32)); return $_SESSION['_csrf']; }
function csrf_field(): string { return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">'; }
function verify_csrf(): void { if (!hash_equals($_SESSION['_csrf'] ?? '', $_POST['_csrf'] ?? '')) { http_response_code(419); exit('Invalid CSRF token'); } }
function current_user(): ?array { return $_SESSION['user'] ?? null; }
function require_auth(): void { if (!current_user()) redirect('login'); }
function old(string $key, string $default=''): string { return e($_POST[$key] ?? $default); }
function status_score(?string $status): ?int { return match($status) { 'Compliant'=>100, 'Partially Compliant'=>50, 'Non-Compliant'=>0, default=>null }; }
function status_class(?string $status): string { return match($status) { 'Compliant'=>'success','Partially Compliant'=>'warning','Non-Compliant'=>'danger','N/A'=>'muted',default=>'pending' }; }
