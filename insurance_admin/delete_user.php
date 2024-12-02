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
        header("Location: users.php.php"); // Redirect back to the admin page
        exit();
    }

    // Retrieve and sanitize form inputs
    $user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;

    // Initialize an array to store errors
    $errors = [];

    // Validate User ID
    if ($user_id <= 0) {
        $errors[] = "Invalid User ID.";
    }

    // If there are validation errors, redirect back with errors
    if (!empty($errors)) {
        $_SESSION['error'] = implode("<br>", $errors);
        header("Location: users.php.php");
        exit();
    }

    try {
        // Fetch the user to get the username for confirmation
        $fetchQuery = "SELECT username FROM insurance_users WHERE insurance_user_id = :user_id";
        $stmt = $connection->prepare($fetchQuery);
        $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        $stmt->execute();
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            $_SESSION['error'] = "User not found.";
            header("Location: users.php.php");
            exit();
        }

        // Delete the user
        $deleteQuery = "DELETE FROM insurance_users WHERE insurance_user_id = :user_id";
        $stmt = $connection->prepare($deleteQuery);
        $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        $stmt->execute();

        $_SESSION['success'] = "User \"" . htmlspecialchars($user['username']) . "\" deleted successfully.";
        header("Location: users.php.php");
        exit();

    } catch (PDOException $e) {
        // Log the error
        error_log("Database Error [delete_insurance_user.php]: " . $e->getMessage());
        $_SESSION['error'] = "An error occurred while deleting the user.";
        header("Location: users.php.php");
        exit();
    }

} else {
    // If accessed without POST data, redirect back
    header("Location: insurance_admin.php");
    exit();
}
?>
