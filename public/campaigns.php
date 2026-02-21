<?php
require_once __DIR__ . '/../vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

session_start();
use App\Auth;
use App\Database;

$auth = new Auth();
$user = $auth->getUser();

if (!$user) {
    header('Location: index.php');
    exit;
}

$db = Database::getInstance()->getConnection();
$message = '';

// --- Daemon Management ---
$pidFile = __DIR__ . '/../src/daemon.pid';
$logFile = __DIR__ . '/../src/daemon.log';

function isDaemonRunning($pidFile) {
    if (!file_exists($pidFile)) return false;
    $pid = (int) trim(file_get_contents($pidFile));
    if ($pid <= 0) return false;
    // Check if process exists
    return file_exists("/proc/$pid");
}

$daemon_running = isDaemonRunning($pidFile);

// Handle Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Start Daemon
    if (isset($_POST['start_daemon'])) {
        if (!$daemon_running) {
            $cmd = 'nohup php ' . escapeshellarg(realpath(__DIR__ . '/../src/queue_daemon.php')) . ' > /dev/null 2>&1 & echo $!';
            $pid = trim(shell_exec($cmd));
            if ($pid) {
                file_put_contents($pidFile, $pid);
                $message = "✅ Queue daemon started (PID: $pid). Campaigns will process automatically.";
                $daemon_running = true;
            } else {
                $message = "❌ Failed to start daemon.";
            }
        } else {
            $message = "Daemon is already running.";
        }
    }

    // Stop Daemon
    if (isset($_POST['stop_daemon'])) {
        if ($daemon_running) {
            $pid = (int) trim(file_get_contents($pidFile));
            posix_kill($pid, SIGTERM);
            sleep(1);
            if (file_exists("/proc/$pid")) {
                posix_kill($pid, SIGKILL);
            }
            @unlink($pidFile);
            $message = "⏹ Queue daemon stopped.";
            $daemon_running = false;
        } else {
            $message = "Daemon is not running.";
        }
    }

    // Send Now — set start_time to NOW and mark scheduled
    if (isset($_POST['send_now'])) {
        $campaign_id = (int) $_POST['campaign_id'];
        $stmt = $db->prepare("UPDATE campaigns SET start_time = NOW(), status = 'scheduled' WHERE id = ? AND user_id = ? AND status IN ('scheduled', 'draft', 'paused')");
        $stmt->execute([$campaign_id, $user['id']]);
        // Also reset any queue entries to pending
        $db->prepare("UPDATE email_queue SET status = 'pending' WHERE campaign_id = ? AND status IN ('paused')")->execute([$campaign_id]);
        $message = $stmt->rowCount() > 0 
            ? "✅ Campaign set to send now. " . ($daemon_running ? "The daemon will pick it up shortly." : "Start the daemon to begin processing.")
            : "Campaign not found or already running/completed.";
    }

    // Pause
    if (isset($_POST['pause'])) {
        $campaign_id = (int) $_POST['campaign_id'];
        $db->prepare("UPDATE campaigns SET status = 'paused' WHERE id = ? AND user_id = ? AND status IN ('running', 'scheduled')")
           ->execute([$campaign_id, $user['id']]);
        $message = 'Campaign paused.';
    }

    // Resume
    if (isset($_POST['resume'])) {
        $campaign_id = (int) $_POST['campaign_id'];
        $db->prepare("UPDATE campaigns SET status = 'scheduled' WHERE id = ? AND user_id = ? AND status = 'paused'")
           ->execute([$campaign_id, $user['id']]);
        $message = 'Campaign resumed.';
    }

    // Delete
    if (isset($_POST['delete'])) {
        $campaign_id = (int) $_POST['campaign_id'];
        $db->prepare("DELETE FROM campaigns WHERE id = ? AND user_id = ?")->execute([$campaign_id, $user['id']]);
        $message = 'Campaign deleted.';
    }
}

// Fetch campaigns with stats from email_sent_log
$stmt = $db->prepare("
    SELECT c.*, 
    (SELECT COUNT(*) FROM email_queue eq WHERE eq.campaign_id = c.id) as total_recipients,
    (SELECT COUNT(*) FROM email_sent_log esl WHERE esl.campaign_id = c.id) as total_sent,
    (SELECT COUNT(*) FROM email_sent_log esl WHERE esl.campaign_id = c.id AND esl.sent_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)) as sent_last_hour
    FROM campaigns c 
    WHERE c.user_id = ? 
    ORDER BY c.created_at DESC
