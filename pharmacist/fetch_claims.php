<?php
// File: pharmacy_manage_claims.php
session_start();
require_once '../include/connection.php'; // Include your PDO connection file

// Ensure only authenticated pharmacy staff can access this page
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'pharmacist') {
    header("Location: ../login.php");
    exit();
}

// Retrieve the pharmacy_id of the logged-in user
$pharmacy_id = intval($_SESSION['pharmacy_id']);

// Fetch claims associated with the pharmacy, grouped by status
$stmt = $connection->prepare("
        SELECT 
            tm.transaction_medication_id,
            t.transaction_id,
            c.first_name,
            c.last_name,
            m.medication_name,
            tm.quantity,
            tm.price,
            t.transaction_date,
            t.prescription_attachment,
            tm.status
        FROM 
            transaction_medications tm
        JOIN 
            transactions t ON tm.transaction_id = t.transaction_id
        JOIN 
            clients c ON t.client_id = c.client_id
        JOIN 
            medications m ON tm.medication_id = m.medication_id
        WHERE 
            t.insurance_id = :insurance_id
            AND tm.status = 'pending'
        ORDER BY 
            t.transaction_date DESC
    ");

    // Bind the insurance ID parameter
    $stmt->bindParam(':insurance_id', $insurance_id, PDO::PARAM_INT);

    // Execute the query
    $stmt->execute();

    // Fetch all results
    $claims = $stmt->fetchAll(PDO::FETCH_ASSOC);





// Output claims as JSON
echo json_encode($claims);
?>
