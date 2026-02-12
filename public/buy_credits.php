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
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_payment'])) {
    $amount = $_POST['amount'];
    $method = $_POST['method'];

    // File Upload handling
    $target_dir = "../uploads/proofs/";
    if (!file_exists($target_dir)) {
        mkdir($target_dir, 0777, true);
    }

    $file_name = time() . '_' . basename($_FILES["proof_file"]["name"]);
    $target_file = $target_dir . $file_name;

    if (move_uploaded_file($_FILES["proof_file"]["tmp_name"], $target_file)) {
        $stmt = $db->prepare("INSERT INTO transactions (user_id, amount, method, proof_file, status) VALUES (?, ?, ?, ?, 'pending')");
        if ($stmt->execute([$user['id'], $amount, $method, $target_file])) {
            $message = "Payment request submitted successfully. Waiting for admin approval.";
        }
        else {
            $message = "Database error.";
        }
    }
    else {
        $message = "Error uploading file.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Buy Credits - Ethical Bulk Sender</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>

<body>
    <div class="container">
        <header style="margin-bottom: 20px;">
            <h1 style="color: var(--primary-color);">Buy Credits</h1>
            <a href="dashboard.php" class="btn btn-secondary">Back to Dashboard</a>
        </header>

        <?php if ($message): ?>
        <div class="alert <?php echo strpos($message, 'success') !== false ? 'alert-success' : 'alert-error'; ?>">
            <?php echo htmlspecialchars($message); ?>
        </div>
        <?php
endif; ?>

        <div class="card">
            <h3>Payment Details</h3>
            <div
                style="background: #f9f9f9; padding: 15px; border-left: 5px solid var(--primary-color); margin-bottom: 20px;">
                <p><strong>Bitcoin (BTC):</strong> 1A1zP1eP5QGefi2DMPTfTL5SLmv7DivfNa</p>
                <p><strong>USDT (TRC20):</strong> T9yD14Nj9j7xAB4dbGeiX9h8bN89abc</p>
                <p><strong>Bank Transfer:</strong> Bank of Internet, Acc: 123456789</p>
                <p><strong>Rate:</strong> $1 = 100 Credits</p>
            </div>

            <form method="POST" enctype="multipart/form-data">
                <label>Amount (USD)</label>
                <input type="number" name="amount" placeholder="Amount in USD" required>

                <label>Payment Method</label>
                <select name="method" required>
                    <option value="Crypto">Crypto (BTC/USDT)</option>
                    <option value="Bank">Bank Transfer</option>
                </select>

                <label>Upload Proof of Payment (Screenshot/PDF)</label>
                <input type="file" name="proof_file" required style="border: none; padding: 10px 0;">

                <button type="submit" name="submit_payment" class="btn">Submit Payment Request</button>
            </form>
        </div>
    </div>
</body>

</html>