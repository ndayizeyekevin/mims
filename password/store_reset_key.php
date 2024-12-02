<?php
// Database connection details
require '../include/connection.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get the raw POST data
    $data = json_decode(file_get_contents('php://input'), true);
    $email = $data['email'];
    $resetKey = $data['resetKey'];

    try {
        // Prepare SQL to update the reset_key for the users
        $sqlUsers = "UPDATE users SET reset_key = :resetKey WHERE email = :email";
        $sqlPharmacyUsers = "UPDATE pharmacy_users SET reset_key = :resetKey WHERE email = :email";
        $sqlInsuranceUsers = "UPDATE insurance_users SET reset_key = :resetKey WHERE email = :email";

        // Start a transaction
        $connection->beginTransaction();

        // Update for the users table
        $stmt = $connection->prepare($sqlUsers);
        $stmt->bindParam(':resetKey', $resetKey, PDO::PARAM_STR);
        $stmt->bindParam(':email', $email, PDO::PARAM_STR);
        $stmt->execute();

        // Update for the pharmacy_users table
        $stmt = $connection->prepare($sqlPharmacyUsers);
        $stmt->bindParam(':resetKey', $resetKey, PDO::PARAM_STR);
        $stmt->bindParam(':email', $email, PDO::PARAM_STR);
        $stmt->execute();

        // Update for the insurance_users table
        $stmt = $connection->prepare($sqlInsuranceUsers);
        $stmt->bindParam(':resetKey', $resetKey, PDO::PARAM_STR);
        $stmt->bindParam(':email', $email, PDO::PARAM_STR);
        $stmt->execute();

        // Commit the transaction
        $connection->commit();
        
        echo "Reset key updated successfully.";
    } catch (PDOException $e) {
        // Rollback the transaction on error
        $connection->rollBack();
        echo "Failed to update reset key: " . $e->getMessage();
    }
}
?>
