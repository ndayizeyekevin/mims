<?php
session_start();
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
        header("Location: users.php"); // Redirect back to the admin page
        exit();
    }

    // Retrieve and sanitize form inputs
    $first_name = sanitize_input($_POST['first_name']);
    $last_name = sanitize_input($_POST['last_name']);
    $username = sanitize_input($_POST['username']);
    $email = sanitize_input($_POST['email']);
    $password = $_POST['password'];
    $role = sanitize_input($_POST['role']);
    $insurance_id = $_SESSION['insurance_id'];
    

    // Initialize an array to store errors
    $errors = [];

    // Validate inputs
    if (empty($first_name)) {
        $errors[] = "First name is required.";
    }
    if (empty($last_name)) {
        $errors[] = "Last name is required.";
    }
    if (empty($username)) {
        $errors[] = "Username is required.";
    }
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "A valid email is required.";
    }
    if (empty($password) || strlen($password) < 8) {
        $errors[] = "Password must be at least 8 characters.";
    }
    if (empty($role) || !in_array($role, ['insurance_admin', 'insurance_employee'])) {
        $errors[] = "Invalid role selected.";
    }
    if ($insurance_id <= 0) {
        $errors[] = "Invalid insurance company selected.";
    }

    // If there are validation errors, redirect back with errors
    if (!empty($errors)) {
        $_SESSION['error'] = implode("<br>", $errors);
        header("Location: users.php");
        exit();
    }

    try {
        // Check if username or email already exists
        $checkQuery = "SELECT insurance_user_id FROM insurance_users WHERE username = :username OR email = :email";
        $stmt = $connection->prepare($checkQuery);
        $stmt->bindParam(':username', $username, PDO::PARAM_STR);
        $stmt->bindParam(':email', $email, PDO::PARAM_STR);
        $stmt->execute();

        if ($stmt->fetch()) {
            $_SESSION['error'] = "Username or email already exists. Please choose a different one.";
            header("Location: users.php");
            exit();
        }

        // Hash the password
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);

        // Insert the new user
        $insertQuery = "INSERT INTO insurance_users (first_name, last_name, username, email, password, role, insurance_id)
                        VALUES (:first_name, :last_name, :username, :email, :password, :role, :insurance_id)";
        $stmt = $connection->prepare($insertQuery);
        $stmt->bindParam(':first_name', $first_name, PDO::PARAM_STR);
        $stmt->bindParam(':last_name', $last_name, PDO::PARAM_STR);
        $stmt->bindParam(':username', $username, PDO::PARAM_STR);
        $stmt->bindParam(':email', $email, PDO::PARAM_STR);
        $stmt->bindParam(':password', $hashed_password, PDO::PARAM_STR);
        $stmt->bindParam(':role', $role, PDO::PARAM_STR);
        $stmt->bindParam(':insurance_id', $insurance_id, PDO::PARAM_INT);
        $stmt->execute();

        $_SESSION['success'] = "Insurance user \"$username\" registered successfully.";
        header("Location: users.php");
        exit();

    } catch (PDOException $e) {
        // Log the error
        error_log("Database Error [register_insurance_user.php]: " . $e->getMessage());
        $_SESSION['error'] = "An error occurred while registering the user.";
        header("Location: users.php");
        exit();
    }

} else {
    // If accessed without POST data, redirect back
    header("Location: users.php");
    exit();
}
?>
