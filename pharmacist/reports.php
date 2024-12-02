<?php
// File: reports.php
session_start();
require_once '../include/connection.php'; // Adjust the path as necessary


// Include the Composer autoloader to load PhpSpreadsheet classes
require_once '../vendor/autoload.php'; // Adjust the path to your autoload.php file

// Use PhpSpreadsheet classes
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

// Ensure only authenticated pharmacy staff can access this page
if (!isset($_SESSION['role']) || (!isset($_SESSION['pharmacy_id']) ) ) {
    header("Location: ../login.php");
    exit();
}

// Generate CSRF token if not set
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Initialize variables
$errors = [];
$transactions = [];

// Handle form submission for filtering
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['filter_reports'])) {
    // CSRF Token Validation
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $errors[] = "Invalid CSRF token.";
    } else {
        // Retrieve and sanitize inputs
        $start_date = isset($_POST['start_date']) ? trim($_POST['start_date']) : '';
        $end_date = isset($_POST['end_date']) ? trim($_POST['end_date']) : '';
        $client_id = isset($_POST['client_id']) ? intval($_POST['client_id']) : 0;
        $insurance_id = isset($_POST['insurance_id']) ? intval($_POST['insurance_id']) : 0;
        $pharmacy_id = isset($_SESSION['pharmacy_id']) ? intval($_SESSION['pharmacy_id']) : 0; // Assuming pharmacy_id comes from session
        
        // echo $insurance_id;
        
        // Validate date inputs
        if ($start_date && !validateDate($start_date)) {
            $errors[] = "Invalid start date format.";
        }
        if ($end_date && !validateDate($end_date)) {
            $errors[] = "Invalid end date format.";
        }
        if ($start_date && $end_date && $start_date > $end_date) {
            $errors[] = "Start date cannot be later than end date.";
        }
    
        // Build the SQL query with dynamic conditions
        $sql = "
            SELECT 
                t.transaction_id,
                t.transaction_date,
                c.first_name,
                c.last_name,
                i.insurance_name,
                m.medication_name,
                tm.quantity,
                tm.price,
                tm.status,
                tm.rejection_comment
            FROM 
                transaction_medications tm
            JOIN 
                transactions t ON tm.transaction_id = t.transaction_id
            JOIN 
                clients c ON t.client_id = c.client_id
            JOIN 
                insurance_companies i ON t.insurance_id = i.insurance_id
            JOIN
                medications m ON tm.medication_id = m.medication_id
            WHERE 
                t.pharmacy_id = :pharmacy_id
        ";
    
        // Initialize the parameters array
        $params = [':pharmacy_id' => $pharmacy_id];
    
        // Dynamically add conditions and bind parameters
        if ($start_date) {
            $sql .= " AND t.transaction_date >= :start_date";
            $params[':start_date'] = $start_date . ' 00:00:00';
        }
        if ($end_date) {
            $sql .= " AND t.transaction_date <= :end_date";
            $params[':end_date'] = $end_date . ' 23:59:59';
        }
        if ($client_id > 0) {
            $sql .= " AND c.client_id = :client_id";
            $params[':client_id'] = $client_id;
        }
        if ($insurance_id > 0) {
            $sql .= " AND t.insurance_id = :insurance_id";
            $params[':insurance_id'] = $insurance_id;
        }
    
        // Add final ordering clause
        $sql .= " ORDER BY t.transaction_date DESC";
    
        try {
            // Prepare the statement
            $stmt = $connection->prepare($sql);
    
            // Bind parameters using bindValue for more straightforward values
            foreach ($params as $key => $val) {
                $stmt->bindValue($key, $val);
            }
    
            // Execute the query
            $stmt->execute();
            
            // Fetch the data
            $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
            // Check if any data was returned
            if (empty($transactions)) {
                $errors[] = "No data found for the selected criteria.";
            }
    
        } catch (PDOException $e) {
            $errors[] = "Failed to fetch transactions: " . $e->getMessage();
        }
    }
    
}

// Function to validate date format (YYYY-MM-DD)
function validateDate($date, $format = 'Y-m-d') {
    $d = DateTime::createFromFormat($format, $date);
    return $d && $d->format($format) === $date;
}

