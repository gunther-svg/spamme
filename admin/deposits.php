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
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $transactionId = (int)$_POST['transaction_id'];

    if (isset($_POST['approve'])) {
        try {
            $db->beginTransaction();

            // Get transaction details
            $stmt = $db->prepare("SELECT * FROM transactions WHERE id = ? AND status = 'pending'");
            $stmt->execute([$transactionId]);
            $transaction = $stmt->fetch();

            if ($transaction) {
                // Rate: $1 = 100 Credits (as per buy_credits.php)
                $creditsToAdd = floor($transaction['amount'] * 100);

                // Update transaction status
                $stmt = $db->prepare("UPDATE transactions SET status = 'approved' WHERE id = ?");
                $stmt->execute([$transactionId]);

                // Add credits to user
                $stmt = $db->prepare("UPDATE users SET credits = credits + ? WHERE id = ?");
                $stmt->execute([$creditsToAdd, $transaction['user_id']]);

                $db->commit();
                $message = "Transaction #$transactionId approved. Added $creditsToAdd credits to User #{$transaction['user_id']}.";
            }
            else {
                $db->rollBack();
                $message = "Transaction not found or already processed.";
            }
        }
        catch (Exception $e) {
            $db->rollBack();
            $message = "Database error: " . $e->getMessage();
        }
    }
    elseif (isset($_POST['reject'])) {
        $stmt = $db->prepare("UPDATE transactions SET status = 'rejected' WHERE id = ? AND status = 'pending'");
        if ($stmt->execute([$transactionId])) {
            $message = "Transaction #$transactionId rejected.";
        }
        else {
            $message = "Failed to reject transaction.";
        }
    }
}

// Fetch all transactions
$transactions = $db->query("
    SELECT t.*, u.email as user_email 
    FROM transactions t 
    JOIN users u ON t.user_id = u.id 
    ORDER BY t.created_at DESC
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Deposit Approvals - Admin Portal</title>
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
                <a href="deposits.php" class="btn btn-small" style="margin-right: 10px;">Pending Deposits</a>
                <a href="activities.php" class="btn btn-secondary btn-small">System Activities</a>
            </nav>
        </header>

        <h2>Deposit Approvals</h2>
        <?php if ($message): ?>
        <div class="alert alert-success">
            <?php echo htmlspecialchars($message); ?>
        </div>
        <?php
endif; ?>

        <div class="card">
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>User</th>
                            <th>Amount ($)</th>
                            <th>Method</th>
                            <th>Proof</th>
                            <th>Status</th>
                            <th>Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($transactions as $tx): ?>
                        <tr>
                            <td>
                                <?php echo $tx['id']; ?>
                            </td>
                            <td>
                                <?php echo htmlspecialchars($tx['user_email']); ?>
                            </td>
                            <td>$
                                <?php echo number_format($tx['amount'], 2); ?>
                            </td>
                            <td>
                                <?php echo htmlspecialchars($tx['method']); ?>
                            </td>
                            <td>
                                <?php if ($tx['proof_file']): ?>
                                <a href="../public/<?php echo htmlspecialchars(str_replace('../', '', $tx['proof_file'])); ?>"
                                    target="_blank" style="color:var(--primary-color);">View File</a>
                                <?php
    else: ?>
                                N/A
                                <?php
    endif; ?>
                            </td>
                            <td>
                                <?php if ($tx['status'] === 'pending'): ?>
                                <span class="badge"
                                    style="background:#f39c12;color:white;padding:3px 6px;border-radius:4px;">Pending</span>
                                <?php
    elseif ($tx['status'] === 'approved'): ?>
                                <span class="badge"
                                    style="background:#2ecc71;color:white;padding:3px 6px;border-radius:4px;">Approved</span>
                                <?php
    else: ?>
                                <span class="badge"
                                    style="background:#e74c3c;color:white;padding:3px 6px;border-radius:4px;">Rejected</span>
                                <?php
    endif; ?>
                            </td>
                            <td>
                                <?php echo date('Y-m-d H:i', strtotime($tx['created_at'])); ?>
                            </td>
                            <td>
                                <?php if ($tx['status'] === 'pending'): ?>
                                <form method="POST" style="display:inline-block; margin:0;">
                                    <input type="hidden" name="transaction_id" value="<?php echo $tx['id']; ?>">
                                    <button type="submit" name="approve" class="btn btn-small"
                                        style="background:#2ecc71;"
                                        onsubmit="return confirm('Approve transaction?');">Approve</button>
                                    <button type="submit" name="reject" class="btn btn-small"
                                        style="background:#e74c3c; margin-left: 5px;"
                                        onsubmit="return confirm('Reject transaction?');">Reject</button>
                                </form>
                                <?php
    else: ?>
                                -
                                <?php
    endif; ?>
                            </td>
                        </tr>
                        <?php
endforeach; ?>
                        <?php if (empty($transactions)): ?>
                        <tr>
                            <td colspan="8" style="text-align: center;">No transactions found.</td>
                        </tr>
                        <?php
endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</body>

</html>