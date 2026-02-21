<?php

namespace App;

use PDO;

class AdminAuth
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
    }

    public function login($email, $password)
    {
        $stmt = $this->db->prepare("SELECT * FROM admins WHERE email = ?");
        $stmt->execute([$email]);
        $admin = $stmt->fetch();

        if ($admin && password_verify($password, $admin['password'])) {
            $_SESSION['admin_id'] = $admin['id'];
            $_SESSION['admin_email'] = $admin['email'];
            return ['success' => true, 'message' => 'Login successful.'];
        }
        return ['success' => false, 'message' => 'Invalid credentials or not an admin.'];
    }

    public function isAdminLoggedIn()
    {
        return isset($_SESSION['admin_id']);
    }

    public function logout()
    {
        unset($_SESSION['admin_id']);
        unset($_SESSION['admin_email']);
    }

    public function getAdmin()
    {
        if (!$this->isAdminLoggedIn())
            return null;
        $stmt = $this->db->prepare("SELECT id, email FROM admins WHERE id = ?");
        $stmt->execute([$_SESSION['admin_id']]);
        return $stmt->fetch();
    }
}