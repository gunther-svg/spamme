<?php
require_once __DIR__ . '/../vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

session_start();
use App\Auth;
use App\Database;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$auth = new Auth();
$user = $auth->getUser();

if (!$user) {
    header('Location: index.php');
    exit;
}

$db = Database::getInstance()->getConnection();
$message = '';

// Handle Test Email (AJAX)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'test_email') {
    header('Content-Type: application/json');
    $smtp_id = $_POST['smtp_config_id'];
    $to = $_POST['test_email'];
    $subject = $_POST['subject'];
    $body = $_POST['body'];
    $from_email = $_POST['from_email'];
    $from_name = $_POST['from_name'];

    try {
        $stmt = $db->prepare("SELECT * FROM smtp_configs WHERE id = ? AND user_id = ?");
        $stmt->execute([$smtp_id, $user['id']]);
        $config = $stmt->fetch();

        if (!$config) {
            echo json_encode(['success' => false, 'message' => 'Invalid SMTP Config']);
            exit;
        }

        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = $config['host'];
        $mail->SMTPAuth = true;
        $mail->Username = $config['username'];
        $mail->Password = $config['password'];
        $mail->SMTPSecure = 'tls'; // Assuming TLS for now, should be in DB
        $mail->Port = $config['port'];

        $mail->setFrom($from_email, $from_name);
        $mail->addAddress($to);
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $body;

        $mail->send();
        echo json_encode(['success' => true, 'message' => 'Test email sent successfully!']);
    }
    catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Mailer Error: ' . $mail->ErrorInfo]);
    }
    exit; // Stop execution for AJAX
}

// Fetch SMTP Configs
$stmt = $db->prepare("SELECT * FROM smtp_configs WHERE user_id = ?");
$stmt->execute([$user['id']]);
$smtp_configs = $stmt->fetchAll();

