<?php
/**
 * Auth Class - Handle authentication
 */

class Auth {
    private $db;
    
    public function __construct() {
        $this->db = db();
    }
    
    /**
     * Authenticate user with email and PIN
     */
    public function login($email, $pin) {
        // Get user by email
        $stmt = $this->db->prepare("
            SELECT u.*, c.company_name, c.id as company_id
            FROM users u
            JOIN companies c ON u.company_id = c.id
            WHERE u.email = ? AND u.is_active = 1
        ");
        $stmt->execute([$email]);
        $users = $stmt->fetchAll();
        
        // Try to find user with matching PIN
        $user = null;
        foreach ($users as $u) {
            if ($u['pin'] === $pin) {
                $user = $u;
                break;
            }
        }
        
        if (!$user) {
            return ['success' => false, 'message' => 'Invalid email or PIN'];
        }
        
        // Update last login
        $stmt = $this->db->prepare("
            UPDATE users SET last_login = NOW() WHERE id = ?
        ");
        $stmt->execute([$user['id']]);
        
        // Set session
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_name'] = $user['full_name'];
        $_SESSION['user_email'] = $user['email'];
        $_SESSION['user_role'] = $user['role'];
        $_SESSION['company_id'] = $user['company_id'];
        $_SESSION['company_name'] = $user['company_name'];
        
        return [
            'success' => true,
            'role' => $user['role'],
            'user_name' => $user['full_name']
        ];
    }
    
    /**
     * Logout user
     */
    public function logout() {
        session_destroy();
        return ['success' => true];
    }
    
    /**
     * Check if session is still valid
     */
    public function validateSession() {
        if (!isLoggedIn()) {
            return false;
        }
        
        // You can add session timeout check here
        return true;
    }
}