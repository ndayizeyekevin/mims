<?php
// File: pharmacy_claims.php
session_start();
require_once '../include/connection.php'; // Include your PDO connection file

// Initialize transaction_id session
$_SESSION['transaction_id'] =null;

// Ensure only authenticated pharmacy staff can access this page
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'pharmacist') {
    header("Location: ../login.php");
    exit();
}

// Retrieve the pharmacy_id of the logged-in user
$pharmacy_id = intval($_SESSION['pharmacy_id']);



// Initialize variables
$errors = [];
$success = "";

// Generate CSRF token if not set
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Fetch claims categorized by status
try {
    $stmt = $connection->prepare("
        SELECT 
            tm.transaction_medication_id,
            t.transaction_id,
            c.first_name,
            c.last_name,
            m.medication_name,
            tm.quantity,
            tm.price,
            i.coverage_percentage,
            i.insurance_name,
            t.transaction_date,
            t.prescription_attachment,
            tm.status,
            tm.rejection_comment
        FROM 
            transaction_medications tm
        JOIN 
            transactions t ON tm.transaction_id = t.transaction_id
        JOIN 
            clients c ON t.client_id = c.client_id
        JOIN 
            medications m ON tm.medication_id = m.medication_id
        JOIN
            insurance_companies i ON t.insurance_id = i.insurance_id
        WHERE 
            t.pharmacy_id = :pharmacy_id
        ORDER BY 
            t.transaction_date DESC
    ");
    $stmt->bindParam(':pharmacy_id', $pharmacy_id, PDO::PARAM_INT);
    $stmt->execute();
    $claims = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Categorize claims
    $pending_claims = [];
    $approved_claims = [];
    $rejected_claims = [];

    foreach ($claims as $claim) {
        switch ($claim['status']) {
            case 'pending':
                $pending_claims[] = $claim;
                break;
            case 'approved':
                $approved_claims[] = $claim;
                break;
            case 'rejected':
                $rejected_claims[] = $claim;
                break;
        }
    }
} catch (PDOException $e) {
    $errors[] = "Failed to fetch claims: " . $e->getMessage();
}



    // Handle Prescription Attachment for Rejected Claims
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['attach_prescription'])) {
        // CSRF Token Validation
        if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
            $errors[] = "Invalid CSRF token.";
        } else {
            if (isset($_POST['transaction_id'])){
                $transaction_id = intval($_POST['transaction_id']);
                $transaction_medication_id = intval($_POST['transaction_medication_id']);
                $prescription_attachment = $_FILES['prescription_attachment'];
                
        
                // Validate transaction_medication_id
                if ($transaction_medication_id <= 0) {
                    $errors[] = "Invalid transaction medication ID.";
                }
        
                // Validate file upload
                if ($prescription_attachment['error'] !== UPLOAD_ERR_OK) {
                    $errors[] = "Error uploading the prescription attachment.";
                } else {
                    $file_tmp_path = $prescription_attachment['tmp_name'];
                    $file_name = basename($prescription_attachment['name']);
                    $file_size = $prescription_attachment['size'];
                    $file_type = mime_content_type($file_tmp_path);
        
                    // Validate file type (e.g., PDF, JPG, PNG)
                    $allowed_types = ['application/pdf', 'image/jpeg', 'image/png'];
                    if (!in_array($file_type, $allowed_types)) {
                        $errors[] = "Invalid file type. Allowed types: PDF, JPG, PNG.";
                    } elseif ($file_size > 2 * 1024 * 1024) { // 2MB limit
                        $errors[] = "Prescription file size exceeds 2MB.";
                    } else {
                        // Define upload directory
                        $upload_dir = '../uploads/prescriptions/';
                        if (!is_dir($upload_dir)) {
                            mkdir($upload_dir, 0755, true);
                        }
        
                        // Generate a unique file name to prevent overwriting
                        $new_file_name = uniqid('presc_', true) . '_' . preg_replace("/[^a-zA-Z0-9.\-]/", "_", $file_name);
                        $destination = $upload_dir . $new_file_name;
        
                        if (move_uploaded_file($file_tmp_path, $destination)) {
                            // Update the transaction_medications table with the attachment path and reset status to 'pending'
                            try {
                                // Begin a transaction
                                $connection->beginTransaction();
                            
                                // First query: Update `transactions` table
                                $stmt1 = $connection->prepare("
                                    UPDATE transactions 
                                    SET 
                                        prescription_attachment = :attachment
                                    WHERE 
                                        transaction_id = :transaction_id 
                                ");
                                $stmt1->bindParam(':attachment', $destination, PDO::PARAM_STR);
                                $stmt1->bindParam(':transaction_id', $transaction_id, PDO::PARAM_INT);
                                $stmt1->execute();
                            
                                if($stmt1 == true){
                                    // Second query: Update `transaction_medications` table
                                        $stmt2 = $connection->prepare("
                                        UPDATE transaction_medications 
                                        SET 
                                            status = 'pending',
                                            rejection_comment = NULL
                                        WHERE 
                                            transaction_medication_id = :id
                                    ");
                                    $stmt2->bindParam(':id', $transaction_medication_id, PDO::PARAM_INT);
                                    $stmt2->execute();
                                
                                    // Check if both queries updated rows
                                    if ($stmt1->rowCount() > 0 || $stmt2->rowCount() > 0) {
                                        // Commit the transaction
                                        $connection->commit();
                                        $success = "Prescription attached successfully. The claim status has been reset to pending. ". $transaction_id;
                                }
                                } else {
                                    // Rollback the transaction if no rows were affected
                                    $connection->rollBack();
                                    $errors[] = "Failed to update the claim. It might have been already processed or does not exist.";
                                }
                            } catch (PDOException $e) {
                                // Rollback the transaction on error
                                $connection->rollBack();
                                $errors[] = "Database error: " . $e->getMessage();
                                // Optionally, remove the uploaded file if database update fails
                                unlink($destination);
                            }
                            
                        } else {
                            $errors[] = "Failed to move the uploaded file.";
                        }
                }
            }
        }
        else{
        ?>
            <script>
                alert('Transaction id not stored.');
            </script>
        <?php
    }
    }
}
    

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pharmacy Claims Management</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome for Icons -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    

    <!-- <link rel="stylesheet" href="../css/style.css"> -->

    <link rel="icon" type="image/x-icon" href="../images/logo-removebg-preview.png">
    
    <!-- Custom CSS -->
    <style>
        body {
            background-color: #f8f9fa;
        }
        .container {
            margin-top: 50px;
        }
        .table-responsive {
            max-height: 600px;
        }
        .modal-img {
            width: 100%;
            height: auto;
        }
        .sidebar .nav-link {
    margin-bottom: 10px;
}
    </style>
</head>
<body>


    <!-- Header with logo and title -->
    <?php include './header.php'; ?>




<!-- Main Content -->
<div class="container-fluid">

    <div class="row">
    
    <!-- Navigation -->

    <?php include './nav.php'; ?>
    <?php include './mobile.php'; ?>
        
    <main class="col-lg-10 ms-auto main-content p-4">
        <div class="container">
                <h1>Pharmacy Claims Management</h1>
                
                <!-- Display Success Message -->
                <?php if (!empty($success)): ?>
                    <div class="alert alert-success">
                        <?php echo htmlspecialchars($success); ?>
                    </div>
                <?php endif; ?>
                
                <!-- Display Errors -->
                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger">
                        <ul>
                        <?php foreach ($errors as $error): ?>
                            <li><?php echo htmlspecialchars($error); ?></li>
                        <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>
                
                <!-- Tabs for Different Claim Statuses -->
                <ul class="nav nav-tabs" id="claimsTab" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="pending-tab" data-bs-toggle="tab" data-bs-target="#pending" type="button" role="tab" aria-controls="pending" aria-selected="true">
                            Pending Claims
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="approved-tab" data-bs-toggle="tab" data-bs-target="#approved" type="button" role="tab" aria-controls="approved" aria-selected="false">
                            Approved Claims
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="rejected-tab" data-bs-toggle="tab" data-bs-target="#rejected" type="button" role="tab" aria-controls="rejected" aria-selected="false">
                            Rejected Claims
                        </button>
                    </li>
                </ul>
                <div class="tab-content" id="claimsTabContent">
                    <!-- Pending Claims Tab -->
                    <div class="tab-pane fade show active" id="pending" role="tabpanel" aria-labelledby="pending-tab">
                        <div class="table-responsive mt-3">
                            <table id="pending-claims" class="table table-bordered table-hover align-middle">
                                <thead class="table-warning">
                                    <tr>
                                        <th>Transaction ID</th>
                                        <th>Client Name</th>
                                        <th>Medication</th>
                                        <th>Quantity</th>
                                        <th>Unit Price (RWF)</th>
                                        <th>Total Amount (RWF)</th>
                                        <th>Insurance Payout (RWF)</th>
                                        <th>Insurance Name</th>
                                        <th>Transaction Date</th>
                                        <th>Prescription</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($pending_claims)): ?>
                                        <tr>
                                            <td colspan="9" class="text-center">No pending claims found.</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($pending_claims as $claim): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($claim['transaction_id']); ?></td>
                                                <td><?php echo htmlspecialchars($claim['first_name'] . ' ' . $claim['last_name']); ?></td>
                                                <td><?php echo htmlspecialchars($claim['medication_name']); ?></td>
                                                <td><?php echo htmlspecialchars($claim['quantity']); ?></td>
                                                <td><?php echo number_format($claim['price'], 2); ?></td>
                                                <td><?php echo number_format($claim['price'] * $claim['quantity'], 2); ?></td>
                                                <td class="text-light bg-dark">
                                                    <?php 
                                                          $total = $claim['price'] * $claim['quantity'];
                                                          $coverage = ($total * $claim['coverage_percentage'])/100;
                                                          echo number_format($coverage, 2); 
                                                    ?>
                                                </td>
                                                <td><?php echo htmlspecialchars($claim['insurance_name']); ?></td>
                                                <td><?php echo htmlspecialchars(date("Y-m-d H:i:s", strtotime($claim['transaction_date']))); ?></td>
                                                <td>
                                                    <?php if ($claim['prescription_attachment']): ?>
                                                        <button type="button" class="btn btn-sm btn-info" 
                                                                data-bs-toggle="modal" 
                                                                data-bs-target="#prescriptionModal<?php echo htmlspecialchars($claim['transaction_medication_id']); ?> <?php $_SESSION['transaction_id'] = htmlspecialchars($claim['transaction_id']); ?>">
                                                                
                                                            <i class="fas fa-eye"></i> View
                                                        </button>
                                                        
                                                        <!-- Prescription Modal -->
                                                        <div class="modal fade" id="prescriptionModal<?php echo htmlspecialchars($claim['transaction_medication_id']); ?>" tabindex="-1" aria-labelledby="prescriptionModalLabel<?php echo htmlspecialchars($claim['transaction_medication_id']); ?>" aria-hidden="true">
                                                            <div class="modal-dialog modal-lg">
                                                                <div class="modal-content">
                                                                    <div class="modal-header">
                                                                        <h5 class="modal-title" id="prescriptionModalLabel<?php echo htmlspecialchars($claim['transaction_medication_id']); ?>">Prescription Attachment</h5>
                                                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                                    </div>
                                                                    <div class="modal-body">
                                                                        <?php
                                                                        $file_path = $claim['prescription_attachment'];
                                                                        $file_ext = strtolower(pathinfo($file_path, PATHINFO_EXTENSION));
                                                                        if (in_array($file_ext, ['jpg', 'jpeg', 'png', 'gif'])):
                                                                        ?>
                                                                            <img src="<?php echo htmlspecialchars($file_path); ?>" alt="Prescription Image" class="modal-img">
                                                                        <?php elseif ($file_ext === 'pdf'): ?>
                                                                            <embed src="<?php echo htmlspecialchars($file_path); ?>" type="application/pdf" width="100%" height="600px" />
                                                                        <?php else: ?>
                                                                            <p>Unable to display the prescription attachment.</p>
                                                                        <?php endif; ?>
                                                                    </div>
                                                                    <div class="modal-footer">
                                                                    <a href="<?php echo htmlspecialchars($file_path); ?>" download target="_blank" class="btn btn-primary">Download</a>

                                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    <?php else: ?>
                                                        <span class="text-muted">No Attachment</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <span class="badge bg-warning text-dark"><?php echo ucfirst(htmlspecialchars($claim['status'])); ?></span>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Approved Claims Tab -->
                    <div class="tab-pane fade" id="approved" role="tabpanel" aria-labelledby="approved-tab">
                        <div class="table-responsive mt-3">
                            <table id="approved-claims" class="table table-bordered table-hover align-middle">
                                <thead class="table-success">
                                    <tr>
                                        <th>Transaction ID</th>
                                        <th>Client Name</th>
                                        <th>Medication</th>
                                        <th>Quantity</th>
                                        <th>Unit Price (RWF)</th>
                                        <th>Total Amount (RWF)</th>
                                        <th>Insurance Payout</th>
                                        <th>Insurance Name</th>
                                        <th>Transaction Date</th>
                                        <th>Prescription</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($approved_claims)): ?>
                                        <tr>
                                            <td colspan="9" class="text-center">No approved claims found.</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($approved_claims as $claim): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($claim['transaction_id']); ?></td>
                                                <td><?php echo htmlspecialchars($claim['first_name'] . ' ' . $claim['last_name']); ?></td>
                                                <td><?php echo htmlspecialchars($claim['medication_name']); ?></td>
                                                <td><?php echo htmlspecialchars($claim['quantity']); ?></td>
                                                <td><?php echo number_format($claim['price'], 2); ?></td>
                                                <td><?php echo number_format($claim['price'] * $claim['quantity'], 2); ?></td>
                                                <td class="text-light bg-dark">
                                                    <?php 
                                                          $total = $claim['price'] * $claim['quantity'];
                                                          $coverage = ($total * $claim['coverage_percentage'])/100;
                                                          echo number_format($coverage, 2); 
                                                    ?>
                                                </td>
                                                <td><?php echo htmlspecialchars($claim['insurance_name']); ?></td>
                                                <td><?php echo htmlspecialchars(date("Y-m-d H:i:s", strtotime($claim['transaction_date']))); ?></td>
                                                <td>
                                                    <?php if ($claim['prescription_attachment']): ?>
                                                        <button type="button" class="btn btn-sm btn-info" data-bs-toggle="modal" data-bs-target="#prescriptionModal<?php echo htmlspecialchars($claim['transaction_medication_id']); ?>">
                                                            <i class="fas fa-eye"></i> View
                                                        </button>
                                                        
                                                        <!-- Prescription Modal -->
                                                        <div class="modal fade" id="prescriptionModal<?php echo htmlspecialchars($claim['transaction_medication_id']); ?>" tabindex="-1" aria-labelledby="prescriptionModalLabel<?php echo htmlspecialchars($claim['transaction_medication_id']); ?>" aria-hidden="true">
                                                            <div class="modal-dialog modal-lg">
                                                                <div class="modal-content">
                                                                    <div class="modal-header">
                                                                        <h5 class="modal-title" id="prescriptionModalLabel<?php echo htmlspecialchars($claim['transaction_medication_id']); ?>">Prescription Attachment</h5>
                                                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                                    </div>
                                                                    <div class="modal-body">
                                                                        <?php
                                                                        $file_path = $claim['prescription_attachment'];
                                                                        $file_ext = strtolower(pathinfo($file_path, PATHINFO_EXTENSION));
                                                                        if (in_array($file_ext, ['jpg', 'jpeg', 'png', 'gif'])):
                                                                        ?>
                                                                            <img src="<?php echo htmlspecialchars($file_path); ?>" alt="Prescription Image" class="modal-img">
                                                                        <?php elseif ($file_ext === 'pdf'): ?>
                                                                            <embed src="<?php echo htmlspecialchars($file_path); ?>" type="application/pdf" width="100%" height="600px" />
                                                                        <?php else: ?>
                                                                            <p>Unable to display the prescription attachment.</p>
                                                                        <?php endif; ?>
                                                                    </div>
                                                                    <div class="modal-footer">
                                                                        <a href="<?php echo htmlspecialchars($file_path); ?>" target="_blank" class="btn btn-primary">Download</a>
                                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    <?php else: ?>
                                                        <span class="text-muted">No Attachment</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <span class="badge bg-success text-white"><?php echo ucfirst(htmlspecialchars($claim['status'])); ?></span>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Rejected Claims Tab -->
                    <div class="tab-pane fade" id="rejected" role="tabpanel" aria-labelledby="rejected-tab">
                        <div class="table-responsive mt-3">
                            <table id="rejected-claims" class="table table-bordered table-hover align-middle">
                                <thead class="table-danger">
                                    <tr>
                                        <th>Transaction ID</th>
                                        <th>Client Name</th>
                                        <th>Medication</th>
                                        <th>Quantity</th>
                                        <th>Unit Price (RWF)</th>
                                        <th>Total Amount (RWF)</th>
                                        <th>Insurance Payout</th>
                                        <th>Insurance Name</th>
                                        <th>Transaction Date</th>
                                        <th>Prescription</th>
                                        <th>Status</th>
                                        <th>Rejection Comment</th>
                                        <th>Attach Prescription</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($rejected_claims)): ?>
                                        <tr>
                                            <td colspan="11" class="text-center">No rejected claims found.</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($rejected_claims as $claim): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($claim['transaction_id']); ?></td>
                                                <td><?php echo htmlspecialchars($claim['first_name'] . ' ' . $claim['last_name']); ?></td>
                                                <td><?php echo htmlspecialchars($claim['medication_name']); ?></td>
                                                <td><?php echo htmlspecialchars($claim['quantity']); ?></td>
                                                <td><?php echo number_format($claim['price'], 2); ?></td>
                                                <td><?php echo number_format($claim['price'] * $claim['quantity'], 2); ?></td>
                                                <td class="text-light bg-dark">
                                                    <?php 
                                                          $total = $claim['price'] * $claim['quantity'];
                                                          $coverage = ($total * $claim['coverage_percentage'])/100;
                                                          echo number_format($coverage, 2); 
                                                    ?>
                                                </td>
                                                <td><?php echo htmlspecialchars($claim['insurance_name']); ?></td>
                                                <td><?php echo htmlspecialchars(date("Y-m-d H:i:s", strtotime($claim['transaction_date']))); ?></td>
                                                <td>
                                                    <?php if ($claim['prescription_attachment']): ?>
                                                        <button type="button" class="btn btn-sm btn-info" data-bs-toggle="modal" data-bs-target="#prescriptionModal<?php echo htmlspecialchars($claim['transaction_medication_id']); ?>">
                                                            <i class="fas fa-eye"></i> View
                                                        </button>
                                                        
                                                        <!-- Prescription Modal -->
                                                        <div class="modal fade" id="prescriptionModal<?php echo htmlspecialchars($claim['transaction_medication_id']); ?>" tabindex="-1" aria-labelledby="prescriptionModalLabel<?php echo htmlspecialchars($claim['transaction_medication_id']); ?>" aria-hidden="true">
                                                            <div class="modal-dialog modal-lg">
                                                                <div class="modal-content">
                                                                    <div class="modal-header">
                                                                        <h5 class="modal-title" id="prescriptionModalLabel<?php echo htmlspecialchars($claim['transaction_medication_id']); ?>">Prescription Attachment</h5>
                                                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                                    </div>
                                                                    <div class="modal-body">
                                                                        <?php
                                                                        $file_path = $claim['prescription_attachment'];
                                                                        $file_ext = strtolower(pathinfo($file_path, PATHINFO_EXTENSION));
                                                                        if (in_array($file_ext, ['jpg', 'jpeg', 'png', 'gif'])):
                                                                        ?>
                                                                            <img src="<?php echo htmlspecialchars($file_path); ?>" alt="Prescription Image" class="modal-img">
                                                                        <?php elseif ($file_ext === 'pdf'): ?>
                                                                            <embed src="<?php echo htmlspecialchars($file_path); ?>" type="application/pdf" width="100%" height="600px" />
                                                                        <?php else: ?>
                                                                            <p>Unable to display the prescription attachment.</p>
                                                                        <?php endif; ?>
                                                                    </div>
                                                                    <div class="modal-footer">
                                                                        <a href="<?php echo htmlspecialchars($file_path); ?>" target="_blank" class="btn btn-primary">Download</a>
                                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    <?php else: ?>
                                                        <span class="text-muted">No Attachment</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <span class="badge bg-danger text-white"><?php echo ucfirst(htmlspecialchars($claim['status'])); ?></span>
                                                </td>
                                                <td><?php echo htmlspecialchars($claim['rejection_comment']); ?></td>
                                                <td>
                                                    <!-- Button to Trigger Attachment Modal -->
                                                    <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#attachModal<?php echo htmlspecialchars($claim['transaction_medication_id']); ?>">
                                                        <i class="fas fa-paperclip"></i> Attach
                                                    </button>
                                                    
                                                    <!-- Attach Prescription Modal -->
                                                    <div class="modal fade" id="attachModal<?php echo htmlspecialchars($claim['transaction_medication_id']); ?>" tabindex="-1" aria-labelledby="attachModalLabel<?php echo htmlspecialchars($claim['transaction_medication_id']); ?>" aria-hidden="true">
                                                        <div class="modal-dialog">
                                                            <form method="POST" enctype="multipart/form-data">
                                                                <div class="modal-content">
                                                                    <div class="modal-header">
                                                                        <h5 class="modal-title" id="attachModalLabel<?php echo htmlspecialchars($claim['transaction_medication_id']); ?>">Attach Prescription</h5>
                                                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                                    </div>
                                                                    <div class="modal-body">
                                                                        <div class="mb-3">
                                                                            <label for="prescription_attachment" class="form-label">Prescription Attachment:</label>
                                                                            <input class="form-control" type="file" id="prescription_attachment" name="prescription_attachment" accept=".pdf,.jpg,.jpeg,.png" required>
                                                                            <div class="form-text">Allowed file types: PDF, JPG, PNG. Max size: 2MB.</div>
                                                                        </div>
                                                                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                                                        <input type="hidden" name="transaction_medication_id" value="<?php echo htmlspecialchars($claim['transaction_medication_id']); ?>">
                                                                        <input type="hidden" name="action" value="attach_prescription">
                                                                        <input type="hidden" name = "transaction_id" value ="<?php echo htmlspecialchars($claim['transaction_id']); ?>">
                                                                    </div>
                                                                    <div class="modal-footer">
                                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                                        <button type="submit" name="attach_prescription" class="btn btn-primary">Attach</button>
                                                                    </div>
                                                                </div>
                                                            </form>
                                                        </div>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
            </div>
        </main>
    </div>
