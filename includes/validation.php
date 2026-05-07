<?php
/**
 * Input Validation Helper Functions
 */

class Validator {
    private $errors = [];
    
    /**
     * Validate email
     */
    public function email($value, $fieldName = 'Email') {
        if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
            $this->errors[] = "$fieldName must be a valid email address";
            return false;
        }
        return true;
    }
    
    /**
     * Validate required field
     */
    public function required($value, $fieldName = 'Field') {
        if (empty(trim($value))) {
            $this->errors[] = "$fieldName is required";
            return false;
        }
        return true;
    }
    
    /**
     * Validate minimum length
     */
    public function minLength($value, $min, $fieldName = 'Field') {
        if (strlen($value) < $min) {
            $this->errors[] = "$fieldName must be at least $min characters";
            return false;
        }
        return true;
    }
    
    /**
     * Validate maximum length
     */
    public function maxLength($value, $max, $fieldName = 'Field') {
        if (strlen($value) > $max) {
            $this->errors[] = "$fieldName must be no more than $max characters";
            return false;
        }
        return true;
    }
    
    /**
     * Validate password strength
     */
    public function password($value) {
        if (strlen($value) < 8) {
            $this->errors[] = "Password must be at least 8 characters";
            return false;
        }
        if (!preg_match('/[A-Z]/', $value)) {
            $this->errors[] = "Password must contain at least one uppercase letter";
            return false;
        }
        if (!preg_match('/[a-z]/', $value)) {
            $this->errors[] = "Password must contain at least one lowercase letter";
            return false;
        }
        if (!preg_match('/[0-9]/', $value)) {
            $this->errors[] = "Password must contain at least one number";
            return false;
        }
        return true;
    }
    
    /**
     * Sanitize string
     */
    public function sanitize($value) {
        return htmlspecialchars(strip_tags(trim($value)), ENT_QUOTES, 'UTF-8');
    }
    
    /**
     * Validate matches (for password confirmation)
     */
    public function matches($value1, $value2, $fieldName = 'Field') {
        if ($value1 !== $value2) {
            $this->errors[] = "$fieldName do not match";
            return false;
        }
        return true;
    }
    
    /**
     * Get all errors
     */
    public function getErrors() {
        return $this->errors;
    }
    
    /**
     * Check if validation passed
     */
    public function passes() {
        return empty($this->errors);
    }
    
    /**
     * Get first error
     */
    public function getFirstError() {
        return empty($this->errors) ? null : $this->errors[0];
    }
}
?>