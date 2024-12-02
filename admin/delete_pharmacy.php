<?php
session_start();
include '../include/connection.php'; // Include the database connection

// Check if the form was submitted
if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    // Validate CSRF Token
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $_SESSION['error'] = "Invalid CSRF token.";
        header("Location: pharmacies.php");
        exit();
    }

    // Retrieve and sanitize form inputs
    $pharmacy_id = $_POST['pharmacy_id'];

    // Basic validation
    if (empty($pharmacy_id)) {
        $_SESSION['error'] = "Invalid pharmacy ID.";
        header("Location: pharmacies.php");
        exit();
    }

    try {
        // Fetch the pharmacy to get its name for the success message
        $fetchQuery = "SELECT pharmacy_name FROM pharmacies WHERE pharmacy_id = :pharmacy_id";
        $stmt = $connection->prepare($fetchQuery);
        $stmt->bindParam(':pharmacy_id', $pharmacy_id, PDO::PARAM_INT);
        $stmt->execute();
        $pharmacy = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($pharmacy) {
            $pharmacy_name = $pharmacy['pharmacy_name'];

            // Delete the pharmacy
            $deleteQuery = "DELETE FROM pharmacies WHERE pharmacy_id = :pharmacy_id";
            $stmt = $connection->prepare($deleteQuery);
            $stmt->bindParam(':pharmacy_id', $pharmacy_id, PDO::PARAM_INT);
            $stmt->execute();

            $_SESSION['success'] = "Pharmacy \"$pharmacy_name\" deleted successfully.";
            header("Location: pharmacies.php");
            exit();
        } else {
            $_SESSION['error'] = "Pharmacy not found.";
            header("Location: pharmacies.php");
            exit();
        }

    } catch (PDOException $e) {
        // Log the error
        error_log("Database Error: " . $e->getMessage());
        $_SESSION['error'] = "An error occurred while deleting the pharmacy.";
        header("Location: pharmacies.php");
        exit();
    }
} else {
    // If not a POST request, redirect back
    header("Location: pharmacies.php");
    exit();
}
?>
