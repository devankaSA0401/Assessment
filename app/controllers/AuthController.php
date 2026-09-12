<?php
require_once __DIR__.'/../Auth.php';
if (current_user()) redirect('dashboard');
$error=null;
if ($_SERVER['REQUEST_METHOD']==='POST') { verify_csrf(); if (login_user(trim($_POST['email']??''), $_POST['password']??'')) redirect('dashboard'); $error='Email atau password tidak valid.'; }
$title='Login'; require __DIR__.'/../views/auth/login.php';
