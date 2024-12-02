<?php
// File: record_transaction.php
session_start();
require_once '../include/connection.php'; // Include your PDO connection file
                    
// Ensure only authenticated pharmacy staff can access this page
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'pharmacist') {
    header("Location: ../login.php");
    exit();
}
// UserId
if (!isset($_SESSION['user_id']) ) {
    header("Location: ../login.php");
    exit();
}

// Generate CSRF token if not set
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Initialize variables
$client = null;
$errors = [];
$receipt_html = "";

// Handle form submissions

// 1. Search for Client
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['search_client'])) {
    // CSRF Token Validation
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $errors[] = "Invalid CSRF token.";
    } else {
        $client_id = intval($_POST['id']);
        $_SESSION['client_id'] = $client_id; 
        
        if ($client_id <= 0) {
            $errors[] = "Please enter a valid client ID.";
        } else {
            // Search for the client across insurance companies
            $stmt = $connection->prepare("
                SELECT c.client_id, c.first_name, c.last_name, c.email, c.insurance_id, i.insurance_name, i.coverage_percentage
                FROM clients c
                JOIN insurance_companies i ON c.insurance_id = i.insurance_id
                WHERE c.client_id = :client_id
                LIMIT 1
            ");
            $stmt->bindParam(':client_id', $client_id, PDO::PARAM_INT);
            $stmt->execute();
            $client = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$client) {
                $errors[] = "Client with ID '$client_id' does not exist.";
            }
        }
    }
}

