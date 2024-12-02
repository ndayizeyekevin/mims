<?php
session_start();
include '../include/connection.php'; // Include the database connection

// Check if the form was submitted
if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    // Validate CSRF Token
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $_SESSION['error'] = "Invalid CSRF token.";
        header("Location: pharmacy_admin.php");
        exit();
    }

    // Retrieve and sanitize form inputs
    $first_name = trim($_POST['first_name']);
    $last_name = trim($_POST['last_name']);
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $password = trim($_POST['password']);
    $role = trim($_POST['role']);

    // Basic validation
    $errors = [];
    if (empty($first_name)) {
        $errors[] = "First name is required.";
    }
    if (empty($last_name)) {
        $errors[] = "Last name is required.";
    }
    if (empty($username) || strlen($username) < 5) {
        $errors[] = "Username is required and must be at least 5 characters.";
    }
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "A valid email is required.";
    }
    if (empty($password) || strlen($password) < 8) {
        $errors[] = "Password is required and must be at least 8 characters.";
    }
    if (empty($role) || !in_array($role, ['pharmacy_admin', 'pharmacist'])) {
        $errors[] = "A valid role is required.";
    }

    if (!empty($errors)) {
        $_SESSION['error'] = implode("<br>", $errors);
        header("Location: pharmacy_admin.php");
        exit();
    }

    try {
        // Check if username or email already exists
        $checkQuery = "SELECT pharmacy_user_id FROM pharmacy_users WHERE username = :username OR email = :email";
        $stmt = $connection->prepare($checkQuery);
        $stmt->bindParam(':username', $username, PDO::PARAM_STR);
        $stmt->bindParam(':email', $email, PDO::PARAM_STR);
        $stmt->execute();
        if ($stmt->fetch()) {
            $_SESSION['error'] = "Username or email already exists.";
            header("Location: pharmacy_admin.php");
            exit();
        }

        // Hash the password
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);

        // Insert the new user
        $insertQuery = "INSERT INTO pharmacy_users (first_name, last_name, username, email, password, role, pharmacy_id) 
                        VALUES (:first_name, :last_name, :username, :email, :password, :role, :pharmacy_id)";
        $stmt = $connection->prepare($insertQuery);
        $stmt->bindParam(':first_name', $first_name, PDO::PARAM_STR);
        $stmt->bindParam(':last_name', $last_name, PDO::PARAM_STR);
        $stmt->bindParam(':username', $username, PDO::PARAM_STR);
        $stmt->bindParam(':email', $email, PDO::PARAM_STR);
        $stmt->bindParam(':password', $hashed_password, PDO::PARAM_STR);
        $stmt->bindParam(':role', $role, PDO::PARAM_STR);
        $stmt->bindParam(':pharmacy_id', $_SESSION['pharmacy_id'], PDO::PARAM_INT);
        $stmt->execute();

        $_SESSION['success'] = "User \"$username\" registered successfully.";
        header("Location: index.php");
        exit();

    } catch (PDOException $e) {
        // Log the error
        error_log("Database Error: " . $e->getMessage());
        $_SESSION['error'] = "An error occurred while registering the user.";
        header("Location: users.php");
        exit();
    }
} else {
    // If not a POST request, redirect back
    header("Location: users.php");
    exit();
}
?>
