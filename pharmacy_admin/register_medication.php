<?php
session_start();
include '../include/connection.php'; // Include the database connection

// Check if the form was submitted
if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    // Validate CSRF Token
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $_SESSION['error'] = "Invalid CSRF token.";
        header("Location: ../login.php");
        exit();
    }

    // Retrieve and sanitize form inputs
    $medication_name = trim($_POST['medication_name']);
    $description = trim($_POST['description']);
    $insurance_coverage = isset($_POST['insurance_coverage']) ? (int)$_POST['insurance_coverage'] : 0;
    $unit_price = trim($_POST['unit_price']);

    // Basic validation
    $errors = [];
    if (empty($medication_name)) {
        $errors[] = "Medication name is required.";
    }
    if (empty($description)) {
        $errors[] = "Description is required.";
    }
    if (!in_array($insurance_coverage, [0,1])) {
        $errors[] = "Invalid insurance coverage value.";
    }
    if (empty($unit_price) || !is_numeric($unit_price) || $unit_price < 0) {
        $errors[] = "A valid unit price is required.";
    }

    if (!empty($errors)) {
        $_SESSION['error'] = implode("<br>", $errors);
        header("Location: ../login.php");
        exit();
    }

    try {
        // Check if medication_name already exists
        $checkQuery = "SELECT medication_id FROM medications WHERE medication_name = :medication_name";
        $stmt = $connection->prepare($checkQuery);
        $stmt->bindParam(':medication_name', $medication_name, PDO::PARAM_STR);
        $stmt->execute();
        if ($stmt->fetch()) {
            $_SESSION['error'] = "Medication name already exists.";
            header("Location: ./users.php");
            exit();
        }

        // Insert the new medication
        $insertQuery = "INSERT INTO medications (medication_name, description, insurance_coverage, unit_price) 
                        VALUES (:medication_name, :description, :insurance_coverage, :unit_price)";
        $stmt = $connection->prepare($insertQuery);
        $stmt->bindParam(':medication_name', $medication_name, PDO::PARAM_STR);
        $stmt->bindParam(':description', $description, PDO::PARAM_STR);
        $stmt->bindParam(':insurance_coverage', $insurance_coverage, PDO::PARAM_INT);
        $stmt->bindParam(':unit_price', $unit_price, PDO::PARAM_STR);
        $stmt->execute();

        $_SESSION['success'] = "Medication \"$medication_name\" registered successfully.";
        header("Location: ./users.php");
        exit();

    } catch (PDOException $e) {
        // Log the error
        error_log("Database Error: " . $e->getMessage());
        $_SESSION['error'] = "An error occurred while registering the medication.";
        header("Location: ./users.php");
        exit();
    }
} else {
    // If not a POST request, redirect back
    header("Location: ./users.php");
    exit();
}
?>
