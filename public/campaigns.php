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

$stmt = $db->prepare("
    SELECT c.*, 
    (SELECT COUNT(*) FROM email_queue eq WHERE eq.campaign_id = c.id) as total,
    (SELECT COUNT(*) FROM email_queue eq WHERE eq.campaign_id = c.id AND eq.status = 'sent') as sent,
    (SELECT COUNT(*) FROM email_queue eq WHERE eq.campaign_id = c.id AND eq.status = 'failed') as failed
    FROM campaigns c 
    WHERE c.user_id = ? 
    ORDER BY c.created_at DESC
");
$stmt->execute([$user['id']]);
$campaigns = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Campaigns - Ethical Bulk Sender</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>

<body>
    <div class="container">
        <header style="margin-bottom: 20px;">
            <h1 style="color: var(--primary-color);">Your Campaigns</h1>
            <a href="dashboard.php" class="btn btn-secondary">Back to Dashboard</a>
            <a href="create_campaign.php" class="btn" style="margin-left: 10px;">New Campaign</a>
        </header>

        <div class="card">
            <?php if (empty($campaigns)): ?>
            <p>No campaigns found.</p>
            <?php else: ?>
            <table style="width: 100%; border-collapse: collapse;">
                <thead>
                    <tr style="text-align: left; background: #eee;">
                        <th style="padding: 10px;">Name</th>
                        <th style="padding: 10px;">Status</th>
                        <th style="padding: 10px;">Progress</th>
                        <th style="padding: 10px;">Start Time</th>
                        <th style="padding: 10px;">Rate</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($campaigns as $campaign): ?>
                    <tr style="border-bottom: 1px solid #ddd;">
                        <td style="padding: 10px;">
                            <?php echo htmlspecialchars($campaign['name']); ?>
                        </td>
                        <td style="padding: 10px;">
                            <span style="
                                        padding: 3px 8px; border-radius: 4px; font-size: 12px;
                                        background: <?php 
                                            echo match($campaign['status']) {
                                                'completed' => '#4CAF50',
                                                'running' => '#2196F3',
                                                'scheduled' => '#FFC107',
                                                default => '#9E9E9E'
                                            }; 
                                        ?>; color: white;">
                                <?php echo ucfirst($campaign['status']); ?>
                            </span>
                        </td>
                        <td style="padding: 10px;">
                            <?php echo $campaign['sent'] . ' / ' . $campaign['total']; ?>
                            (
                            <?php echo $campaign['failed']; ?> failed)
                        </td>
                        <td style="padding: 10px;">
                            <?php echo $campaign['start_time']; ?>
                        </td>
                        <td style="padding: 10px;">
                            <?php echo $campaign['send_rate']; ?>/hr
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