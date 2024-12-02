<?php
session_start();

// Include the database connection file
include '../include/connection.php'; // Adjust the path as needed

// Function to sanitize input data
function sanitize_input($data) {
    return htmlspecialchars(stripslashes(trim($data)));
}

// Check if the form was submitted via POST
if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    // Validate CSRF Token
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $_SESSION['error'] = "Invalid CSRF token.";
        header("Location: login.php"); // Adjust the redirect as needed
        exit();
    }

    // Retrieve and sanitize form inputs
    $medication_id = isset($_POST['medication_id']) ? intval($_POST['medication_id']) : 0;
    $medication_name = isset($_POST['medication_name']) ? sanitize_input($_POST['medication_name']) : '';
    $description = isset($_POST['description']) ? sanitize_input($_POST['description']) : '';
    $insurance_coverage = isset($_POST['insurance_coverage']) ? intval($_POST['insurance_coverage']) : null;
    $unit_price = isset($_POST['unit_price']) ? floatval($_POST['unit_price']) : null;

    // Initialize an array to store errors
    $errors = [];

    // Validate Medication ID
    if ($medication_id <= 0) {
        $errors[] = "Invalid Medication ID.";
    }

    // Validate Medication Name
    if (empty($medication_name)) {
        $errors[] = "Medication name is required.";
    } elseif (strlen($medication_name) > 100) {
        $errors[] = "Medication name should not exceed 100 characters.";
    }

    // Validate Description
    if (empty($description)) {
        $errors[] = "Description is required.";
    }

    // Validate Insurance Coverage
    if (!in_array($insurance_coverage, [0, 1], true)) {
        $errors[] = "Invalid value for Insurance Coverage.";
    }

    // Validate Unit Price
    if ($unit_price === null || $unit_price < 0) {
        $errors[] = "Unit price must be a positive number.";
    }

    // If there are validation errors, redirect back with errors
    if (!empty($errors)) {
        $_SESSION['error'] = implode("<br>", $errors);
        header("Location: users.php"); // Adjust the redirect as needed
        exit();
    }

    try {
        // Check if the medication exists
        $checkExistQuery = "SELECT medication_id FROM medications WHERE medication_id = :medication_id";
        $stmt = $connection->prepare($checkExistQuery);
        $stmt->bindParam(':medication_id', $medication_id, PDO::PARAM_INT);
        $stmt->execute();

        if ($stmt->rowCount() == 0) {
            $_SESSION['error'] = "Medication not found.";
            header("Location: users.php"); // Adjust the redirect as needed
            exit();
        }

        // Check for uniqueness of medication_name excluding the current medication
        $checkUniqueQuery = "SELECT medication_id FROM medications WHERE medication_name = :medication_name AND medication_id != :medication_id";
        $stmt = $connection->prepare($checkUniqueQuery);
        $stmt->bindParam(':medication_name', $medication_name, PDO::PARAM_STR);
        $stmt->bindParam(':medication_id', $medication_id, PDO::PARAM_INT);
        $stmt->execute();

        if ($stmt->rowCount() > 0) {
            $_SESSION['error'] = "The medication name \"$medication_name\" is already in use. Please choose a different name.";
            header("Location: users.php"); // Adjust the redirect as needed
            exit();
        }

        // Update the medication details
        $updateQuery = "UPDATE medications 
                        SET medication_name = :medication_name, 
                            description = :description, 
                            insurance_coverage = :insurance_coverage, 
                            unit_price = :unit_price 
                        WHERE medication_id = :medication_id";
        $stmt = $connection->prepare($updateQuery);
        $stmt->bindParam(':medication_name', $medication_name, PDO::PARAM_STR);
        $stmt->bindParam(':description', $description, PDO::PARAM_STR);
        $stmt->bindParam(':insurance_coverage', $insurance_coverage, PDO::PARAM_INT);
        $stmt->bindParam(':unit_price', $unit_price, PDO::PARAM_STR); // Using PARAM_STR for DECIMAL
        $stmt->bindParam(':medication_id', $medication_id, PDO::PARAM_INT);

        if ($stmt->execute()) {
            $_SESSION['success'] = "Medication \"$medication_name\" updated successfully.";
        } else {
            $_SESSION['error'] = "Error: Medication could not be updated.";
        }

    } catch (PDOException $e) {
        // Log the error message to a file or monitoring system
        error_log("Database Error [edit_medication.php]: " . $e->getMessage());

        // Set a generic error message for the user
        $_SESSION['error'] = "An error occurred while updating the medication. Please try again later.";
    }

    // Redirect back to the pharmacy admin page
    header("Location: users.php"); // Adjust the redirect as needed
    exit();
} else {
    // If accessed without POST data, redirect back
    header("Location: users.php"); // Adjust the redirect as needed
    exit();
}
?>
