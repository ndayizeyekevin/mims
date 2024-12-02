<?php
session_start();
include '../include/connection.php'; // Include the database connection

// Check if the user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id']; // Assuming user_id is stored in session when logged in
$error = $success = "";

// Handle password update form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $current_password = trim($_POST['current_password']);
    $new_password = trim($_POST['new_password']);
    $confirm_password = trim($_POST['confirm_password']);

    // Validate inputs
    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        $error = "All fields are required.";
    } elseif ($new_password !== $confirm_password) {
        $error = "New password and confirmation do not match.";
    } else {
        try {
            // Fetch the user's current password from the database
            $stmt = $connection->prepare("SELECT password FROM users WHERE user_id = :user_id");
            $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
            $stmt->execute();
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user && password_verify($current_password, $user['password'])) {
                // Password is correct, now update the password
                $hashed_new_password = password_hash($new_password, PASSWORD_DEFAULT);
                $stmt_update = $connection->prepare("UPDATE users SET password = :new_password WHERE user_id = :user_id");
                $stmt_update->bindParam(':new_password', $hashed_new_password, PDO::PARAM_STR);
                $stmt_update->bindParam(':user_id', $user_id, PDO::PARAM_INT);
                if ($stmt_update->execute()) {
                    $success = "Password successfully updated!";
                } else {
                    $error = "An error occurred while updating the password.";
                }
            } else {
                $error = "Current password is incorrect.";
            }
        } catch (PDOException $e) {
            $error = "An error occurred: " . $e->getMessage();
        }
    }
}
?>