");
$stmt->execute([$user['id']]);
$campaigns = $stmt->fetchAll();

// Get last 10 daemon log lines
$log_lines = '';
if (file_exists($logFile)) {
    $lines = file($logFile);
    $log_lines = implode('', array_slice($lines, -10));
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Campaigns - Ethical Bulk Sender</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .actions form {
            display: inline-block;
            margin: 0 2px;
        }

        .actions button {
            padding: 5px 12px;
            font-size: 12px;
            cursor: pointer;
            border: none;
            border-radius: 4px;
            color: white;
        }

        .btn-send {
            background: #2196F3;
        }

        .btn-pause {
            background: #FF9800;
        }

        .btn-resume {
            background: #4CAF50;
        }

        .btn-delete {
            background: #f44336;
        }

        .daemon-status {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 500;
        }

        .daemon-running {
            background: #e8f5e9;
            color: #2e7d32;
        }

        .daemon-stopped {
            background: #ffebee;
            color: #c62828;
        }

        .daemon-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            display: inline-block;
        }

        .daemon-dot.on {
            background: #4CAF50;
            animation: pulse 1.5s infinite;
        }

        .daemon-dot.off {
            background: #f44336;
        }

        @keyframes pulse {

            0%,
            100% {
                opacity: 1;
            }

            50% {
                opacity: 0.4;
            }
        }

        .stat-box {
            text-align: center;
            padding: 15px;
            background: #f5f5f5;
            border-radius: 8px;
            flex: 1;
        }

        .stat-box h3 {
            margin: 0 0 5px;
            font-size: 24px;
            color: var(--primary-color);
        }

        .stat-box p {
            margin: 0;
            font-size: 12px;
            color: #666;
        }

        .log-box {
            background: #1e1e1e;
            color: #00ff41;
            padding: 12px;
            border-radius: 6px;
            font-family: monospace;
            font-size: 12px;
            max-height: 150px;
            overflow-y: auto;
            white-space: pre-wrap;
            word-wrap: break-word;
        }
    </style>
</head>

