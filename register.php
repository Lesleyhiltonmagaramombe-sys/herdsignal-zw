
<?php
require_once __DIR__ . '/config/config.php';

if (!empty($_SESSION['user_id'])) {
    header('Location: ' . BASE_URL . '/dashboard.php');
    exit;
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $name = trim($_POST['full_name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $email = strtolower(trim($_POST['email'] ?? ''));
    $community = trim($_POST['community'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    // Validate the registration details.
    if ($name === '' || $phone === '' || $community === '') {
        $errors[] = 'Complete all required fields.';
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Enter a valid email address for reminders.';
    }

    if (strlen($password) < 8) {
        $errors[] = 'Password must be at least 8 characters.';
    }

    if ($password !== $confirm) {
        $errors[] = 'Passwords do not match.';
    }

    // Check whether the phone number or email already exists.
    if (!$errors) {
        $check = $conn->prepare(
            'SELECT id FROM users WHERE phone = ? OR email = ? LIMIT 1'
        );

        $check->bind_param('ss', $phone, $email);
        $check->execute();

        if ($check->get_result()->num_rows > 0) {
            $errors[] = 'That phone number or email is already registered.';
        }

        $check->close();
    }

    // Create the account.
    if (!$errors) {
        $hash = password_hash($password, PASSWORD_DEFAULT);

        $stmt = $conn->prepare(
            "INSERT INTO users
            (full_name, phone, email, password_hash, role, community)
            VALUES (?, ?, ?, ?, 'farmer', ?)"
        );

        $stmt->bind_param(
            'sssss',
            $name,
            $phone,
            $email,
            $hash,
            $community
        );

        try {
            $stmt->execute();
            $stmt->close();

            // Account is saved. Now attempt the welcome email.
            $welcomeEmailSent = false;

            try {
                require_once __DIR__ . '/vendor/autoload.php';
                require_once __DIR__ . '/config/mail_config.php';

                $mail = new \PHPMailer\PHPMailer\PHPMailer(true);

                $mail->isSMTP();
                $mail->Host = MAIL_HOST;
                $mail->SMTPAuth = true;
                $mail->Username = MAIL_USERNAME;
                $mail->Password = MAIL_PASSWORD;
                $mail->SMTPSecure = MAIL_ENCRYPTION;
                $mail->Port = MAIL_PORT;
                $mail->CharSet = 'UTF-8';

                $mail->setFrom(
                    MAIL_FROM_ADDRESS,
                    MAIL_FROM_NAME
                );

                $mail->addAddress($email, $name);

                $mail->isHTML(true);
                $mail->Subject = 'Welcome to HerdSignal ZW';

                $safeName = e($name);
                $safeCommunity = e($community);

                $mail->Body = '
                    <div style="font-family:Arial,sans-serif;
                                max-width:600px;margin:auto;
                                color:#202820;line-height:1.7;">
                        <div style="background:#0F3D24;color:#ffffff;
                                    padding:24px;border-radius:12px 12px 0 0;">
                            <h1 style="margin:0;">HerdSignal ZW</h1>
                            <p style="margin:8px 0 0;">
                                Smarter livestock management
                            </p>
                        </div>

                        <div style="padding:24px;border:1px solid #e4e8e4;">
                            <h2>Welcome, ' . $safeName . '!</h2>

                            <p>
                                Your HerdSignal ZW farmer account has been
                                created successfully.
                            </p>

                            <p>
                                You can use HerdSignal ZW to record your
                                livestock, track health information,
                                monitor animal movements and manage reminders.
                            </p>

                            <p>
                                <strong>Community:</strong>
                                ' . $safeCommunity . '
                            </p>

                            <p>
                                Log in to your account to get started and
                                keep your livestock records up to date.
                            </p>

                            <p style="margin-top:28px;">
                                Welcome aboard,<br>
                                <strong>The HerdSignal ZW Team</strong>
                            </p>
                        </div>

                        <p style="font-size:12px;color:#687368;
                                  text-align:center;padding:12px;">
                            This email was sent because a farmer account
                            was registered using this email address.
                        </p>
                    </div>
                ';

                $mail->AltBody =
                    "Welcome to HerdSignal ZW, {$name}!\n\n" .
                    "Your farmer account has been created successfully.\n\n" .
                    "You can use HerdSignal ZW to record livestock, " .
                    "track health information, monitor animal movements " .
                    "and manage reminders.\n\n" .
                    "Community: {$community}\n\n" .
                    "Log in to your account to get started.\n\n" .
                    "The HerdSignal ZW Team";

                $mail->send();
                $welcomeEmailSent = true;

            } catch (\Throwable $mailError) {
                // Keep the account even if the email cannot be sent.
                error_log(
                    'HerdSignal welcome email failed: ' .
                    $mailError->getMessage()
                );
            }

            if ($welcomeEmailSent) {
                flash(
                    'Account created successfully! A welcome email has been sent. You can now sign in.'
                );
            } else {
                flash(
                    'Account created successfully. You can now sign in. The welcome email could not be sent right now.',
                    'error'
                );
            }

            header('Location: ' . BASE_URL . '/login.php');
            exit;

        } catch (mysqli_sql_exception $e) {
            if (isset($stmt)) {
                $stmt->close();
            }

            error_log('HerdSignal registration failed: ' . $e->getMessage());

            $errors[] =
                'Could not create the account. Check whether the phone or email is already in use.';
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Farmer registration | HerdSignal ZW</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body class="auth-page">
    <div class="auth-card">
        <div class="auth-brand">
            <span class="brand-mark">H</span> HerdSignal ZW
        </div>

        <h1>Create your farmer account.</h1>
        <p>Keep your livestock details and reminders in one place.</p>

        <?php foreach ($errors as $error): ?>
            <div class="alert error"><?= e($error) ?></div>
        <?php endforeach; ?>

        <form method="post">
            <input
                type="hidden"
                name="csrf_token"
                value="<?= e(csrf_token()) ?>"
            >

            <div class="field">
                <label>Full name *</label>
                <input
                    name="full_name"
                    required
                    maxlength="120"
                    value="<?= e($_POST['full_name'] ?? '') ?>"
                >
            </div>

            <div class="field">
                <label>Phone number *</label>
                <input
                    name="phone"
                    required
                    maxlength="30"
                    autocomplete="tel"
                    value="<?= e($_POST['phone'] ?? '') ?>"
                >
            </div>

            <div class="field">
                <label>Email address *</label>
                <input
                    type="email"
                    name="email"
                    required
                    maxlength="160"
                    autocomplete="email"
                    value="<?= e($_POST['email'] ?? '') ?>"
                >
                <span class="hint">
                    Used for welcome emails and livestock reminders.
                </span>
            </div>

            <div class="field">
                <label>Community / village *</label>
                <input
                    name="community"
                    required
                    maxlength="120"
                    value="<?= e($_POST['community'] ?? '') ?>"
                >
            </div>

            <div class="field">
                <label>Password (minimum 8 characters) *</label>
                <input
                    type="password"
                    name="password"
                    required
                    minlength="8"
                    autocomplete="new-password"
                >
            </div>

            <div class="field">
                <label>Confirm password *</label>
                <input
                    type="password"
                    name="confirm_password"
                    required
                    minlength="8"
                    autocomplete="new-password"
                >
            </div>

            <button class="btn btn-primary" type="submit">
                Create farmer account →
            </button>
        </form>

        <div class="auth-foot">
            Already registered? <a href="login.php">Sign in</a>
        </div>
    </div>
</body>
</html>
