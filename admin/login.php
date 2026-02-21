<?php
require_once __DIR__ . '/../vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

session_start();
use App\AdminAuth;

$auth = new AdminAuth();
$message = '';

if ($auth->isAdminLoggedIn()) {
    header('Location: index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['login'])) {
        $result = $auth->login($_POST['email'], $_POST['password']);
        if ($result['success']) {
            header('Location: index.php');
            exit;
        }
        else {
            $message = $result['message'];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - Ethical Bulk Sender</title>
    <!-- Use relative path pointing back to the public folder styles -->
    <link rel="stylesheet" href="../public/assets/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500&display=swap" rel="stylesheet">
</head>

<body>
    <div class="container auth-form">
        <div class="card">
            <h2 style="text-align: center; color: var(--primary-color);">Admin Access</h2>
            <?php if ($message): ?>
            <div class="alert alert-error">
                <?php echo htmlspecialchars($message); ?>
            </div>
            <?php
endif; ?>

            <form method="POST">
                <input type="email" name="email" placeholder="Admin Email" required>
                <input type="password" name="password" placeholder="Password" required>
                <button type="submit" name="login" class="btn" style="width: 100%;">Login</button>
            </form>

            <div style="text-align: center; margin-top: 20px;">
                <a href="../public/index.php" style="color: #666; text-decoration: none;">Return to Public Site</a>
            </div>
        </div>
    </div>
</body>

</html>