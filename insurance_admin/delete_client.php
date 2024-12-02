<?php
session_start();
require_once '../include/connection.php'; // Include your PDO connection file

// Check if the form was submitted via POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Validate CSRF Token
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $_SESSION['error'] = "Invalid CSRF token.";
        header("Location: clients.php");
        exit();
    }

    // Retrieve and sanitize form inputs
    $client_id = isset($_POST['client_id']) ? intval($_POST['client_id']) : 0;

    // Initialize an array to store errors
    $errors = [];

    // Validate Client ID
    if ($client_id <= 0) {
        $errors[] = "Invalid Client ID.";
    }

    // If there are validation errors, redirect back with errors
    if (!empty($errors)) {
        $_SESSION['error'] = implode("<br>", $errors);
        header("Location: clients.php");
        exit();
    }

    try {
        // Check if the client exists
        $checkClientQuery = "SELECT client_id, first_name, last_name FROM clients WHERE client_id = :client_id";
        $stmt = $connection->prepare($checkClientQuery);
        $stmt->bindParam(':client_id', $client_id, PDO::PARAM_INT);
        $stmt->execute();

        if ($stmt->rowCount() === 0) {
            $_SESSION['error'] = "Client not found.";
            header("Location: clients.php");
            exit();
        }

        // Optionally, you can perform additional checks before deletion

        // Delete the client
        $deleteQuery = "DELETE FROM clients WHERE client_id = :client_id";
        $stmt = $connection->prepare($deleteQuery);
        $stmt->bindParam(':client_id', $client_id, PDO::PARAM_INT);

        if ($stmt->execute()) {
            // Fetch the client's name for the success message
            $client = $stmt->fetch(PDO::FETCH_ASSOC);
            $_SESSION['success'] = "Client deleted successfully.";
        } else {
            $_SESSION['error'] = "Failed to delete the client.";
        }

    } catch (PDOException $e) {
        // Log the error for debugging (do not display to the user)
        error_log("Database Error [delete_client.php]: " . $e->getMessage());
        $_SESSION['error'] = "An error occurred while deleting the client. Please try again later.";
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
