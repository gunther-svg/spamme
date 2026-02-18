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
$message_type = '';

// --- Handle Actions ---

// Delete a list
if (isset($_GET['delete_list'])) {
    $stmt = $db->prepare("DELETE FROM email_lists WHERE id = ? AND user_id = ?");
    if ($stmt->execute([$_GET['delete_list'], $user['id']])) {
        $message = "List deleted successfully.";
        $message_type = 'success';
    }
}

// Delete an entry from a list
if (isset($_GET['delete_entry']) && isset($_GET['list_id'])) {
    $list_id = (int)$_GET['list_id'];
    $entry_id = (int)$_GET['delete_entry'];
    // Verify ownership
    $stmt = $db->prepare("SELECT id FROM email_lists WHERE id = ? AND user_id = ?");
    $stmt->execute([$list_id, $user['id']]);
    if ($stmt->fetch()) {
        $db->prepare("DELETE FROM email_list_entries WHERE id = ? AND list_id = ?")->execute([$entry_id, $list_id]);
        $message = "Email removed from list.";
        $message_type = 'success';
    }
}

// Create a new list
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_list'])) {
    $name = trim($_POST['list_name']);
    $desc = trim($_POST['list_description'] ?? '');
    if ($name) {
        $stmt = $db->prepare("INSERT INTO email_lists (user_id, name, description) VALUES (?, ?, ?)");
        if ($stmt->execute([$user['id'], $name, $desc])) {
            $message = "List \"$name\" created successfully.";
            $message_type = 'success';
        }
        else {
            $message = "Failed to create list.";
            $message_type = 'error';
        }
    }
}

// Add single email to list
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_email'])) {
    $list_id = (int)$_POST['list_id'];
    $email = trim($_POST['email']);
    $name = trim($_POST['entry_name'] ?? '');

    // Verify ownership
    $stmt = $db->prepare("SELECT id FROM email_lists WHERE id = ? AND user_id = ?");
    $stmt->execute([$list_id, $user['id']]);
    if ($stmt->fetch() && filter_var($email, FILTER_VALIDATE_EMAIL)) {
        try {
            $stmt = $db->prepare("INSERT INTO email_list_entries (list_id, email, name) VALUES (?, ?, ?)");
            $stmt->execute([$list_id, $email, $name]);
            $message = "Email added to list.";
            $message_type = 'success';
        }
        catch (Exception $e) {
            $message = "Email already exists in this list.";
            $message_type = 'error';
        }
    }
    else {
        $message = "Invalid email address or list.";
        $message_type = 'error';
    }
}

// Bulk import from CSV
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['import_csv'])) {
    $list_id = (int)$_POST['list_id'];

    // Verify ownership
    $stmt = $db->prepare("SELECT id FROM email_lists WHERE id = ? AND user_id = ?");
    $stmt->execute([$list_id, $user['id']]);
    if ($stmt->fetch() && isset($_FILES['csv_file']) && $_FILES['csv_file']['error'] === UPLOAD_ERR_OK) {
        $file = fopen($_FILES['csv_file']['tmp_name'], 'r');
        $imported = 0;
        $skipped = 0;

        while (($row = fgetcsv($file)) !== false) {
            $email = trim($row[0] ?? '');
            $name = trim($row[1] ?? '');

            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $skipped++;
                continue;
            }

            try {
                $stmt = $db->prepare("INSERT INTO email_list_entries (list_id, email, name) VALUES (?, ?, ?)");
                $stmt->execute([$list_id, $email, $name]);
                $imported++;
            }
            catch (Exception $e) {
                $skipped++; // Duplicate
            }
        }
        fclose($file);
        $message = "Imported $imported emails ($skipped skipped/duplicates).";
        $message_type = 'success';
    }
    else {
        $message = "Please upload a valid CSV file.";
        $message_type = 'error';
    }
}

// Bulk add from textarea
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_add'])) {
    $list_id = (int)$_POST['list_id'];
    $emails_text = trim($_POST['emails_text'] ?? '');

    // Verify ownership
    $stmt = $db->prepare("SELECT id FROM email_lists WHERE id = ? AND user_id = ?");
    $stmt->execute([$list_id, $user['id']]);
    if ($stmt->fetch() && $emails_text) {
        $lines = preg_split('/[\n,;]+/', $emails_text);
        $imported = 0;
        $skipped = 0;

        foreach ($lines as $line) {
            $email = trim($line);
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $skipped++;
                continue;
            }
            try {
                $stmt = $db->prepare("INSERT INTO email_list_entries (list_id, email, name) VALUES (?, ?, '')");
                $stmt->execute([$list_id, $email]);
                $imported++;
            }
            catch (Exception $e) {
                $skipped++;
            }
        }
        $message = "Added $imported emails ($skipped skipped/duplicates).";
        $message_type = 'success';
    }
}

