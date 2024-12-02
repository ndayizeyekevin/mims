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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $claimId = $_POST['claim_id'];
    
    // Handle file upload
    if (isset($_FILES['prescription_attachment']) && $_FILES['prescription_attachment']['error'] == 0) {
        $fileTmpPath = $_FILES['prescription_attachment']['tmp_name'];
        $fileName = $_FILES['prescription_attachment']['name'];
        $fileSize = $_FILES['prescription_attachment']['size'];
        $fileType = $_FILES['prescription_attachment']['type'];

        // Define valid extensions and maximum size
        $allowedExtensions = ['pdf', 'jpg', 'jpeg', 'png'];
        $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

        if (in_array($fileExtension, $allowedExtensions) && $fileSize <= 2097152) {
            // Generate unique file name and move file to uploads directory
            $newFileName = uniqid() . '.' . $fileExtension;
            $uploadDir = './uploads/';
            $destPath = $uploadDir . $newFileName;

            if (move_uploaded_file($fileTmpPath, $destPath)) {
                // Update the transaction to attach the prescription
                $sql = "UPDATE transactions SET prescription_attachment = :prescription, status = 'pending' WHERE transaction_id = :transaction_id";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    ':prescription' => $destPath,
                    ':transaction_id' => $claimId
                ]);

                echo 'Prescription attached successfully!';
            } else {
                echo 'Error moving file to destination directory.';
            }
        } else {
            echo 'Invalid file extension or file too large.';
        }
    } else {
        echo 'No file uploaded or file upload error.';
    }
}
?>
