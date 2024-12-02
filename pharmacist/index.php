<?php
session_start();

// Generate CSRF token if not set
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Check if the user is authenticated and is a pharmacy_admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'pharmacist') {
    header("Location: ../login.php"); // Redirect to login page
    exit();
}

$pharmacy_id = $_SESSION['pharmacy_id']; // Assuming pharmacy_id is stored in session

include '../include/connection.php'; // Include the database connection

// Function to fetch users (pharmacy_admin and pharmacist)
function getPharmacyUsers($connection, $pharmacy_id, $search = '', $limit = 10, $offset = 0) {
    $query = "SELECT * FROM pharmacy_users 
              WHERE pharmacy_id = :pharmacy_id 
              AND (first_name LIKE :search OR last_name LIKE :search OR email LIKE :search)
              ORDER BY first_name ASC 
              LIMIT :limit OFFSET :offset";
    $stmt = $connection->prepare($query);
    $search_param = "%$search%";
    $stmt->bindParam(':pharmacy_id', $pharmacy_id, PDO::PARAM_INT);
    $stmt->bindParam(':search', $search_param, PDO::PARAM_STR);
    $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Function to count total pharmacy users for pagination
function countPharmacyUsers($connection, $pharmacy_id, $search = '') {
    $query = "SELECT COUNT(*) FROM pharmacy_users 
              WHERE pharmacy_id = :pharmacy_id 
              AND (first_name LIKE :search OR last_name LIKE :search OR email LIKE :search)";
    $stmt = $connection->prepare($query);
    $search_param = "%$search%";
    $stmt->bindParam(':pharmacy_id', $pharmacy_id, PDO::PARAM_INT);
    $stmt->bindParam(':search', $search_param, PDO::PARAM_STR);
    $stmt->execute();
    return $stmt->fetchColumn();
}

// Function to fetch medications
function getMedications($connection, $search = '', $limit = 10, $offset = 0) {
    $query = "SELECT * FROM medications 
              WHERE medication_name LIKE :search OR description LIKE :search
              ORDER BY medication_name ASC 
              LIMIT :limit OFFSET :offset";
    $stmt = $connection->prepare($query);
    $search_param = "%$search%";
    $stmt->bindParam(':search', $search_param, PDO::PARAM_STR);
    $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Function to count total medications for pagination
function countMedications($connection, $search = '') {
    $query = "SELECT COUNT(*) FROM medications 
              WHERE medication_name LIKE :search OR description LIKE :search";
    $stmt = $connection->prepare($query);
    $search_param = "%$search%";
    $stmt->bindParam(':search', $search_param, PDO::PARAM_STR);
    $stmt->execute();
    return $stmt->fetchColumn();
}

// Handle success and error messages
$success = '';
$error = '';
if (isset($_SESSION['success'])) {
    $success = $_SESSION['success'];
    unset($_SESSION['success']);
}
if (isset($_SESSION['error'])) {
    $error = $_SESSION['error'];
    unset($_SESSION['error']);
}

// Pagination settings for users
$records_per_page = 10;
$current_page_users = isset($_GET['page_users']) ? (int)$_GET['page_users'] : 1;
if ($current_page_users < 1) $current_page_users = 1;
$search_users = isset($_GET['search_users']) ? trim($_GET['search_users']) : '';
$offset_users = ($current_page_users - 1) * $records_per_page;

// Pagination settings for medications
$current_page_medications = isset($_GET['page_medications']) ? (int)$_GET['page_medications'] : 1;
if ($current_page_medications < 1) $current_page_medications = 1;
$search_medications = isset($_GET['search_medications']) ? trim($_GET['search_medications']) : '';
$offset_medications = ($current_page_medications - 1) * $records_per_page;

// Fetch data
$pharmacy_users = getPharmacyUsers($connection, $pharmacy_id, $search_users, $records_per_page, $offset_users);
$total_users = countPharmacyUsers($connection, $pharmacy_id, $search_users);
$total_pages_users = ceil($total_users / $records_per_page);

$medications = getMedications($connection, $search_medications, $records_per_page, $offset_medications);
$total_medications = countMedications($connection, $search_medications);
$total_pages_medications = ceil($total_medications / $records_per_page);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pharmacist Dashboard</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- DataTables CSS -->
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap5.min.css">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="../css/style.css">
</head>
<body>

<?php include './header.php'; ?>

<!-- Navigation -->


<!-- Main Content -->
<div class="container-fluid">

    <div class="row">


    <?php include './nav.php'; ?>
    <?php include './mobile.php'; ?>
        
    <main class="col-lg-10 ms-auto main-content p-4">
            
            <h1 class="mb-4">Welcome, <?php echo htmlspecialchars($_SESSION['first_name']); ?>!</h1>

            <div class="container mt-4">
            <div class="row">
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
                
                <div class="col-md-4">
                    <div class="card text-white bg-success mb-3">
                        <div class="card-body">
                            <h5 class="card-title"><i class="fas fa-pills"></i> Medications</h5>
                            <p class="card-text">Manage medication details.</p>
                            <a href="./medications.php" class="btn btn-dark">Go to Medications Page</a>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="row">
            

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

<!-- Footer -->
<?php include '../include/footer.php'; ?>


 <!-- Bootstrap 5 JS Bundle (Includes Popper) -->
 <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>


</body>
</html>