// Export to CSV
if (isset($_GET['export']) && isset($_GET['list_id'])) {
    $list_id = (int)$_GET['list_id'];
    $stmt = $db->prepare("SELECT el.name as list_name FROM email_lists el WHERE el.id = ? AND el.user_id = ?");
    $stmt->execute([$list_id, $user['id']]);
    $list = $stmt->fetch();
    if ($list) {
        $stmt = $db->prepare("SELECT email, name FROM email_list_entries WHERE list_id = ? ORDER BY id");
        $stmt->execute([$list_id]);
        $entries = $stmt->fetchAll();

        $filename = preg_replace('/[^a-zA-Z0-9_-]/', '_', $list['list_name']) . '_' . date('Y-m-d') . '.csv';
        header('Content-Type: text/csv');
        header("Content-Disposition: attachment; filename=\"$filename\"");
        $output = fopen('php://output', 'w');
        fputcsv($output, ['Email', 'Name']);
        foreach ($entries as $entry) {
            fputcsv($output, [$entry['email'], $entry['name']]);
        }
        fclose($output);
        exit;
    }
}

// Currently viewing list
$view_list_id = isset($_GET['view']) ? (int)$_GET['view'] : null;
$viewing_list = null;
$list_entries = [];

if ($view_list_id) {
    $stmt = $db->prepare("SELECT * FROM email_lists WHERE id = ? AND user_id = ?");
    $stmt->execute([$view_list_id, $user['id']]);
    $viewing_list = $stmt->fetch();
    if ($viewing_list) {
        $stmt = $db->prepare("SELECT * FROM email_list_entries WHERE list_id = ? ORDER BY id DESC");
        $stmt->execute([$view_list_id]);
        $list_entries = $stmt->fetchAll();
    }
}

// Fetch all lists with counts
$stmt = $db->prepare("
    SELECT el.*, 
    (SELECT COUNT(*) FROM email_list_entries ele WHERE ele.list_id = el.id) as entry_count
    FROM email_lists el 
    WHERE el.user_id = ? 
    ORDER BY el.updated_at DESC
");
$stmt->execute([$user['id']]);
$lists = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email Lists - Ethical Bulk Sender</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500&display=swap" rel="stylesheet">
    <style>
        .list-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 15px;
        }

        .list-card {
            background: white;
            border-radius: 8px;
            padding: 18px;
            border: 1px solid #e0e0e0;
            transition: box-shadow 0.2s, transform 0.2s;
            cursor: pointer;
            text-decoration: none;
            color: inherit;
            display: block;
        }

        .list-card:hover {
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.12);
            transform: translateY(-2px);
        }

        .list-card h4 {
            margin: 0 0 6px;
            color: var(--primary-color);
        }

        .list-card .count {
            font-size: 28px;
            font-weight: 500;
            color: #333;
        }

        .list-card .meta {
            font-size: 12px;
            color: #999;
            margin-top: 8px;
        }

        .entry-actions form {
            display: inline;
        }

        .entry-actions button,
        .entry-actions a {
            margin: 0 2px;
        }

        .tag {
            display: inline-block;
            background: #e8f5e9;
            color: #2e7d32;
            padding: 2px 8px;
            border-radius: 10px;
            font-size: 11px;
        }

        .tab-bar {
            display: flex;
            gap: 0;
            margin-bottom: 0;
            border-bottom: 2px solid #e0e0e0;
        }

        .tab {
            padding: 10px 20px;
            cursor: pointer;
            border: none;
            background: none;
            font-size: 14px;
            color: #666;
            border-bottom: 2px solid transparent;
            margin-bottom: -2px;
        }

        .tab.active {
            color: var(--primary-color);
            border-bottom-color: var(--primary-color);
            font-weight: 500;
        }

        .tab-content {
            display: none;
            padding: 15px 0;
        }

        .tab-content.active {
            display: block;
        }

        textarea.emails-input {
            width: 100%;
            min-height: 120px;
            font-family: monospace;
            font-size: 13px;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-sizing: border-box;
            resize: vertical;
        }
    </style>
</head>

