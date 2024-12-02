<?php
// File: fetch_medications.php
session_start();
require_once '../include/connection.php'; // Include your PDO connection file

// Ensure only authenticated pharmacy staff can access this endpoint
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'pharmacist') {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized access.']);
    exit();
}

// Set header for JSON response
header('Content-Type: application/json');

// Retrieve search and filter parameters
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Build the SQL query with dynamic conditions
$sql = "SELECT medication_id, medication_name, unit_price, insurance_coverage FROM medications WHERE medication_name LIKE :search ORDER BY medication_name ASC LIMIT 10";
$stmt = $connection->prepare($sql);
$stmt->bindValue(':search', '%' . $search . '%', PDO::PARAM_STR);
$stmt->execute();
$medications = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Return the medications as JSON
echo json_encode(['medications' => $medications]);
?>
