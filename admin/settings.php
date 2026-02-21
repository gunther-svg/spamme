<?php
require_once __DIR__ . '/../vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

session_start();
use App\AdminAuth;
use App\Settings;

$auth = new AdminAuth();
$admin = $auth->getAdmin();

if (!$admin) {
    header('Location: login.php');
    exit;
}

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_settings'])) {
    $creditsPerDollar = (int)$_POST['credits_per_dollar'];
    $costPerEmail = (int)$_POST['cost_per_email'];

    if ($creditsPerDollar <= 0 || $costPerEmail <= 0) {
        $message = "Values must be greater than zero.";
    }
    else {
        Settings::set('credits_per_dollar', $creditsPerDollar);
        Settings::set('cost_per_email', $costPerEmail);
        $message = "Settings updated successfully.";
    }
}

// Fetch current values
$currentCreditsPerDollar = Settings::get('credits_per_dollar', 100);
$currentCostPerEmail = Settings::get('cost_per_email', 1);

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Settings - Admin Portal</title>
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
                    <a href="index.php?action=logout" class="btn btn-secondary btn-small">Logout</a>
                </div>
            </div>
            <nav style="margin-top: 15px;">
                <a href="index.php" class="btn btn-secondary btn-small" style="margin-right: 10px;">Dashboard</a>
                <a href="users.php" class="btn btn-secondary btn-small" style="margin-right: 10px;">Manage Users</a>
                <a href="deposits.php" class="btn btn-secondary btn-small" style="margin-right: 10px;">Pending
                    Deposits</a>
                <a href="activities.php" class="btn btn-secondary btn-small" style="margin-right: 10px;">System
                    Activities</a>
                <a href="settings.php" class="btn btn-small">Settings</a>
            </nav>
        </header>

        <h2>System Settings</h2>

        <?php if ($message): ?>
        <div class="alert <?php echo strpos($message, 'success') !== false ? 'alert-success' : 'alert-error'; ?>">
            <?php echo htmlspecialchars($message); ?>
        </div>
        <?php
endif; ?>

        <div class="card auth-form" style="max-width: 600px; margin: 0 auto;">
            <h3>Global Configuration</h3>
            <p style="margin-bottom: 20px; color: #666; line-height: 1.5;">Adjust the core platform economics. Note that
                changes take effect immediately on all new transactions and emails sent.</p>

            <form method="POST">
                <label>Credits per Dollar ($1 USD = ? Credits)</label>
                <input type="number" name="credits_per_dollar"
                    value="<?php echo htmlspecialchars($currentCreditsPerDollar); ?>" required min="1">
                <small style="display:block; margin-top:-10px; margin-bottom:15px; color:#999;">How many credits a user
                    receives when they deposit $1.</small>

                <label>Cost per Email (Credits)</label>
                <input type="number" name="cost_per_email" value="<?php echo htmlspecialchars($currentCostPerEmail); ?>"
                    required min="1">
                <small style="display:block; margin-top:-10px; margin-bottom:20px; color:#999;">How many credits are
                    deducted from a user's balance per single email sent.</small>

                <button type="submit" name="save_settings" class="btn" style="width: 100%;">Save Settings</button>
            </form>
        </div>
    </div>
</body>

</html>