<?php
session_start();
include '../include/connection.php'; // Include the database connection

// Check if the form was submitted
if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    // Validate CSRF Token
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $_SESSION['error'] = "Invalid CSRF token.";
        header("Location: insurance_companies.php");
        exit();
    }

    // Retrieve and sanitize form inputs
    $insurance_id = $_POST['insurance_id'];
    $insurance_name = trim($_POST['insurance_name']);
    $coverage_details = trim($_POST['coverage_details']);
    $email = trim($_POST['email']);
    $phonenumber = trim($_POST['phonenumber']);

    // Basic validation
    $errors = [];
    if (empty($insurance_name)) {
        $errors[] = "Insurance company name is required.";
    }
    if (empty($coverage_details)) {
        $errors[] = "Coverage details are required.";
    }
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "A valid email is required.";
    }
    if (empty($phonenumber) || !preg_match('/^([0-9]{10}|[0-9]{4})$/', $phonenumber)) {
        $errors[] = "A valid 10 or 4-digit phone number is required.";
    }

    if (!empty($errors)) {
        $_SESSION['error'] = implode("<br>", $errors);
        header("Location: insurance_companies.php");
        exit();
    }

    try {
        // Check if insurance_name is unique excluding current insurance
        $checkQuery = "SELECT insurance_id FROM insurance_companies WHERE insurance_name = :insurance_name AND insurance_id != :insurance_id";
        $stmt = $connection->prepare($checkQuery);
        $stmt->bindParam(':insurance_name', $insurance_name, PDO::PARAM_STR);
        $stmt->bindParam(':insurance_id', $insurance_id, PDO::PARAM_INT);
        $stmt->execute();
        if ($stmt->fetch()) {
            $_SESSION['error'] = "The insurance company name \"$insurance_name\" is already taken.";
            header("Location: insurance_companies.php");
            exit();
        }

        // Update the insurance company
        $updateQuery = "UPDATE insurance_companies 
                        SET insurance_name = :insurance_name, coverage_percentage = :coverage_details, email = :email, phonenumber = :phonenumber 
                        WHERE insurance_id = :insurance_id";
        $stmt = $connection->prepare($updateQuery);
        $stmt->bindParam(':insurance_name', $insurance_name, PDO::PARAM_STR);
        $stmt->bindParam(':coverage_details', $coverage_details, PDO::PARAM_STR);
        $stmt->bindParam(':email', $email, PDO::PARAM_STR);
        $stmt->bindParam(':phonenumber', $phonenumber, PDO::PARAM_STR);
        $stmt->bindParam(':insurance_id', $insurance_id, PDO::PARAM_INT);
        $stmt->execute();

        $_SESSION['success'] = "Insurance company \"$insurance_name\" updated successfully.";
        header("Location: insurance_companies.php");
        exit();

    } catch (PDOException $e) {
        // Log the error
        error_log("Database Error: " . $e->getMessage());
        $_SESSION['error'] = "An error occurred while updating the insurance company.";
        header("Location: insurance_companies.php");
        exit();
    }
} else {
    // If not a POST request, redirect back
    header("Location: insurance_companies.php");
    exit();
}
?>
