<?php
require_once __DIR__ . '/../core/Model.php';

/**
 * Payment Model
 * Handles all payment-related database operations
 */
class PaymentModel extends Model {
    
    public function __construct() {
        parent::__construct();
        $this->table = 'payments';
    }
    
    /**
     * Create new payment
     * @param array $paymentData
     * @return int - Payment ID
     */
    public function createPayment($paymentData) {
        $stmt = $this->conn->prepare(
            "INSERT INTO payments (user_id, amount, status, paid_at) VALUES (?, ?, ?, ?)"
        );
        
        if (!$stmt) {
            throw new Exception('Prepare failed: ' . $this->conn->error);
        }
        
        $stmt->bind_param(
            'idss',
            $paymentData['user_id'],
            $paymentData['amount'],
            $paymentData['status'],
            $paymentData['paid_at']
        );
        
        if (!$stmt->execute()) {
            throw new Exception('Execute failed: ' . $stmt->error);
        }
        
        $paymentId = $this->conn->insert_id;
        $stmt->close();
        
        return $paymentId;
    }
    
    /**
     * Get payments by user ID
     * @param int $userId
     * @return array
     */
    public function getPaymentsByUser($userId) {
        $stmt = $this->conn->prepare("SELECT * FROM payments WHERE user_id = ? ORDER BY paid_at DESC");
        
        if (!$stmt) {
            throw new Exception('Prepare failed: ' . $this->conn->error);
        }
        
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $payments = [];
        while ($row = $result->fetch_assoc()) {
            $payments[] = $row;
        }
        
        $stmt->close();
        return $payments;
    }
    
    /**
     * Update payment status
     * @param int $paymentId
     * @param string $status
     * @return bool
     */
    public function updatePaymentStatus($paymentId, $status) {
        $stmt = $this->conn->prepare("UPDATE payments SET status = ? WHERE id = ?");
        
        if (!$stmt) {
            throw new Exception('Prepare failed: ' . $this->conn->error);
        }
        
        $stmt->bind_param('si', $status, $paymentId);
        
        if (!$stmt->execute()) {
            throw new Exception('Execute failed: ' . $stmt->error);
        }
        
        $success = $stmt->affected_rows > 0;
        $stmt->close();
        
        return $success;
    }
    
    /**
     * Get payment by ID
     * @param int $paymentId
     * @return array|null
     */
    public function getPaymentById($paymentId) {
        $stmt = $this->conn->prepare("SELECT * FROM payments WHERE id = ? LIMIT 1");
        
        if (!$stmt) {
            throw new Exception('Prepare failed: ' . $this->conn->error);
        }
        
        $stmt->bind_param('i', $paymentId);
        $stmt->execute();
        $result = $stmt->get_result();
        $payment = $result->fetch_assoc();
        $stmt->close();
        
        return $payment;
    }
    
    /**
     * Get total payments for user
     * @param int $userId
     * @return float
     */
    public function getTotalPaymentsByUser($userId) {
        $stmt = $this->conn->prepare("SELECT SUM(amount) as total FROM payments WHERE user_id = ? AND status = 'completed'");
        
        if (!$stmt) {
            throw new Exception('Prepare failed: ' . $this->conn->error);
        }
        
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $stmt->close();
        
        return (float)($row['total'] ?? 0);
    }
}
