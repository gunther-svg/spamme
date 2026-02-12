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

// Handle Delete
if (isset($_GET['delete'])) {
    $stmt = $db->prepare("DELETE FROM smtp_configs WHERE id = ? AND user_id = ?");
    if ($stmt->execute([$_GET['delete'], $user['id']])) {
        $message = "Config deleted successfully.";
    }
}

// Handle Add
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_config'])) {
    $stmt = $db->prepare("INSERT INTO smtp_configs (user_id, host, port, username, password) VALUES (?, ?, ?, ?, ?)");
    if ($stmt->execute([$user['id'], $_POST['host'], $_POST['port'], $_POST['username'], $_POST['password']])) {
        $message = "Config added successfully.";
    }
    else {
        $message = "Failed to add config.";
    }
}

// Fetch Configs
$stmt = $db->prepare("SELECT * FROM smtp_configs WHERE user_id = ?");
$stmt->execute([$user['id']]);
$configs = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SMTP Configs - Ethical Bulk Sender</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>

<body>
    <div class="container">
        <header style="margin-bottom: 20px;">
            <h1 style="color: var(--primary-color);">SMTP Configurations</h1>
            <a href="dashboard.php" class="btn btn-secondary">Back to Dashboard</a>
        </header>

        <?php if ($message): ?>
        <div class="alert alert-success">
            <?php echo htmlspecialchars($message); ?>
        </div>
        <?php
endif; ?>

        <div class="card">
            <h3>Add New SMTP Server</h3>
            <form method="POST">
                <input type="text" name="host" placeholder="SMTP Host (e.g. smtp.gmail.com)" required>
                <input type="number" name="port" placeholder="Port (e.g. 587)" required>
                <input type="text" name="username" placeholder="Username" required>
                <input type="password" name="password" placeholder="Password" required>
                <button type="submit" name="add_config" class="btn">Add Configuration</button>
            </form>
        </div>

        <div class="card">
            <h3>Your Configurations</h3>
            <?php if (empty($configs)): ?>
            <p>No configurations found.</p>
            <?php
else: ?>
            <table style="width: 100%; border-collapse: collapse;">
                <thead>
                    <tr style="text-align: left; background: #eee;">
                        <th style="padding: 10px;">Host</th>
                        <th style="padding: 10px;">Port</th>
                        <th style="padding: 10px;">Username</th>
                        <th style="padding: 10px;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($configs as $config): ?>
                    <tr style="border-bottom: 1px solid #ddd;">
                        <td style="padding: 10px;">
                            <?php echo htmlspecialchars($config['host']); ?>
                        </td>
                        <td style="padding: 10px;">
                            <?php echo htmlspecialchars($config['port']); ?>
                        </td>
                        <td style="padding: 10px;">
                            <?php echo htmlspecialchars($config['username']); ?>
                        </td>
                        <td style="padding: 10px;">
                            <a href="?delete=<?php echo $config['id']; ?>" class="btn btn-secondary"
                                style="background: var(--error-color); color: white;"
                                onclick="return confirm('Are you sure?')">Delete</a>
                        </td>
                    </tr>
                    <?php
    endforeach; ?>
                </tbody>
            </table>
            <?php
endif; ?>
        </div>
    </div>
</body>

</html>