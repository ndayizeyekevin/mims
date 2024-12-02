<?php
// File: pharmacy_claims.php
session_start();
require_once '../include/connection.php'; // Include your PDO connection file

// Initialize transaction_id session
$_SESSION['transaction_id'] =null;

// Ensure only authenticated pharmacy staff can access this page
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'pharmacy_admin') {
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
            t.transaction_date,
            t.prescription_attachment,
            tm.status,
            tm.rejection_comment,
            i.insurance_name,
            i.coverage_percentage
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
    <title>Pharmacy Admin Dashboard</title>
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
            <h1 class="mb-4">Welcome, <?php echo htmlspecialchars($_SESSION['first_name']); ?>!</h1>

            <div class="container mt-4">
            <div class="row">
                <div class="col-md-4">
                    <div class="card text-white bg-primary mb-3">
                        <div class="card-body">
                            <h5 class="card-title"><i class="fas fa-users"></i> Users</h5>
                            <p class="card-text">Manage pharmacy users.</p>
                            <a href="./users.php" class="btn btn-dark">Go to Users</a>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card text-white bg-success mb-3">
                        <div class="card-body">
                            <h5 class="card-title"><i class="fas fa-exchange-alt"></i> Transactions</h5>
                            <p class="card-text"> Manage transactions</p>
                            <a href="./transactions.php" class="btn btn-dark">Record a Transaction</a>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card text-white bg-info mb-3">
                        <div class="card-body">
                            <h5 class="card-title"><i class="fas fa-chart-line"></i> Reports</h5>
                            <p class="card-text">View Reports</p>
                            <a href="./reports.php" class="btn btn-dark">Go to Reports</a>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row">
            <div class="col-md-4">
                    <div class="card text-white bg-success mb-3">
                        <div class="card-body">
                            <h5 class="card-title"><i class="fas fa-pills"></i> Medications</h5>
                            <p class="card-text">Manage medication details.</p>
                            <a href="./users.php" class="btn btn-dark">Go to Medications Page</a>
                        </div>
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="card text-white bg-primary mb-3">
                        <div class="card-body">
                            <h5 class="card-title"><i class="fas fa-file-alt"></i> Claims</h5>
                            <p class="card-text">Manage pharmacy claims.</p>
                            <a href="./view_claims.php" class="btn btn-dark">Go to Claims</a>
                        </div>
                    </div>
                </div>

                
                <div class="col-md-4">
                    <div class="card text-white bg-secondary mb-3">
                        <div class="card-body">
                            <h5 class="card-title"><i class="fas fa-user-circle"></i> Profile</h5>
                            <p class="card-text">View and edit your profile.</p>
                            <a href="./profile.php" class="btn btn-dark">Go to Profile</a>
                        </div>
                    </div>
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
