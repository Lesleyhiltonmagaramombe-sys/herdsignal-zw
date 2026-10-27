
<?php
/**
 * HerdSignal ZW — CLI reminder email sender.
 * Run using PHP from Windows Task Scheduler.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Command-line execution only.\n");
}

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/config/mail_config.php';

use PHPMailer\PHPMailer\PHPMailer;

date_default_timezone_set('Africa/Harare');

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// Keep these database settings consistent with config/config.php.
$dbHost = 'localhost';
$dbName = 'herdsignal_zw';
$dbUser = 'root';
$dbPass = '';

try {
    $conn = new mysqli($dbHost, $dbUser, $dbPass, $dbName);
    $conn->set_charset('utf8mb4');
} catch (Throwable $e) {
    error_log('HerdSignal database connection failed: ' . $e->getMessage());
    fwrite(STDERR, "Database connection failed. Check the database settings.\n");
    exit(1);
}

$sql = "
    SELECT
        r.id AS reminder_id,
        r.owner_id,
        r.title,
        r.due_date,
        r.notes,
        u.full_name,
        u.email,
        a.name AS animal_name,
        a.animal_code,
        a.species
    FROM reminders r
    INNER JOIN users u ON u.id = r.owner_id
    LEFT JOIN animals a
        ON a.id = r.animal_id
       AND a.owner_id = r.owner_id
    WHERE r.status = 'Pending'
      AND r.email_sent_at IS NULL
      AND r.due_date <= DATE_ADD(CURDATE(), INTERVAL 7 DAY)
      AND u.role = 'farmer'
      AND u.email IS NOT NULL
      AND TRIM(u.email) <> ''
    ORDER BY r.due_date ASC, r.id ASC
";

try {
    $result = $conn->query($sql);
} catch (Throwable $e) {
    error_log('HerdSignal reminder query failed: ' . $e->getMessage());
    fwrite(STDERR, "Could not load reminders. Check the PHP error log.\n");
    $conn->close();
    exit(1);
}

$sent = 0;
$failed = 0;
$skipped = 0;
$matched = $result->num_rows;

while ($r = $result->fetch_assoc()) {
    $id = (int)$r['reminder_id'];
    $email = trim((string)$r['email']);

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Farmer email address is invalid.';
        $stmt = $conn->prepare(
            'UPDATE reminders SET email_last_error = ? WHERE id = ?'
        );
        $stmt->bind_param('si', $error, $id);
        $stmt->execute();
        $stmt->close();
        $skipped++;
        continue;
    }

    $due = date('d M Y', strtotime($r['due_date']));
    $animal = $r['animal_code'] ?: ($r['species'] ?: 'Not specified');

    if ($r['animal_code'] && $r['animal_name']) {
        $animal .= ' (' . $r['animal_name'] . ')';
    }

    $notes = trim((string)($r['notes'] ?? ''));

    $safeName = htmlspecialchars((string)$r['full_name'], ENT_QUOTES, 'UTF-8');
    $safeTitle = htmlspecialchars((string)$r['title'], ENT_QUOTES, 'UTF-8');
    $safeAnimal = htmlspecialchars($animal, ENT_QUOTES, 'UTF-8');
    $safeDue = htmlspecialchars($due, ENT_QUOTES, 'UTF-8');
    $safeNotes = nl2br(htmlspecialchars($notes, ENT_QUOTES, 'UTF-8'));

    $mail = new PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host = MAIL_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = MAIL_USERNAME;
        $mail->Password = MAIL_PASSWORD;
        $mail->Port = MAIL_PORT;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->CharSet = 'UTF-8';

        $mail->setFrom(MAIL_FROM_ADDRESS, MAIL_FROM_NAME);
        $mail->addAddress($email, (string)$r['full_name']);
        $mail->isHTML(true);
        $mail->Subject = 'HerdSignal ZW Reminder: ' . $r['title'];

        $mail->Body = "
            <div style='font-family:Arial,sans-serif;max-width:600px;
                        margin:auto;color:#20352a;line-height:1.6'>
                <div style='background:#14532d;color:#fff;padding:20px'>
                    <h2>HerdSignal ZW</h2>
                    <p>Livestock care reminder</p>
                </div>
                <div style='padding:22px;border:1px solid #ddd'>
                    <p>Hello {$safeName},</p>
                    <p>This is a reminder about an upcoming or overdue
                       livestock activity.</p>
                    <p><strong>Reminder:</strong> {$safeTitle}</p>
                    <p><strong>Animal:</strong> {$safeAnimal}</p>
                    <p><strong>Due date:</strong> {$safeDue}</p>
                    " . ($notes !== '' ? "<p><strong>Notes:</strong><br>{$safeNotes}</p>" : "") . "
                    <p>Please take the necessary action and update your
                       reminder in HerdSignal ZW when completed.</p>
                    <p>Thank you,<br><strong>HerdSignal ZW</strong></p>
                </div>
            </div>
        ";

        $mail->AltBody =
            "Hello {$r['full_name']},\n\n" .
            "Reminder: {$r['title']}\nAnimal: {$animal}\nDue date: {$due}\n" .
            ($notes !== '' ? "Notes: {$notes}\n" : '') .
            "\nHerdSignal ZW";

        $mail->send();

        $stmt = $conn->prepare(
            'UPDATE reminders
             SET email_sent_at = NOW(), email_last_error = NULL
             WHERE id = ? AND email_sent_at IS NULL'
        );
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $stmt->close();

        $sent++;
    } catch (Throwable $e) {
        // Avoid logging SMTP credentials or returning technical details to users.
        $error = substr(
            preg_replace('/[\r\n\t]+/', ' ', $e->getMessage()),
            0,
            255
        );

        try {
            $stmt = $conn->prepare(
                'UPDATE reminders SET email_last_error = ? WHERE id = ?'
            );
            $stmt->bind_param('si', $error, $id);
            $stmt->execute();
            $stmt->close();
        } catch (Throwable $dbError) {
            error_log('HerdSignal could not save an email error for reminder ' . $id);
        }

        error_log("HerdSignal email failed for reminder {$id}: {$error}");
        $failed++;
    }
}

echo "HerdSignal reminder email run completed.\n";
echo "Matching reminders: {$matched}\n";
echo "Sent: {$sent}\n";
echo "Failed: {$failed}\n";
echo "Skipped (invalid email): {$skipped}\n";

$conn->close();
