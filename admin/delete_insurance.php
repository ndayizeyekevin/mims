<?php
session_start();
include '../include/connection.php'; // Include the database connection

// Check if the form was submitted
if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    // Validate CSRF Token
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $_SESSION['error'] = "Invalid CSRF token.";
        header("Location: insurance_companies.php");
        exit();
    }

    // Retrieve and sanitize form inputs
    $insurance_id = $_POST['insurance_id'];

    try {
        // Fetch the insurance company to get its name for the success message
        $fetchQuery = "SELECT insurance_name FROM insurance_companies WHERE insurance_id = :insurance_id";
        $stmt = $connection->prepare($fetchQuery);
        $stmt->bindParam(':insurance_id', $insurance_id, PDO::PARAM_INT);
        $stmt->execute();
        $insurance = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($insurance) {
            $insurance_name = $insurance['insurance_name'];

            // Delete the insurance company
            $deleteQuery = "DELETE FROM insurance_companies WHERE insurance_id = :insurance_id";
            $stmt = $connection->prepare($deleteQuery);
            $stmt->bindParam(':insurance_id', $insurance_id, PDO::PARAM_INT);
            $stmt->execute();

            $_SESSION['success'] = "Insurance company \"$insurance_name\" deleted successfully.";
            header("Location: insurance_companies.php");
            exit();
        } else {
            $_SESSION['error'] = "Insurance company not found.";
            header("Location: insurance_companies.php");
            exit();
        }

    } catch (PDOException $e) {
        // Log the error
        error_log("Database Error: " . $e->getMessage());
        $_SESSION['error'] = "An error occurred while deleting the insurance company.";
        header("Location: insurance_companies.php");
        exit();
    }
} else {
    // If not a POST request, redirect back
    header("Location: insurance_companies.php");
    exit();
}
?>
