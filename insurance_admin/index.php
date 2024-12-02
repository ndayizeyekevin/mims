<?php
// Start session and ensure the user is authenticated
session_start();
require_once '../include/connection.php'; // Include your PDO connection file

// Check if the user is logged in and has the correct role
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['insurance_employee', 'insurance_admin'])) {
    header("Location: ../login.php");
    exit();
}

// Get the logged-in user's insurance_id from the session
$insurance_id = intval($_SESSION['insurance_id']);

// Fetch total clients for this insurance company
$stmt_clients = $connection->prepare("
    SELECT COUNT(*) as total_clients 
    FROM clients c
    JOIN
        insurance_companies i on c.insurance_id = i.insurance_id
    WHERE c.insurance_id = :insurance_id
");
$stmt_clients->bindParam(':insurance_id', $insurance_id, PDO::PARAM_INT);
$stmt_clients->execute();
$total_clients = $stmt_clients->fetch(PDO::FETCH_ASSOC)['total_clients'];

// Fetch total claims for this insurance company
$stmt_claims = $connection->prepare("
    SELECT COUNT(*) as total_claims 
    FROM transaction_medications tm
    JOIN 
        transactions t ON tm.transaction_id = t.transaction_id
    JOIN
        insurance_companies i on t.insurance_id = i.insurance_id
    WHERE t.insurance_id = :insurance_id
");
$stmt_claims->bindParam(':insurance_id', $insurance_id, PDO::PARAM_INT);
$stmt_claims->execute();
$total_claims = $stmt_claims->fetch(PDO::FETCH_ASSOC)['total_claims'];

// Fetch pending claims for this insurance company
$stmt_pending_claims = $connection->prepare("
    SELECT COUNT(*) as pending_claims 
    FROM transaction_medications tm
    JOIN 
        transactions t ON tm.transaction_id = t.transaction_id
    JOIN
        insurance_companies i on t.insurance_id = i.insurance_id
    WHERE t.insurance_id = :insurance_id AND tm.status = 'pending'
");
$stmt_pending_claims->bindParam(':insurance_id', $insurance_id, PDO::PARAM_INT);
$stmt_pending_claims->execute();
$pending_claims = $stmt_pending_claims->fetch(PDO::FETCH_ASSOC)['pending_claims'];

// Fetch total users for this insurance company
$stmt_users = $connection->prepare("
    SELECT COUNT(*) as total_users 
    FROM insurance_users i
    WHERE i.insurance_id = :insurance_id
");
$stmt_users->bindParam(':insurance_id', $insurance_id, PDO::PARAM_INT);
$stmt_users->execute();
$total_users = $stmt_users->fetch(PDO::FETCH_ASSOC)['total_users'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Insurance Admin Dashboard</title>
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.9.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../css/style.css">
    
    <link rel="icon" type="image/x-icon" href="../images/logo-removebg-preview.png">

    <!-- Custom CSS -->
    <style>
        body {
            background-color: #f1f3f5;
        }
        
        .card-title {
            font-size: 1.5rem;
            color: #17a2b8;
        }
        @media (max-width: 768px) {
            .card-title {
                font-size: 1.2rem;
            }
        }
        .content {
            flex-grow: 1;
            padding: 20px;
        }
        .card {
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            border: none;
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
                <!-- Main Content -->
                <div class="content">
                    <h1>Insurance Dashboard</h1>

                    <!-- Dashboard Stats Section -->
                    <div class="row">
                        <div class="col-md-4 mt-3">
                            <div class="card bg-success text-light">
                                <div class="card-body">
                                <h5><i class="bi bi-people-fill"></i> Total Clients </h5>
                                <p id="total-clients"><?php echo htmlspecialchars($total_clients); ?> Clients</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4 mt-3">
                            <div class="card bg-primary text-light">
                                <div class="card-body">
                                    <h5><i class="bi bi-file-earmark-text"></i> Total Claims</h5>
                                    <p id="total-claims"><?php echo htmlspecialchars($total_claims); ?> Claims</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4 mt-3">
                            <div class="card bg-secondary text-light">
                                <div class="card-body">
                                    <h5><i class="bi bi-file-earmark-text"></i> Pending Claims</h5>
                                    <p id="pending-claims"><?php echo htmlspecialchars($pending_claims); ?> Pending Claims</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4 mt-3">
                            <div class="card bg-dark text-light">
                                <div class="card-body">
                                    <h5> <i class="fas fa-users"></i> Users</h5>
                                    <p id="pending-claims"><?php echo htmlspecialchars($total_users); ?> Users</p>
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

        

       
</body>
</html>
