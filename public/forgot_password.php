<?php
require_once __DIR__ . '/../vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

use App\Auth;

$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $auth = new Auth();
    $result = $auth->requestPasswordReset($_POST['email']);
    $message = $result['message'];
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Forgot Password</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>

<body>
    <div class="container auth-form">
        <div class="card">
            <h2>Forgot Password</h2>
            <?php if ($message): ?>
            <div class="alert">
                <?php echo htmlspecialchars($message); ?>
            </div>
            <?php
endif; ?>
            <form method="POST">
                <input type="email" name="email" placeholder="Enter your email" required>
                <button type="submit" class="btn">Send Reset Link</button>
            </form>
            <p><a href="index.php">Back to Login</a></p>
        </div>
    </div>
</body>

</html>