// Handle Export to PDF
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['export_pdf'])) {
    // CSRF Token Validation
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $errors[] = "Invalid CSRF token.";
    } else {
        if(empty($_POST['insurance_id'])){ 
            
            $errors[] = "Failed to export PDF, No Insurance company choosen. Choose one and resubmit your request!" ;

        } else {
            
        // Retrieve filter inputs
        $start_date = isset($_POST['start_date']) ? trim($_POST['start_date']) : '';
        $end_date = isset($_POST['end_date']) ? trim($_POST['end_date']) : '';
        $client_id = isset($_POST['client_id']) ? intval($_POST['client_id']) : 0;
        $insurance_id = isset($_POST['insurance_id']) ? intval($_POST['insurance_id']) : 0;
        $pharmacy_id = isset($_SESSION['pharmacy_id']);
        
        // Build the SQL query with dynamic conditions (same as above)
        $sql = "
            SELECT 
                t.transaction_id,
                t.transaction_date,
                c.first_name,
                c.last_name,
                i.insurance_name,
                i.coverage_percentage,
                m.medication_name,
                tm.quantity,
                tm.price,
                tm.status,
                tm.rejection_comment,
                p.pharmacy_name
            FROM 
                transaction_medications tm
            JOIN 
                transactions t ON tm.transaction_id = t.transaction_id
            JOIN 
                clients c ON t.client_id = c.client_id
            JOIN 
                insurance_companies i ON t.insurance_id = i.insurance_id
            JOIN
                medications m ON tm.medication_id = m.medication_id
            JOIN
                pharmacies p ON t.pharmacy_id = p.pharmacy_id
            WHERE 
                tm.status = 'approved' AND 1=1
        ";
        $params = [];

        if ($start_date) {
            $sql .= " AND t.transaction_date >= :start_date";
            $params[':start_date'] = $start_date . ' 00:00:00';
        }
        if ($end_date) {
            $sql .= " AND t.transaction_date <= :end_date";
            $params[':end_date'] = $end_date . ' 23:59:59';
        }
        if ($client_id > 0) {
            $sql .= " AND c.client_id = :client_id";
            $params[':client_id'] = $client_id;
        }
        if ($pharmacy_id > 0) {
            $sql .= " AND t.pharmacy_id = :pharmacy_id";
            $params[':pharmacy_id'] = $pharmacy_id;
        }
        if ($insurance_id > 0) {
            $sql .= " AND i.insurance_id = :insurance_id";
            $params[':insurance_id'] = $insurance_id;
        }

        $sql .= " ORDER BY t.transaction_date DESC";

        try {
            $stmt = $connection->prepare($sql);
            foreach ($params as $key => &$val) {
                $stmt->bindParam($key, $val);
            }
            $stmt->execute();
            $export_data = $stmt->fetchAll();
            
            // Generate PDF using TCPDF
            require_once('../vendor/tecnickcom/tcpdf/tcpdf.php'); // Adjust the path as necessary

            // Create new PDF document
            $pdf = new TCPDF('L'); // 'L' for landscape
            $pdf->SetCreator(PDF_CREATOR);
            $pdf->SetAuthor('MIMS Pharmacy');
            $pdf->SetTitle('MIMS Pharmacy');
            $pdf->SetHeaderData('', 0,  htmlspecialchars($export_data[0]['pharmacy_name']). ' - '. htmlspecialchars($export_data[0]['insurance_name']). ' Approved Claims Report ', '');

            // Set header and footer fonts
            $pdf->setHeaderFont(Array(PDF_FONT_NAME_MAIN, '', PDF_FONT_SIZE_MAIN));
            $pdf->setFooterFont(Array(PDF_FONT_NAME_DATA, '', PDF_FONT_SIZE_DATA));

            // Set default monospaced font
            $pdf->SetDefaultMonospacedFont(PDF_FONT_MONOSPACED);

            // Set margins
            $pdf->SetMargins(PDF_MARGIN_LEFT, PDF_MARGIN_TOP, PDF_MARGIN_RIGHT);
            $pdf->SetHeaderMargin(PDF_MARGIN_HEADER);
            $pdf->SetFooterMargin(PDF_MARGIN_FOOTER);

            // Set auto page breaks
            $pdf->SetAutoPageBreak(TRUE, PDF_MARGIN_BOTTOM);

            // Set image scale factor
            $pdf->setImageScale(PDF_IMAGE_SCALE_RATIO);

            // Add a page with landscape orientation
            $pdf->AddPage('L'); // 'L' for landscape

            // Set font
            $pdf->SetFont('helvetica', '', 10);

            // Create HTML content
            $html = "<h2>Approved Claims Report: " . htmlspecialchars($start_date . " - " . $end_date) . "</h2>";

            $html .= '<table border="1" cellpadding="4">
                        <thead>
                            <tr>
                                <th><strong>Transaction ID</strong></th>
                                <th><strong>Date</strong></th>
                                <th><strong>Client Name</strong></th>
                                <th><strong>Insurance Company</strong></th>
                                <th><strong>Medication</strong></th>
                                <th><strong>Quantity</strong></th>
                                <th><strong>Unit Price (RWF)</strong></th>
                                <th><strong>Insurance Payout (RWF)</strong></th>
                                <th><strong>Status</strong></th>
                            </tr>
                        </thead>
                        <tbody>';

        // Calculating the sum
        $total_insurance_payout = 0; 
        foreach ($export_data as $row) {
            $insurance_payout = ($row['quantity'] * $row['price'] * $row['coverage_percentage']) / 100;
            $html .= '<tr>
                        <td>' . htmlspecialchars($row['transaction_id']) . '</td>
                        <td>' . htmlspecialchars($row['transaction_date']) . '</td>
                        <td>' . htmlspecialchars($row['first_name'] . ' ' . $row['last_name']) . '</td>
                        <td>' . htmlspecialchars($row['insurance_name']) . '</td>
                        <td>' . htmlspecialchars($row['medication_name']) . '</td>
                        <td>' . htmlspecialchars($row['quantity']) . '</td>
                        <td>' . number_format($row['price'], 2) . '</td>
                        <td>' . number_format($insurance_payout, 2) . '</td>
                        <td>' . htmlspecialchars(ucfirst($row['status'])) . '</td>
                    </tr>';
            $total_insurance_payout += $insurance_payout;
        }

        $html .= '</tbody>
                    <tfoot>
                        <tr>
                            <td colspan="7" style="font-size: 15px; color: blue;"><strong>Total Insurance Payout (RWF)</strong></td>
                            <td colspan="2" style="font-size: 15px; color: blue;"><strong>' . number_format($total_insurance_payout, 2) . '</strong></td>
                        </tr>
                    </tfoot>
                </table>';


            // Output the HTML content
            $pdf->writeHTML($html, true, false, true, false, '');

            // Output PDF document
            $pdf->Output('approved_claims_report.pdf', 'D'); // D for download

            exit(); // Ensure no further output
        } catch (PDOException $e) {
            $errors[] = "Failed to export PDF: " . $e->getMessage();
        }
        }
    }
}


