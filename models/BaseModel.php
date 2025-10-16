<?php
/**
 * Base Model Class
 * ระบบจองอุปกรณ์ Live Streaming
 *
 * This class provides common functionality for all models
 */

abstract class BaseModel {
    protected $conn;
    protected $table_name;
    protected $primary_key = 'id';

    /**
     * Constructor
     */
    public function __construct($db) {
        $this->conn = $db;
    }

    /**
     * Get database connection
     */
    protected function getConnection() {
        return $this->conn;
    }

    /**
     * Begin transaction
     */
    protected function beginTransaction() {
        return $this->conn->beginTransaction();
    }

    /**
     * Commit transaction
     */
    protected function commit() {
        return $this->conn->commit();
    }

    /**
     * Rollback transaction
     */
    protected function rollback() {
        return $this->conn->rollBack();
    }

    /**
     * Execute query with parameters
     */
    protected function executeQuery($query, $params = []) {
        $stmt = $this->conn->prepare($query);

        foreach ($params as $key => $value) {
            $param_type = PDO::PARAM_STR;
            if (is_int($value)) {
                $param_type = PDO::PARAM_INT;
            } elseif (is_bool($value)) {
                $param_type = PDO::PARAM_BOOL;
            } elseif (is_null($value)) {
                $param_type = PDO::PARAM_NULL;
            }

            if (is_int($key)) {
                $stmt->bindValue($key + 1, $value, $param_type);
            } else {
                $stmt->bindValue($key, $value, $param_type);
            }
        }

        $stmt->execute();
        return $stmt;
    }

    /**
     * Get record by ID
     */
    public function findById($id) {
        $query = "SELECT * FROM " . $this->table_name . " WHERE " . $this->primary_key . " = :id LIMIT 1";
        $stmt = $this->executeQuery($query, [':id' => $id]);

        if ($stmt->rowCount() > 0) {
            return $stmt->fetch(PDO::FETCH_ASSOC);
        }

        return false;
    }

    /**
     * Get all records
     */
    public function findAll($limit = 50, $offset = 0, $order_by = 'created_at', $order = 'DESC') {
        $query = "SELECT * FROM " . $this->table_name . "
                  ORDER BY " . $order_by . " " . $order . "
                  LIMIT :limit OFFSET :offset";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":limit", $limit, PDO::PARAM_INT);
        $stmt->bindParam(":offset", $offset, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Count records
     */
    public function count($where = '') {
        $query = "SELECT COUNT(*) as total FROM " . $this->table_name;
        if (!empty($where)) {
            $query .= " WHERE " . $where;
        }

        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return (int)$result['total'];
    }

    /**
     * Delete record by ID
     */
    public function delete($id) {
        $query = "DELETE FROM " . $this->table_name . " WHERE " . $this->primary_key . " = :id";
        $stmt = $this->executeQuery($query, [':id' => $id]);

        return $stmt->rowCount() > 0;
    }

    /**
     * Update record by ID
     */
    public function update($id, $data) {
        $set_parts = [];
        $params = [':id' => $id];

        foreach ($data as $key => $value) {
            $set_parts[] = $key . " = :" . $key;
            $params[':' . $key] = $value;
        }

        $query = "UPDATE " . $this->table_name . " SET " . implode(', ', $set_parts) . "
                  WHERE " . $this->primary_key . " = :id";

        $stmt = $this->executeQuery($query, $params);
        return $stmt->rowCount() > 0;
    }

    /**
     * Sanitize input value
     */
    protected function sanitize($value) {
        if (function_exists('sanitize_input')) {
            return sanitize_input($value);
        }
        return htmlspecialchars(strip_tags(trim($value)), ENT_QUOTES, 'UTF-8');
    }

    /**
     * Log error with context
     */
    protected function logError($message, $context = []) {
        if (function_exists('log_event')) {
            log_event($message, 'ERROR');
        } else {
            error_log($message);
        }
    }

    /**
     * Validate required fields
     */
    protected function validateRequired($data, $required_fields) {
        $errors = [];

        foreach ($required_fields as $field) {
            if (!isset($data[$field]) || empty(trim($data[$field]))) {
                $errors[] = "Field {$field} is required";
            }
        }

        return $errors;
    }

    /**
     * Get last insert ID
     */
    protected function lastInsertId() {
        return $this->conn->lastInsertId();
    }
}
?>