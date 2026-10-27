
<?php
/**
 * Checks and sends due-soon reminder emails for the logged-in farmer.
 * This file is included by dashboard.php, not opened directly.
 */

if (!defined('HERDSIGNAL_DASHBOARD_REQUEST')) {
    http_response_code(403);
    exit('Access denied.');
}

if (empty($_SESSION['user_id'])) {
    return;
}

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/config/mail_config.php';

use PHPMailer\PHPMailer\PHPMailer;

try {
    $uid = (int) $_SESSION['user_id'];

    // Only send reminders belonging to this logged-in farmer.
    $stmt = $conn->prepare("
        SELECT
            r.id,
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
        WHERE r.owner_id = ?
          AND u.role = 'farmer'
          AND r.status = 'Pending'
          AND r.email_sent_at IS NULL
          AND r.due_date <= DATE_ADD(CURDATE(), INTERVAL 7 DAY)
          AND u.email IS NOT NULL
          AND TRIM(u.email) <> ''
        ORDER BY r.due_date ASC, r.id ASC
        LIMIT 10
    ");

    $stmt->bind_param('i', $uid);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($r = $result->fetch_assoc()) {
        $id = (int) $r['id'];
        $email = trim((string) $r['email']);

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            continue;
        }

        $due = date('d M Y', strtotime($r['due_date']));
        $animal = $r['animal_code']
            ?: ($r['species'] ?: 'Not specified');

        if ($r['animal_code'] && $r['animal_name']) {
            $animal .= ' (' . $r['animal_name'] . ')';
        }

        $notes = trim((string) ($r['notes'] ?? ''));

        $safeName = htmlspecialchars(
            (string) $r['full_name'],
            ENT_QUOTES,
            'UTF-8'
        );
        $safeTitle = htmlspecialchars(
            (string) $r['title'],
            ENT_QUOTES,
            'UTF-8'
        );
        $safeAnimal = htmlspecialchars($animal, ENT_QUOTES, 'UTF-8');
        $safeDue = htmlspecialchars($due, ENT_QUOTES, 'UTF-8');
        $safeNotes = nl2br(
            htmlspecialchars($notes, ENT_QUOTES, 'UTF-8')
        );

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
            $mail->addAddress($email, (string) $r['full_name']);
            $mail->isHTML(true);
            $mail->Subject = 'HerdSignal ZW Reminder: ' . $r['title'];

            $mail->Body = "
                <div style='font-family:Arial,sans-serif;
                            max-width:600px;margin:auto;
                            color:#20352a;line-height:1.6'>
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
                        " . ($notes !== ''
                            ? "<p><strong>Notes:</strong><br>{$safeNotes}</p>"
                            : "") . "
                        <p>Please update your reminder in HerdSignal ZW
                           when the activity is completed.</p>
                        <p>Thank you,<br><strong>HerdSignal ZW</strong></p>
                    </div>
                </div>
            ";

            $mail->AltBody =
                "Hello {$r['full_name']},\n\n" .
                "Reminder: {$r['title']}\n" .
                "Animal: {$animal}\n" .
                "Due date: {$due}\n" .
                ($notes !== '' ? "Notes: {$notes}\n" : '') .
                "\nHerdSignal ZW";

            $mail->send();

            // Mark it as sent only after successful SMTP submission.
            $update = $conn->prepare("
                UPDATE reminders
                SET email_sent_at = NOW(), email_last_error = NULL
                WHERE id = ? AND owner_id = ? AND email_sent_at IS NULL
            ");
            $update->bind_param('ii', $id, $uid);
            $update->execute();
            $update->close();

        } catch (Throwable $mailError) {
            error_log(
                'HerdSignal dashboard reminder email failed for reminder ' .
                $id . ': ' . $mailError->getMessage()
            );

            // Leave email_sent_at NULL so a later check can retry.
        }
    }

    $stmt->close();

} catch (Throwable $error) {
    // An email-check failure must not stop the dashboard from loading.
    error_log('HerdSignal dashboard reminder check failed: ' .
        $error->getMessage());
}
