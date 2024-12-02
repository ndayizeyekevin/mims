<?php
session_start();
require_once '../include/connection.php'; // Include your PDO connection file

// Function to sanitize input data
function sanitize_input($data) {
    return htmlspecialchars(stripslashes(trim($data)));
}

// Check if the form was submitted via POST and the register_client field is set
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Validate CSRF Token
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $_SESSION['error'] = "Invalid CSRF token.";
        header("Location: clients.php");
        exit();
    }

    // Retrieve and sanitize form inputs
    $first_name = isset($_POST['first_name']) ? sanitize_input($_POST['first_name']) : '';
    $last_name = isset($_POST['last_name']) ? sanitize_input($_POST['last_name']) : '';
    $phone_number = isset($_POST['phone_number']) ? sanitize_input($_POST['phone_number']) : '';
    $email = isset($_POST['email']) ? sanitize_input($_POST['email']) : '';
    $insurance_id = isset($_POST['insurance_id']) ? intval($_POST['insurance_id']) : 0;

    // Initialize an array to store errors
    $errors = [];

    // Validate First Name
    if (empty($first_name)) {
        $errors[] = "First name is required.";
    } elseif (strlen($first_name) > 100) {
        $errors[] = "First name should not exceed 100 characters.";
    }

    // Validate Last Name
    if (empty($last_name)) {
        $errors[] = "Last name is required.";
    } elseif (strlen($last_name) > 100) {
        $errors[] = "Last name should not exceed 100 characters.";
    }

    // Validate Phone Number
    if (empty($phone_number)) {
        $errors[] = "Phone number is required.";
    } elseif (!preg_match('/^\d{10}$/', $phone_number)) {
        $errors[] = "Phone number must be a 10-digit number.";
    }

    // Validate Email
    if (empty($email)) {
        $errors[] = "Email is required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format.";
    } elseif (strlen($email) > 255) {
        $errors[] = "Email should not exceed 255 characters.";
    }

    // Validate Insurance ID
    if ($insurance_id <= 0) {
        $errors[] = "Invalid insurance selection.";
    }

    // If there are validation errors, redirect back with errors
    if (!empty($errors)) {
        $_SESSION['error'] = implode("<br>", $errors);
        header("Location: clients.php");
        exit();
    }

    try {
        // Check if the email already exists
        $checkEmailQuery = "SELECT client_id FROM clients WHERE email = :email";
        $stmt = $connection->prepare($checkEmailQuery);
        $stmt->bindParam(':email', $email, PDO::PARAM_STR);
        $stmt->execute();

        if ($stmt->rowCount() > 0) {
            $_SESSION['error'] = "The email \"$email\" is already registered.";
            header("Location: clients.php");
            exit();
        }

        // Insert the new insurance client
        $insertQuery = "INSERT INTO clients (first_name, last_name, phone_number, email, insurance_id)
                        VALUES (:first_name, :last_name, :phone_number, :email, :insurance_id)";
        $stmt = $connection->prepare($insertQuery);
        $stmt->bindParam(':first_name', $first_name, PDO::PARAM_STR);
        $stmt->bindParam(':last_name', $last_name, PDO::PARAM_STR);
        $stmt->bindParam(':phone_number', $phone_number, PDO::PARAM_STR);
        $stmt->bindParam(':email', $email, PDO::PARAM_STR);
        $stmt->bindParam(':insurance_id', $insurance_id, PDO::PARAM_INT);

        if ($stmt->execute()) {
            $_SESSION['success'] = "Insurance client \"$first_name $last_name\" registered successfully.";
        } else {
            $_SESSION['error'] = "Failed to register the insurance client.";
        }

    } catch (PDOException $e) {
        // Log the error for debugging (do not display to the user)
        error_log("Database Error [register_client.php]: " . $e->getMessage());
        $_SESSION['error'] = "An error occurred while registering the insurance client. Please try again later.";
    }

    // Redirect back to the clients.php page
    header("Location: clients.php");
    exit();
} else {
    // If the script is accessed without POST data, redirect back
    header("Location: clients.php");
    exit();
}
?>