// Handle Export to Excel
// Handle Export to Excel
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['export_excel'])) {
    // CSRF Token Validation
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $errors[] = "Invalid CSRF token.";
    } else {
        if(empty($_POST['insurance_id'])){ 
            
            $errors[] = "Failed to export Excel sheet, No Insurance company choosen. Choose one and resubmit your request!" ;

        } else {
            // Retrieve and sanitize filter inputs
            $start_date = isset($_POST['start_date']) ? trim($_POST['start_date']) : '';
            $end_date = isset($_POST['end_date']) ? trim($_POST['end_date']) : '';
            $client_id = isset($_POST['client_id']) ? intval($_POST['client_id']) : 0;
            $insurance_id = isset($_POST['insurance_id']) ? intval($_POST['insurance_id']) : 0;
        
            // Build the SQL query with dynamic conditions
            $sql = "
                SELECT 
                    t.transaction_id,
                    t.transaction_date,
                    c.first_name,
                    c.last_name,
                    i.insurance_name,
                    i.coverage_percentage,
                    m.medication_name,
                    tm.quantity,
                    tm.price,
                    tm.status,
                    tm.rejection_comment,
                    p.pharmacy_name
                FROM 
                    transaction_medications tm
                JOIN 
                    transactions t ON tm.transaction_id = t.transaction_id
                JOIN 
                    clients c ON t.client_id = c.client_id
                JOIN 
                    insurance_companies i ON t.insurance_id = i.insurance_id
                JOIN 
                    medications m ON tm.medication_id =  m.medication_id
                JOIN pharmacies p ON t.pharmacy_id = p.pharmacy_id
                WHERE 
                    tm.status = 'approved'
            ";
            $params = [];
        
            if ($start_date) {
                $sql .= " AND t.transaction_date >= :start_date";
                $params[':start_date'] = $start_date . ' 00:00:00';
            }
            if ($end_date) {
                $sql .= " AND t.transaction_date <= :end_date";
                $params[':end_date'] = $end_date . ' 23:59:59';
            }
            if ($client_id > 0) {
                $sql .= " AND c.client_id = :client_id";
                $params[':client_id'] = $client_id;
            }
            if ($insurance_id > 0) {
                $sql .= " AND i.insurance_id = :insurance_id";
                $params[':insurance_id'] = $insurance_id;
            }
        
            $sql .= " ORDER BY t.transaction_date DESC";
        
            try {
                // Prepare and execute the SQL statement
                $stmt = $connection->prepare($sql);
                foreach ($params as $key => $val) {
                    // Use bindValue instead of bindParam
                    $stmt->bindValue($key, $val);
                }
                $stmt->execute();
                $export_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
                // Generate Excel using PhpSpreadsheet
                $spreadsheet = new Spreadsheet();
                $sheet = $spreadsheet->getActiveSheet();
        
                // Set header row
                $headers = [
                    'A1' => 'Transaction ID',
                    'B1' => 'Date',
                    'C1' => 'Client Name',
                    'D1' => 'Insurance Company',
                    'E1' => 'Medication',
                    'F1' => 'Quantity',
                    'G1' => 'Unit Price (RWF)',
                    'H1' => 'Total Price (RWF)',
                    'I1' => 'Insurance Coverage Amount (RWF)',
                    'J1' => 'Status',
                ];
        
                foreach ($headers as $cell => $header) {
                    $sheet->setCellValue($cell, $header);
                }
        
                // Assuming your SQL execution and data fetching have been done and you have $export_data
                // Generate a new spreadsheet
                $spreadsheet = new Spreadsheet();
                $sheet = $spreadsheet->getActiveSheet();
                $sheet->setCellValue('A1', $export_data[0]['pharmacy_name'] .' - '. $export_data[0]['insurance_name'] .' Approved Claims Report: from '. $start_date .' to '. $end_date);
                // Set header titles
                $sheet->setCellValue('A3', 'Transaction ID');
                $sheet->setCellValue('B3', 'Date');
                $sheet->setCellValue('C3', 'Client Name');
                $sheet->setCellValue('D3', 'Insurance Company');
                $sheet->setCellValue('E3', 'Medication');
                $sheet->setCellValue('F3', 'Quantity');
                $sheet->setCellValue('G3', 'Unit Price (RWF)');
                $sheet->setCellValue('H3', 'Total Price (RWF)');
                $sheet->setCellValue('I3', 'Insurance Coverage Amount (RWF)');
                $sheet->setCellValue('J3', 'Status');

                // Populate data rows
                $row_num = 4;
                $total_insurance_payout = 0;
                foreach ($export_data as $row) {
                    $Amount = $row['quantity'] * $row['price'];
                    $insurance_payout = ($Amount * number_format($row['coverage_percentage']))/100;
                    $sheet->setCellValue('A' . $row_num, $row['transaction_id']);
                    $sheet->setCellValue('B' . $row_num, $row['transaction_date']);
                    $sheet->setCellValue('C' . $row_num, $row['first_name'] . ' ' . $row['last_name']);
                    $sheet->setCellValue('D' . $row_num, $row['insurance_name']);
                    $sheet->setCellValue('E' . $row_num, $row['medication_name']);
                    $sheet->setCellValue('F' . $row_num, $row['quantity']);
                    $sheet->setCellValue('G' . $row_num, number_format($row['price'], 2));
                    $sheet->setCellValue('H' . $row_num, number_format($Amount, 2));
                    $sheet->setCellValue('I' . $row_num, number_format($insurance_payout, 2));
                    $sheet->setCellValue('J' . $row_num, ucfirst($row['status']));
                    $total_insurance_payout += $insurance_payout; 
                    $row_num++;
                }

                // Populate the total insurance payout columns
                $sheet->setCellValue('A' . $row_num, 'Total Insurance coverage');
                $sheet->setCellValue('I' . $row_num, number_format($total_insurance_payout, 2) ." Frw");

                // Auto size columns
                foreach (range('A', 'J') as $columnID) {
                    $sheet->getColumnDimension($columnID)->setAutoSize(true);
                }

                // Create the writer
                $writer = new Xlsx($spreadsheet);

                // Set headers for file download
                header('Content-Description: File Transfer');
                header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
                header('Content-Disposition: attachment; filename="approved_claims_report_' . date('Y-m-d_H-i-s') . '.xlsx"');
                header('Expires: 0');
                header('Cache-Control: must-revalidate');
                header('Pragma: public');

                // Output the Excel file directly to the browser
                $writer->save('php://output');

                // Ensure no other content is sent after the file output
                exit();

                    
            
            } catch (Exception $e) {
                // Handle exceptions and display user-friendly error messages
                echo '<div class="alert alert-danger">Error generating the Excel report: ' . htmlspecialchars($e->getMessage()) . '</div>';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports Management</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- DataTables CSS -->
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap5.min.css">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="../css/style.css">

    <link rel="icon" type="image/x-icon" href="../images/logo-removebg-preview.png">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
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
        .export-buttons {
            margin-bottom: 20px;
        }

        #insurance-list {
    position: absolute;
    z-index: 1000;
    background-color: white;
    border: 1px solid #ccc;
    width: 80%; /* Adjusted width for small screens */
    max-width: 300px; /* Optional: Set a maximum width */
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1); /* Add some shadow for better visibility */
    border-radius: 4px; /* Rounded corners */
    }

    .list-group-item {
        padding: 10px; /* Increased padding for better touch targets */
        cursor: pointer;
        font-size: 14px; /* Slightly larger font for readability */
    }

    .list-group-item:hover {
        background-color: #f1f1f1;
    }

    /* Media query for screens smaller than 600px */
    @media (max-width: 600px) {
        #insurance-list {
            width: 90%; /* Take up more space on smaller screens */
            left: 10%; /* Center the list */
        }

        .list-group-item {
            font-size: 16px; /* Increase font size for better readability on small screens */
            padding: 12px; /* Add more padding for touch targets */
        }
    }


    </style>
