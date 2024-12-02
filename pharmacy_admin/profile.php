<?php
session_start();
include '../include/connection.php'; // Include the database connection

// Check if the user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit();
}

$user_id = $_SESSION['user_id']; // Assuming user_id is stored in session when logged in
$error = $success = "";

// Handle password update form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $current_password = $_POST['current_password'];
    $new_password = trim($_POST['new_password']);
    $confirm_password = trim($_POST['confirm_password']);

    // Validate inputs
    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        $error = "All fields are required.";
    } elseif ($new_password !== $confirm_password) {
        $error = "New password and confirmation do not match.";
    } else {
        try {
            // Fetch the user's current password from the database
            $stmt = $connection->prepare("SELECT password FROM pharmacy_users WHERE pharmacy_user_id = :user_id");
            $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
            $stmt->execute();
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if (password_verify($current_password, $user['password'])) {
                // Password is correct, now update the password
                $hashed_new_password = password_hash($new_password, PASSWORD_DEFAULT);
                $stmt_update = $connection->prepare("UPDATE pharmacy_users SET password = :new_password WHERE pharmacy_user_id = :user_id");
                $stmt_update->bindParam(':new_password', $hashed_new_password, PDO::PARAM_STR);
                $stmt_update->bindParam(':user_id', $user_id, PDO::PARAM_INT);
                if ($stmt_update->execute()) {
                    $success = "Password successfully updated!";
                } else {
                    $error = "An error occurred while updating the password.";
                }
            } else {
                $error = "Current password is incorrect.";
            }
        } catch (PDOException $e) {
            $error = "An error occurred: " . $e->getMessage();
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
                    
                <div class="container mt-5 px-4">
                    <h2>Change Password</h2>
                    <?php if (!empty($error)) : ?>
                        <div class="alert alert-danger"><?php echo $error; ?></div>
                    <?php elseif (!empty($success)) : ?>
                        <div class="alert alert-success"><?php echo $success; ?></div>
                    <?php endif; ?>

                    <form method="POST" action="">
                        <div class="form-group">
                            <label for="current_password">Current Password</label>
                            <input type="password" class="form-control" id="current_password"  minlength='8' name="current_password" minlength='8' required>
                        </div>
                        <div class="form-group">
                            <label for="new_password">New Password</label>
                            <input type="password" class="form-control" id="new_password"  minlength='8' name="new_password" minlength='8' required>
                        </div>
                        <div class="form-group">
                            <label for="confirm_password">Confirm New Password</label>
                            <input type="password" class="form-control" id="confirm_password"  minlength='8' name="confirm_password" minlength='8' required>
                        </div>
                        <button type="submit" class="btn btn-primary mt-3">Change Password</button>
                    </form>
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
