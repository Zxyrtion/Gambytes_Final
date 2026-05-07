<?php
require_once __DIR__ . '/../core/Controller.php';
require_once __DIR__ . '/../models/PaymentModel.php';

/**
 * Payment Controller
 * Handles payment processing and management
 */
class PaymentController extends Controller {
    private $paymentModel;
    
    public function __construct() {
        parent::__construct();
        $this->paymentModel = new PaymentModel();
    }
    
    /**
     * Display payment page
     */
    public function index() {
        $this->requireAuth();
        $this->view('payment/index');
    }
    
    /**
     * Process payment
     */
    public function process() {
        $this->requireAuth();
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->json(['success' => false, 'error' => 'Invalid request method'], 400);
        }
        
        $userId = $_SESSION['user_id'];
        $amount = $_POST['amount'] ?? 0;
        
        // Validate amount
        if ($amount <= 0) {
            $this->json(['success' => false, 'error' => 'Invalid amount'], 400);
        }
        
        // Create payment record
        $paymentData = [
            'user_id' => $userId,
            'amount' => $amount,
            'status' => 'pending',
            'paid_at' => date('Y-m-d H:i:s')
        ];
        
        try {
            $paymentId = $this->paymentModel->createPayment($paymentData);
            
            // TODO: Integrate with payment gateway
            
            // For now, mark as completed
            $this->paymentModel->updatePaymentStatus($paymentId, 'completed');
            
            $this->json([
                'success' => true,
                'payment_id' => $paymentId,
                'message' => 'Payment processed successfully'
            ]);
            
        } catch (Exception $e) {
            $this->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }
    
    /**
     * Get user payments
     */
    public function getUserPayments() {
        $this->requireAuth();
        
        $userId = $_SESSION['user_id'];
        
        try {
            $payments = $this->paymentModel->getPaymentsByUser($userId);
            
            $this->json([
                'success' => true,
                'payments' => $payments
            ]);
            
        } catch (Exception $e) {
            $this->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }
    
    /**
     * Get payment details
     */
    public function getPayment($paymentId) {
        $this->requireAuth();
        
        try {
            $payment = $this->paymentModel->getPaymentById($paymentId);
            
            if (!$payment) {
                $this->json(['success' => false, 'error' => 'Payment not found'], 404);
            }
            
            // Check if user owns this payment
            if ($payment['user_id'] != $_SESSION['user_id']) {
                $this->json(['success' => false, 'error' => 'Access denied'], 403);
            }
            
            $this->json([
                'success' => true,
                'payment' => $payment
            ]);
            
        } catch (Exception $e) {
            $this->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }
    
    /**
     * Display receipt
     */
    public function receipt($paymentId) {
        $this->requireAuth();
        
        try {
            $payment = $this->paymentModel->getPaymentById($paymentId);
            
            if (!$payment) {
                die('Payment not found');
            }
            
            // Check if user owns this payment
            if ($payment['user_id'] != $_SESSION['user_id']) {
                die('Access denied');
            }
            
            $this->view('payment/receipt', ['payment' => $payment]);
            
        } catch (Exception $e) {
            die('Error: ' . $e->getMessage());
        }
    }
}
