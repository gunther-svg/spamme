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

// Delete template
if (isset($_GET['delete_template'])) {
    $stmt = $db->prepare("DELETE FROM email_templates WHERE id = ? AND user_id = ?");
    if ($stmt->execute([$_GET['delete_template'], $user['id']])) {
        $message = "Template deleted successfully.";
        $message_type = 'success';
    }
}

// Create or Edit template
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_template'])) {
    $name = trim($_POST['template_name']);
    $content = trim($_POST['template_content']);
    $template_id = isset($_POST['template_id']) ? (int)$_POST['template_id'] : 0;

    if ($name && $content) {
        if ($template_id > 0) {
            // Update
            $stmt = $db->prepare("UPDATE email_templates SET name = ?, content = ? WHERE id = ? AND user_id = ?");
            if ($stmt->execute([$name, $content, $template_id, $user['id']])) {
                $message = "Template updated successfully.";
                $message_type = 'success';
            }
        }
        else {
            // Create
            $stmt = $db->prepare("INSERT INTO email_templates (user_id, name, content) VALUES (?, ?, ?)");
            if ($stmt->execute([$user['id'], $name, $content])) {
                $message = "Template created successfully.";
                $message_type = 'success';
            }
        }
    }
    else {
        $message = "Name and content are required.";
        $message_type = 'error';
    }
}

// Get editing template
$edit_template = null;
if (isset($_GET['edit'])) {
    $stmt = $db->prepare("SELECT * FROM email_templates WHERE id = ? AND user_id = ?");
    $stmt->execute([$_GET['edit'], $user['id']]);
    $edit_template = $stmt->fetch();
}

// Get all templates
$stmt = $db->prepare("SELECT * FROM email_templates WHERE user_id = ? ORDER BY updated_at DESC");
$stmt->execute([$user['id']]);
$templates = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email Templates - Ethical Bulk Sender</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500&display=swap" rel="stylesheet">
    <link href="https://cdn.quilljs.com/1.3.6/quill.snow.css" rel="stylesheet">
    <style>
        .grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 15px;
        }

        .template-card {
            background: white;
            border-radius: 8px;
            padding: 15px;
            border: 1px solid #e0e0e0;
            display: flex;
            flex-direction: column;
            height: 100%;
            transition: box-shadow 0.2s;
        }

        .template-card:hover {
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }

        .template-name {
            font-weight: 500;
            font-size: 16px;
            margin-bottom: 5px;
            color: var(--primary-color);
        }

        .template-meta {
            font-size: 12px;
            color: #888;
            margin-bottom: 15px;
            flex-grow: 1;
        }

        .template-actions {
            display: flex;
            gap: 8px;
            border-top: 1px solid #eee;
            padding-top: 10px;
        }

        .template-actions a {
            font-size: 13px;
            padding: 6px 12px;
        }
    </style>
</head>

<body>
    <div class="container" style="max-width: 1000px;">
        <header style="margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center;">
            <h1 style="color: var(--primary-color); margin: 0;">Email Templates</h1>
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

        <!-- Editor Section -->
        <div class="card">
            <h3 style="margin-top: 0;">
                <?php echo $edit_template ? 'Edit Template' : 'Create New Template'; ?>
            </h3>
            <form method="POST" id="templateForm">
                <?php if ($edit_template): ?>
                <input type="hidden" name="template_id" value="<?php echo $edit_template['id']; ?>">
                <?php
endif; ?>

                <div style="margin-bottom: 15px;">
                    <label style="font-size: 13px; font-weight: 500;">Template Name</label>
                    <input type="text" name="template_name"
                        value="<?php echo $edit_template ? htmlspecialchars($edit_template['name']) : ''; ?>"
                        placeholder="e.g., Welcome Newsletter" required style="margin-bottom: 0;">
                </div>

                <div style="margin-bottom: 15px;">
                    <label style="font-size: 13px; font-weight: 500;">Email Body</label>
                    <input type="hidden" name="template_content">
                    <div id="editor" style="height: 300px; background: white;">
                        <?php echo $edit_template ? $edit_template['content'] : ''; ?>
                    </div>
                </div>

                <div style="display: flex; gap: 10px;">
                    <button type="submit" name="save_template" class="btn">
                        <?php echo $edit_template ? 'Update Template' : 'Save Template'; ?>
                    </button>
                    <?php if ($edit_template): ?>
                    <a href="email_templates.php" class="btn btn-secondary">Cancel Edit</a>
                    <?php
endif; ?>
                </div>
            </form>
        </div>

        <!-- Saved Templates -->
        <div class="card">
            <h3 style="margin-top: 0; margin-bottom: 15px;">Saved Templates</h3>
            <?php if (empty($templates)): ?>
            <p style="color: #666; font-size: 14px;">You haven't saved any templates yet. Create one above!</p>
            <?php
else: ?>
            <div class="grid">
                <?php foreach ($templates as $t): ?>
                <div class="template-card">
                    <div class="template-name">
                        <?php echo htmlspecialchars($t['name']); ?>
                    </div>
                    <div class="template-meta">
                        Last updated:
                        <?php echo date('M j, Y g:ia', strtotime($t['updated_at'])); ?>
                    </div>
                    <div class="template-actions">
                        <a href="?edit=<?php echo $t['id']; ?>" class="btn"
                            style="flex: 1; text-align: center; padding: 6px;">✏️ Edit</a>
                        <a href="?delete_template=<?php echo $t['id']; ?>" class="btn btn-secondary"
                            style="flex: 1; text-align: center; background: #ffebee; color: #f44336; padding: 6px;"
                            onclick="return confirm('Delete this template forever?')">🗑 Delete</a>
                    </div>
                </div>
                <?php
    endforeach; ?>
            </div>
            <?php
endif; ?>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.quilljs.com/1.3.6/quill.js"></script>
    <script>
        var quill = new Quill('#editor', {
            theme: 'snow',
            modules: {
                toolbar: [
                    ['bold', 'italic', 'underline', 'strike'],
                    ['blockquote', 'code-block'],
                    [{ 'list': 'ordered' }, { 'list': 'bullet' }],
                    [{ 'header': [1, 2, 3, 4, 5, 6, false] }],
                    [{ 'color': [] }, { 'background': [] }],
                    ['link', 'image'],
                    ['video', 'clean']
                ]
            }
        });

        var form = document.getElementById('templateForm');
        form.onsubmit = function () {
            var body = document.querySelector('input[name=template_content]');
            body.value = quill.root.innerHTML;
        };
    </script>
</body>

</html>