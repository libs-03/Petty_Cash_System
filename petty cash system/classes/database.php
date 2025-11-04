<?php
class Database {
    private $host = "localhost";
    private $db_name = "petty_cash_db";
    private $username = "root";
    private $password = "";
    public $conn;

    public function connect() {
        $this->conn = null;

        try {
            $this->conn = new PDO("mysql:host=" . $this->host . ";dbname=" . $this->db_name,
                                  $this->username, $this->password);
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch (PDOException $e) {
            echo "Database connection error: " . $e->getMessage();
        }

        return $this->conn;
    }

    public function createTables() {
        $conn = $this->connect();

        // Create database if not exists
        $conn->exec("CREATE DATABASE IF NOT EXISTS petty_cash_db");
        $conn->exec("USE petty_cash_db");

        // Create tables only if they don't exist
        $sql = "CREATE TABLE IF NOT EXISTS users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(50) UNIQUE NOT NULL,
            password VARCHAR(255) NOT NULL,
            role ENUM('admin', 'employee') NOT NULL,
            name VARCHAR(100) NOT NULL,
            department VARCHAR(100),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )";

        $conn->exec($sql);

        $sql = "CREATE TABLE IF NOT EXISTS petty_cash_requests (
            id INT AUTO_INCREMENT PRIMARY KEY,
            request_id VARCHAR(10) UNIQUE NOT NULL,
            user_id INT NOT NULL,
            employee_name VARCHAR(100) NOT NULL,
            department VARCHAR(100) NOT NULL,
            amount_requested DECIMAL(10,2) NOT NULL,
            purpose TEXT NOT NULL,
            expense_category VARCHAR(100) NOT NULL,
            justification TEXT NOT NULL,
            breakdown TEXT NOT NULL,
            date_requested DATE NOT NULL,
            status ENUM('Pending', 'Approved', 'Rejected', 'Liquidated') DEFAULT 'Pending',
            actual_expenses DECIMAL(10,2) NULL,
            total_spent DECIMAL(10,2) NULL,
            receipts TEXT NULL,
            refund_needed DECIMAL(10,2) NULL,
            reimbursement DECIMAL(10,2) NULL,
            liquidation_status ENUM('Not Submitted', 'Pending', 'Approved', 'Rejected') DEFAULT 'Not Submitted',
            date_liquidated DATE NULL,
            FOREIGN KEY (user_id) REFERENCES users(id)
        )";

        $conn->exec($sql);

        $sql = "CREATE TABLE IF NOT EXISTS liquidation_expenses (
            id INT AUTO_INCREMENT PRIMARY KEY,
            request_id VARCHAR(10) NOT NULL,
            description VARCHAR(255) NOT NULL,
            amount DECIMAL(10,2) NOT NULL,
            FOREIGN KEY (request_id) REFERENCES petty_cash_requests(request_id) ON DELETE CASCADE
        )";

        $conn->exec($sql);

        // Insert admin user only if not exists
        $stmt = $conn->prepare("SELECT COUNT(*) FROM users WHERE username = 'admin'");
        $stmt->execute();
        if ($stmt->fetchColumn() == 0) {
            $stmt = $conn->prepare("INSERT INTO users (username, password, role, name, department) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute(['admin', password_hash('admin123', PASSWORD_DEFAULT), 'admin', 'Administrator', 'Admin']);
        }

        // Insert employee users only if not exist
        $employees = [
            ['employee1', 'Employee One', 'IT'],
            ['employee2', 'Employee Two', 'HR'],
            ['employee3', 'Employee Three', 'Finance']
        ];

        foreach ($employees as $emp) {
            $stmt = $conn->prepare("SELECT COUNT(*) FROM users WHERE username = ?");
            $stmt->execute([$emp[0]]);
            if ($stmt->fetchColumn() == 0) {
                $stmt = $conn->prepare("INSERT INTO users (username, password, role, name, department) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$emp[0], password_hash('password', PASSWORD_DEFAULT), 'employee', $emp[1], $emp[2]]);
            }
        }
    }
}
?>
