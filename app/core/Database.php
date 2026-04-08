<?php
require_once __DIR__ . '/../../includes/config.php';

class Database {
    private $host;
    private $user;
    private $pass;
    private $dbname;

    public function __construct() {
        $this->host = defined('DB_HOST') ? DB_HOST : 'localhost';
        $this->user = defined('DB_USER') ? DB_USER : 'root';
        $this->pass = defined('DB_PASS') ? DB_PASS : '';
        $this->dbname = defined('DB_NAME') ? DB_NAME : 'gambytes';
    }

    public function connect() {
        return new mysqli($this->host, $this->user, $this->pass, $this->dbname);
    }
}
