<?php
// File: manage_claims.php
session_start();
require_once '../include/connection.php'; // Include your PDO connection file

// Ensure only authenticated insurance staff can access this page
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['insurance_employee', 'insurance_admin'])) {
    header("Location: ../login.php");
    exit();
}

// Retrieve the insurance_id of the logged-in user
$insurance_id = intval($_SESSION['insurance_id']);

// Initialize variables
$errors = [];
$success = "";

// Handle Approve/Deny Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'], $_POST['transaction_medication_id'])) {
    // CSRF Token Validation
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $errors[] = "Invalid CSRF token.";
    } else {
        $action = $_POST['action']; // 'approve' or 'deny'
        $transaction_medication_id = intval($_POST['transaction_medication_id']);

        // Validate action
        if (!in_array($action, ['approve', 'reject'])) {
            $errors[] = "Invalid action selected.";
        }

        // If action is deny, ensure a comment is provided
        if ($action === 'reject' && empty(trim($_POST['rejection_comment']))) {
            $errors[] = "Rejection comment is required when rejecting a claim.";
        }

        if (empty($errors)) {
            // Determine the new status and handle rejection comment
            $new_status = ($action === 'approve') ? 'approved' : 'rejected';
            $rejection_comment = ($action === 'reject') ? trim($_POST['rejection_comment']) : null;

            try {
                // Update the status and rejection_comment in the database
                $stmt = $connection->prepare("
                    UPDATE transaction_medications 
                    SET status = :status, rejection_comment = :rejection_comment 
                    WHERE transaction_medication_id = :id 
                    AND transaction_id IN (
                        SELECT transaction_id 
                        FROM transactions 
                        WHERE insurance_id = :insurance_id
                    )
                ");
                $stmt->bindParam(':status', $new_status, PDO::PARAM_STR);
                $stmt->bindParam(':rejection_comment', $rejection_comment, PDO::PARAM_STR);
                $stmt->bindParam(':id', $transaction_medication_id, PDO::PARAM_INT);
                $stmt->bindParam(':insurance_id', $insurance_id, PDO::PARAM_INT);
                $stmt->execute();

                if ($stmt->rowCount() > 0) {
                    $success = "Claim has been successfully $new_status.";
                } else {
                    $errors[] = "Failed to update the claim. It might have been already processed or does not exist.";
                }
            } catch (PDOException $e) {
                $errors[] = "Database error: " . $e->getMessage();
            }
        }
    }
}

