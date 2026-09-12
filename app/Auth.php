<?php
require_once __DIR__ . '/Database.php';
function login_user(string $email, string $password): bool {
    $stmt = Database::get()->prepare('SELECT * FROM users WHERE email = ? LIMIT 1'); $stmt->execute([$email]); $user=$stmt->fetch();
    if ($user && password_verify($password, $user['password'])) { session_regenerate_id(true); $_SESSION['user']=['id'=>$user['id'],'name'=>$user['name'],'email'=>$user['email'],'role'=>$user['role']]; return true; }
    return false;
}
function logout_user(): void { $_SESSION=[]; if (ini_get('session.use_cookies')) { $p=session_get_cookie_params(); setcookie(session_name(), '', time()-42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']); } session_destroy(); }
