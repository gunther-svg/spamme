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

// Basic stats
$totalUsers = $db->query("SELECT COUNT(*) FROM users")->fetchColumn();
$pendingDeposits = $db->query("SELECT COUNT(*) FROM transactions WHERE status = 'pending'")->fetchColumn();
$activeCampaigns = $db->query("SELECT COUNT(*) FROM campaigns WHERE status = 'running'")->fetchColumn();
$totalSent = $db->query("SELECT COUNT(*) FROM email_sent_log")->fetchColumn();

// If logout is requested
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    $auth->logout();
    header('Location: login.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Ethical Bulk Sender</title>
    <!-- Use relative path to public css -->
    <link rel="stylesheet" href="../public/assets/css/style.css">
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
                    <a href="?action=logout" class="btn btn-secondary btn-small">Logout</a>
                </div>
            </div>
            <nav style="margin-top: 15px;">
                <a href="index.php" class="btn btn-small" style="margin-right: 10px;">Dashboard</a>
                <a href="users.php" class="btn btn-secondary btn-small" style="margin-right: 10px;">Manage Users</a>
                <a href="deposits.php" class="btn btn-secondary btn-small" style="margin-right: 10px;">Pending Deposits
                    <?php if ($pendingDeposits > 0)
    echo "<span style='background:#ff4757;color:white;padding:2px 6px;border-radius:10px;font-size:12px;'>$pendingDeposits</span>"; ?>
                </a>
                <a href="activities.php" class="btn btn-secondary btn-small">System Activities</a>
            </nav>
        </header>

        <h2>Overview</h2>
        <div class="stats-grid">
            <div class="stat-card">
                <h3>Total Users</h3>
                <div class="value">
                    <?php echo number_format($totalUsers); ?>
                </div>
            </div>
            <div class="stat-card">
                <h3>Pending Deposits</h3>
                <div class="value">
                    <?php echo number_format($pendingDeposits); ?>
                </div>
            </div>
            <div class="stat-card">
                <h3>Active Campaigns</h3>
                <div class="value">
                    <?php echo number_format($activeCampaigns); ?>
                </div>
            </div>
            <div class="stat-card">
                <h3>Total Emails Sent</h3>
                <div class="value">
                    <?php echo number_format($totalSent); ?>
                </div>
            </div>
        </div>

        <div class="card" style="margin-top: 30px;">
            <h3>Quick Actions</h3>
            <p>Welcome to the Admin Portal! From here you can oversee the entire system.</p>
            <ul style="line-height: 1.8;">
                <li><strong>Manage Users:</strong> View, update credits, and remove users as needed.</li>
                <li><strong>Pending Deposits:</strong> Review proof of payments and manually inject credits into user
                    accounts.</li>
                <li><strong>System Activities:</strong> Look at active campaigns and recent emails sent to ensure
                    compliance.</li>
            </ul>
        </div>
    </div>
</body>

</html>