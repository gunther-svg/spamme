<?php
require_once __DIR__ . '/../vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

use App\Auth;

$message = '';
$token = $_GET['token'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $auth = new Auth();
    $result = $auth->resetPassword($_POST['token'], $_POST['password']);
    $message = $result['message'];
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Reset Password</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>

<body>
    <div class="container auth-form">
        <div class="card">
            <h2>Reset Password</h2>
            <?php if ($message): ?>
            <div class="alert">
                <?php echo htmlspecialchars($message); ?>
            </div>
            <?php
endif; ?>

            <form method="POST">
                <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
                <input type="password" name="password" placeholder="New Password" required>
                <button type="submit" class="btn">Reset Password</button>
            </form>
            <p><a href="index.php">Back to Login</a></p>
        </div>
    </div>
</body>

</html>