<?php
include '../include/connection.php'; // Include your database connection

if (isset($_GET['id']) && isset($_GET['role'])) {
        $userId = $_GET['id'];
        $role = $_GET['role']; // Get the role to determine the table to update
        
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

        $deleteStatement = $connection->prepare("DELETE FROM $table WHERE $idField = :user_id");
        $deleteStatement->bindParam(':user_id', $userId, PDO::PARAM_INT);
        if ($deleteStatement->execute()) {
            echo json_encode(['success' => true, 'message' => 'User deleted successfully']);
            ?>
            <script>
                alert('User deleted successfully');
                window.location.href = './users.php'; // Redirect to the users page after update
            </script>
            <?php
        } else {
            ?>
            <script>
                alert('Error deleting user');
                window.history.back();
            </script>
            <?php
        }


}
?>