// Fetch Email Lists
$stmt = $db->prepare("
    SELECT el.*, (SELECT COUNT(*) FROM email_list_entries ele WHERE ele.list_id = el.id) as entry_count
    FROM email_lists el WHERE el.user_id = ?
");
$stmt->execute([$user['id']]);
$email_lists = $stmt->fetchAll();

// Fetch Email Templates
$stmt = $db->prepare("SELECT * FROM email_templates WHERE user_id = ? ORDER BY name ASC");
$stmt->execute([$user['id']]);
$email_templates = $stmt->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['action'])) {
    $name = $_POST['name'];
    $smtp_id = $_POST['smtp_config_id'];
    $subject = $_POST['subject'];
    $body = $_POST['body'];
    $start_time = $_POST['start_time'];
    $send_rate = $_POST['send_rate'];
    $rate_type = $_POST['rate_type'] ?? 'recipient_hour';
    $batch_delay = (int)($_POST['batch_delay'] ?? 0);
    $from_email = $_POST['from_email'];
    $from_name = $_POST['from_name'];

    // Collect recipient emails from either CSV upload or saved list
    $emails = [];
    $recipient_source = $_POST['recipient_source'] ?? 'csv';

    if ($recipient_source === 'list' && !empty($_POST['email_list_id'])) {
        // Load from saved email list
        $list_id = (int)$_POST['email_list_id'];
        $stmt = $db->prepare("SELECT email FROM email_list_entries WHERE list_id = ? AND list_id IN (SELECT id FROM email_lists WHERE user_id = ?)");
        $stmt->execute([$list_id, $user['id']]);
        while ($row = $stmt->fetch()) {
            $emails[] = $row['email'];
        }
    }
    elseif (isset($_FILES['recipient_list']) && $_FILES['recipient_list']['error'] == 0) {
        // CSV upload
        $file = fopen($_FILES['recipient_list']['tmp_name'], 'r');
        while (($line = fgetcsv($file)) !== FALSE) {
            foreach ($line as $cell) {
                if (filter_var(trim($cell), FILTER_VALIDATE_EMAIL)) {
                    $emails[] = trim($cell);
                }
            }
        }
        fclose($file);
    }

    if (count($emails) > 0) {
        // Create Campaign
        $stmt = $db->prepare("INSERT INTO campaigns (user_id, name, status, start_time, send_rate, rate_type, batch_delay, smtp_config_id, subject, body, from_email, from_name) VALUES (?, ?, 'scheduled', ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$user['id'], $name, $start_time, $send_rate, $rate_type, $batch_delay, $smtp_id, $subject, $body, $from_email, $from_name]);
        $campaign_id = $db->lastInsertId();

        // Add to Queue
        $stmt = $db->prepare("INSERT INTO email_queue (campaign_id, recipient_email, status) VALUES (?, ?, 'pending')");
        foreach ($emails as $email) {
            $stmt->execute([$campaign_id, $email]);
        }

        $message = "Campaign created with " . count($emails) . " recipients.";
    }
    else {
        $message = "No valid emails found. Please upload a CSV or select an email list.";
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
    <!-- Quill CSS -->
    <link href="https://cdn.quilljs.com/1.3.6/quill.snow.css" rel="stylesheet">
    <!-- GrapesJS CSS & JS -->
    <link href="https://unpkg.com/grapesjs/dist/css/grapes.min.css" rel="stylesheet"/>
    <script src="https://unpkg.com/grapesjs"></script>
    <!-- Email plugin -->
    <script src="https://unpkg.com/grapesjs-plugin-mail"></script>
    <style>
        .split-view {
            display: flex;
            gap: 20px;
        }

        .split-view>div {
            flex: 1;
        }

        .editor-container {
            height: 300px;
            background: white;
        }

        .preview-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }

        .preview-content {
            background: white;
            padding: 20px;
            border-radius: 8px;
            width: 80%;
            max-height: 80%;
            overflow-y: auto;
        }
    </style>
</head>

<body>
    <div class="container" style="max-width: 1000px;">
        <header style="margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center;">
            <h1 style="color: var(--primary-color); margin: 0;">Create Campaign</h1>
            <a href="dashboard.php" class="btn btn-secondary">Back to Dashboard</a>
        </header>

        <?php if ($message): ?>
        <div class="alert">
            <?php echo htmlspecialchars($message); ?>
        </div>
        <?php
endif; ?>

        <div class="card">
            <form method="POST" enctype="multipart/form-data" id="campaignForm">
                <div class="split-view">
                    <div>
                        <label>Campaign Name</label>
                        <input type="text" name="name" required placeholder="My Newsletter">

                        <label>SMTP Configuration</label>
                        <select name="smtp_config_id" id="smtp_config_id" required>
                            <?php foreach ($smtp_configs as $config): ?>
                            <option value="<?php echo $config['id']; ?>">
                                <?php echo htmlspecialchars($config['host'] . ' (' . $config['username'] . ')'); ?>
                            </option>
                            <?php
endforeach; ?>
                        </select>
                        <p style="font-size: 0.8em; color: #666;"><a href="smtp_configs.php">Manage SMTP Configs</a></p>

                        <hr style="border: 0; border-top: 1px solid #eee; margin: 15px 0;">

                        <label>Sending Speed / Rate Limit</label>
                        <div
                            style="background: #f9f9f9; padding: 15px; border-radius: 6px; border: 1px solid #eee; margin-bottom: 15px;">
                            <label style="font-weight: normal; font-size: 13px; display: block; margin-bottom: 8px;">
                                <input type="radio" name="rate_type" value="recipient_hour" checked>
                                <strong>Per Recipient per Hour</strong> (Current standard — Good for drip campaigns)
                            </label>
                            <label style="font-weight: normal; font-size: 13px; display: block; margin-bottom: 8px;">
                                <input type="radio" name="rate_type" value="global_hour">
                                <strong>Global per Hour</strong> (Good for IP warmup or daily limits)
                            </label>
                            <label style="font-weight: normal; font-size: 13px; display: block; margin-bottom: 12px;">
                                <input type="radio" name="rate_type" value="global_minute">
                                <strong>Global per Minute</strong> (Fast sending on established IPs)
                            </label>

                            <div style="display: flex; gap: 15px;">
                                <div style="flex: 1;">
                                    <label style="font-size: 13px;">Emails Amount</label>
                                    <input type="number" name="send_rate" value="100" min="1" required
                                        style="margin-top: 4px;">
                                </div>
                                <div style="flex: 1;">
                                    <label style="font-size: 13px;">Delay Between Emails (sec)</label>
                                    <input type="number" name="batch_delay" value="0" min="0" style="margin-top: 4px;">
                                </div>
                            </div>
                            <p style="font-size: 11px; color: #666; margin: 5px 0 0;">(Optional) A delay helps avoid
                                triggering spam filters during fast sends.</p>
                        </div>

                        <label>Start Time</label>
                        <input type="datetime-local" name="start_time" required
                            value="<?php echo date('Y-m-d\TH:i'); ?>">
                    </div>
                    <div>
                        <label>From Name</label>
                        <input type="text" name="from_name" id="from_name" required placeholder="John Doe">

                        <label>From Email</label>
                        <input type="email" name="from_email" id="from_email" required placeholder="john@example.com">

                        <label>Recipients</label>
                        <div style="margin: 8px 0;">
                            <label style="font-weight: normal; font-size: 13px; margin-right: 15px;">
                                <input type="radio" name="recipient_source" value="list"
                                    onchange="toggleRecipientSource()" checked> Use Saved List
                            </label>
                            <label style="font-weight: normal; font-size: 13px;">
                                <input type="radio" name="recipient_source" value="csv"
                                    onchange="toggleRecipientSource()"> Upload CSV
                            </label>
                        </div>
                        <div id="source-list">
                            <select name="email_list_id" id="email_list_id" style="width: 100%;">
                                <option value="">— Select an email list —</option>
                                <?php foreach ($email_lists as $el): ?>
                                <option value="<?php echo $el['id']; ?>">
                                    <?php echo htmlspecialchars($el['name'] . ' (' . $el['entry_count'] . ' emails)'); ?>
                                </option>
                                <?php
endforeach; ?>
                            </select>
                            <p style="font-size: 0.8em; color: #666;"><a href="email_lists.php">Manage Email Lists</a>
                            </p>
                        </div>
                        <div id="source-csv" style="display: none;">
                            <input type="file" name="recipient_list" accept=".csv"
                                style="border: none; padding: 10px 0;">
                            <p style="font-size: 0.8em; color: #666;">CSV should contain emails in the first column.</p>
                        </div>
                    </div>
                </div>

                <hr style="margin: 20px 0; border: 0; border-top: 1px solid #eee;">

                <label>Email Subject</label>
                <input type="text" name="subject" id="subject" required placeholder="Subject Line">

                <div style="display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 5px;">
                    <label style="margin: 0;">Email Body</label>
                    <?php if (!empty($email_templates)): ?>
                        <div style="display: flex; align-items: center; gap: 10px;">
                            <span style="font-size: 12px; color: #666;">Load Template:</span>
                            <select id="templateSelector" onchange="loadTemplate()" style="padding: 4px; font-size: 13px; margin: 0; width: 200px;">
                                <option value="">— Select Template —</option>
                                <?php foreach ($email_templates as $t): ?>
                                    <option value="<?php echo $t['id']; ?>"><?php echo htmlspecialchars($t['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    <?php endif; ?>
                </div>
                <div id="gjs" style="height:600px; border:1px solid #ddd; margin-bottom:20px;"></div>
                <input type="hidden" name="body" id="body">

                <div style="margin-top: 20px; display: flex; gap: 10px;">
                    <button type="submit" class="btn">Schedule Campaign</button>
                    <button type="button" class="btn btn-secondary" onclick="showPreview()">Preview</button>
                    <button type="button" class="btn btn-secondary" onclick="showTestEmail()">Send Test</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Preview Modal -->
    <div id="previewModal" class="preview-modal">
        <div class="preview-content">
            <h2 style="margin-top: 0;">Email Preview</h2>
            <div style="border-bottom: 1px solid #ddd; padding-bottom: 10px; margin-bottom: 10px;">
                <strong>From:</strong> <span id="previewFrom"></span><br>
                <strong>Subject:</strong> <span id="previewSubject"></span>
            </div>
            <div id="previewBody" style="border: 1px solid #ddd; padding: 15px; background: #f9f9f9;"></div>
            <div style="margin-top: 20px; text-align: right;">
                <button class="btn btn-secondary" onclick="hidePreview()">Close</button>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.quilljs.com/1.3.6/quill.js"></script>
    <script>
        var savedTemplates = <?php 
            $templates_js = [];
            foreach ($email_templates as $t) {
                $templates_js[$t['id']] = $t['content'];
            }
            echo json_encode($templates_js);
        ?>;

        function loadTemplate() {
            var selector = document.getElementById('templateSelector');
            var templateId = selector.value;
            if (templateId && savedTemplates[templateId]) {
                quill.clipboard.dangerouslyPasteHTML(savedTemplates[templateId]);
            }
        }

        function toggleRecipientSource() {
            var source = document.querySelector('input[name="recipient_source"]:checked').value;
            document.getElementById('source-list').style.display = source === 'list' ? 'block' : 'none';
            document.getElementById('source-csv').style.display = source === 'csv' ? 'block' : 'none';
        }

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
                    ['clean']
                ]
            }
        });

        var form = document.querySelector('form');
        form.onsubmit = function () {
            var body = document.querySelector('input[name=body]');
            body.value = quill.root.innerHTML;
        };

        function showPreview() {
            document.getElementById('previewFrom').innerText = document.getElementById('from_name').value + ' <' + document.getElementById('from_email').value + '>';
            document.getElementById('previewSubject').innerText = document.getElementById('subject').value;
            document.getElementById('previewBody').innerHTML = quill.root.innerHTML;
            document.getElementById('previewModal').style.display = 'flex';
        }

        function hidePreview() {
            document.getElementById('previewModal').style.display = 'none';
        }

        function showTestEmail() {
            var email = prompt("Enter email address to send test to:");
            if (email) {
                var formData = new FormData();
                formData.append('action', 'test_email');
                formData.append('smtp_config_id', document.getElementById('smtp_config_id').value);
                formData.append('test_email', email);
                formData.append('subject', '[TEST] ' + document.getElementById('subject').value);
                formData.append('body', quill.root.innerHTML);
                formData.append('from_email', document.getElementById('from_email').value);
                formData.append('from_name', document.getElementById('from_name').value);

                fetch('create_campaign.php', {
                    method: 'POST',
                    body: formData
                })
                    .then(response => response.json())
                    .then(data => {
                        alert(data.message);
                    })
                    .catch(error => {
                        alert('Error sending test email.');
                        console.error(error);
                    });
            }
        }
    </script>
    <script>
