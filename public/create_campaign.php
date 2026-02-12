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

// Fetch SMTP Configs
$stmt = $db->prepare("SELECT * FROM smtp_configs WHERE user_id = ?");
$stmt->execute([$user['id']]);
$smtp_configs = $stmt->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_POST['name'];
    $smtp_id = $_POST['smtp_config_id'];
    $subject = $_POST['subject'];
    $body = $_POST['body'];
    $start_time = $_POST['start_time'];
    $send_rate = $_POST['send_rate'];

    // Check credits/Validation could go here

    // Basic CSV parsing for recipients
    if (isset($_FILES['recipient_list']) && $_FILES['recipient_list']['error'] == 0) {
        $file = fopen($_FILES['recipient_list']['tmp_name'], 'r');
        $emails = [];
        while (($line = fgetcsv($file)) !== FALSE) {
            // Assuming email is the first column
            if (filter_var($line[0], FILTER_VALIDATE_EMAIL)) {
                $emails[] = $line[0];
            }
        }
        fclose($file);

        if (count($emails) > 0) {
            // Create Campaign
            $stmt = $db->prepare("INSERT INTO campaigns (user_id, name, status, start_time, send_rate, smtp_config_id, subject, body) VALUES (?, ?, 'scheduled', ?, ?, ?, ?, ?)");
            $stmt->execute([$user['id'], $name, $start_time, $send_rate, $smtp_id, $subject, $body]);
            $campaign_id = $db->lastInsertId();

            // Add to Queue
            $stmt = $db->prepare("INSERT INTO email_queue (campaign_id, recipient_email, status) VALUES (?, ?, 'pending')");
            foreach ($emails as $email) {
                $stmt->execute([$campaign_id, $email]);
            }

            // Allow saving body/subject - For simplicity, assumed to be part of campaign or a separate 'templates' table. 
            // In this quick prototype, I'll add body/subject columns to campaign table dynamically or just save it to a file?
            // Let's modify the campaign table to hold subject/body or create a new column now.
            // For now, I'll just save it to a separate 'campaign_contents' table or update the schema.
            // Updating schema is cleaner.
            // Schema updated in setup_db.php, so we just insert subject/body if needed during creation or update here
            // Note: The INSERT above didn't include subject/body. Let's fix the INSERT instead.

            $message = "Campaign created with " . count($emails) . " recipients.";

        }
        else {
            $message = "No valid emails found in CSV.";
        }
    }
    else {
        $message = "Please upload a CSV file.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Campaign - Ethical Bulk Sender</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>

<body>
    <div class="container">
        <header style="margin-bottom: 20px;">
            <h1 style="color: var(--primary-color);">Create Campaign</h1>
            <a href="dashboard.php" class="btn btn-secondary">Back to Dashboard</a>
        </header>

        <?php if ($message): ?>
        <div class="alert">
            <?php echo htmlspecialchars($message); ?>
        </div>
        <?php
endif; ?>

        <div class="card">
            <form method="POST" enctype="multipart/form-data">
                <label>Campaign Name</label>
                <input type="text" name="name" required>

                <label>SMTP Configuration</label>
                <select name="smtp_config_id" required>
                    <?php foreach ($smtp_configs as $config): ?>
                    <option value="<?php echo $config['id']; ?>">
                        <?php echo htmlspecialchars($config['host'] . ' (' . $config['username'] . ')'); ?>
                    </option>
                    <?php
endforeach; ?>
                </select>

                <label>Email Subject</label>
                <input type="text" name="subject" required>

                <label>Email Body (HTML supported)</label>
                <textarea name="body" rows="10" required></textarea>

                <label>Recipient List (CSV)</label>
                <input type="file" name="recipient_list" accept=".csv" required style="border: none; padding: 10px 0;">

                <label>Start Time</label>
                <input type="datetime-local" name="start_time" required>

                <label>Send Rate (Emails per Hour)</label>
                <input type="number" name="send_rate" value="100" min="1" required>

                <button type="submit" class="btn">Schedule Campaign</button>
            </form>
        </div>
    </div>
</body>

</html>