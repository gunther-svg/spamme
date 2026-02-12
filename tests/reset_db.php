<?php
require_once __DIR__ . '/../vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

use App\Database;

$db = Database::getInstance()->getConnection();
$db->exec("SET FOREIGN_KEY_CHECKS = 0");
$db->exec("TRUNCATE TABLE transactions");
$db->exec("TRUNCATE TABLE email_queue");
$db->exec("TRUNCATE TABLE campaigns");
$db->exec("TRUNCATE TABLE smtp_configs");
$db->exec("TRUNCATE TABLE users");
$db->exec("SET FOREIGN_KEY_CHECKS = 1");
echo "Database reset.\n";