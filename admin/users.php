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
    if (isset($_POST['update_credits'])) {
        $userId = (int)$_POST['user_id'];
        $credits = (int)$_POST['credits'];
        $stmt = $db->prepare("UPDATE users SET credits = ? WHERE id = ?");
        if ($stmt->execute([$credits, $userId])) {
            $message = "Credits updated for User #$userId.";
        }
        else {
            $message = "Failed to update credits.";
        }
    }
    elseif (isset($_POST['delete_user'])) {
        $userId = (int)$_POST['user_id'];
        $stmt = $db->prepare("DELETE FROM users WHERE id = ?");
        if ($stmt->execute([$userId])) {
            $message = "User #$userId completely deleted.";
        }
        else {
            $message = "Failed to delete user.";
        }
    }
}

$users = $db->query("SELECT * FROM users ORDER BY created_at DESC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Users - Admin Portal</title>
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
                <a href="users.php" class="btn btn-small" style="margin-right: 10px;">Manage Users</a>
                <a href="deposits.php" class="btn btn-secondary btn-small" style="margin-right: 10px;">Pending
                    Deposits</a>
                <a href="activities.php" class="btn btn-secondary btn-small" style="margin-right: 10px;">System
                    Activities</a>
                <a href="settings.php" class="btn btn-secondary btn-small">Settings</a>
            </nav>
        </header>

        <h2>Manage Users</h2>
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
                            <th>Email</th>
                            <th>Status</th>
                            <th>Joined</th>
                            <th>Credits</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $user): ?>
                        <tr>
                            <td>
                                <?php echo $user['id']; ?>
                            </td>
                            <td>
                                <?php echo htmlspecialchars($user['email']); ?>
                            </td>
                            <td>
                                <?php if ($user['verified']): ?>
                                <span class="badge"
                                    style="background:#2ecc71;color:white;padding:3px 6px;border-radius:4px;">Verified</span>
                                <?php
    else: ?>
                                <span class="badge"
                                    style="background:#e74c3c;color:white;padding:3px 6px;border-radius:4px;">Unverified</span>
                                <?php
    endif; ?>
                            </td>
                            <td>
                                <?php echo date('Y-m-d', strtotime($user['created_at'])); ?>
                            </td>
                            <td>
                                <form method="POST" style="display:inline-block; margin:0;"
                                    onsubmit="return confirm('Update credits?');">
                                    <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                    <input type="number" name="credits" value="<?php echo $user['credits']; ?>"
                                        style="width: 80px; padding: 5px; margin-right: 5px;">
                                    <button type="submit" name="update_credits" class="btn btn-small">Update</button>
                                </form>
                            </td>
                            <td>
                                <form method="POST" style="display:inline-block; margin:0;"
                                    onsubmit="return confirm('Are you sure you want to delete this user completely? This action cannot be undone.');">
                                    <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                    <button type="submit" name="delete_user" class="btn btn-small"
                                        style="background-color: #ff4757; color: white;">Delete</button>
                                </form>
                            </td>
                        </tr>
                        <?php
endforeach; ?>
                        <?php if (empty($users)): ?>
                        <tr>
                            <td colspan="6" style="text-align: center;">No standard users found.</td>
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