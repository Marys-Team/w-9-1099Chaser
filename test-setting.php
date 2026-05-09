<?php
// Test script to verify the setting save functionality
require_once('wp-config.php');

// Test database connection
try {
    $pdo = new PDO('mysql:host=' . DB_HOST . ';dbname=' . DB_NAME, DB_USER, DB_PASSWORD);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "Database connected successfully<br>";
    
    // Check current setting
    $stmt = $pdo->prepare("SELECT option_value FROM wp_options WHERE option_name = ?");
    $stmt->execute(['w91099ch_allow_earn_reward_download']);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($result) {
        echo "Current setting value: " . $result['option_value'] . "<br>";
    } else {
        echo "Setting not found in database<br>";
    }
    
    // Test update
    $stmt = $pdo->prepare("INSERT INTO wp_options (option_name, option_value, autoload) VALUES (?, ?, 'yes') ON DUPLICATE KEY UPDATE option_value = VALUES(option_value)");
    $stmt->execute(['w91099ch_allow_earn_reward_download', '1']);
    
    echo "Setting updated to 1<br>";
    
    // Verify update
    $stmt->execute(['w91099ch_allow_earn_reward_download']);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "New setting value: " . $result['option_value'] . "<br>";
    
} catch (PDOException $e) {
    echo "Database error: " . $e->getMessage() . "<br>";
}
?>
