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
    $user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
    $first_name = sanitize_input($_POST['first_name']);
    $last_name = sanitize_input($_POST['last_name']);
    $username = sanitize_input($_POST['username']); // Read-only, but still sanitize
    $email = sanitize_input($_POST['email']);
    $role = sanitize_input($_POST['role']);
    $insurance_id = $_SESSION['insurance_id'];

    // Initialize an array to store errors
    $errors = [];

    // Validate inputs
    if ($user_id <= 0) {
        $errors[] = "Invalid User ID.";
    }
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
        // Check if the user exists
        $checkExistQuery = "SELECT insurance_user_id FROM insurance_users WHERE insurance_user_id = :user_id";
        $stmt = $connection->prepare($checkExistQuery);
        $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        $stmt->execute();

        if ($stmt->rowCount() == 0) {
            $_SESSION['error'] = "User not found.";
            header("Location: users.php");
            exit();
        }

        // Check for uniqueness of email excluding the current user
        $checkUniqueQuery = "SELECT insurance_user_id FROM insurance_users WHERE email = :email AND insurance_user_id != :user_id";
        $stmt = $connection->prepare($checkUniqueQuery);
        $stmt->bindParam(':email', $email, PDO::PARAM_STR);
        $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        $stmt->execute();

        if ($stmt->rowCount() > 0) {
            $_SESSION['error'] = "The email \"$email\" is already taken by another user.";
            header("Location: users.php");
            exit();
        }

        // Update the user details
        $updateQuery = "UPDATE insurance_users 
                        SET first_name = :first_name, 
                            last_name = :last_name, 
                            email = :email, 
                            role = :role, 
                            insurance_id = :insurance_id 
                        WHERE insurance_user_id = :user_id";
        $stmt = $connection->prepare($updateQuery);
        $stmt->bindParam(':first_name', $first_name, PDO::PARAM_STR);
        $stmt->bindParam(':last_name', $last_name, PDO::PARAM_STR);
        $stmt->bindParam(':email', $email, PDO::PARAM_STR);
        $stmt->bindParam(':role', $role, PDO::PARAM_STR);
        $stmt->bindParam(':insurance_id', $insurance_id, PDO::PARAM_INT);
        $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        $stmt->execute();

        $_SESSION['success'] = "User \"$username\" updated successfully.";
        header("Location: users.php");
        exit();

    } catch (PDOException $e) {
        // Log the error
        error_log("Database Error [edit_insurance_user.php]: " . $e->getMessage());
        $_SESSION['error'] = "An error occurred while updating the user.";
        header("Location: users.php");
        exit();
    }

} else {
    // If accessed without POST data, redirect back
    header("Location: users.php");
    exit();
}
?>
