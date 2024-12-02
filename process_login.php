<?php
// File: ../admin/process_login.php
session_start();
include './include/connection.php'; // Include the database connection

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Retrieve and sanitize form inputs
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);
    $role = trim($_POST['role']);

    // Validate inputs
    if (empty($username) || empty($password) || empty($role)) {
        $_SESSION['error'] = 'All fields are required.';
        header('Location: login.php');
        exit();
    }

    try {
        // Determine the table and ID column based on the role
        switch ($role) {
            case 'admin':
                $table = 'users';
                $id_column = 'user_id';
                $extra_column = null; // No extra column needed
                break;
            case 'pharmacy_admin':
            case 'pharmacist':
                $table = 'pharmacy_users';
                $id_column = 'pharmacy_user_id';
                $extra_column = 'pharmacy_id'; // Need to retrieve pharmacy_id
                break;
            case 'insurance_admin':
            case 'insurance_employee':
                $table = 'insurance_users';
                $id_column = 'insurance_user_id';
                $extra_column = 'insurance_id'; // Need to retrieve insurance_id
                break;
            default:
                throw new Exception('Invalid role selected.');
        }

        // Prepare the SQL statement, including the extra column if needed
        $sql = "SELECT $id_column AS user_id, username, password, first_name, role" . 
               ($extra_column ? ", $extra_column" : "") . 
               " FROM $table WHERE username = :username LIMIT 1";
        $stmt = $connection->prepare($sql);
        $stmt->bindParam(':username', $username, PDO::PARAM_STR);
        $stmt->execute();

        // Fetch the user data
        $user = $stmt->fetch();

        if ($user) {
            // Verify the password
            if (password_verify($password, $user['password'])) {
                // Password is correct, start the session
                session_regenerate_id(true); // Prevent session fixation

                // Set common session variables
                $_SESSION['user_id'] = $user['user_id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['first_name'] = $user['first_name'];
                $_SESSION['last_name'] = $user['last_name'];

                // Set pharmacy_id or insurance_id based on the role
                if ($role === 'pharmacy_admin' || $role === 'pharmacist') {
                    $_SESSION['pharmacy_id'] = $user['pharmacy_id']; // Set pharmacy_id for these roles
                    $_SESSION['first_name'] = $user['first_name'];
                } elseif ($role === 'insurance_admin' || $role === 'insurance_employee') {
                    $_SESSION['insurance_id'] = $user['insurance_id']; // Set insurance_id for these roles
                    $_SESSION['first_name'] = $user['first_name'];

                }

                // Redirect based on the user's role
                switch ($user["role"]) {
                    case 'admin':
                        header('Location: ./admin/');
                        break;
                    case 'pharmacy_admin':
                        header('Location: ./pharmacy_admin/');
                        break;
                    case 'pharmacist':
                        header('Location: ./pharmacist/');
                        break;
                    case 'insurance_admin':
                        header('Location: ./insurance_admin/');
                        break;
                    case 'insurance_employee':
                        header('Location: ./insurance_employee/');
                        break;
                    default:
                        throw new Exception('Unknown role.');
                }
                exit();
            } else {
                // Invalid password
                $_SESSION['error'] = 'Invalid username or password.';
                header('Location: login.php');
                exit();
            }
        } else {
            // User not found
            $_SESSION['error'] = 'Invalid username or password.';
            header('Location: login.php');
            exit();
        }
    } catch (Exception $e) {
        // Handle exceptions (log them in production)
        // error_log($e->getMessage()); // Uncomment in production
        $_SESSION['error'] = 'An error occurred during login. Please try again.';
        header('Location: login.php');
        exit();
    }
} else {
    // If not a POST request, redirect to login form
    header('Location: login.php');
    exit();
}
?>
