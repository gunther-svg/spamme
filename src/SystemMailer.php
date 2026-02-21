<?php
namespace App;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class SystemMailer
{
    private $mail;

    public function __construct()
    {
        $this->mail = new PHPMailer(true);
        try {
            $this->mail->isSMTP();
            $this->mail->Host = $_ENV['SMTP_HOST'];
            $this->mail->SMTPAuth = true;
            $this->mail->Username = $_ENV['SMTP_USERNAME'];
            $this->mail->Password = $_ENV['SMTP_PASSWORD'];
            $this->mail->SMTPSecure = $_ENV['SMTP_ENCRYPTION'];
            $this->mail->Port = $_ENV['SMTP_PORT'];
            $this->mail->setFrom($_ENV['SMTP_FROM'], 'Bulk Sender System');
            $this->mail->isHTML(true);
        }
        catch (Exception $e) {
        // Log error
        }
    }

    public function sendVerificationEmail($email, $token)
    {
        try {
            $link = $_ENV['APP_URL'] . "/verify.php?token=" . $token;
            $this->mail->addAddress($email);
            $this->mail->Subject = 'Verify Your Account';
            $this->mail->Body = "<h1>Welcome!</h1><p>Please click the link below to verify your account:</p><p><a href='$link'>$link</a></p>";
            $this->mail->send();
            return true;
        }
        catch (Exception $e) {
            return false;
        }
    }

    public function sendPasswordReset($email, $token)
    {
        try {
            $link = $_ENV['APP_URL'] . "/reset_password.php?token=" . $token;
            $this->mail->addAddress($email);
            $this->mail->Subject = 'Password Reset Request';
            $this->mail->Body = "<h1>Password Reset</h1><p>Click the link below to reset your password:</p><p><a href='$link'>$link</a></p><p>If you did not request this, please ignore this email.</p>";
            $this->mail->send();
            return true;
        }
        catch (Exception $e) {
            return false;
        }
    }

    public function sendDepositNotification($userEmail, $amount, $method)
    {
        try {
            $this->mail->addAddress($userEmail);
            $this->mail->Subject = 'Deposit Received';
            $this->mail->Body = "<h1>Deposit Received</h1><p>We have received your deposit request of $$amount via $method.</p><p>Our team will review and approve it shortly.</p>";
            $this->mail->send();
            return true;
        }
        catch (Exception $e) {
            return false;
        }
    }

    public function sendAdminDepositNotification($amount, $method, $userEmail)
    {
        try {
            $this->mail->clearAllRecipients();
            $this->mail->addAddress($_ENV['SMTP_FROM']);
            $this->mail->Subject = 'New Deposit Pending Approval';
            $this->mail->Body = "<h1>New Deposit</h1><p>User $userEmail just submitted a deposit request for $$amount via $method.</p><p>Please log in to the Admin Portal to review.</p>";
            $this->mail->send();
            return true;
        }
        catch (Exception $e) {
            return false;
        }
    }

    public function sendAdminRegistrationNotification($userEmail)
    {
        try {
            $this->mail->clearAllRecipients();
            $this->mail->addAddress($_ENV['SMTP_FROM']);
            $this->mail->Subject = 'New User Registration';
            $this->mail->Body = "<h1>New User</h1><p>A new user has registered with the email: $userEmail.</p>";
            $this->mail->send();
            return true;
        }
        catch (Exception $e) {
            return false;
        }
    }
}