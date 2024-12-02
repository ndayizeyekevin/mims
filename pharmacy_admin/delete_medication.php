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
    $medication_id = $_POST['medication_id'];

    // Basic validation
    if (empty($medication_id)) {
        $_SESSION['error'] = "Invalid medication ID.";
        header("Location: users.php");
        exit();
    }

    try {
        // Fetch the medication to get its name for the success message
        $fetchQuery = "SELECT medication_name FROM medications WHERE medication_id = :medication_id";
        $stmt = $connection->prepare($fetchQuery);
        $stmt->bindParam(':medication_id', $medication_id, PDO::PARAM_INT);
        $stmt->execute();
        $medication = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($medication) {
            $medication_name = $medication['medication_name'];

            // Delete the medication
            $deleteQuery = "DELETE FROM medications WHERE medication_id = :medication_id";
            $stmt = $connection->prepare($deleteQuery);
            $stmt->bindParam(':medication_id', $medication_id, PDO::PARAM_INT);
            $stmt->execute();

            $_SESSION['success'] = "Medication \"$medication_name\" deleted successfully.";
            header("Location: users.php");
            exit();
        } else {
            $_SESSION['error'] = "Medication not found.";
            header("Location: users.php");
            exit();
        }

    } catch (PDOException $e) {
        // Log the error
        error_log("Database Error: " . $e->getMessage());
        $_SESSION['error'] = "An error occurred while deleting the medication.";
        header("Location: users.php");
        exit();
    }
} else {
    // If not a POST request, redirect back
    header("Location: users.php");
    exit();
}
?>
