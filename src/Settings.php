<?php
namespace App;

use PDO;

class Settings
{
    /**
     * Get a setting value by key. Returns the given default if not found.
     */
    public static function get($key, $default = null)
    {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("SELECT setting_value FROM system_settings WHERE setting_key = ?");
        $stmt->execute([$key]);
        $result = $stmt->fetchColumn();

        return $result !== false ? $result : $default;
    }

    /**
     * Update or insert a setting value by key.
     */
    public static function set($key, $value)
    {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("
            INSERT INTO system_settings (setting_key, setting_value) 
            VALUES (?, ?) 
            ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
        ");
        return $stmt->execute([$key, $value]);
    }
}