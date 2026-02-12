<?php
require_once __DIR__ . '/../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

session_start();

use App\Auth;

$auth = new Auth();
$message = '';

if ($auth->isLoggedIn()) {
    header('Location: dashboard.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['login'])) {
        $result = $auth->login($_POST['email'], $_POST['password']);
        if ($result['success']) {
            header('Location: dashboard.php');
            exit;
        }
        else {
            $message = $result['message'];
        }
    }
    elseif (isset($_POST['register'])) {
        $result = $auth->register($_POST['email'], $_POST['password']);
        $message = $result['message'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ethical Bulk Sender</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500&display=swap" rel="stylesheet">
</head>

<body>
    <div class="container auth-form">
        <div class="card">
            <h2 style="text-align: center; color: var(--primary-color);">Bulk Sender</h2>
            <?php if ($message): ?>
            <div class="alert <?php echo strpos($message, 'success') !== false ? 'alert-success' : 'alert-error'; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
            <?php
endif; ?>

            <!-- Tabs for Login/Register (Simple JS toggle can be added, for now just two forms) -->
            <h3>Login</h3>
            <form method="POST">
                <input type="email" name="email" placeholder="Email" required>
                <input type="password" name="password" placeholder="Password" required>
                <button type="submit" name="login" class="btn" style="width: 100%;">Login</button>
            </form>

            <hr style="margin: 20px 0; border: 0; border-top: 1px solid #eee;">

            <h3>Register</h3>
            <form method="POST">
                <input type="email" name="email" placeholder="Email" required>
                <input type="password" name="password" placeholder="Password" required>
                <button type="submit" name="register" class="btn btn-secondary" style="width: 100%;">Create
                    Account</button>
            </form>
        </div>
    </div>
</body>

</html>