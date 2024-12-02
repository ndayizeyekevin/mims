<!-- File: ../admin/login.php -->
<?php
session_start();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Login</title>
    <!-- Bootstrap CSS -->
    <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css" rel="stylesheet">
    <link rel="icon" type="image/x-icon" href="./images/logo-removebg-preview.png">

    <style>
        body {
            background-color: #f8f9fa;
        }
        .login-container {
            max-width: 400px;
            padding: 20px;
            margin: 100px auto;
            background-color: #fff;
            border-radius: 10px;
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.1);
        }
        .login-container h2 {
            text-align: center;
            margin-bottom: 20px;
            font-size: 24px;
            color: #007bff;
        }
        .error {
            color: red;
            margin-top: 10px;
            text-align: center;
        }
        .form-group {
            margin-bottom: 15px;
        }
        .toggle-password {
            cursor: pointer;
            position: absolute;
            right: 15px;
            top: 38px;
        }
        .btn-primary {
            background-color: #007bff;
            border: none;
        }
        .btn-primary:hover {
            background-color: #0056b3;
        }
        .form-group select {
            height: 45px;
            padding-left: 10px;
        }
    </style>
</head>
<body>

<div class="login-container">
    <a href="./index.html" class='btn btn-primary text-light py-2 px-3 mb-3'><i class="bi bi-house"></i> Homepage </a>
    
    <div class="text-center">
        <img src="./images/logo-removebg-preview.png" alt="Logo" width='40%' height='40%' class="mb-3">
    </div>
    
    <h2>User Login</h2>
    
    <?php
    // Display error message if set
    if (isset($_SESSION['error'])) {
        echo '<div class="alert alert-danger text-center">'.htmlspecialchars($_SESSION['error']).'</div>';
        unset($_SESSION['error']);
    }
    ?>
    <form action="process_login.php" method="POST" novalidate>
        <div class="form-group">
            <label for="username">Username:</label>
            <input type="text" id="username" name="username" class="form-control" required autocomplete="off" placeholder="Enter your username" aria-label="Username">
        </div>

        <div class="form-group position-relative">
            <label for="password">Password:</label>
            <input type="password" id="password" name="password" class="form-control" required minlength="8" placeholder="Enter your password" aria-label="Password" autocomplete="off">
            <i class="fas fa-eye toggle-password" id="togglePassword"></i>
        </div>

        <div class="form-group">
            <label for="role">Role:</label>
            <select id="role" name="role" class="form-control" required aria-label="Select your role">
                <option value="">--Select Role--</option>
                <option value="admin">Admin</option>
                <option value="pharmacy_admin">Pharmacy Admin</option>
                <option value="pharmacist">Pharmacist</option>
                <option value="insurance_admin">Insurance Admin</option>
                <option value="insurance_employee">Insurance Employee</option>
            </select>
        </div>

        <button type="submit" class="btn btn-primary btn-block">
            <span id="login-text">Login</span>
            <span id="loading-spinner" class="spinner-border spinner-border-sm d-none" role="status" aria-hidden="true"></span>
        </button>
    </form>
    <div class="text-center mt-3">
        <a href="./password/password_reset.php">Forgot Password?</a>
    </div>
</div>

<!-- Bootstrap JS (optional) -->
<script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.5.2/dist/umd/popper.min.js"></script>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>

<!-- JavaScript for password visibility toggle -->
<script>
    document.getElementById('togglePassword').addEventListener('click', function () {
        const passwordInput = document.getElementById('password');
        const passwordType = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
        passwordInput.setAttribute('type', passwordType);
        this.classList.toggle('fa-eye-slash');
    });

    // Show loading spinner on login
    document.querySelector('form').addEventListener('submit', function () {
        document.getElementById('login-text').classList.add('d-none');
        document.getElementById('loading-spinner').classList.remove('d-none');
    });
</script>

</body>
</html>