// Generate CSRF token if not set
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Fetch pending claims
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
            t.transaction_date,
            t.prescription_attachment,
            tm.status,
            i.coverage_percentage, 
            p.pharmacy_name
        FROM 
            transaction_medications tm
        JOIN 
            transactions t ON tm.transaction_id = t.transaction_id
        JOIN 
            clients c ON t.client_id = c.client_id
        JOIN 
            medications m ON tm.medication_id = m.medication_id
        JOIN 
            insurance_companies  i ON i.insurance_id = t.insurance_id
        JOIN 
            pharmacies p ON p.pharmacy_id = t.pharmacy_id
        WHERE 
            t.insurance_id = :insurance_id
            AND tm.status = 'pending'
        ORDER BY 
            t.transaction_date DESC
    ");
    $stmt->bindParam(':insurance_id', $insurance_id, PDO::PARAM_INT);
    $stmt->execute();
    $claims = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $errors[] = "Failed to fetch claims: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Claims</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome for Icons -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <!-- External local css -->
    <link rel="stylesheet" href="../css/style.css">
    <!-- Icon -->
    <link rel="icon" type="image/x-icon" href="../images/logo-removebg-preview.png">

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Bootstrap JS (for modal functionality) -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>


    <!-- Custom CSS -->
    <style>
        body {
            background-color: #f8f9fa;
        }
        .table-responsive {
            max-height: 600px;
        }
        .modal-img {
            width: 100%;
            height: auto;
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
            
                <h1>Manage Claims</h1>
                
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
                
                <!-- Claims Table -->
                <div class="table-responsive my-4">
                    <table id="claimsTable" class="table table-bordered table-hover align-middle">
                        <thead class="table-dark">
                            <tr>
                                <th>Transaction ID</th>
                                <th>Client Name</th>
                                <th>Medication</th>
                                <th>Quantity</th>
                                <th>Unit Price (Rwf)</th>
                                <th>Total Amount (Rwf)</th>
                                <th>Insurance Payout (Rwf)</th>
                                <th>Pharmacy Name</th>
                                <th>Transaction Date</th>
                                <th>Prescription</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($claims)): ?>
                                <tr>
                                    <td colspan="11" class="text-center">No pending claims found.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($claims as $claim): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($claim['transaction_id']); ?></td>
                                        <td><?php echo htmlspecialchars($claim['first_name'] . ' ' . $claim['last_name']); ?></td>
                                        <td><?php echo htmlspecialchars($claim['medication_name']); ?></td>
                                        <td><?php echo htmlspecialchars($claim['quantity']); ?></td>
                                        <td><?php echo number_format($claim['price'], 2); ?></td>
                                        <td><?php echo number_format($claim['price'] * $claim['quantity'], 2); ?></td>
                                        <td><?php echo number_format(($claim['price'] * $claim['quantity'] * $claim['coverage_percentage'])/100, 2); ?></td>
                                        <td><?php echo htmlspecialchars($claim['pharmacy_name']); ?></td>
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
                                            <!-- Approve Button -->
                                            <!-- Trigger Button -->
                                            <form method="POST" class="d-inline" id="approveForm" data-bs-toggle="modal" data-bs-target="#approveModal">
                                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                                <input type="hidden" name="transaction_medication_id" value="<?php echo htmlspecialchars($claim['transaction_medication_id']); ?>">
                                                <button type="button" class="btn btn-sm btn-success">
                                                    <i class="fas fa-check"></i> Approve
                                                </button>
                                            </form>

                                            <!-- Approve Confirmation Modal -->
                                            <div class="modal fade" id="approveModal" tabindex="-1" aria-labelledby="approveModalLabel" aria-hidden="true">
                                                <div class="modal-dialog">
                                                    <form method="POST" class="d-inline" id="approveForm" data-bs-toggle="modal" data-bs-target="#approveModal">
                                                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                                        <input type="hidden" name="transaction_medication_id" value="<?php echo htmlspecialchars($claim['transaction_medication_id']); ?>">
                                                    <div class="modal-content">
                                                        <div class="modal-header">
                                                            <h5 class="modal-title" id="approveModalLabel">Confirm Approval</h5>
                                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                        </div>
                                                        <div class="modal-body">
                                                            Are you sure you want to approve this claim?
                                                        </div>
                                                        <div class="modal-footer">
                                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                            <button type="submit" name ='action' value='approve' form="approveForm" class="btn btn-success">Yes, Approve</button>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>


                                            
                                            <!-- Deny Button with Modal for Rejection Comment -->
                                            <button type="button" class="btn btn-sm btn-danger my-2" data-bs-toggle="modal" data-bs-target="#denyModal<?php echo htmlspecialchars($claim['transaction_medication_id']); ?>">
                                                <i class="fas fa-times"></i> Reject
                                            </button>
                                            
                                            <!-- Deny Modal -->
                                            <div class="modal fade" id="denyModal<?php echo htmlspecialchars($claim['transaction_medication_id']); ?>" tabindex="-1" aria-labelledby="denyModalLabel<?php echo htmlspecialchars($claim['transaction_medication_id']); ?>" aria-hidden="true">
                                                <div class="modal-dialog">
                                                    <form method="POST">
                                                        <div class="modal-content">
                                                            <div class="modal-header">
                                                                <h5 class="modal-title" id="denyModalLabel<?php echo htmlspecialchars($claim['transaction_medication_id']); ?>">Reject Claim</h5>
                                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                            </div>
                                                            <div class="modal-body">
                                                                <p>Please provide a reason for denying this claim:</p>
                                                                <div class="mb-3">
                                                                    <textarea class="form-control" name="rejection_comment" rows="4" required></textarea>
                                                                </div>
                                                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                                                <input type="hidden" name="transaction_medication_id" value="<?php echo htmlspecialchars($claim['transaction_medication_id']); ?>">
                                                                <input type="hidden" name="action" value="reject">
                                                            </div>
                                                            <div class="modal-footer">
                                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                                <button type="submit" class="btn btn-danger">Reject Claim</button>
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
            
        </main>
                                    
    </div>
</div>
<?php include '../include/footer.php'; ?>    
    <!-- Bootstrap 5 JS Bundle (Includes Popper) -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

  
    <!-- Bootstrap 5 JS Bundle (Includes Popper) -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- jQuery (Required for DataTables) -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <!-- DataTables JS -->
    <script type="text/javascript" src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
    <script type="text/javascript" src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>

    <script>
        $(document).ready(function() {
        $('#claimsTable').DataTable({
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
    </script>
</body>
</html>