// Initialize GrapesJS
var editor = grapesjs.init({
    container: '#gjs',
    height: '600px',
    plugins: ['gjs-plugin-mail'],
    storageManager: { type: 'local' }, // Save/load templates locally
    fromElement: false,
    components: '', // Start empty or load a default template
    style: '',
});

// Save GrapesJS HTML to hidden input on form submit
var form = document.querySelector('form');
form.onsubmit = function () {
    var body = document.querySelector('input[name=body]');
    body.value = editor.getHtml();
};

function showPreview() {
    var fromName = document.getElementById('from_name').value;
    var fromEmail = document.getElementById('from_email').value;
    var subject = document.getElementById('subject').value;
    var htmlPreview = editor.getHtml();

    document.getElementById('previewFrom').innerText = fromName + ' <' + fromEmail + '>';
    document.getElementById('previewSubject').innerText = subject;
    document.getElementById('previewBody').innerHTML = htmlPreview;
    document.getElementById('previewModal').style.display = 'flex';
}

function hidePreview() {
    document.getElementById('previewModal').style.display = 'none';
}

function showTestEmail() {
    var email = prompt("Enter email address to send test to:");
    if (email) {
        var formData = new FormData();
        formData.append('action', 'test_email');
        formData.append('smtp_config_id', document.getElementById('smtp_config_id').value);
        formData.append('test_email', email);
        formData.append('subject', '[TEST] ' + document.getElementById('subject').value);
        formData.append('body', editor.getHtml());
        formData.append('from_email', document.getElementById('from_email').value);
        formData.append('from_name', document.getElementById('from_name').value);

        fetch('create_campaign.php', {
            method: 'POST',
            body: formData
        })
            .then(response => response.json())
            .then(data => {
                alert(data.message);
            })
            .catch(error => {
                alert('Error sending test email.');
                console.error(error);
            });
    }
}
</script>
</body>

</html>