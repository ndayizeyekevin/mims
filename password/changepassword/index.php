<?php
session_start();
require '../../include/connection.php'; // Include your database connection file

// Check if the reset key and email are provided in the URL
if (!isset($_GET['key']) || !isset($_GET['email'])) {
    die('Invalid request.');
}

$resetKey = htmlspecialchars($_GET['key']);
$email = htmlspecialchars($_GET['email']);
$error = "";

// Validate the email format
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    die('Invalid email format.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $role = $_POST['role'];
    $newPassword = $_POST['new_password'];
    $confirmPassword = $_POST['confirm_password'];

    // Validate password match
    if ($newPassword !== $confirmPassword) {
        $error = "Passwords do not match.";
    } else {
        // Determine the table to update based on the selected role
        $table = "";
        switch ($role) {
            case 'admin':
                $table = 'users';
                break;
            case 'pharmacy_admin':
            case 'pharmacist':
                $table = 'pharmacy_users';
                break;
            case 'insurance_admin':
            case 'insurance_employee':
                $table = 'insurance_users';
                break;
            default:
                $error = "Invalid role selected.";
                break;
        }

        // Check if the email exists in the corresponding table
        if ($table && !$error) {
            $stmt = $connection->prepare("SELECT * FROM {$table} WHERE email = :email AND reset_key = :reset_key");
            $stmt->bindParam(':email', $email);
            $stmt->bindParam(':reset_key', $resetKey);
            $stmt->execute();

            if ($stmt->rowCount() > 0) {
                // Update the password
                $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
                $updateStmt = $connection->prepare("UPDATE {$table} SET password = :password WHERE email = :email");
                $updateStmt->execute(['password' => $hashedPassword, 'email' => $email]);

                // Optionally clear the reset key after successful password change
                $clearKeyStmt = $connection->prepare("UPDATE {$table} SET reset_key = NULL WHERE email = :email");
                $clearKeyStmt->execute(['email' => $email]);

                // Set a success message for display
                $_SESSION['success'] = "Password changed successfully.";
                $success = true; // Add this line to trigger the success modal
            } else {
                $error = "Invalid role, email, or reset key.";
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
    <title>Change Password</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    
    <link rel="icon" type="image/x-icon" href="../../images/logo-removebg-preview.png">
    <style>
        body {
            background-color: #f8f9fa;
            color: #333;
            font-family: Arial, sans-serif;
        }
        .container {
            max-width: 500px;
            margin-top: 50px;
            background-color: white;
            padding: 20px;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
            border-radius: 10px;
        }
        .btn-home {
            margin-top: 15px;
            display: flex;
            justify-content: center;
            align-items: center;
            text-decoration: none;
        }
        .btn-home i {
            margin-right: 5px;
        }
        .form-group label {
            font-weight: bold;
        }
    </style>
</head>
<body>

<div class="container">
    <h2 class="text-center">Change Password</h2>

    <!-- Form for Changing Password -->
    <form action="" method="POST">
        <div class="form-group">
            <label for="role">Select Role:</label>
            <select id="role" name="role" class="form-control" required>
                <option value="">--Select Role--</option>
                <option value="admin">Admin</option>
                <option value="pharmacy_admin">Pharmacy Admin</option>
                <option value="pharmacist">Pharmacist</option>
                <option value="insurance_admin">Insurance Admin</option>
                <option value="insurance_employee">Insurance Employee</option>
            </select>
        </div>
        <div class="form-group">
            <label for="new_password">New Password:</label>
            <input type="password" id="new_password" name="new_password" class="form-control" minlength="5" required>
        </div>
        <div class="form-group">
            <label for="confirm_password">Confirm Password:</label>
            <input type="password" id="confirm_password" name="confirm_password" class="form-control" minlength="5" required>
        </div>
        <button type="submit" class="btn btn-primary btn-block">Change Password</button>
    </form>

    <!-- Link to Homepage -->
    <div class="text-center mt-4">
        <a href="../../index.html" class="btn-home"><i class="bi bi-house"></i> Back to Homepage</a>
    </div>
</div>

<!-- Error Modal -->
<div class="modal fade" id="errorModal" tabindex="-1" aria-labelledby="errorModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="errorModalLabel">Error</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <?= $error; ?>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Success Modal -->
<div class="modal fade" id="successModal" tabindex="-1" aria-labelledby="successModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="successModalLabel">Success</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                Password changed successfully!
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-primary" id="redirectToLogin">Go to Login</button>
            </div>
        </div>
    </div>
</div>

<!-- Bootstrap JS and dependencies -->
<script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.9.3/dist/umd/popper.min.js"></script>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">

<script>
    // Trigger the error modal if there's an error
    <?php if ($error): ?>
    $(document).ready(function(){
        $('#errorModal').modal('show');
    });
    <?php endif; ?>

    // Trigger the success modal if password change is successful
    <?php if (isset($success) && $success): ?>
    $(document).ready(function(){
        $('#successModal').modal('show');
        $('#redirectToLogin').on('click', function() {
            window.location.href = '../../login.php'; // Redirect to login page
        });
    });
    <?php endif; ?>
</script>

</body>
</html>