<body>
    <div class="container" style="max-width: 1000px;">
        <header
            style="margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
            <h1 style="color: var(--primary-color); margin: 0;">
                <?php if ($viewing_list): ?>
                <a href="email_lists.php" style="text-decoration: none; color: #999;">Email Lists</a> /
                <?php echo htmlspecialchars($viewing_list['name']); ?>
                <?php
else: ?>
                Email Lists
                <?php
endif; ?>
            </h1>
            <div style="display: flex; gap: 10px;">
                <a href="dashboard.php" class="btn btn-secondary">Dashboard</a>
                <a href="create_campaign.php" class="btn btn-secondary">Create Campaign</a>
            </div>
        </header>

        <?php if ($message): ?>
        <div class="alert <?php echo $message_type === 'error' ? 'alert-error' : 'alert-success'; ?>">
            <?php echo htmlspecialchars($message); ?>
        </div>
        <?php
endif; ?>

        <?php if ($viewing_list): ?>
        <!-- ===== VIEWING A SINGLE LIST ===== -->
        <div class="card">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                <div>
                    <h3 style="margin: 0;">
                        <?php echo htmlspecialchars($viewing_list['name']); ?>
                    </h3>
                    <?php if ($viewing_list['description']): ?>
                    <p style="color: #888; margin: 4px 0 0; font-size: 13px;">
                        <?php echo htmlspecialchars($viewing_list['description']); ?>
                    </p>
                    <?php
    endif; ?>
                    <p style="font-size: 12px; color: #aaa; margin: 4px 0 0;">
                        <?php echo count($list_entries); ?> emails
                    </p>
                </div>
                <div style="display: flex; gap: 8px;">
                    <a href="?export=1&list_id=<?php echo $viewing_list['id']; ?>" class="btn btn-secondary"
                        style="font-size: 13px;">⬇ Export CSV</a>
                    <a href="?delete_list=<?php echo $viewing_list['id']; ?>" class="btn btn-secondary"
                        style="background: #f44336; color: white; font-size: 13px;"
                        onclick="return confirm('Delete this entire list?')">🗑 Delete List</a>
                </div>
            </div>
        </div>

        <!-- Add Emails -->
        <div class="card">
            <h3 style="margin-top: 0;">Add Emails</h3>
            <div class="tab-bar">
                <button class="tab active" onclick="switchTab('single')">Single Email</button>
                <button class="tab" onclick="switchTab('bulk')">Bulk Paste</button>
                <button class="tab" onclick="switchTab('csv')">Import CSV</button>
            </div>

            <div id="tab-single" class="tab-content active">
                <form method="POST" style="display: flex; gap: 10px; align-items: flex-end; flex-wrap: wrap;">
                    <input type="hidden" name="list_id" value="<?php echo $viewing_list['id']; ?>">
                    <div style="flex: 2;">
                        <label style="font-size: 13px;">Email Address</label>
                        <input type="email" name="email" placeholder="user@example.com" required style="margin: 4px 0;">
                    </div>
                    <div style="flex: 1;">
                        <label style="font-size: 13px;">Name (optional)</label>
                        <input type="text" name="entry_name" placeholder="John Doe" style="margin: 4px 0;">
                    </div>
                    <button type="submit" name="add_email" class="btn" style="height: 40px; white-space: nowrap;">+
                        Add</button>
                </form>
            </div>

            <div id="tab-bulk" class="tab-content">
                <form method="POST">
                    <input type="hidden" name="list_id" value="<?php echo $viewing_list['id']; ?>">
                    <label style="font-size: 13px;">Paste emails (one per line, or comma/semicolon separated)</label>
                    <textarea class="emails-input" name="emails_text"
                        placeholder="user1@example.com&#10;user2@example.com&#10;user3@example.com"></textarea>
                    <button type="submit" name="bulk_add" class="btn" style="margin-top: 10px;">Import All</button>
                </form>
            </div>

            <div id="tab-csv" class="tab-content">
                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="list_id" value="<?php echo $viewing_list['id']; ?>">
                    <label style="font-size: 13px;">Upload CSV file (columns: Email, Name)</label>
                    <input type="file" name="csv_file" accept=".csv,.txt" required
                        style="margin: 10px 0; border: none; padding: 0;">
                    <button type="submit" name="import_csv" class="btn">⬆ Import CSV</button>
                </form>
            </div>
        </div>

        <!-- Email Entries Table -->
        <div class="card">
            <h3 style="margin-top: 0;">Emails in List</h3>
            <?php if (empty($list_entries)): ?>
            <p style="color: #999;">No emails yet. Add some above.</p>
            <?php
    else: ?>
            <table style="width: 100%; border-collapse: collapse;">
                <thead>
                    <tr style="text-align: left; background: #f5f5f5;">
                        <th style="padding: 8px 10px; width: 40px;">#</th>
                        <th style="padding: 8px 10px;">Email</th>
                        <th style="padding: 8px 10px;">Name</th>
                        <th style="padding: 8px 10px;">Added</th>
                        <th style="padding: 8px 10px; width: 60px;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $n = 1;
        foreach ($list_entries as $entry): ?>
                    <tr style="border-bottom: 1px solid #eee;">
                        <td style="padding: 6px 10px; color: #aaa; font-size: 12px;">
                            <?php echo $n++; ?>
                        </td>
                        <td style="padding: 6px 10px; font-family: monospace; font-size: 13px;">
                            <?php echo htmlspecialchars($entry['email']); ?>
                        </td>
                        <td style="padding: 6px 10px; color: #666;">
                            <?php echo htmlspecialchars($entry['name'] ?: '—'); ?>
                        </td>
                        <td style="padding: 6px 10px; font-size: 12px; color: #999;">
                            <?php echo date('M j, g:ia', strtotime($entry['created_at'])); ?>
                        </td>
                        <td style="padding: 6px 10px;" class="entry-actions">
                            <a href="?view=<?php echo $viewing_list['id']; ?>&delete_entry=<?php echo $entry['id']; ?>&list_id=<?php echo $viewing_list['id']; ?>"
                                onclick="return confirm('Remove this email?')"
                                style="color: #f44336; text-decoration: none; font-size: 13px;">✕</a>
                        </td>
                    </tr>
                    <?php
        endforeach; ?>
                </tbody>
            </table>
            <?php
    endif; ?>
        </div>

        <?php
