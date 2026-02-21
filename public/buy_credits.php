<?php
require_once __DIR__ . '/../vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

session_start();
use App\Auth;
use App\Database;
use App\Settings;

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
    // File Upload handling
    $target_dir = "../uploads/proofs/";
    if (!file_exists($target_dir)) {
        if (!mkdir($target_dir, 0777, true)) {
            $message = "Failed to create upload directory.";
        }
    }

    if (empty($message)) {
        if ($_FILES['proof_file']['error'] !== UPLOAD_ERR_OK) {
            switch ($_FILES['proof_file']['error']) {
                case UPLOAD_ERR_INI_SIZE:
                case UPLOAD_ERR_FORM_SIZE:
                    $message = "File is too large. Max size is " . ini_get('upload_max_filesize');
                    break;
                case UPLOAD_ERR_NO_FILE:
                    $message = "No file uploaded.";
                    break;
                default:
                    $message = "File upload error code: " . $_FILES['proof_file']['error'];
            }
        }
        else {
            $file_name = time() . '_' . basename($_FILES["proof_file"]["name"]);
            $target_file = $target_dir . $file_name;

            if (move_uploaded_file($_FILES["proof_file"]["tmp_name"], $target_file)) {
                $stmt = $db->prepare("INSERT INTO transactions (user_id, amount, method, proof_file, status) VALUES (?, ?, ?, ?, 'pending')");
                if ($stmt->execute([$user['id'], $amount, $method, $target_file])) {
                    $message = "Payment request submitted successfully. Waiting for admin approval.";

                    $mailer = new \App\SystemMailer();
                    $mailer->sendDepositNotification($user['email'], $amount, $method);
                    $mailer->sendAdminDepositNotification($amount, $method, $user['email']);
                    $success = true; // Flag for styling
                }
                else {
                    $message = "Database error.";
                }
            }
            else {
                $message = "Error moving uploaded file. Check permissions.";
            }
        }
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
            <div style="display: flex; gap: 20px; flex-wrap: wrap; justify-content: space-between;">
                <div style="flex: 1; text-align: center; min-width: 200px;">
                    <p><strong>Bitcoin (BTC)</strong></p>
                    <p style="font-size: 0.9em; word-break: break-all;">1A1zP1eP5QGefi2DMPTfTL5SLmv7DivfNa</p>
                    <img src="https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=1A1zP1eP5QGefi2DMPTfTL5SLmv7DivfNa"
                        alt="BTC QR Code" style="border: 1px solid #ddd; padding: 5px;">
                </div>
                <div style="flex: 1; text-align: center; min-width: 200px;">
                    <p><strong>USDT (TRC20)</strong></p>
                    <p style="font-size: 0.9em; word-break: break-all;">T9yD14Nj9j7xAB4dbGeiX9h8bN89abc</p>
                    <img src="https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=T9yD14Nj9j7xAB4dbGeiX9h8bN89abc"
                        alt="USDT QR Code" style="border: 1px solid #ddd; padding: 5px;">
                </div>
                <div style="flex: 1; text-align: center; min-width: 200px;">
                    <p><strong>Bank Transfer</strong></p>
                    <p style="font-size: 0.9em;">Bank of Internet<br>Acc: 123456789</p>
                    <div
                        style="display: inline-block; border: 1px solid #ddd; padding: 5px; width: 150px; height: 150px; box-sizing: content-box; display: flex; align-items: center; justify-content: center; margin: 0 auto;">
                        <!-- Bank Icon SVG -->
                        <svg fill="var(--primary-color)" height="100px" width="100px" version="1.1" id="Capa_1"
                            xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink"
                            viewBox="0 0 490.667 490.667" xml:space="preserve">
                            <g>
                                <path
                                    d="M245.333,0L24.96,117.973v45.013h440.747v-45.013L245.333,0z M62.293,120.32L245.333,22.347l183.04,97.973H62.293z" />
                                <path d="M65.707,490.667h359.253v-42.667H65.707V490.667z" />
                                <path d="M100.267,405.333H152V192h-51.733V405.333z" />
                                <path d="M219.093,405.333h52.48V192h-52.48V405.333z" />
                                <path d="M338.667,192v213.333h51.733V192H338.667z" />
                            </g>
                        </svg>
                    </div>
                </div>
            </div>
            <div
                style="text-align: center; margin-top: 20px; font-weight: bold; background: #e8f5e9; padding: 10px; border-radius: 4px; color: var(--primary-color);">
                Rate: $1 =
                <?php echo htmlspecialchars(App\Settings::get('credits_per_dollar', 100)); ?> Credits
            </div>
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

</html>iv>
</div>
</body>

</html>