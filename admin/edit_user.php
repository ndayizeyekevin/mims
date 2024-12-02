<?php
include '../include/connection.php'; // Include your database connection

if (isset($_POST['EditUser'])) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $userId = $_POST['user_id'];
        $firstName = $_POST['first_name'];
        $lastName = $_POST['last_name'];
        $username = $_POST['username'];
        $email = $_POST['email'];
        $role = $_POST['role']; // Get the role to determine the table to update
        
        // Determine which table to update based on the role
        if ($role === 'admin') {
            $table = 'users';
            $idField = 'user_id'; // The field used in the users table for ID
        } elseif ($role === 'pharmacist' || $role === 'pharmacy_admin') {
            $table = 'pharmacy_users';
            $idField = 'pharmacy_user_id'; // Adjust based on your table structure
        } elseif ($role === 'pharmacy_insurance_user' || $role === 'insurance_admin') {
            $table = 'insurance_users';
            $idField = 'insurance_user_id'; // Adjust based on your table structure
        } else {
            echo json_encode(['success' => false, 'message' => 'Invalid role']);
            exit;
        }
        
        // Check if the email is already in use by another user
        $checkEmailStmt = $connection->prepare("SELECT COUNT(*) FROM $table WHERE email = :email AND $idField != :user_id");
        $checkEmailStmt->bindParam(':email', $email);
        $checkEmailStmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
        $checkEmailStmt->execute();
        $emailExists = $checkEmailStmt->fetchColumn();

        if ($emailExists > 0) {
            // If email already exists, return an error
            ?>
            <script>
                alert('This email is already in use by another user. Please choose a different email.');
                window.history.back();
            </script>
            <?php
            exit;
        }

        // Prepare the SQL statement to update the user using PDO
        $stmt = $connection->prepare("UPDATE $table SET first_name = :first_name, last_name = :last_name, username = :username, email = :email WHERE $idField = :user_id");

        // Bind the parameters
        $stmt->bindParam(':first_name', $firstName);
        $stmt->bindParam(':last_name', $lastName);
        $stmt->bindParam(':username', $username);
        $stmt->bindParam(':email', $email);
        $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);

        // Execute the statement
        if ($stmt->execute()) {
            // echo json_encode(['success' => true, 'message' => 'User updated successfully']);
            ?>
            <script>
                alert('User information updated successfully');
                window.location.href = './users.php'; // Redirect to the users page after update
            </script>
            <?php
        } else {
            ?>
            <script>
                alert('Error updating user');
                window.history.back();
            </script>
            <?php
        }
    }
} else {
    echo 'form data not validated!';
}
?>
