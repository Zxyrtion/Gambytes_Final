<?php
require_once __DIR__ . '/../core/Controller.php';
require_once __DIR__ . '/../models/UserModel.php';

/**
 * Authentication Controller
 * Handles user authentication and registration
 */
class AuthController extends Controller {
    private $userModel;
    
    public function __construct() {
        parent::__construct();
        $this->userModel = new UserModel();
    }
    
    /**
     * Display login page
     */
    public function login() {
        // If already logged in, redirect to dashboard
        if ($this->isAuthenticated()) {
            $this->redirect('/app/views/auth/dashboard.php');
        }
        
        $this->view('auth/login');
    }
    
    /**
     * Process login
     */
    public function processLogin() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('/app/views/auth/login.php');
        }
        
        $email = $_POST['email'] ?? '';
        $password = $_POST['password'] ?? '';
        
        // Validate inputs
        if (empty($email) || empty($password)) {
            $_SESSION['error'] = 'Email and password are required';
            $this->redirect('/app/views/auth/login.php');
        }
        
        // Find user by email
        $user = $this->userModel->findByEmail($email);
        
        if (!$user) {
            $_SESSION['error'] = 'User not found';
            $this->redirect('/app/views/auth/login.php');
        }
        
        // Verify password
        if (!password_verify($password, $user['password'])) {
            $_SESSION['error'] = 'Wrong password';
            $this->redirect('/app/views/auth/login.php');
        }
        
        // Check if email is verified
        if ($user['is_verified'] == 0) {
            $_SESSION['error'] = 'Please verify your email address before logging in';
            $this->redirect('/app/views/auth/login.php');
        }
        
        // Set session variables
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['user_email'] = $user['email'];
        $_SESSION['user_name'] = $user['first_name'] . ' ' . $user['last_name'];
        
        // Regenerate session ID for security
        session_regenerate_id(true);
        
        // Redirect based on role
        if (empty($user['role'])) {
            $this->redirect('/app/views/auth/role_selection.php');
        } else {
            $this->redirect('/app/views/auth/dashboard.php');
        }
    }
    
    /**
     * Display registration page
     */
    public function register() {
        $this->view('auth/register');
    }
    
    /**
     * Process registration
     */
    public function processRegister() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('/app/views/auth/register.php');
        }
        
        // Get form data
        $firstName = $_POST['first_name'] ?? '';
        $middleName = $_POST['middle_name'] ?? '';
        $lastName = $_POST['last_name'] ?? '';
        $email = $_POST['email'] ?? '';
        $password = $_POST['password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';
        
        // Validate inputs
        if (empty($firstName) || empty($lastName) || empty($email) || empty($password)) {
            $_SESSION['error'] = 'All required fields must be filled';
            $this->redirect('/app/views/auth/register.php');
        }
        
        // Validate email format
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $_SESSION['error'] = 'Invalid email format';
            $this->redirect('/app/views/auth/register.php');
        }
        
        // Check password match
        if ($password !== $confirmPassword) {
            $_SESSION['error'] = 'Passwords do not match';
            $this->redirect('/app/views/auth/register.php');
        }
        
        // Check if user already exists
        $existingUser = $this->userModel->findByEmail($email);
        if ($existingUser) {
            $_SESSION['error'] = 'Email already registered';
            $this->redirect('/app/views/auth/register.php');
        }
        
        // Hash password
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        
        // Generate verification token
        $verificationToken = bin2hex(random_bytes(32));
        
        // Create user
        $userData = [
            'first_name' => $firstName,
            'middle_name' => $middleName,
            'last_name' => $lastName,
            'email' => $email,
            'password' => $hashedPassword,
            'role' => null,
            'verification_token' => $verificationToken,
            'is_verified' => 0
        ];
        
        try {
            $userId = $this->userModel->createUser($userData);
            
            // TODO: Send verification email
            
            $_SESSION['message'] = 'Registration successful! Please check your email to verify your account.';
            $this->redirect('/app/views/auth/login.php');
            
        } catch (Exception $e) {
            $_SESSION['error'] = 'Registration failed: ' . $e->getMessage();
            $this->redirect('/app/views/auth/register.php');
        }
    }
    
    /**
     * Logout user
     */
    public function logout() {
        // Destroy session
        session_unset();
        session_destroy();
        
        // Redirect to login
        $this->redirect('/app/views/auth/login.php');
    }
    
    /**
     * Display dashboard
     */
    public function dashboard() {
        $this->requireAuth();
        $this->view('auth/dashboard');
    }
    
    /**
     * Verify email
     */
    public function verify() {
        $token = $_GET['token'] ?? '';
        
        if (empty($token)) {
            $_SESSION['error'] = 'Invalid verification link';
            $this->redirect('/app/views/auth/login.php');
        }
        
        try {
            $success = $this->userModel->verifyEmail($token);
            
            if ($success) {
                $_SESSION['message'] = 'Email verified successfully! You can now login.';
            } else {
                $_SESSION['error'] = 'Invalid or expired verification token';
            }
            
        } catch (Exception $e) {
            $_SESSION['error'] = 'Verification failed: ' . $e->getMessage();
        }
        
        $this->redirect('/app/views/auth/login.php');
    }
}
