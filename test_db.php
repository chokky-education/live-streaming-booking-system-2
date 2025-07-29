<?php
/**
 * Test Database Connection
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>Testing Database Connection</h2>";

try {
    // Test basic connection
    $pdo = new PDO(
        "mysql:host=localhost;charset=utf8mb4",
        "root",
        "",
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );
    echo "<p style='color: green;'>✓ MySQL connection successful</p>";
    
    // Test database exists
    $stmt = $pdo->query("SHOW DATABASES LIKE 'live_streaming_booking'");
    if ($stmt->rowCount() > 0) {
        echo "<p style='color: green;'>✓ Database 'live_streaming_booking' exists</p>";
        
        // Test connection to specific database
        $pdo_db = new PDO(
            "mysql:host=localhost;dbname=live_streaming_booking;charset=utf8mb4",
            "root",
            "",
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]
        );
        echo "<p style='color: green;'>✓ Connection to live_streaming_booking successful</p>";
        
        // Test tables exist
        $tables = ['users', 'packages', 'bookings', 'payments', 'equipment'];
        foreach ($tables as $table) {
            $stmt = $pdo_db->query("SHOW TABLES LIKE '$table'");
            if ($stmt->rowCount() > 0) {
                echo "<p style='color: green;'>✓ Table '$table' exists</p>";
            } else {
                echo "<p style='color: red;'>✗ Table '$table' missing</p>";
            }
        }
        
        // Test admin user exists
        $stmt = $pdo_db->prepare("SELECT * FROM users WHERE username = 'admin'");
        $stmt->execute();
        if ($stmt->rowCount() > 0) {
            echo "<p style='color: green;'>✓ Admin user exists</p>";
        } else {
            echo "<p style='color: red;'>✗ Admin user missing</p>";
        }
        
    } else {
        echo "<p style='color: red;'>✗ Database 'live_streaming_booking' does not exist</p>";
    }
    
} catch (PDOException $e) {
    echo "<p style='color: red;'>✗ Database error: " . $e->getMessage() . "</p>";
} catch (Exception $e) {
    echo "<p style='color: red;'>✗ General error: " . $e->getMessage() . "</p>";
}

echo "<hr>";
echo "<h3>PHP Info:</h3>";
echo "<p>PHP Version: " . phpversion() . "</p>";
echo "<p>PDO MySQL Available: " . (extension_loaded('pdo_mysql') ? 'Yes' : 'No') . "</p>";
?>