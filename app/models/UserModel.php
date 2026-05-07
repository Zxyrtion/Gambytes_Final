<?php
require_once __DIR__ . '/../core/Model.php';

/**
 * User Model
 * Handles all user-related database operations
 */
class UserModel extends Model {
    
    public function __construct() {
        parent::__construct();
        $this->table = 'users';
    }
    
    /**
     * Find user by email
     * @param string $email
     * @return array|null
     */
    public function findByEmail($email) {
        $stmt = $this->conn->prepare("SELECT * FROM users WHERE email = ? LIMIT 1");
        
        if (!$stmt) {
            throw new Exception('Prepare failed: ' . $this->conn->error);
        }
        
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        $stmt->close();
        
        return $user;
    }
    
    /**
     * Create new user
     * @param array $userData
     * @return int - User ID
     */
    public function createUser($userData) {
        $stmt = $this->conn->prepare(
            "INSERT INTO users (first_name, middle_name, last_name, email, password, role, verification_token, is_verified) 
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
        );
        
        if (!$stmt) {
            throw new Exception('Prepare failed: ' . $this->conn->error);
        }
        
        $stmt->bind_param(
            'sssssssi',
            $userData['first_name'],
            $userData['middle_name'],
            $userData['last_name'],
            $userData['email'],
            $userData['password'],
            $userData['role'],
            $userData['verification_token'],
            $userData['is_verified']
        );
        
        if (!$stmt->execute()) {
            throw new Exception('Execute failed: ' . $stmt->error);
        }
        
        $userId = $this->conn->insert_id;
        $stmt->close();
        
        return $userId;
    }
    
    /**
     * Verify user email
     * @param string $token
     * @return bool
     */
    public function verifyEmail($token) {
        $stmt = $this->conn->prepare("UPDATE users SET is_verified = 1, verification_token = NULL WHERE verification_token = ?");
        
        if (!$stmt) {
            throw new Exception('Prepare failed: ' . $this->conn->error);
        }
        
        $stmt->bind_param('s', $token);
        
        if (!$stmt->execute()) {
            throw new Exception('Execute failed: ' . $stmt->error);
        }
        
        $success = $stmt->affected_rows > 0;
        $stmt->close();
        
        return $success;
    }
    
    /**
     * Update user role
     * @param int $userId
     * @param string $role
     * @return bool
     */
    public function updateRole($userId, $role) {
        $stmt = $this->conn->prepare("UPDATE users SET role = ? WHERE id = ?");
        
        if (!$stmt) {
            throw new Exception('Prepare failed: ' . $this->conn->error);
        }
        
        $stmt->bind_param('si', $role, $userId);
        
        if (!$stmt->execute()) {
            throw new Exception('Execute failed: ' . $stmt->error);
        }
        
        $success = $stmt->affected_rows > 0;
        $stmt->close();
        
        return $success;
    }
    
    /**
     * Set password reset token
     * @param string $email
     * @param string $token
     * @param string $expiry
     * @return bool
     */
    public function setResetToken($email, $token, $expiry) {
        $stmt = $this->conn->prepare("UPDATE users SET reset_token = ?, reset_expiry = ? WHERE email = ?");
        
        if (!$stmt) {
            throw new Exception('Prepare failed: ' . $this->conn->error);
        }
        
        $stmt->bind_param('sss', $token, $expiry, $email);
        
        if (!$stmt->execute()) {
            throw new Exception('Execute failed: ' . $stmt->error);
        }
        
        $success = $stmt->affected_rows > 0;
        $stmt->close();
        
        return $success;
    }
    
    /**
     * Verify reset token
     * @param string $token
     * @return array|null
     */
    public function verifyResetToken($token) {
        $stmt = $this->conn->prepare("SELECT * FROM users WHERE reset_token = ? AND reset_expiry > NOW() LIMIT 1");
        
        if (!$stmt) {
            throw new Exception('Prepare failed: ' . $this->conn->error);
        }
        
        $stmt->bind_param('s', $token);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        $stmt->close();
        
        return $user;
    }
    
    /**
     * Update password
     * @param int $userId
     * @param string $hashedPassword
     * @return bool
     */
    public function updatePassword($userId, $hashedPassword) {
        $stmt = $this->conn->prepare("UPDATE users SET password = ?, reset_token = NULL, reset_expiry = NULL WHERE id = ?");
        
        if (!$stmt) {
            throw new Exception('Prepare failed: ' . $this->conn->error);
        }
        
        $stmt->bind_param('si', $hashedPassword, $userId);
        
        if (!$stmt->execute()) {
            throw new Exception('Execute failed: ' . $stmt->error);
        }
        
        $success = $stmt->affected_rows > 0;
        $stmt->close();
        
        return $success;
    }
    
    /**
     * Get users by role
     * @param string $role
     * @return array
     */
    public function getUsersByRole($role) {
        $stmt = $this->conn->prepare("SELECT * FROM users WHERE role = ? ORDER BY created_at DESC");
        
        if (!$stmt) {
            throw new Exception('Prepare failed: ' . $this->conn->error);
        }
        
        $stmt->bind_param('s', $role);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $users = [];
        while ($row = $result->fetch_assoc()) {
            $users[] = $row;
        }
        
        $stmt->close();
        return $users;
    }
}
