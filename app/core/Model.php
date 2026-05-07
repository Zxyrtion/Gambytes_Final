<?php
require_once __DIR__ . '/Database.php';

/**
 * Base Model Class
 * All models should extend this class
 */
class Model {
    protected $db;
    protected $conn;
    protected $table;
    
    /**
     * Constructor - Initialize database connection
     */
    public function __construct() {
        $this->db = new Database();
        $this->conn = $this->db->connect();
        
        if ($this->conn->connect_error) {
            throw new Exception('Database connection failed: ' . $this->conn->connect_error);
        }
    }
    
    /**
     * Get all records from table
     * @param string $orderBy - Order by column
     * @param string $order - ASC or DESC
     * @return array
     */
    public function getAll($orderBy = 'id', $order = 'DESC') {
        $query = "SELECT * FROM {$this->table} ORDER BY {$orderBy} {$order}";
        $result = $this->conn->query($query);
        
        if (!$result) {
            throw new Exception('Query failed: ' . $this->conn->error);
        }
        
        $data = [];
        while ($row = $result->fetch_assoc()) {
            $data[] = $row;
        }
        
        return $data;
    }
    
    /**
     * Get record by ID
     * @param int $id
     * @return array|null
     */
    public function getById($id) {
        $stmt = $this->conn->prepare("SELECT * FROM {$this->table} WHERE id = ? LIMIT 1");
        
        if (!$stmt) {
            throw new Exception('Prepare failed: ' . $this->conn->error);
        }
        
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $result = $stmt->get_result();
        $data = $result->fetch_assoc();
        $stmt->close();
        
        return $data;
    }
    
    /**
     * Insert new record
     * @param array $data - Associative array of column => value
     * @return int - Inserted ID
     */
    public function insert($data) {
        $columns = implode(', ', array_keys($data));
        $placeholders = implode(', ', array_fill(0, count($data), '?'));
        
        $query = "INSERT INTO {$this->table} ({$columns}) VALUES ({$placeholders})";
        $stmt = $this->conn->prepare($query);
        
        if (!$stmt) {
            throw new Exception('Prepare failed: ' . $this->conn->error);
        }
        
        // Bind parameters dynamically
        $types = '';
        $values = [];
        foreach ($data as $value) {
            if (is_int($value)) {
                $types .= 'i';
            } elseif (is_float($value)) {
                $types .= 'd';
            } else {
                $types .= 's';
            }
            $values[] = $value;
        }
        
        $stmt->bind_param($types, ...$values);
        
        if (!$stmt->execute()) {
            throw new Exception('Execute failed: ' . $stmt->error);
        }
        
        $insertId = $this->conn->insert_id;
        $stmt->close();
        
        return $insertId;
    }
    
    /**
     * Update record by ID
     * @param int $id
     * @param array $data - Associative array of column => value
     * @return bool
     */
    public function update($id, $data) {
        $setParts = [];
        foreach (array_keys($data) as $column) {
            $setParts[] = "{$column} = ?";
        }
        $setClause = implode(', ', $setParts);
        
        $query = "UPDATE {$this->table} SET {$setClause} WHERE id = ?";
        $stmt = $this->conn->prepare($query);
        
        if (!$stmt) {
            throw new Exception('Prepare failed: ' . $this->conn->error);
        }
        
        // Bind parameters dynamically
        $types = '';
        $values = [];
        foreach ($data as $value) {
            if (is_int($value)) {
                $types .= 'i';
            } elseif (is_float($value)) {
                $types .= 'd';
            } else {
                $types .= 's';
            }
            $values[] = $value;
        }
        $types .= 'i';
        $values[] = $id;
        
        $stmt->bind_param($types, ...$values);
        
        if (!$stmt->execute()) {
            throw new Exception('Execute failed: ' . $stmt->error);
        }
        
        $success = $stmt->affected_rows > 0;
        $stmt->close();
        
        return $success;
    }
    
    /**
     * Delete record by ID
     * @param int $id
     * @return bool
     */
    public function delete($id) {
        $stmt = $this->conn->prepare("DELETE FROM {$this->table} WHERE id = ?");
        
        if (!$stmt) {
            throw new Exception('Prepare failed: ' . $this->conn->error);
        }
        
        $stmt->bind_param('i', $id);
        
        if (!$stmt->execute()) {
            throw new Exception('Execute failed: ' . $stmt->error);
        }
        
        $success = $stmt->affected_rows > 0;
        $stmt->close();
        
        return $success;
    }
    
    /**
     * Execute custom query with prepared statement
     * @param string $query
     * @param array $params
     * @param string $types
     * @return mysqli_result|bool
     */
    protected function query($query, $params = [], $types = '') {
        $stmt = $this->conn->prepare($query);
        
        if (!$stmt) {
            throw new Exception('Prepare failed: ' . $this->conn->error);
        }
        
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        
        if (!$stmt->execute()) {
            throw new Exception('Execute failed: ' . $stmt->error);
        }
        
        $result = $stmt->get_result();
        $stmt->close();
        
        return $result;
    }
    
    /**
     * Close database connection
     */
    public function __destruct() {
        if ($this->conn) {
            $this->conn->close();
        }
    }
}