</head>
<body>

<!-- Navigation -->
<?php include './header.php'; ?>

<!-- Main Content -->
<div class="container-fluid">


    <div class="row">
    
    <!-- Navigation bar -->

    <?php include './nav.php'; ?>
    <?php include './mobile.php'; ?>

    <main class="col-lg-10 ms-auto main-content p-4">
            
        <div class="container">
            <h1>Reports Management</h1>
            
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


            <!-- Filter Form -->
            <form method="POST" class="mb-4">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                <div class="row g-3 align-items-center">
                    <div class="col-md-3">
                        <label for="start_date" class="form-label">Start Date:</label>
                        <input type="date" id="start_date" name="start_date" class="form-control" value="<?php echo isset($_POST['start_date']) ? htmlspecialchars($_POST['start_date']) : ''; ?>">
                    </div>
                    <div class="col-md-3">
                        <label for="end_date" class="form-label">End Date:</label>
                        <input type="date" id="end_date" name="end_date" class="form-control" value="<?php echo isset($_POST['end_date']) ? htmlspecialchars($_POST['end_date']) : ''; ?>">
                    </div>
                    <div class="col-md-3">
                        <label for="client_id" class="form-label">Client ID:</label>
                        <input type="number" id="client_id" name="client_id" class="form-control" min="1" value="<?php echo isset($_POST['client_id']) ? htmlspecialchars($_POST['client_id']) : ''; ?>">
                    </div>
                    <br>
                    <div class="col-md-3">
                        <label for="insurance_name" class="form-label">Insurance Company:</label>
                        <input type="text" id="insurance_name" name="insurance_name" class="form-control" autocomplete="off">
                        <div id="insurance-list" class="autocomplete-items"></div> <!-- This is where suggestions will be shown -->
                        <input type="hidden" id="selected_insurance_id" name="insurance_id" value="" required>
                    </div>
                </div>
                <button type="submit" name="filter_reports" class="btn btn-primary mt-3">Filter Reports</button>
            </form>



            <!-- Export Buttons -->
            <div class="export-buttons">
                <?php if (!empty($transactions)): ?>
                    <form method="POST" style="display: inline;">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                        <input type="hidden" name="start_date" value="<?php echo htmlspecialchars($start_date); ?>">
                        <input type="hidden" name="end_date" value="<?php echo htmlspecialchars($end_date); ?>">
                        <input type="hidden" name="client_id" value="<?php echo htmlspecialchars($client_id); ?>">
                        <input type="hidden" name="insurance_id" value="<?php echo htmlspecialchars($insurance_id); ?>">
                        <button type="submit" name="export_pdf" class="btn btn-danger">Export to PDF</button>
                    </form>
                    <form method="POST" style="display: inline;">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                        <input type="hidden" name="start_date" value="<?php echo htmlspecialchars($start_date); ?>">
                        <input type="hidden" name="end_date" value="<?php echo htmlspecialchars($end_date); ?>">
                        <input type="hidden" name="client_id" value="<?php echo htmlspecialchars($client_id); ?>">
                        <input type="hidden" name="insurance_id" value="<?php echo htmlspecialchars($insurance_id); ?>">
                        <button type="submit" name="export_excel" class="btn btn-success">Export to Excel</button>
                    </form>
                <?php endif; ?>
            </div>

            <!-- Transactions Table -->
            <div class="table-responsive my-4">
                <table id="claimsTable" class="table table-bordered table-hover align-middle">
                    <thead class="table-dark">
                        <tr>
                            <th>Transaction ID</th>
                            <th>Date</th>
                            <th>Client Name</th>
                            <th>Insurance Company</th>
                            <th>Medication</th>
                            <th>Quantity</th>
                            <th>Unit Price (RWF)</th>
                            <th>Status</th>
                            <th>Rejection Comment</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($transactions)): ?>
                            <tr>
                                <td colspan="9" class="text-center">No transactions found.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($transactions as $trans): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($trans['transaction_id']); ?></td>
                                    <td><?php echo htmlspecialchars($trans['transaction_date']); ?></td>
                                    <td><?php echo htmlspecialchars($trans['first_name'] . ' ' . $trans['last_name']); ?></td>
                                    <td><?php echo htmlspecialchars($trans['insurance_name']); ?></td>
                                    <td><?php echo htmlspecialchars($trans['medication_name']); ?></td>
                                    <td><?php echo htmlspecialchars($trans['quantity']); ?></td>
                                    <td><?php echo number_format($trans['price'], 2); ?></td>
                                    <td>
                                        <?php
                                            switch ($trans['status']) {
                                                case 'pending':
                                                    echo '<span class="badge bg-warning text-dark">Pending</span>';
                                                    break;
                                                case 'approved':
                                                    echo '<span class="badge bg-success">Approved</span>';
                                                    break;
                                                case 'rejected':
                                                    echo '<span class="badge bg-danger">Rejected</span>';
                                                    break;
                                                default:
                                                    echo '<span class="badge bg-secondary">Unknown</span>';
                                            }
                                        ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($trans['rejection_comment']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
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
            // Set up the event listener for the insurance name input field
            $('#insurance_name').on('input', function() {
                var query = $(this).val();
                if (query.length > 0) { // Only fetch suggestions if there's input
                    $.ajax({
                        url: './fetch_insurances.php', // Path to your PHP script
                        type: 'GET',
                        data: { query: query }, // Pass the input value as a parameter
                        success: function(data) {
                            console.log(data); // Log the raw data received

                            // Parse the JSON response
                            var insuranceList = JSON.parse(data);

                            // Check if insuranceList is an array
                            if (Array.isArray(insuranceList)) {
                                $('#insurance-list').empty(); // Clear previous results

                                // Populate the suggestions
                                insuranceList.forEach(function(item) {
                                    $('#insurance-list').append(
                                        '<a href="#" class="list-group-item list-group-item-action insurance-item" data-id="' + item.insurance_id + '">' +
                                        item.insurance_name + 
                                        '</a>'
                                    );
                                });
                            } else {
                                console.error('Response is not an array:', insuranceList);
                                $('#insurance-list').empty(); // Clear the list if response is invalid
                            }
                        },
                        error: function(jqXHR, textStatus, errorThrown) {
                            console.error('AJAX request failed:', textStatus, errorThrown);
                            $('#insurance-list').empty(); // Clear the list on error
                        }
                    });
                } else {
                    // Clear suggestions if input is empty
                    $('#insurance-list').empty();
                }
            });

            // Event delegation for clicking on suggestions
            $(document).on('click', '.insurance-item', function(e) {
                e.preventDefault();
                var selectedInsurance = $(this).text();
                var insuranceId = $(this).data('id');

                // Set the selected insurance name in the input field
                $('#insurance_name').val(selectedInsurance);
                // Store the selected insurance ID in the hidden input field
                $('#selected_insurance_id').val(insuranceId);
                $('#insurance-list').empty(); // Clear the suggestions after selection

                // Optionally, you can do something with the selected insurance ID
                console.log('Selected Insurance ID:', insuranceId);
            });
        });





        $(document).ready(function() {
    // Check if the table contains rows (excluding the header)
    if ($('#claimsTable tbody tr').length > 0) {
        // Initialize DataTable if there is data
        $('#claimsTable').DataTable({
            "paging": true,  // Enables pagination
            "searching": true,  // Enables search functionality
            "ordering": true,   // Enables column sorting
            "lengthMenu": [5, 10, 25, 50],  // Page length options
            "pageLength": 5     // Default number of rows per page
        });
    }
});



    
    </script>
  

</body>
</html>
