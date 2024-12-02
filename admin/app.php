<?php
@session_start();
include '../include/connection.php'; // Include the connection file

// Check if the form was submitted
if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    // Retrieve and sanitize form inputs
    $first_name = trim($_POST['first_name']);
    $last_name = trim($_POST['last_name']);
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $password = trim($_POST['password']);
    $role = trim($_POST['role']);
    $pharmacy_id = isset($_POST['pharmacy_id']) ? $_POST['pharmacy_id'] : null; // Only if pharmacy_admin
    $insurance_id = isset($_POST['insurance_id']) ? $_POST['insurance_id'] : null; // Only if insurance_admin

    // Check if the username is taken across all user tables
    $usernameCheckQuery = "
        SELECT username FROM (
            SELECT username FROM users
            UNION
            SELECT username FROM pharmacy_users
            UNION
            SELECT username FROM insurance_users
        ) AS combined WHERE username = :username";
    $stmt = $connection->prepare($usernameCheckQuery);
    $stmt->bindParam(':username', $username, PDO::PARAM_STR);
    $stmt->execute();
    $existingUsername = $stmt->fetch(PDO::FETCH_ASSOC);

    // Check if the email is taken across all user tables
    $emailCheckQuery = "
        SELECT email FROM (
            SELECT email FROM users
            UNION
            SELECT email FROM pharmacy_users
            UNION
            SELECT email FROM insurance_users
        ) AS combined WHERE email = :email";
    $stmt = $connection->prepare($emailCheckQuery);
    $stmt->bindParam(':email', $email, PDO::PARAM_STR);
    $stmt->execute();
    $existingEmail = $stmt->fetch(PDO::FETCH_ASSOC);

    // If the username or email already exists, alert the user
    if ($existingUsername) {
        echo "<script>
                alert('The username \"$username\" is already taken. Please choose a different one.');
                window.history.back();
              </script>";
        exit();
    } elseif ($existingEmail) {
        echo "<script>
                alert('The email \"$email\" is already taken. Please choose a different one.');
                window.history.back();
              </script>";
        exit();
    }

    // Hash the password before storing
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

    // Insert into the appropriate table based on role
    if ($role == 'admin') {
        $insertQuery = "INSERT INTO users (first_name, last_name, username, email, password, role) 
                        VALUES (:first_name, :last_name, :username, :email, :password, :role)";
        $stmt = $connection->prepare($insertQuery);
        $stmt->bindParam(':first_name', $first_name, PDO::PARAM_STR);
        $stmt->bindParam(':last_name', $last_name, PDO::PARAM_STR);
        $stmt->bindParam(':username', $username, PDO::PARAM_STR);
        $stmt->bindParam(':email', $email, PDO::PARAM_STR);
        $stmt->bindParam(':password', $hashed_password, PDO::PARAM_STR);
        $stmt->bindParam(':role', $role, PDO::PARAM_STR);
        $stmt->execute();

    } elseif ($role == 'pharmacy_admin') {
        if ($pharmacy_id) {
            $insertQuery = "INSERT INTO pharmacy_users (first_name, last_name, username, email, password, pharmacy_id, role) 
                            VALUES (:first_name, :last_name, :username, :email, :password, :pharmacy_id, :role)";
            $stmt = $connection->prepare($insertQuery);
            $stmt->bindParam(':first_name', $first_name, PDO::PARAM_STR);
            $stmt->bindParam(':last_name', $last_name, PDO::PARAM_STR);
            $stmt->bindParam(':username', $username, PDO::PARAM_STR);
            $stmt->bindParam(':email', $email, PDO::PARAM_STR);
            $stmt->bindParam(':password', $hashed_password, PDO::PARAM_STR);
            $stmt->bindParam(':pharmacy_id', $pharmacy_id, PDO::PARAM_INT);
            $stmt->bindParam(':role', $role, PDO::PARAM_STR);

            $stmt->execute();
        } else {
            echo "<script>alert('Please select a pharmacy.'); window.history.back();</script>";
            exit();
        }

    } elseif ($role == 'insurance_admin') {
        if ($insurance_id) {
            $insertQuery = "INSERT INTO insurance_users (first_name, last_name, username, email, password, insurance_id, role) 
                            VALUES (:first_name, :last_name, :username, :email, :password, :insurance_id, :role)";
            $stmt = $connection->prepare($insertQuery);
            $stmt->bindParam(':first_name', $first_name, PDO::PARAM_STR);
            $stmt->bindParam(':last_name', $last_name, PDO::PARAM_STR);
            $stmt->bindParam(':username', $username, PDO::PARAM_STR);
            $stmt->bindParam(':email', $email, PDO::PARAM_STR);
            $stmt->bindParam(':password', $hashed_password, PDO::PARAM_STR);
            $stmt->bindParam(':insurance_id', $insurance_id, PDO::PARAM_INT);
            $stmt->bindParam(':role', $role, PDO::PARAM_STR);
            $stmt->execute();
        } else {
            echo "<script>alert('Please select an insurance company.'); window.history.back();</script>";
            exit();
        }
    }

    // After successful registration
    echo "<script>
            alert('User registered successfully.');
            window.location.href = './users.php'; // Redirect to user list or dashboard
          </script>";
    exit();
}

// Function to fetch all users from different tables
function getUsers() {
    global $connection;
    $user_id = $_SESSION['user_id'];

    // Query to fetch data from users, pharmacy_users, and insurance_users
    $query = "
        SELECT u.user_id as user_id, u.first_name, u.last_name, u.username, u.email, role as 'userRole', 'System Admin' as role, '' as associated_institution 
        FROM users u 
        WHERE u.user_id != :user_id
        UNION ALL
        SELECT p.pharmacy_user_id as user_id, p.first_name, p.last_name, p.username, p.email, role as 'userRole', 'Pharmacy Admin' as role, ph.pharmacy_name as associated_institution 
        FROM pharmacy_users p 
        JOIN pharmacies ph ON p.pharmacy_id = ph.pharmacy_id 
        WHERE p.role = 'pharmacy_admin'
        UNION ALL
        SELECT i.insurance_user_id as user_id, i.first_name, i.last_name, i.username, i.email, role as 'userRole', 'Insurance Admin' as role, ic.insurance_name as associated_institution 
        FROM insurance_users i 
        JOIN insurance_companies ic ON i.insurance_id = ic.insurance_id 
        WHERE i.role = 'insurance_admin'
    ";

    $stmt = $connection->prepare($query);
    $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    $stmt->execute();
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    return $users; // Return the fetched users
}
?>
