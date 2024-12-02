<?php
session_start();
include '../include/connection.php'; // Include the database connection

// Check if the form was submitted
if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    // Validate CSRF Token
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $_SESSION['error'] = "Invalid CSRF token.";
        header("Location: pharmacies.php");
        exit();
    }

    // Retrieve and sanitize form inputs
    $pharmacy_name = trim($_POST['pharmacy_name']);
    $email = trim($_POST['email']);
    $phone_number = trim($_POST['phone_number']);

    // Basic validation
    $errors = [];
    if (empty($pharmacy_name)) {
        $errors[] = "Pharmacy name is required.";
    }
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "A valid email is required.";
    }
    if (empty($phone_number) || !preg_match('/^[0-9]{10}$/', $phone_number)) {
        $errors[] = "A valid 10-digit phone number is required.";
    }

    if (!empty($errors)) {
        $_SESSION['error'] = implode("<br>", $errors);
        header("Location: pharmacies.php");
        exit();
    }

    try {
        // Check if pharmacy_name, email, or phone_number already exists
        $checkQuery = "SELECT pharmacy_id FROM pharmacies WHERE pharmacy_name = :pharmacy_name OR email = :email OR phone_number = :phone_number";
        $stmt = $connection->prepare($checkQuery);
        $stmt->bindParam(':pharmacy_name', $pharmacy_name, PDO::PARAM_STR);
        $stmt->bindParam(':email', $email, PDO::PARAM_STR);
        $stmt->bindParam(':phone_number', $phone_number, PDO::PARAM_STR);
        $stmt->execute();
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($existing) {
            $_SESSION['error'] = "Pharmacy name, email, or phone number already exists.";
            header("Location: pharmacies.php");
            exit();
        }

        // Insert the new pharmacy
        $insertQuery = "INSERT INTO pharmacies (pharmacy_name, email, phone_number) VALUES (:pharmacy_name, :email, :phone_number)";
        $stmt = $connection->prepare($insertQuery);
        $stmt->bindParam(':pharmacy_name', $pharmacy_name, PDO::PARAM_STR);
        $stmt->bindParam(':email', $email, PDO::PARAM_STR);
        $stmt->bindParam(':phone_number', $phone_number, PDO::PARAM_STR);
        $stmt->execute();

        $_SESSION['success'] = "Pharmacy \"$pharmacy_name\" registered successfully.";
        header("Location: pharmacies.php");
        exit();

    } catch (PDOException $e) {
        // Log the error
        error_log("Database Error: " . $e->getMessage());
        $_SESSION['error'] = "An error occurred while registering the pharmacy.";
        header("Location: pharmacies.php");
        exit();
    }
} else {
    // If not a POST request, redirect back
    header("Location: pharmacies.php");
    exit();
}
?>
