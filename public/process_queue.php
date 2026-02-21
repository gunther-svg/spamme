<?php
require_once __DIR__ . '/../vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

use App\Database;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use App\Settings;

$db = Database::getInstance()->getConnection();
$cost_per_email = (int)Settings::get('cost_per_email', 1);

echo "Starting queue processing...\n";

// 1. Get running or scheduled campaigns that are due
$stmt = $db->prepare("
    SELECT c.*, s.host, s.port, s.username, s.password as smtp_password, u.credits 
    FROM campaigns c
    JOIN smtp_configs s ON c.smtp_config_id = s.id
    JOIN users u ON c.user_id = u.id
    WHERE c.status IN ('scheduled', 'running') 
    AND (c.start_time IS NULL OR c.start_time <= NOW())
");
$stmt->execute();
$campaigns = $stmt->fetchAll();

foreach ($campaigns as $campaign) {
    echo "Processing campaign: {$campaign['name']} (ID: {$campaign['id']})\n";

    if ($campaign['credits'] < $cost_per_email) {
        echo "User has not enough credits. Pausing campaign.\n";
        // Optionally pause campaign
        continue;
    }

    // Check rate limit
    $stmt = $db->prepare("SELECT COUNT(*) FROM email_queue WHERE campaign_id = ? AND status = 'sent' AND sent_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)");
    $stmt->execute([$campaign['id']]);
    $sent_last_hour = $stmt->fetchColumn();

    if ($sent_last_hour >= $campaign['send_rate']) {
        echo "Rate limit reached for campaign {$campaign['id']}. Sent $sent_last_hour/{$campaign['send_rate']} in last hour.\n";
        continue;
    }

    $limit = $campaign['send_rate'] - $sent_last_hour;
    // Process a batch (e.g., max 10 at a time per run to avoid timeouts, or use $limit)
    $batch_size = min($limit, 10);

    // Fetch pending emails
    $stmt = $db->prepare("SELECT * FROM email_queue WHERE campaign_id = ? AND status = 'pending' LIMIT $batch_size");
    $stmt->execute([$campaign['id']]);
    $emails = $stmt->fetchAll();

    if (empty($emails)) {
        echo "No pending emails for campaign {$campaign['id']}.\n";
        // Update campaign status to completed if no pending emails left?
        // Check total pending
        $stmt_check = $db->prepare("SELECT COUNT(*) FROM email_queue WHERE campaign_id = ? AND status = 'pending'");
        $stmt_check->execute([$campaign['id']]);
        if ($stmt_check->fetchColumn() == 0) {
            $db->prepare("UPDATE campaigns SET status = 'completed' WHERE id = ?")->execute([$campaign['id']]);
        }
        continue;
    }

    // Update campaign status to running if it was scheduled
    if ($campaign['status'] == 'scheduled') {
        $db->prepare("UPDATE campaigns SET status = 'running' WHERE id = ?")->execute([$campaign['id']]);
    }

    $mail = new PHPMailer(true);
    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host = $campaign['host'];
        $mail->SMTPAuth = true;
        $mail->Username = $campaign['username'];
        $mail->Password = $campaign['smtp_password'];
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = $campaign['port'];

        // Use Campaign From Address if available, otherwise use SMTP config default
        $fromEmail = !empty($campaign['from_email']) ? $campaign['from_email'] : $campaign['username'];
        $fromName = !empty($campaign['from_name']) ? $campaign['from_name'] : 'Bulk Sender';
        $mail->setFrom($fromEmail, $fromName);

        $mail->Subject = $campaign['subject'];
        $mail->Body = $campaign['body'];
        $mail->isHTML(true);

        foreach ($emails as $email_row) {
            if ($campaign['credits'] < $cost_per_email)
                break;

            try {
                $mail->addAddress($email_row['recipient_email']);

                $mail->send();
                echo "Sent to: {$email_row['recipient_email']}\n";

                // Update queue
                $db->prepare("UPDATE email_queue SET status = 'sent', sent_at = NOW() WHERE id = ?")->execute([$email_row['id']]);

                // Deduct credit
                $db->prepare("UPDATE users SET credits = credits - ? WHERE id = ?")->execute([$cost_per_email, $campaign['user_id']]);
                $campaign['credits'] -= $cost_per_email;

                $mail->clearAddresses();

            }
            catch (Exception $e) {
                echo "Message could not be sent to {$email_row['recipient_email']}. Mailer Error: {$mail->ErrorInfo}\n";
                $db->prepare("UPDATE email_queue SET status = 'failed' WHERE id = ?")->execute([$email_row['id']]);
                $mail->getSMTPInstance()->reset(); // Reset connection if needed
                $mail->clearAddresses();
            }
        }

    }
    catch (Exception $e) {
        echo "Mailer Configuration Error: {$mail->ErrorInfo}\n";
    }
}
echo "Queue processing complete.\n";