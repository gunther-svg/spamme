<?php
require_once __DIR__ . '/vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

try {
    echo "Instantiating SystemMailer...\n";
    $mailer = new App\SystemMailer();
    echo "SystemMailer instantiated.\n";

    echo "Sending test email...\n";
    $result = $mailer->sendVerificationEmail('test@example.com', 'test_token');
    echo "Result: " . ($result ? 'True' : 'False') . "\n";

}
catch (Throwable $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString();
}