// 2. Record Transaction
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['record_transaction'])) {
    // CSRF Token Validation
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $errors[] = "Invalid CSRF token.";
    } else {
        // Retrieve and sanitize inputs
        $client_id = intval($_POST['client_id']);
        $insurance_id = intval($_POST['insurance_id']);
        $user_id = intval($_SESSION['user_id']); // Assuming user_id is stored in session
        $pharmacy_id = intval($_SESSION['pharmacy_id']); // Assuming pharmacy_id is stored in session
        $medications = isset($_POST['medication_id']) ? $_POST['medication_id'] : []; // Array of medication IDs
        $quantities = isset($_POST['quantity']) ? $_POST['quantity'] : []; // Array of quantities
        $prescription_attachments = isset($_FILES['prescription_attachment']) ? $_FILES['prescription_attachment'] : null;
        
        // Validate client and insurance IDs
        if ($client_id <= 0 || $insurance_id <= 0) {
            $errors[] = "Invalid client or insurance selection.";
        }
        
        // Validate medications and quantities
        if (empty($medications) || empty($quantities) || count($medications) !== count($quantities)) {
            $errors[] = "Please select at least one medication and specify its quantity.";
        } else {
            // Clean and prepare medications and quantities
            $clean_medications = [];
            foreach ($medications as $index => $med_id) {
                $med_id = intval($med_id);
                $quantity = intval($quantities[$index]);
                if ($med_id > 0 && $quantity > 0) {
                    $clean_medications[] = ['id' => $med_id, 'quantity' => $quantity];
                } else {
                    $errors[] = "Invalid medication selection or quantity.";
                    break;
                }
            }
        }
        
        // Handle prescription attachment upload
        $attachment_paths = [];
        if ($prescription_attachments) {
            foreach ($prescription_attachments['error'] as $key => $error) {
                if ($error === UPLOAD_ERR_OK) {
                    $file_tmp_path = $prescription_attachments['tmp_name'][$key];
                    $file_name = basename($prescription_attachments['name'][$key]);
                    $file_size = $prescription_attachments['size'][$key];
                    $file_type = mime_content_type($file_tmp_path);
                    
                    // Validate file type (e.g., PDF, JPG, PNG)
                    $allowed_types = ['application/pdf', 'image/jpeg', 'image/png'];
                    if (!in_array($file_type, $allowed_types)) {
                        $errors[] = "Invalid file type for prescription. Allowed types: PDF, JPG, PNG.";
                        continue;
                    } elseif ($file_size > 2 * 1024 * 1024) { // 2MB limit
                        $errors[] = "Prescription file size exceeds 2MB.";
                        continue;
                    } else {
                        // Define upload directory
                        $upload_dir = '../uploads/prescriptions/';
                        if (!is_dir($upload_dir)) {
                            mkdir($upload_dir, 0755, true);
                        }
                        // Generate a unique file name
                        $new_file_name = uniqid('presc_', true) . '_' . preg_replace("/[^a-zA-Z0-9.\-]/", "_", $file_name);
                        $destination = $upload_dir . $new_file_name;
                        
                        if (move_uploaded_file($file_tmp_path, $destination)) {
                            $attachment_paths[] = $destination;
                        } else {
                            $errors[] = "Failed to upload the prescription file.";
                        }
                    }
                } elseif ($error !== UPLOAD_ERR_NO_FILE) {
                    $errors[] = "Error uploading the prescription file.";
                }
            }
        }
        
        // Proceed if no errors
        if (empty($errors)) {
            try {
                // Start Transaction
                $connection->beginTransaction();
                
                // Initialize totals
                $total_amount = 0;
                $insurance_payment = 0;
                $client_payment = 0;
                
                // Prepare data for insertion into transaction_medications
                $transaction_medications = [];
                foreach ($clean_medications as $med) {
                    // Fetch medication details
                    $stmt = $connection->prepare("SELECT medication_name, unit_price, insurance_coverage FROM medications WHERE medication_id = :med_id");
                    $stmt->bindParam(':med_id', $med['id'], PDO::PARAM_INT);
                    $stmt->execute();
                    $medication = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if (!$medication) {
                        throw new Exception("Selected medication does not exist.");
                    }

                    // Fetch insurance coverage percentage
                    $stmt = $connection->prepare("SELECT coverage_percentage FROM insurance_companies WHERE insurance_id = :insurance_id");
                    $stmt->bindParam(':insurance_id', $insurance_id, PDO::PARAM_INT);
                    $stmt->execute();
                    $insurance = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    
                    $quantity = $med['quantity'];
                    $unit_price = floatval($medication['unit_price']);
                    $insurance_coverage = floatval($insurance['coverage_percentage']);
                    
                    // Calculate totals
                    $med_total = $unit_price * $quantity;
                    $total_amount += $med_total;
                    
                    $insurance_payout = round(($med_total * $insurance_coverage) / 100, 2);
                    $client_pay = round($med_total - $insurance_payout, 2);
                    
                    $insurance_payment += $insurance_payout;
                    $client_payment += $client_pay;
                    
                    $transaction_medications[] = [
                        'medication_id' => $med['id'],
                        'quantity' => $quantity,
                        'price' => $unit_price
                    ];
                }
                
                // Handle prescription attachment
                // Assuming one attachment per transaction; modify if multiple are allowed
                $attachment_path = !empty($attachment_paths) ? $attachment_paths[0] : null;
                
                // Insert into transactions table
                $stmt = $connection->prepare("
                    INSERT INTO transactions 
                        (client_id, pharmacy_user_id, transaction_date, prescription_attachment, client_payment, insurance_payout, pharmacy_id, insurance_id, total_amount)
                    VALUES 
                        (:client_id, :user_id, NOW(), :prescription_attachment, :client_payment, :insurance_payout, :pharmacy_id, :insurance_id, :total_amount)
                ");
                $stmt->bindParam(':client_id', $client_id, PDO::PARAM_INT);
                $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
                $stmt->bindParam(':prescription_attachment', $attachment_path, PDO::PARAM_STR);
                $stmt->bindParam(':client_payment', $client_payment, PDO::PARAM_STR);
                $stmt->bindParam(':insurance_payout', $insurance_payment, PDO::PARAM_STR);
                $stmt->bindParam(':pharmacy_id', $pharmacy_id, PDO::PARAM_INT);
                $stmt->bindParam(':insurance_id', $insurance_id, PDO::PARAM_INT);
                $stmt->bindParam(':total_amount', $total_amount, PDO::PARAM_STR);
                $stmt->execute();
                
                // Get the transaction ID
                $transaction_id = $connection->lastInsertId();
                
                // Insert into transaction_medications table
                $stmt = $connection->prepare("
                    INSERT INTO transaction_medications 
                        (transaction_id, medication_id, quantity, price)
                    VALUES 
                        (:transaction_id, :medication_id, :quantity, :price)
                ");
                
                foreach ($transaction_medications as $tm) {
                    $stmt->bindParam(':transaction_id', $transaction_id, PDO::PARAM_INT);
                    $stmt->bindParam(':medication_id', $tm['medication_id'], PDO::PARAM_INT);
                    $stmt->bindParam(':quantity', $tm['quantity'], PDO::PARAM_INT);
                    $stmt->bindParam(':price', $tm['price'], PDO::PARAM_STR);
                    $stmt->execute();
                }
                
                // Commit Transaction
                $connection->commit();
                
                // Generate Receipt HTML
                ob_start();
    
                // Fetching client information
                $stmt = $connection->prepare("
                    SELECT c.client_id, c.first_name, c.last_name, c.email, c.insurance_id, i.insurance_name, i.coverage_percentage
                    FROM clients c
                    JOIN insurance_companies i ON c.insurance_id = i.insurance_id
                    WHERE c.client_id = :client_id
                    LIMIT 1
                ");
                $stmt->bindParam(':client_id', $client_id, PDO::PARAM_INT);
                $stmt->execute();
                $client_info = $stmt->fetch(PDO::FETCH_ASSOC);
                
                // Fetching pharmacy information
                $stmt = $connection->prepare("SELECT pharmacy_name FROM pharmacies WHERE pharmacy_id = :pharmacy_id");
                $stmt->bindParam(':pharmacy_id', $pharmacy_id, PDO::PARAM_INT);
                $stmt->execute();
                $pharmacy = $stmt->fetch(PDO::FETCH_ASSOC);
                
                // Fetching medications for receipt
                $stmt = $connection->prepare("
                    SELECT m.medication_name, tm.quantity, tm.price
                    FROM transaction_medications tm
                    JOIN medications m ON tm.medication_id = m.medication_id
                    WHERE tm.transaction_id = :transaction_id
                ");
                $stmt->bindParam(':transaction_id', $transaction_id, PDO::PARAM_INT);
                $stmt->execute();
                $receipt_medications = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
                ?>
                <div>
                    <h3>Transaction Receipt</h3>
                    <p><strong>Pharmacy Name:</strong> <?php echo htmlspecialchars($pharmacy['pharmacy_name']); ?></p>
    
                    <p><strong>Transaction ID:</strong> <?php echo htmlspecialchars($transaction_id); ?></p>
                    <p><strong>Client:</strong> <?php echo htmlspecialchars($client_info['first_name'] . ' ' . $client_info['last_name']); ?></p>
                    <p><strong>Insurance Company:</strong> <?php echo htmlspecialchars($client_info['insurance_name']); ?> (<?php echo htmlspecialchars($client_info['coverage_percentage']); ?>% Coverage)</p>
                    <p><strong>Date:</strong> <?php echo htmlspecialchars(date("Y-m-d H:i:s")); ?></p>
    
                    <hr>
                    <table class="table table-bordered">
                        <thead>
                            <tr>
                                <th>Medication</th>
                                <th>Quantity</th>
                                <th>Unit Price (Rwf)</th>
                                <th>Total (Rwf)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            foreach ($receipt_medications as $med): 
                                $med_total = $med['quantity'] * $med['price'];
                            ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($med['medication_name']); ?></td>
                                    <td><?php echo htmlspecialchars($med['quantity']); ?></td>
                                    <td><?php echo number_format($med['price'], 2); ?></td>
                                    <td><?php echo number_format($med_total, 2); ?></td>
                                </tr>
                            <?php endforeach; ?>
                            <tr>
                                <td colspan="3" class="text-end"><strong>Client Payment (<?php echo htmlspecialchars(100 - $client_info['coverage_percentage']); ?>%)</strong></td>
                                <td><strong>Rwf<?php echo number_format($client_payment, 2); ?></strong></td>
                            </tr>
                            <tr>
                                <td colspan="3" class="text-end"><strong>Insurance Payment (<?php echo htmlspecialchars($client_info['coverage_percentage']); ?>%)</strong></td>
                                <td><strong>Rwf<?php echo number_format($insurance_payment, 2); ?></strong></td>
                            </tr>
                            <tr>
                                <td colspan="3" class="text-end"><strong>Total Amount</strong></td>
                                <td><strong>Rwf<?php echo number_format($total_amount, 2); ?></strong></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <?php
                $receipt_html = ob_get_clean();
                
                // Store receipt in session to display it
                $_SESSION['receipt'] = $receipt_html;
                
                // Redirect to show receipt
                header("Location: transactions.php?success=1");
                exit();
                
            } catch (PDOException $e) {
                $connection->rollBack();
                $errors[] = "Failed to record transaction: " . $e->getMessage();
            } catch (Exception $e) {
                $connection->rollBack();
                $errors[] = "Error: " . $e->getMessage();
            }
        }
    }
}

// Check if receipt needs to be displayed
$show_receipt = false;
if (isset($_GET['success']) && $_GET['success'] == 1 && isset($_SESSION['receipt'])) {
    $show_receipt = true;
    $receipt_html = $_SESSION['receipt'];
    unset($_SESSION['receipt']);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Record Transaction</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome for Icons -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link rel="icon" type="image/x-icon" href="../images/logo-removebg-preview.png">
    <link rel="stylesheet" href="../css/style.css">
    <!-- Custom CSS -->

    
    
    <style>
        body {
            background-color: #f8f9fa;
        }
        .container {
            margin-top: 50px;
        }
        .list-group-item:hover {
            cursor: pointer;
            background-color: #f1f1f1;
        }
        /* Spinner for loading medications */
        #medications-spinner {
            display: none;
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
        
            <h1>Record Transaction</h1>
            
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
            <!-- Display Success Message -->
            <?php if ($show_receipt && $receipt_html): ?>
                <div class="alert alert-success">
                    Transaction recorded successfully.
                </div>
            <?php endif; ?>
            
            <!-- Search Client Form -->
            <form method="POST" class="mb-4">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                <div class="row g-3 align-items-center">
                    <div class="col-auto">
                        <label for="id" class="col-form-label">Client ID:</label>
                    </div>
                    <div class="col-auto">
                        <input type="number" id="id" name="id" class="form-control" placeholder="Enter client ID" required>
                    </div>
                    <div class="col-auto">
                        <button type="submit" name="search_client" class="btn btn-primary">Search</button>
                    </div>
                </div>
            </form>
            
            <?php if ($client): ?>
                <div class="card mb-4">
                    <div class="card-header">
                        <h5>Client Information</h5>
                    </div>
                    <div class="card-body">
                        <p><strong>Name:</strong> <?php echo htmlspecialchars($client['first_name'] . ' ' . $client['last_name']); ?></p>
                        <p><strong>Email:</strong> <?php echo htmlspecialchars($client['email']); ?></p>
                        <p><strong>Insurance Company:</strong> 
                                <?php echo htmlspecialchars($client['insurance_name']); ?> 
                                (<?php echo htmlspecialchars($client['coverage_percentage']); ?>% Coverage)
                        </p>
                    
                    </div>
                </div>
                
                <!-- Medication Selection Form with Search -->
                <form method="POST" enctype="multipart/form-data" id="transactionForm">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                    <input type="hidden" name="client_id" value="<?php echo htmlspecialchars($client['client_id']); ?>">
                    <input type="hidden" name="insurance_id" value="<?php echo htmlspecialchars($client['insurance_id']); ?>">
                    
                    <!-- Medication Entries Container -->
                    <div id="medicationsContainer">
                        <div class="mb-3 medication-entry">
                            <label class="form-label">Search Medication</label>
                            <div class="input-group">
                                <input type="text" class="form-control medication-search" placeholder="Type medication name" oninput="fetchMedications(this)" autocomplete="off">
                                <button type="button" class="btn btn-outline-secondary add-medication">Add</button>
                            </div>
                            <div class="list-group medicationsList mt-1"></div>
                            <div class="mb-3 mt-2">
                                <label class="form-label">Quantity</label>
                                <input type="number" class="form-control medication-quantity" name="quantity[]" min="1" required>
                            </div>
                            <button type="button" class="btn btn-danger remove-medication">Remove Medication</button>
                            <hr>
                        </div>
                    </div>
                    
                    <!-- Button to Add More Medications -->
                    <button type="button" class="btn btn-secondary mb-3" id="addMedicationBtn">Add Another Medication</button>
                    
                    <!-- Prescription Attachment -->
                    <div class="mb-3">
                        <label for="prescription_attachment" class="form-label">Prescription Attachment:</label>
                        <input class="form-control" type="file" id="prescription_attachment" name="prescription_attachment[]" accept=".pdf,.jpg,.jpeg,.png">
                        <div class="form-text">Optional. Allowed file types: PDF, JPG, PNG. Max size: 2MB per file.</div>
                    </div>
                    
                    <button type="submit" name="record_transaction" class="btn btn-success">Record Transaction</button>
                </form>
            <?php endif; ?>
        
        
            <!-- Receipt Modal -->
            <div class="modal fade" id="receiptModal" tabindex="-1" aria-labelledby="receiptModalLabel" aria-hidden="true">
                <div class="modal-dialog modal-lg">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">Transaction Receipt</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body" id="receiptContent">
                            <!-- Receipt content will be injected here -->
                            <?php 
                            if ($show_receipt && $receipt_html):
                                echo $receipt_html;
                            endif;
                            ?>
                        </div>
                        <div class="modal-footer">
                            <button class="btn btn-primary" onclick="printReceipt()">Print Receipt</button>
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
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
    <!-- jQuery (for easier DOM manipulation) -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    
    <script>
        // Function to fetch medications based on search term
        function fetchMedications(inputElement) {
            const searchTerm = inputElement.value;
            if (searchTerm.length >= 1) {
                // Make AJAX request to fetch medications
                $.ajax({
                    url: 'fetch_medications.php',
                    type: 'GET',
                    data: { search: searchTerm },
                    dataType: 'json',
                    success: function(response) {
                        let output = '';
                        if(response.medications.length > 0){
                            response.medications.forEach(function(medication){
                                output += `<a href="#" class="list-group-item list-group-item-action" onclick="selectMedication(event, ${medication.medication_id}, '${medication.medication_name}', ${medication.unit_price})">${medication.medication_name} - <strong>${parseFloat(medication.unit_price).toFixed(2)} RWF</strong></a>`;
                            });
                        } else {
                            output += `<div class="list-group-item">No medications found.</div>`;
                        }
                        inputElement.closest('.medication-entry').querySelector('.medicationsList').innerHTML = output;
                    },
                    error: function(xhr, status, error) {
                        console.error('Error fetching medications:', error);
                        inputElement.closest('.medication-entry').querySelector('.medicationsList').innerHTML = `<div class="list-group-item text-danger">Error fetching medications.</div>`;
                    }
                });
            } else {
                inputElement.closest('.medication-entry').querySelector('.medicationsList').innerHTML = '';
            }
        }

        // Function to select medication and populate the input
        function selectMedication(event, medicationId, medicationName, unitPrice) {
            event.preventDefault(); // Prevent default link behavior
            const entry = event.target.closest('.medication-entry');
            entry.querySelector('.medication-search').value = medicationName;
            // Add hidden input for medication_id
            if (!entry.querySelector('.medication_id')) {
                const hiddenInput = document.createElement('input');
                hiddenInput.type = 'hidden';
                hiddenInput.name = 'medication_id[]';
                hiddenInput.classList.add('medication_id');
                hiddenInput.value = medicationId;
                entry.appendChild(hiddenInput);
            } else {
                entry.querySelector('.medication_id').value = medicationId;
            }
            // Set default quantity if not already set
            const quantityInput = entry.querySelector('.medication-quantity');
            if (!quantityInput.value) {
                quantityInput.value = 1;
            }
            // Clear medications list
            entry.querySelector('.medicationsList').innerHTML = '';
        }

        // Function to escape HTML to prevent XSS
        function escapeHtml(text) {
            const map = {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            };
            return text.replace(/[&<>"']/g, function(m) { return map[m]; });
        }

        // Function to add more medication fields
        $(document).ready(function(){
            $('#addMedicationBtn').click(function(){
                const medicationsContainer = $('#medicationsContainer');
                const firstEntry = $('.medication-entry').first();
                const newEntry = firstEntry.clone();
                newEntry.find('input').val(''); // Clear input values
                newEntry.find('.list-group').html(''); // Clear medications list
                newEntry.find('.medication_id').remove(); // Remove hidden medication_id
                medicationsContainer.append(newEntry);
            });

            // Remove medication entry
            $(document).on('click', '.remove-medication', function(){
                if ($('.medication-entry').length > 1) {
                    $(this).closest('.medication-entry').remove();
                } else {
                    alert('You must have at least one medication entry.');
                }
            });
        });

        // Show receipt modal if transaction was successful
        <?php if ($show_receipt && $receipt_html): ?>
            $(document).ready(function(){
                var receiptModal = new bootstrap.Modal(document.getElementById('receiptModal'), {
                    keyboard: false
                });
                receiptModal.show();
            });
        <?php endif; ?>

        // Function to print the receipt
        function printReceipt() {
            var printContents = document.getElementById('receiptContent').innerHTML;
            var originalContents = document.body.innerHTML;
            
            document.body.innerHTML = printContents;
            window.print();
            document.body.innerHTML = originalContents;
            
            // Reload the page to reset the state
            window.location.reload();
        }
    </script>
</body>
</html>