<body>
    <div class="container" style="max-width: 1100px;">
        <header
            style="margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
            <h1 style="color: var(--primary-color); margin: 0;">Campaigns</h1>
            <div style="display: flex; gap: 10px; align-items: center;">
                <a href="create_campaign.php" class="btn btn-secondary">+ New Campaign</a>
                <a href="dashboard.php" class="btn btn-secondary">Dashboard</a>
            </div>
        </header>

        <?php if ($message): ?>
        <div class="alert" style="white-space: pre-line;">
            <?php echo htmlspecialchars($message); ?>
        </div>
        <?php endif; ?>

        <!-- Daemon Control Panel -->
        <div class="card" style="margin-bottom: 20px;">
            <div
                style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
                <div>
                    <h3 style="margin: 0 0 5px;">Queue Daemon</h3>
                    <div class="daemon-status <?php echo $daemon_running ? 'daemon-running' : 'daemon-stopped'; ?>">
                        <span class="daemon-dot <?php echo $daemon_running ? 'on' : 'off'; ?>"></span>
                        <?php echo $daemon_running ? 'Running' : 'Stopped'; ?>
                    </div>
                    <p style="font-size: 12px; color: #888; margin: 5px 0 0;">
                        The daemon automatically sends emails at your configured rate per recipient per hour.
                    </p>
                </div>
                <div>
                    <?php if ($daemon_running): ?>
                    <form method="POST" style="display: inline;">
                        <button type="submit" name="stop_daemon" class="btn btn-secondary"
                            style="background: #f44336; color: white;">⏹ Stop Daemon</button>
                    </form>
                    <?php else: ?>
                    <form method="POST" style="display: inline;">
                        <button type="submit" name="start_daemon" class="btn">▶ Start Daemon</button>
                    </form>
                    <?php endif; ?>
                </div>
            </div>

            <?php if ($log_lines): ?>
            <details style="margin-top: 10px;">
                <summary style="cursor: pointer; font-size: 13px; color: #666;">Recent Daemon Logs</summary>
                <div class="log-box">
                    <?php echo htmlspecialchars($log_lines); ?>
                </div>
            </details>
            <?php endif; ?>
        </div>

        <!-- Campaign List -->
        <div class="card">
            <?php if (empty($campaigns)): ?>
            <p>No campaigns yet. <a href="create_campaign.php">Create one</a>.</p>
            <?php else: ?>
            <table style="width: 100%; border-collapse: collapse;">
                <thead>
                    <tr style="text-align: left; background: #f5f5f5;">
                        <th style="padding: 10px;">Campaign</th>
                        <th style="padding: 10px;">Status</th>
                        <th style="padding: 10px;">Recipients</th>
                        <th style="padding: 10px;">Emails Sent</th>
                        <th style="padding: 10px;">Rate</th>
                        <th style="padding: 10px;">Start Time</th>
                        <th style="padding: 10px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($campaigns as $campaign): 
                        $status_color = match($campaign['status']) {
                            'completed' => '#4CAF50',
                            'running' => '#2196F3',
                            'scheduled' => '#FFC107',
                            'paused' => '#FF9800',
                            default => '#9E9E9E'
                        };
                    ?>
                    <tr style="border-bottom: 1px solid #eee;">
                        <td style="padding: 10px;">
                            <strong>
                                <?php echo htmlspecialchars($campaign['name']); ?>
                            </strong>
                            <br><small style="color: #888;">
                                <?php echo htmlspecialchars($campaign['subject']); ?>
                            </small>
                        </td>
                        <td style="padding: 10px;">
                            <span
                                style="padding: 3px 8px; border-radius: 4px; font-size: 12px; background: <?php echo $status_color; ?>; color: white;">
                                <?php echo ucfirst($campaign['status']); ?>
                            </span>
                        </td>
                        <td style="padding: 10px;">
                            <?php echo $campaign['total_recipients']; ?>
                        </td>
                        <td style="padding: 10px;">
                            <strong>
                                <?php echo $campaign['total_sent']; ?>
                            </strong>
                            <br><small style="color: #888;">
                                <?php echo $campaign['sent_last_hour']; ?> last hr
                            </small>
                        </td>
                        <td style="padding: 10px;">
                            <?php 
                                $rate_label = match($campaign['rate_type']) {
                                    'global_hour' => 'Campaign / hr',
                                    'global_minute' => 'Campaign / min',
                                    default => 'Recipient / hr'
                                };
                                echo $campaign['send_rate'] . " <small style='color: #888; font-size: 11px;'>($rate_label)</small>"; 
                            ?>
                            <?php if (!empty($campaign['batch_delay']) && $campaign['batch_delay'] > 0): ?>
                            <br><small style="color: #00bcd4; font-size: 11px;">+
                                <?php echo $campaign['batch_delay']; ?>s delay
                            </small>
                            <?php endif; ?>
                        </td>
                        <td style="padding: 10px; font-size: 13px;">
                            <?php echo $campaign['start_time'] ? date('M j, g:ia', strtotime($campaign['start_time'])) : 'Immediate'; ?>
                        </td>
                        <td style="padding: 10px;" class="actions">
                            <?php if (in_array($campaign['status'], ['scheduled', 'draft', 'paused'])): ?>
                            <form method="POST">
                                <input type="hidden" name="campaign_id" value="<?php echo $campaign['id']; ?>">
                                <button type="submit" name="send_now" class="btn-send" title="Send now">▶ Send</button>
                            </form>
                            <?php endif; ?>
                            <?php if (in_array($campaign['status'], ['running', 'scheduled'])): ?>
                            <form method="POST">
                                <input type="hidden" name="campaign_id" value="<?php echo $campaign['id']; ?>">
                                <button type="submit" name="pause" class="btn-pause">⏸ Pause</button>
                            </form>
                            <?php endif; ?>
                            <?php if ($campaign['status'] === 'paused'): ?>
                            <form method="POST">
                                <input type="hidden" name="campaign_id" value="<?php echo $campaign['id']; ?>">
                                <button type="submit" name="resume" class="btn-resume">▶ Resume</button>
                            </form>
                            <?php endif; ?>
                            <form method="POST" onsubmit="return confirm('Delete this campaign?');">
                                <input type="hidden" name="campaign_id" value="<?php echo $campaign['id']; ?>">
                                <button type="submit" name="delete" class="btn-delete">🗑</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>
    </div>
</body>

</html>