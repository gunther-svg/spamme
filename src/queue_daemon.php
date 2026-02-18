<?php
/**
 * Queue Daemon — runs as a background process.
 * Continuously processes campaigns, sending emails at the configured rate PER RECIPIENT per hour.
 * 
 * Usage: php src/queue_daemon.php &
 * Stop:  Kill the process (PID stored in src/daemon.pid)
 */
require_once __DIR__ . '/../vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

use App\Database;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Write PID file so we can stop it later
$pidFile = __DIR__ . '/daemon.pid';
file_put_contents($pidFile, getmypid());

// Logging
function daemon_log($msg)
{
    $time = date('Y-m-d H:i:s');
    $logFile = __DIR__ . '/daemon.log';
    file_put_contents($logFile, "[$time] $msg\n", FILE_APPEND);
    echo "[$time] $msg\n";
}

daemon_log("Queue daemon started (PID: " . getmypid() . ")");

// Configuration
$LOOP_INTERVAL = 10; // seconds between each processing cycle
$BATCH_PER_CYCLE = 5; // max emails to send per recipient per cycle (to avoid blocking)

while (true) {
    try {
        $db = Database::getInstance()->getConnection();

        // Get active campaigns that are due
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
            if ($campaign['credits'] <= 0) {
                daemon_log("Campaign \"{$campaign['name']}\" (ID:{$campaign['id']}): No credits. Skipping.");
                continue;
            }

            // Update campaign status to running
            if ($campaign['status'] === 'scheduled') {
                $db->prepare("UPDATE campaigns SET status = 'running' WHERE id = ?")->execute([$campaign['id']]);
            }

            // Get all active/pending recipients for this campaign
            $stmt = $db->prepare("
                SELECT * FROM email_queue 
                WHERE campaign_id = ? AND status IN ('pending', 'active')
            ");
            $stmt->execute([$campaign['id']]);
            $recipients = $stmt->fetchAll();

            if (empty($recipients)) {
                // All recipients done — mark campaign completed
                $db->prepare("UPDATE campaigns SET status = 'completed' WHERE id = ?")->execute([$campaign['id']]);
                daemon_log("Campaign \"{$campaign['name']}\" (ID:{$campaign['id']}): All recipients processed. Completed.");
                continue;
            }

            // Set up mailer once per campaign
            try {
                $mail = new PHPMailer(true);
                $mail->isSMTP();
                $mail->Host = $campaign['host'];
                $mail->SMTPAuth = true;
                $mail->Username = $campaign['username'];
                $mail->Password = $campaign['smtp_password'];
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port = $campaign['port'];

                $fromEmail = !empty($campaign['from_email']) ? $campaign['from_email'] : $campaign['username'];
                $fromName = !empty($campaign['from_name']) ? $campaign['from_name'] : 'Bulk Sender';
                $mail->setFrom($fromEmail, $fromName);

                $mail->Subject = $campaign['subject'];
                $mail->Body = $campaign['body'];
                $mail->isHTML(true);
            }
            catch (Exception $e) {
                daemon_log("Campaign \"{$campaign['name']}\" (ID:{$campaign['id']}): SMTP setup error — {$e->getMessage()}");
                continue;
            }

            $send_rate = (int)$campaign['send_rate']; // emails per recipient per hour

            foreach ($recipients as $recipient) {
                if ($campaign['credits'] <= 0)
                    break;

                // Mark as active if still pending
                if ($recipient['status'] === 'pending') {
                    $db->prepare("UPDATE email_queue SET status = 'active' WHERE id = ?")->execute([$recipient['id']]);
                }

                // Count how many emails sent to THIS recipient in the last hour
                $stmt = $db->prepare("
                    SELECT COUNT(*) FROM email_sent_log 
                    WHERE queue_id = ? AND sent_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
                ");
                $stmt->execute([$recipient['id']]);
                $sent_this_hour = (int)$stmt->fetchColumn();

                $remaining = $send_rate - $sent_this_hour;
                if ($remaining <= 0) {
                    continue; // Rate limit reached for this recipient this hour
                }

                // Send up to BATCH_PER_CYCLE emails to this recipient in this cycle
                $to_send = min($remaining, $BATCH_PER_CYCLE, $campaign['credits']);

                for ($i = 0; $i < $to_send; $i++) {
                    try {
                        $mail->clearAddresses();
                        $mail->addAddress($recipient['recipient_email']);
                        $mail->send();

                        // Log the send
                        $db->prepare("INSERT INTO email_sent_log (campaign_id, queue_id, recipient_email) VALUES (?, ?, ?)")
                            ->execute([$campaign['id'], $recipient['id'], $recipient['recipient_email']]);

                        // Deduct credit
                        $db->prepare("UPDATE users SET credits = credits - 1 WHERE id = ?")->execute([$campaign['user_id']]);
                        $campaign['credits']--;

                        $send_num = $sent_this_hour + $i + 1;
                        daemon_log("Sent to {$recipient['recipient_email']} (campaign:{$campaign['id']}, send #{$send_num}/{$send_rate}/hr)");

                    }
                    catch (Exception $e) {
                        daemon_log("Failed to send to {$recipient['recipient_email']}: {$mail->ErrorInfo}");
                        try {
                            $mail->getSMTPInstance()->reset();
                        }
                        catch (\Exception $ex) {
                        }
                        break; // Stop sending to this recipient on error
                    }
                }
            }
        }

    }
    catch (\Exception $e) {
        daemon_log("Daemon error: " . $e->getMessage());
    }

    // Sleep before next cycle
    sleep($LOOP_INTERVAL);
}