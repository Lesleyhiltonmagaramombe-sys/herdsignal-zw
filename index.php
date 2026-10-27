<?php
require_once __DIR__ . '/config/config.php';
header('Location: ' . BASE_URL . (empty($_SESSION['user_id']) ? '/login.php' : '/dashboard.php'));
exit;
