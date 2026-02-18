<?php

namespace App;

use PDO;

class Auth
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
    }

    public function register($email, $password)
    {
        $stmt = $this->db->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            return ['success' => false, 'message' => 'Email already registered.'];
        }

        $hash = password_hash($password, PASSWORD_DEFAULT);
        $token = bin2hex(random_bytes(16));

        $stmt = $this->db->prepare("INSERT INTO users (email, password, verification_token) VALUES (?, ?, ?)");
        if ($stmt->execute([$email, $hash, $token])) {
            $mailer = new SystemMailer();
            $mailer->sendVerificationEmail($email, $token);
            return ['success' => true, 'message' => 'Registration successful using template SMTP. Please check your email to verify your account.'];
        }
        return ['success' => false, 'message' => 'Registration failed.'];
    }

    public function verifyEmail($token)
    {
        $stmt = $this->db->prepare("SELECT id FROM users WHERE verification_token = ?");
        $stmt->execute([$token]);
        $user = $stmt->fetch();

        if ($user) {
            $stmt = $this->db->prepare("UPDATE users SET verified = 1, verification_token = NULL WHERE id = ?");
            if ($stmt->execute([$user['id']])) {
                return ['success' => true, 'message' => 'Email verified successfully. You can now login.'];
            }
        }
        return ['success' => false, 'message' => 'Invalid or expired verification link.'];
    }

    public function requestPasswordReset($email)
    {
        $stmt = $this->db->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user) {
            $token = bin2hex(random_bytes(16));
            $expiry = date('Y-m-d H:i:s', strtotime('+1 hour'));

            // Limit token length to 255 chars just in case, but bin2hex(16) is 32 chars.
            $stmt = $this->db->prepare("UPDATE users SET reset_token = ?, reset_expires = ? WHERE id = ?");
            $stmt->execute([$token, $expiry, $user['id']]);

            $mailer = new SystemMailer();
            $mailer->sendPasswordReset($email, $token);
        }
        // Always return success to prevent user enumeration
        return ['success' => true, 'message' => 'If an account exists with that email, a password reset link has been sent.'];
    }

    public function resetPassword($token, $newPassword)
    {
        $stmt = $this->db->prepare("SELECT id FROM users WHERE reset_token = ? AND reset_expires > NOW()");
        $stmt->execute([$token]);
        $user = $stmt->fetch();

        if ($user) {
            $hash = password_hash($newPassword, PASSWORD_DEFAULT);
            $stmt = $this->db->prepare("UPDATE users SET password = ?, reset_token = NULL, reset_expires = NULL WHERE id = ?");
            if ($stmt->execute([$hash, $user['id']])) {
                return ['success' => true, 'message' => 'Password has been reset. You can now login.'];
            }
        }
        return ['success' => false, 'message' => 'Invalid or expired reset link.'];
    }

    public function login($email, $password)
    {
        $stmt = $this->db->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            if ($user['verified'] == 0) {
                return ['success' => false, 'message' => 'Please verify your email address before logging in.'];
            }
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_email'] = $user['email'];
            return ['success' => true, 'message' => 'Login successful.'];
        }
        return ['success' => false, 'message' => 'Invalid credentials.'];
    }

    public function isLoggedIn()
    {
        return isset($_SESSION['user_id']);
    }

    public function logout()
    {
        session_destroy();
    }

    public function getUser()
    {
        if (!$this->isLoggedIn())
            return null;
        $stmt = $this->db->prepare("SELECT id, email, credits, verified FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        return $stmt->fetch();
    }
}