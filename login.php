<?php
require_once __DIR__ . '/config/config.php';
if (!empty($_SESSION['user_id'])) { header('Location: ' . BASE_URL . '/dashboard.php'); exit; }
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $phone = trim($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';
    $stmt = $conn->prepare('SELECT id, full_name, phone, email, password_hash, role FROM users WHERE phone = ? LIMIT 1');
    $stmt->bind_param('s', $phone); $stmt->execute(); $user = $stmt->get_result()->fetch_assoc();
    if ($user && password_verify($password, $user['password_hash'])) {
        session_regenerate_id(true);
        $_SESSION['user_id'] = (int)$user['id']; $_SESSION['full_name'] = $user['full_name']; $_SESSION['role'] = $user['role']; $_SESSION['phone'] = $user['phone']; $_SESSION['email'] = $user['email'] ?? ''; 
        header('Location: ' . BASE_URL . '/dashboard.php'); exit;
    }
    $error = 'Phone number or password is incorrect.';
}
?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Sign in | HerdSignal ZW</title><link rel="stylesheet" href="assets/style.css"></head><body class="auth-page"><div class="auth-card"><div class="auth-brand"><span class="brand-mark">H</span> HerdSignal ZW</div><h1>Welcome back.</h1><p>Sign in to manage your livestock records and upcoming activities.</p><?php if($error): ?><div class="alert error"><?=e($error)?></div><?php endif; ?><form method="post"><input type="hidden" name="csrf_token" value="<?=e(csrf_token())?>"><div class="field"><label>Phone number</label><input name="phone" required autocomplete="username" placeholder="Enter your phone number"></div><div class="field"><label>Password</label><input type="password" name="password" required autocomplete="current-password" placeholder="Enter your password"></div><button class="btn btn-primary" type="submit">Sign in →</button></form><div class="auth-foot">New farmer? <a href="register.php">Create an account</a>. Administrator setup is only for the system administrator.</div></div></body></html>
