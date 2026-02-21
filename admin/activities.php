<?php
require_once __DIR__ . '/../vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

session_start();
use App\AdminAuth;
use App\Database;

$auth = new AdminAuth();
$admin = $auth->getAdmin();

if (!$admin) {
    header('Location: login.php');
    exit;
}

$db = Database::getInstance()->getConnection();

// Fetch campaigns with user emails
$campaigns = $db->query("
    SELECT c.*, u.email as user_email 
    FROM campaigns c 
    JOIN users u ON c.user_id = u.id 
    ORDER BY c.created_at DESC 
    LIMIT 50
")->fetchAll();

// Fetch recent emails sent
$recentEmails = $db->query("
    SELECT log.*, c.name as campaign_name, u.email as sender_email
    FROM email_sent_log log
    JOIN campaigns c ON log.campaign_id = c.id
    JOIN users u ON c.user_id = u.id
    ORDER BY log.sent_at DESC
    LIMIT 100
")->fetchAll();

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Activities - Admin Portal</title>
    <!-- Use relative path to public css -->
    <link rel="stylesheet" href="../public/assets/css/style.css">
    <style>
        .tabs {
            display: flex;
            border-bottom: 1px solid #ddd;
            margin-bottom: 20px;
        }

        .tab {
            padding: 10px 20px;
            cursor: pointer;
            border: 1px solid transparent;
            margin-bottom: -1px;
        }

        .tab.active {
            background: white;
            border-color: #ddd #ddd white #ddd;
            border-top: 2px solid var(--primary-color);
            font-weight: 500;
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
        }
    </style>
</head>

<body>
    <div class="container">
        <!-- Reusing standard layout, but styled for Admin context -->
        <header style="border-bottom: 2px solid #ff4757; padding-bottom: 15px; margin-bottom: 30px;">
            <div style="display: flex; justify-content: space-between; align-items: center;">
                <h1 style="color: #ff4757; margin: 0;">Admin Portal</h1>
                <div>
                    <span style="margin-right: 15px;">Logged in as: <strong>
                            <?php echo htmlspecialchars($admin['email']); ?>
                        </strong></span>
                    <a href="index.php?action=logout" class="btn btn-secondary btn-small">Logout</a>
                </div>
            </div>
            <nav style="margin-top: 15px;">
                <a href="index.php" class="btn btn-secondary btn-small" style="margin-right: 10px;">Dashboard</a>
                <a href="users.php" class="btn btn-secondary btn-small" style="margin-right: 10px;">Manage Users</a>
                <a href="deposits.php" class="btn btn-secondary btn-small" style="margin-right: 10px;">Pending
                    Deposits</a>
                <a href="activities.php" class="btn btn-small">System Activities</a>
            </nav>
        </header>

        <h2>System Activities</h2>

        <div class="card">
            <div class="tabs">
                <div class="tab active" onclick="switchTab('campaigns')">All Campaigns</div>
                <div class="tab" onclick="switchTab('emails')">Recent Emails Sent</div>
            </div>

            <div id="campaigns" class="tab-content active">
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>User</th>
                                <th>Campaign Name</th>
                                <th>Status</th>
                                <th>Send Rate</th>
                                <th>Created At</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($campaigns as $camp): ?>
                            <tr>
                                <td>
                                    <?php echo $camp['id']; ?>
                                </td>
                                <td>
                                    <?php echo htmlspecialchars($camp['user_email']); ?>
                                </td>
                                <td>
                                    <?php echo htmlspecialchars($camp['name']); ?>
                                </td>
                                <td>
                                    <span class="badge"
                                        style="background:<?php echo $camp['status'] === 'running' ? '#2ecc71' : ($camp['status'] === 'paused' ? '#f39c12' : '#95a5a6'); ?>;color:white;padding:3px 6px;border-radius:4px;">
                                        <?php echo ucfirst($camp['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php echo $camp['send_rate']; ?>/hr
                                </td>
                                <td>
                                    <?php echo date('Y-m-d H:i', strtotime($camp['created_at'])); ?>
                                </td>
                            </tr>
                            <?php
endforeach; ?>
                            <?php if (empty($campaigns)): ?>
                            <tr>
                                <td colspan="6" style="text-align: center;">No campaigns found.</td>
                            </tr>
                            <?php
endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div id="emails" class="tab-content">
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>Log ID</th>
                                <th>Sender</th>
                                <th>Campaign</th>
                                <th>Recipient Email</th>
                                <th>Sent At</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentEmails as $log): ?>
                            <tr>
                                <td>
                                    <?php echo $log['id']; ?>
                                </td>
                                <td>
                                    <?php echo htmlspecialchars($log['sender_email']); ?>
                                </td>
                                <td>
                                    <?php echo htmlspecialchars($log['campaign_name']); ?>
                                </td>
                                <td>
                                    <?php echo htmlspecialchars($log['recipient_email']); ?>
                                </td>
                                <td>
                                    <?php echo date('Y-m-d H:i:s', strtotime($log['sent_at'])); ?>
                                </td>
                            </tr>
                            <?php
endforeach; ?>
                            <?php if (empty($recentEmails)): ?>
                            <tr>
                                <td colspan="5" style="text-align: center;">No emails sent yet.</td>
                            </tr>
                            <?php
endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script>
        function switchTab(tabId) {
            document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
            document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));

            event.target.classList.add('active');
            document.getElementById(tabId).classList.add('active');
        }
    </script>
</body>

</html>