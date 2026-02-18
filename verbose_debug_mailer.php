<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

try {
    echo "Instantiating SystemMailer...\n";
    $mail = new PHPMailer(true);

    // Enable verbose debug output
    $mail->SMTPDebug = 2; // 2 = client and server messages
    $mail->Debugoutput = function ($str, $level) {
        echo "debug level $level; message: $str\n";
    };

    $mail->isSMTP();
    $mail->Host = $_ENV['SMTP_HOST'];
    $mail->SMTPAuth = true;
    $mail->Username = $_ENV['SMTP_USERNAME'];
    $mail->Password = $_ENV['SMTP_PASSWORD'];
    $mail->SMTPSecure = $_ENV['SMTP_ENCRYPTION'];
    $mail->Port = $_ENV['SMTP_PORT'];
    $mail->setFrom($_ENV['SMTP_FROM'], 'Bulk Sender Debug');
    $mail->addAddress('test@example.com'); // We will see the handshake even if this fails

    echo "Attempting to send email to " . $_ENV['SMTP_USERNAME'] . "...\n"; // Send to self for test
    $mail->addAddress($_ENV['SMTP_USERNAME']);

    $mail->Subject = 'Debug Email';
    $mail->Body = 'This is a test email';

    $mail->send();
    echo "Message has been sent\n";

}
catch (Exception $e) {
    echo "Message could not be sent. Mailer Error: {$mail->ErrorInfo}\n";
}