<?php
session_start();
include '../include/connection.php'; // Include the database connection

// Check if the form was submitted
if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    // Validate CSRF Token
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $_SESSION['error'] = "Invalid CSRF token.";
        header("Location: users.php");
        exit();
    }

    // Retrieve and sanitize form inputs
    $pharmacy_user_id = $_POST['pharmacy_user_id'];
    $first_name = trim($_POST['first_name']);
    $last_name = trim($_POST['last_name']);
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $role = trim($_POST['role']);

    // Basic validation
    $errors = [];
    if (empty($pharmacy_user_id)) {
        $errors[] = "Invalid user ID.";
    }
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
    if (empty($role) || !in_array($role, ['pharmacy_admin', 'pharmacist'])) {
        $errors[] = "A valid role is required.";
    }

    if (!empty($errors)) {
        $_SESSION['error'] = implode("<br>", $errors);
        header("Location: users.php");
        exit();
    }

    try {
        // Check if username or email already exists excluding the current user
        $checkQuery = "SELECT pharmacy_user_id FROM pharmacy_users WHERE (username = :username OR email = :email) AND pharmacy_user_id != :pharmacy_user_id";
        $stmt = $connection->prepare($checkQuery);
        $stmt->bindParam(':username', $username, PDO::PARAM_STR);
        $stmt->bindParam(':email', $email, PDO::PARAM_STR);
        $stmt->bindParam(':pharmacy_user_id', $pharmacy_user_id, PDO::PARAM_INT);
        $stmt->execute();
        if ($stmt->fetch()) {
            $_SESSION['error'] = "Username or email already exists.";
            header("Location: users.php");
            exit();
        }

        // Update the user
        $updateQuery = "UPDATE pharmacy_users 
                        SET first_name = :first_name, last_name = :last_name, username = :username, email = :email, role = :role 
                        WHERE pharmacy_user_id = :pharmacy_user_id";
        $stmt = $connection->prepare($updateQuery);
        $stmt->bindParam(':first_name', $first_name, PDO::PARAM_STR);
        $stmt->bindParam(':last_name', $last_name, PDO::PARAM_STR);
        $stmt->bindParam(':username', $username, PDO::PARAM_STR);
        $stmt->bindParam(':email', $email, PDO::PARAM_STR);
        $stmt->bindParam(':role', $role, PDO::PARAM_STR);
        $stmt->bindParam(':pharmacy_user_id', $pharmacy_user_id, PDO::PARAM_INT);
        $stmt->execute();

        $_SESSION['success'] = "User \"$username\" updated successfully.";
        header("Location: users.php");
        exit();

    } catch (PDOException $e) {
        // Log the error
        error_log("Database Error: " . $e->getMessage());
        $_SESSION['error'] = "An error occurred while updating the user.";
        header("Location: users.php");
        exit();
    }
} else {
    // If not a POST request, redirect back
    header("Location: users.php");
    exit();
}
?>