else: ?>
        <!-- ===== LIST OF ALL LISTS ===== -->
        <div class="card">
            <h3 style="margin-top: 0;">Create New List</h3>
            <form method="POST" style="display: flex; gap: 10px; align-items: flex-end; flex-wrap: wrap;">
                <div style="flex: 2;">
                    <label style="font-size: 13px;">List Name</label>
                    <input type="text" name="list_name" placeholder="e.g. Newsletter Subscribers" required
                        style="margin: 4px 0;">
                </div>
                <div style="flex: 3;">
                    <label style="font-size: 13px;">Description (optional)</label>
                    <input type="text" name="list_description" placeholder="Brief description of this list"
                        style="margin: 4px 0;">
                </div>
                <button type="submit" name="create_list" class="btn" style="height: 40px; white-space: nowrap;">+
                    Create</button>
            </form>
        </div>

        <div class="card">
            <h3 style="margin-top: 0;">Your Lists</h3>
            <?php if (empty($lists)): ?>
            <p style="color: #999;">No email lists yet. Create one above.</p>
            <?php
    else: ?>
            <div class="list-grid">
                <?php foreach ($lists as $list): ?>
                <a href="?view=<?php echo $list['id']; ?>" class="list-card">
                    <h4>
                        <?php echo htmlspecialchars($list['name']); ?>
                    </h4>
                    <?php if ($list['description']): ?>
                    <p style="font-size: 12px; color: #888; margin: 0 0 8px;">
                        <?php echo htmlspecialchars($list['description']); ?>
                    </p>
                    <?php
            endif; ?>
                    <div class="count">
                        <?php echo $list['entry_count']; ?> <span style="font-size: 14px; color: #999;">emails</span>
                    </div>
                    <div class="meta">
                        Updated
                        <?php echo date('M j, Y', strtotime($list['updated_at'])); ?>
                        <span style="float: right;">
                            <a href="?export=1&list_id=<?php echo $list['id']; ?>" onclick="event.stopPropagation();"
                                style="color: var(--primary-color); text-decoration: none;">⬇ CSV</a>
                        </span>
                    </div>
                </a>
                <?php
        endforeach; ?>
            </div>
            <?php
    endif; ?>
        </div>
        <?php
endif; ?>
    </div>

    <script>
        function switchTab(tab) {
            document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
            document.querySelectorAll('.tab-content').forEach(tc => tc.classList.remove('active'));
            document.getElementById('tab-' + tab).classList.add('active');
            event.target.classList.add('active');
        }
    </script>
</body>

</html>