<?php
require_once __DIR__ . '/../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

use App\Database;

try {
    // Connect without DB selected to create it
    $host = $_ENV['DB_HOST'];
    $port = $_ENV['DB_PORT'];
    $user = $_ENV['DB_USERNAME'];
    $pass = $_ENV['DB_PASSWORD'];

    $pdo = new PDO("mysql:host=$host;port=$port", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $dbname = $_ENV['DB_DATABASE'];
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `$dbname`");
    $pdo->exec("USE `$dbname`");

    // Admins
    $pdo->exec("CREATE TABLE IF NOT EXISTS admins (
        id INT AUTO_INCREMENT PRIMARY KEY,
        email VARCHAR(255) UNIQUE NOT NULL,
        password VARCHAR(255) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    $adminEmail = $_ENV['SMTP_FROM'];
    $adminPassword = password_hash('admin123', PASSWORD_DEFAULT);

    // Insert default admin if no admins exist
    $stmt = $pdo->query("SELECT COUNT(*) FROM admins");
    if ($stmt->fetchColumn() == 0) {
        $stmt = $pdo->prepare("INSERT INTO admins (email, password) VALUES (?, ?)");
        $stmt->execute([$adminEmail, $adminPassword]);
    }

    // Users
    // Added reset_token, reset_expires, verification_token
    $pdo->exec("CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        email VARCHAR(255) UNIQUE NOT NULL,
        password VARCHAR(255) NOT NULL,
        credits INT DEFAULT 0,
        verified TINYINT(1) DEFAULT 0,
        verification_token VARCHAR(255) NULL,
        reset_token VARCHAR(255) NULL,
        reset_expires DATETIME NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    // Add columns if not exist (quick hack for existing table)
    try {
        $pdo->exec("ALTER TABLE users ADD COLUMN verification_token VARCHAR(255) NULL");
    }
    catch (Exception $e) {
    }
    try {
        $pdo->exec("ALTER TABLE users ADD COLUMN reset_token VARCHAR(255) NULL");
    }
    catch (Exception $e) {
    }
    try {
        $pdo->exec("ALTER TABLE users ADD COLUMN reset_expires DATETIME NULL");
    }
    catch (Exception $e) {
    }

    // SMTP Configs
    $pdo->exec("CREATE TABLE IF NOT EXISTS smtp_configs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        host VARCHAR(255) NOT NULL,
        port INT NOT NULL,
        username VARCHAR(255) NOT NULL,
        password VARCHAR(255) NOT NULL,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )");

    // Transactions
    $pdo->exec("CREATE TABLE IF NOT EXISTS transactions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        amount DECIMAL(10, 2) NOT NULL,
        method VARCHAR(50) NOT NULL,
        proof_file VARCHAR(255),
        status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )");

    // Campaigns
    $pdo->exec("CREATE TABLE IF NOT EXISTS campaigns (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        name VARCHAR(255) NOT NULL,
        status ENUM('draft', 'scheduled', 'running', 'completed', 'paused') DEFAULT 'draft',
        start_time TIMESTAMP NULL,
        send_rate INT DEFAULT 100,
        rate_type ENUM('recipient_hour', 'global_hour', 'global_minute') DEFAULT 'recipient_hour',
        batch_delay INT DEFAULT 0,
        smtp_config_id INT,
        from_email VARCHAR(255),
        from_name VARCHAR(255),
        subject VARCHAR(255),
        body TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (smtp_config_id) REFERENCES smtp_configs(id) ON DELETE SET NULL
    )");

    try {
        $pdo->exec("ALTER TABLE campaigns ADD COLUMN rate_type ENUM('recipient_hour', 'global_hour', 'global_minute') DEFAULT 'recipient_hour'");
    }
    catch (Exception $e) {
    }
    try {
        $pdo->exec("ALTER TABLE campaigns ADD COLUMN batch_delay INT DEFAULT 0");
    }
    catch (Exception $e) {
    }

    try {
        $pdo->exec("ALTER TABLE campaigns ADD COLUMN from_email VARCHAR(255)");
    }
    catch (Exception $e) {
    }
    try {
        $pdo->exec("ALTER TABLE campaigns ADD COLUMN from_name VARCHAR(255)");
    }
    catch (Exception $e) {
    }

    // Email Lists — user-managed mailing lists
    $pdo->exec("CREATE TABLE IF NOT EXISTS email_lists (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        name VARCHAR(255) NOT NULL,
        description TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )");

    // Email List Entries — individual email addresses in a list
    $pdo->exec("CREATE TABLE IF NOT EXISTS email_list_entries (
        id INT AUTO_INCREMENT PRIMARY KEY,
        list_id INT NOT NULL,
        email VARCHAR(255) NOT NULL,
        name VARCHAR(255),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (list_id) REFERENCES email_lists(id) ON DELETE CASCADE,
        UNIQUE KEY unique_list_email (list_id, email)
    )");

    // Email Queue — one row per recipient per campaign
    $pdo->exec("CREATE TABLE IF NOT EXISTS email_queue (
        id INT AUTO_INCREMENT PRIMARY KEY,
        campaign_id INT NOT NULL,
        recipient_email VARCHAR(255) NOT NULL,
        status ENUM('pending', 'active', 'paused', 'completed') DEFAULT 'pending',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (campaign_id) REFERENCES campaigns(id) ON DELETE CASCADE
    )");

    // Email Sent Log — one row per actual email sent (tracks rate per recipient)
    $pdo->exec("CREATE TABLE IF NOT EXISTS email_sent_log (
        id INT AUTO_INCREMENT PRIMARY KEY,
        campaign_id INT NOT NULL,
        queue_id INT NOT NULL,
        recipient_email VARCHAR(255) NOT NULL,
        sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (campaign_id) REFERENCES campaigns(id) ON DELETE CASCADE,
        FOREIGN KEY (queue_id) REFERENCES email_queue(id) ON DELETE CASCADE
    )");

    // Migration: update old enum if needed
    try {
        $pdo->exec("ALTER TABLE email_queue MODIFY COLUMN status ENUM('pending', 'active', 'paused', 'completed', 'sent', 'failed') DEFAULT 'pending'");
    }
    catch (Exception $e) {
    }

    echo "Database initialized successfully.\n";

}
catch (Exception $e) {
// echo "Error: " . $e->getMessage() . "\n";
// Ignore errors for now or log them
}