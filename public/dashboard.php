<?php
require_once __DIR__ . '/../vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

session_start();
use App\Auth;

$auth = new Auth();
$user = $auth->getUser();

if (!$user) {
    header('Location: index.php');
    exit;
}

if (isset($_POST['logout'])) {
    $auth->logout();
    header('Location: index.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Ethical Bulk Sender</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500&display=swap" rel="stylesheet">
</head>

<body>
    <div class="container">
        <header style="display: flex; justify_content: space-between; align-items: center; margin-bottom: 20px;">
            <h1 style="color: var(--primary-color);">Dashboard</h1>
            <div style="display: flex; gap: 10px; align-items: center;">
                <span>Welcome,
                    <?php echo htmlspecialchars($user['email']); ?>
                </span>
                <span
                    style="background: var(--secondary-color); padding: 5px 10px; border-radius: 15px; font-weight: bold;">
                    Credits:
                    <?php echo $user['credits']; ?>
                </span>
                <form method="POST" style="margin: 0;">
                    <button type="submit" name="logout" class="btn btn-secondary">Logout</button>
                </form>
            </div>
        </header>

        <nav style="margin-bottom: 20px;">
            <a href="dashboard.php" class="btn" style="margin-right: 10px;">Home</a>
            <a href="smtp_configs.php" class="btn btn-secondary" style="margin-right: 10px;">SMTP Configs</a>
            <a href="buy_credits.php" class="btn btn-secondary" style="margin-right: 10px;">Buy Credits</a>
            <a href="campaigns.php" class="btn btn-secondary">Campaigns</a>
        </nav>

        <div class="card">
            <h3>Quick Stats</h3>
            <p>You have <strong>
                    <?php echo $user['credits']; ?>
                </strong> credits available.</p>
            <p>Email Limit: <strong>
                    <?php echo $user['credits']; ?>
                </strong> emails.</p>
            <!-- Add more stats here later -->
        </div>

        <div class="card">
            <h3>Start Sending</h3>
            <p>Ready to launch a campaign?</p>
            <a href="create_campaign.php" class="btn">Create New Campaign</a>
        </div>
    </div>
</body>

</html>