</div>

<?php include '../include/footer.php'; ?>
        
 <!-- Bootstrap 5 JS Bundle (Includes Popper) -->
 <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
        <!-- jQuery (Required for DataTables) -->
        <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
        <!-- DataTables JS -->
        <script type="text/javascript" src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
        <script type="text/javascript" src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>

        <script>
            $(document).ready(function() {
                $('#pending-claims').DataTable({
                    "paging": true,  // Enables pagination
                    "searching": true,  // Enables search functionality
                    "ordering": true,   // Enables column sorting
                    "lengthMenu": [5, 10, 25, 50, 100],  // Page length options
                    "pageLength": 5,     // Default number of rows per page
                    "info": true,               // Show info about current page
                    "autoWidth": true,
                    "responsive": true,
                    "language": {
                        "search": "Filter records:"
                    }
                });
            });



            $(document).ready(function() {
                $('#approved-claims').DataTable({
                    "paging": true,  // Enables pagination
                    "searching": true,  // Enables search functionality
                    "ordering": true,   // Enables column sorting
                    "lengthMenu": [5, 10, 25, 50, 100],  // Page length options
                    "pageLength": 5,     // Default number of rows per page
                    "info": true,               // Show info about current page
                    "autoWidth": true,
                    "responsive": true,
                    "language": {
                        "search": "Filter records:"
                    }
                });
            });



            $(document).ready(function() {
                $('#rejected-claims').DataTable({
                    "paging": true,  // Enables pagination
                    "searching": true,  // Enables search functionality
                    "ordering": true,   // Enables column sorting
                    "lengthMenu": [5, 10, 25, 50, 100],  // Page length options
                    "pageLength": 5,     // Default number of rows per page
                    "info": true,               // Show info about current page
                    "autoWidth": true,
                    "responsive": true,
                    "language": {
                        "search": "Filter records:"
                    },
                    "initComplete": function(settings, json) {
                        console.log('DataTables has finished initializing.');
                    }
                });
            });
            // Attach an event listener to each button with the class btn-info
            document.querySelectorAll('.btn-info').forEach(button => {
                button.addEventListener('click', function() {
                    // Get the transaction_id from the data attribute
                    const transactionId = this.getAttribute('data-transaction-id');
                    
                    // Send transactionId to the server via AJAX (using Fetch API)
                    fetch('view_claims.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: new URLSearchParams({
                            'transaction_id': transactionId // Pass the transaction_id
                        })
                    })
                    .then(response => response.text()) // Handle server response (if it's plain text)
                    .then(data => {
                        console.log('Response from server:', data);
                        // Optionally, show a message or handle the server response here
                    })
                    .catch((error) => {
                        console.error('Error:', error);
                    });
                });
            });
        </script>

       
    </body>
    </html>
