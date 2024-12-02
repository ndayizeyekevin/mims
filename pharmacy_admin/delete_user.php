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

    // Basic validation
    if (empty($pharmacy_user_id)) {
        $_SESSION['error'] = "Invalid user ID.";
        header("Location: users.php");
        exit();
    }

    try {
        // Fetch the user to get the username for the success message
        $fetchQuery = "SELECT username FROM pharmacy_users WHERE pharmacy_user_id = :pharmacy_user_id";
        $stmt = $connection->prepare($fetchQuery);
        $stmt->bindParam(':pharmacy_user_id', $pharmacy_user_id, PDO::PARAM_INT);
        $stmt->execute();
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user) {
            $username = $user['username'];

            // Delete the user
            $deleteQuery = "DELETE FROM pharmacy_users WHERE pharmacy_user_id = :pharmacy_user_id";
            $stmt = $connection->prepare($deleteQuery);
            $stmt->bindParam(':pharmacy_user_id', $pharmacy_user_id, PDO::PARAM_INT);
            $stmt->execute();

            $_SESSION['success'] = "User \"$username\" deleted successfully.";
            header("Location: users.php");
            exit();
        } else {
            $_SESSION['error'] = "User not found.";
            header("Location: users.php");
            exit();
        }

    } catch (PDOException $e) {
        // Log the error
        error_log("Database Error: " . $e->getMessage());
        $_SESSION['error'] = "An error occurred while deleting the user.";
        header("Location: users.php");
        exit();
    }
} else {
    // If not a POST request, redirect back
    header("Location: users.php");
    exit();
}
?>
