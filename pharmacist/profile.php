<?php
session_start();
include '../include/connection.php'; // Include the database connection

// Check if the user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
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
            $stmt = $connection->prepare("SELECT password FROM users WHERE pharmacy_user_id = :user_id");
            $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
            $stmt->execute();
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if (password_verify($current_password, $user['password'])) {
                // Password is correct, now update the password
                $hashed_new_password = password_hash($new_password, PASSWORD_DEFAULT);
                $stmt_update = $connection->prepare("UPDATE users SET password = :new_password WHERE pharmacy_user_id = :user_id");
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
    <title>Change Password</title>
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

<!-- Header with logo and title -->
<?php include './header.php'; ?>

<!-- Main Content -->
<div class="container-fluid">
    <div class="row">
        <!-- Sidebar is assumed to be part of nav.php -->
         <?php

            include './nav.php'; // Include navigation
            include './mobile.php'; // Include mobile navigation
?>
        <main class="col-lg-10 ms-auto main-content p-4">
            
            <div class="container mt-5">
                <h2>Change Password</h2>
                <?php if (!empty($error)) : ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php elseif (!empty($success)) : ?>
                    <div class="alert alert-success"><?php echo $success; ?></div>
                <?php endif; ?>

                <form method="POST" action="">
                    <div class="form-group">
                        <label for="current_password">Current Password</label>
                        <input type="password" class="form-control" id="current_password" name="current_password" minlength='8' required>
                    </div>
                    <div class="form-group">
                        <label for="new_password">New Password</label>
                        <input type="password" class="form-control" id="new_password" name="new_password" minlength='8' required>
                    </div>
                    <div class="form-group">
                        <label for="confirm_password">Confirm New Password</label>
                        <input type="password" class="form-control" id="confirm_password" name="confirm_password" minlength='8' required>
                    </div>
                    <button type="submit" class="btn btn-primary">Change Password</button>
                </form>
            </div>
        </main>
    </div>
</div>     

<?php include '../include/footer.php'; ?>
 <!-- Bootstrap 5 JS Bundle (Includes Popper) -->
 <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>
