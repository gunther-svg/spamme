<?php
require_once __DIR__ . '/../vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

use App\Database;

$db = Database::getInstance()->getConnection();

echo "Checking for pending transactions...\n";

$stmt = $db->query("SELECT t.*, u.email FROM transactions t JOIN users u ON t.user_id = u.id WHERE t.status = 'pending'");
$transactions = $stmt->fetchAll();

if (empty($transactions)) {
    echo "No pending transactions found.\n";
    exit;
}

foreach ($transactions as $t) {
    echo "Transaction ID: {$t['id']} | User: {$t['email']} | Amount: \${$t['amount']} | Method: {$t['method']}\n";
    echo "Approve this transaction? (y/n): ";

    $handle = fopen("php://stdin", "r");
    $line = fgets($handle);

    if (trim($line) == 'y') {
        // Calculate credits (Rate: $1 = 100 Credits)
        $credits = $t['amount'] * 100;

        $db->beginTransaction();
        try {
            // Update transaction status
            $update = $db->prepare("UPDATE transactions SET status = 'approved' WHERE id = ?");
            $update->execute([$t['id']]);

            // Add credits to user
            $addCredit = $db->prepare("UPDATE users SET credits = credits + ? WHERE id = ?");
            $addCredit->execute([$credits, $t['user_id']]);

            $db->commit();
            echo "Transaction approved. Added $credits credits to user.\n";
        }
        catch (Exception $e) {
            $db->rollBack();
            echo "Error approving transaction: " . $e->getMessage() . "\n";
        }
    }
    else {
        echo "Skipped.\n";
    }
}