<?php
require_once __DIR__ . '/../vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

use App\Auth;

$message = '';
if (isset($_GET['token'])) {
    $auth = new Auth();
    $result = $auth->verifyEmail($_GET['token']);
    $message = $result['message'];
}
else {
    $message = "No token provided.";
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Email Verification</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>

<body>
    <div class="container auth-form">
        <div class="card">
            <h2>Email Verification</h2>
            <div class="alert">
                <?php echo htmlspecialchars($message); ?>
            </div>
            <a href="index.php" class="btn">Go to Login</a>
        </div>
    </div>
</body>

</html>