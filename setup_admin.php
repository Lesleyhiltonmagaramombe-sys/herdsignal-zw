<?php
require_once __DIR__ . '/config/config.php';
$count = (int)$conn->query("SELECT COUNT(*) AS c FROM users")->fetch_assoc()['c'];
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    if ($count > 0) {
        $message = 'A user already exists. Delete setup_admin.php and sign in.';
    } else {
        $name = trim($_POST['full_name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $password = $_POST['password'] ?? '';
        if ($name === '' || $phone === '' || strlen($password) < 8) {
            $message = 'Enter your name, phone and a password of at least 8 characters.';
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("INSERT INTO users(full_name,phone,password_hash,role) VALUES(?,?,?,'admin')");
            $stmt->bind_param('sss', $name, $phone, $hash);
            $stmt->execute();
            $message = 'Administrator created. Now delete setup_admin.php and sign in.';
            $count = 1;
        }
    }
}
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Initial Setup | HerdSignal ZW</title><link rel="stylesheet" href="assets/style.css"></head><body class="auth-page"><div class="auth-card"><div class="auth-brand"><span class="brand-mark">H</span> HerdSignal ZW</div><h1>Initial setup</h1><p>Create the first administrator account. This page is only for first-time local setup.</p><?php if($message): ?><div class="alert info"><?=e($message)?></div><?php endif; ?><?php if($count===0): ?><form method="post"><input type="hidden" name="csrf_token" value="<?=e(csrf_token())?>"><div class="field"><label>Full name</label><input name="full_name" required maxlength="120"></div><div class="field"><label>Phone number</label><input name="phone" required maxlength="30" placeholder="e.g. 077... "></div><div class="field"><label>Password</label><input type="password" name="password" required minlength="8"></div><button class="btn btn-primary" type="submit">Create administrator</button></form><?php else: ?><a class="btn btn-primary" href="login.php">Go to sign in</a><?php endif; ?><div class="auth-foot">Important: delete setup_admin.php after creating the administrator account.</div></div></body></html>
