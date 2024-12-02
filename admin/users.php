<?php
session_start(); // Start the session

// Generate CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Check if the user_id is set in the session
if (!isset($_SESSION['user_id'])) {
    // Redirect to login page or show an error message
    header("Location: ../login.php"); // Change 'login.php' to your actual login page
    exit();
}



$user_id = $_SESSION['user_id'];

include '../include/connection.php';

try {
    // Fetch pharmacies and insurance companies from the database
    $stmt_pharmacies = $connection->prepare("SELECT pharmacy_id, pharmacy_name FROM pharmacies ORDER BY pharmacy_name ASC");
    $stmt_pharmacies->execute();
    $pharmacies = $stmt_pharmacies->fetchAll(PDO::FETCH_ASSOC);

    $stmt_insurance = $connection->prepare("SELECT insurance_id, insurance_name FROM insurance_companies ORDER BY insurance_name ASC");
    $stmt_insurance->execute();
    $insurances = $stmt_insurance->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Log the error and display a generic message
    error_log("Database Error: " . $e->getMessage());
    echo "An error occurred while fetching data. Please try again later.";
    exit();
}

// Function to fetch users (assuming a function in app.php)
require_once './app.php';
$users = getUsers(); // Ensure this function is properly defined in app.php

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Users Management</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- DataTables CSS -->
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap5.min.css">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="../css/style.css">
    <!-- Icon -->
    <link rel="icon" type="image/x-icon" href="../images/logo-removebg-preview.png">

</head>
<body>

<!-- Header with logo and title -->
<?php include './header.php'; ?>

<!-- Navigation -->
<?php include './mobile.php'; ?>

<!-- Main Content -->
<div class="container-fluid">

    <div class="row">
        <!-- Sidebar is assumed to be part of nav.php -->
        <?php include './nav.php'; ?>
        <main class="col-lg-10 ms-auto main-content p-4">
            
            <h1 class="mb-4">Welcome to Users Management</h1>

            <!-- Register Admin Button -->
            <div class="mb-4">
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#registerAdminModal">
                    <i class="fas fa-user-plus"></i> Register User
                </button>
            </div>

            <!-- Register Admin Modal -->
            <div class="modal fade" id="registerAdminModal" tabindex="-1" aria-labelledby="registerAdminModalLabel" aria-hidden="true">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <form action="app.php" method="POST" onsubmit="return validatePassword()">
                            <div class="modal-header">
                                <h5 class="modal-title" id="registerAdminModalLabel">Register Admin</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                <!-- CSRF Token -->
                                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                
                                <!-- First Name -->
                                <div class="mb-3">
                                    <label for="first_name" class="form-label">First Name <span class="text-danger">*</span></label>
                                    <input type="text" id="first_name" name="first_name" class="form-control" required>
                                </div>

                                <!-- Last Name -->
                                <div class="mb-3">
                                    <label for="last_name" class="form-label">Last Name <span class="text-danger">*</span></label>
                                    <input type="text" id="last_name" name="last_name" class="form-control" required>
                                </div>

                                <!-- Username -->
                                <div class="mb-3">
                                    <label for="username" class="form-label">Username <span class="text-danger">*</span></label>
                                    <input type="text" id="username" name="username" class="form-control" required minlength="5">
                                </div>

                                <!-- Email -->
                                <div class="mb-3">
                                    <label for="email" class="form-label">Email <span class="text-danger">*</span></label>
                                    <input type="email" id="email" name="email" class="form-control" required>
                                </div>

                                <!-- Password -->
                                <div class="mb-3">
                                    <label for="password" class="form-label">Password <span class="text-danger">*</span></label>
                                    <input type="password" id="password" name="password" class="form-control" required minlength="8">
                                    <div class="form-text">Minimum 8 characters.</div>
                                </div>

                                <!-- Confirm Password -->
                                <div class="mb-3">
                                    <label for="confirm_password" class="form-label">Confirm Password <span class="text-danger">*</span></label>
                                    <input type="password" id="confirm_password" class="form-control" required minlength="8">
                                </div>

                                <!-- Role Selection -->
                                <div class="mb-3">
                                    <label for="role" class="form-label">Role <span class="text-danger">*</span></label>
                                    <select id="role" name="role" class="form-select" onchange="toggleFields()" required>
                                        <option value="" disabled selected>Choose User Role</option>
                                        <option value="admin">System Administrator</option>
                                        <option value="pharmacy_admin">Pharmacy Admin</option>
                                        <option value="insurance_admin">Insurance Admin</option>
                                    </select>
                                </div>

                                <!-- Pharmacy ID (for pharmacy_admin) -->
                                <div class="mb-3" id="pharmacy_field" style="display:none;">
                                    <label for="pharmacy_id" class="form-label">Pharmacy <span class="text-danger">*</span></label>
                                    <select id="pharmacy_id" name="pharmacy_id" class="form-select">
                                        <option value="" disabled selected>Select Pharmacy</option>
                                        <?php
                                        if ($pharmacies) {
                                            foreach ($pharmacies as $pharmacy) {
                                                echo '<option value="' . htmlspecialchars($pharmacy['pharmacy_id']) . '">' . htmlspecialchars($pharmacy['pharmacy_name']) . '</option>';
                                            }
                                        } else {
                                            echo '<option value="" disabled>No Pharmacies Available</option>';
                                        }
                                        ?>
                                    </select>
                                </div>

                                <!-- Insurance ID (for insurance_admin) -->
                                <div class="mb-3" id="insurance_field" style="display:none;">
                                    <label for="insurance_id" class="form-label">Insurance Company <span class="text-danger">*</span></label>
                                    <select id="insurance_id" name="insurance_id" class="form-select">
                                        <option value="" disabled selected>Select Insurance Company</option>
                                        <?php
                                        if ($insurances) {
                                            foreach ($insurances as $insurance) {
                                                echo '<option value="' . htmlspecialchars($insurance['insurance_id']) . '">' . htmlspecialchars($insurance['insurance_name']) . '</option>';
                                            }
                                        } else {
                                            echo '<option value="" disabled>No Insurance Companies Available</option>';
                                        }
                                        ?>
                                    </select>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                <button type="submit" class="btn btn-primary">Register Admin</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- User Table -->
            <div class="container mt-5">
                <h2 class="text-center mb-4">System Users</h2>

                <!-- Search Input (Optional if using DataTables' built-in search) -->
                <!-- <div class="mb-3">
                    <input type="text" id="searchInput" class="form-control" placeholder="Search users...">
                </div> -->

                <!-- Users Table -->
                <div class="table-responsive">
                    <table id="usersTable" class="table table-striped table-bordered">
                        <thead class="table-dark">
                            <tr>
                                <th>#</th>
                                <th>First Name</th>
                                <th>Last Name</th>
                                <th>Username</th>
                                <th>Email</th>
                                <th>Role</th>
                                <th>Associated Institution</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            if ($users) {
                                $i = 1;
                                foreach ($users as $user) {
                                    echo "<tr>
                                            <td>" . htmlspecialchars($i) . "</td>
                                            <td>" . htmlspecialchars($user['first_name']) . "</td>
                                            <td>" . htmlspecialchars($user['last_name']) . "</td>
                                            <td>" . htmlspecialchars($user['username']) . "</td>
                                            <td>" . htmlspecialchars($user['email']) . "</td>
                                            <td>" . htmlspecialchars($user['role']) . "</td>
                                            <td>" . htmlspecialchars($user['associated_institution']) . "</td>
                                            <td>
                                                <button class='btn btn-danger btn-sm delete-btn' data-id='" . htmlspecialchars($user['user_id']) . "'
                                                data-role='" . htmlspecialchars($user['userRole']) . "'>
                                                    <i class='fas fa-trash-alt'></i> Delete
                                                </button>
                                                <button class='btn btn-primary btn-sm edit-btn' 
                                                    data-id='" . htmlspecialchars($user['user_id']) . "' 
                                                    data-firstname='" . htmlspecialchars($user['first_name']) . "' 
                                                    data-lastname='" . htmlspecialchars($user['last_name']) . "' 
                                                    data-username='" . htmlspecialchars($user['username']) . "' 
                                                    data-email='" . htmlspecialchars($user['email']) . "' 
                                                    data-role='" . htmlspecialchars($user['userRole']) . "'>
                                                    <i class='fas fa-edit'></i> Edit
                                                </button>
                                            </td>
                                        </tr>";
                                    $i++;
                                }
                            } else {
                                echo "<tr><td colspan='8' class='text-center'>No users found</td></tr>";
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Delete User Modal -->
            <div class="modal fade" id="deleteUserModal" tabindex="-1" aria-labelledby="deleteUserModalLabel" aria-hidden="true">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="deleteUserModalLabel">Delete User</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            Are you sure you want to delete this user?
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="button" class="btn btn-danger" id="confirmDelete">Delete</button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Edit User Modal -->
            <div class="modal fade" id="editUserModal" tabindex="-1" aria-labelledby="editUserModalLabel" aria-hidden="true">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <form id="editUserForm" method="POST" action="edit_user.php">
                            <div class="modal-header">
                                <h5 class="modal-title" id="editUserModalLabel">Edit User</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                <!-- CSRF Token -->
                                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                
                                <!-- User ID -->
                                <input type="hidden" name="user_id" id="editUserId">

                                <!-- First Name -->
                                <div class="mb-3">
                                    <label for="editFirstName" class="form-label">First Name <span class="text-danger">*</span></label>
                                    <input type="text" name="first_name" class="form-control" id="editFirstName" required>
                                </div>

                                <!-- Last Name -->
                                <div class="mb-3">
                                    <label for="editLastName" class="form-label">Last Name <span class="text-danger">*</span></label>
                                    <input type="text" name="last_name" class="form-control" id="editLastName" required>
                                </div>

                                <!-- Username (Read-Only) -->
                                <div class="mb-3">
                                    <label for="editUsername" class="form-label">Username <span class="text-danger">*</span></label>
                                    <input type="text" name="username" class="form-control bg-dark text-light" id="editUsername" readonly required>
                                </div>

                                <!-- Email -->
                                <div class="mb-3">
                                    <label for="editEmail" class="form-label">Email <span class="text-danger">*</span></label>
                                    <input type="email" name="email" class="form-control" id="editEmail" required>
                                </div>

                                <!-- Role (Read-Only) -->
                                <div class="mb-3">
                                    <label for="editRole" class="form-label">Role <span class="text-danger">*</span></label>
                                    <input type="text" name="role" class="form-control bg-dark text-light" id="editRole" readonly required>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                <button type="submit" class="btn btn-primary" name="EditUser">Save Changes</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

        </main>
    </div>
</div>

<?php include './footer.php'; ?>

<!-- Bootstrap 5 JS Bundle (Includes Popper) -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<!-- jQuery (Required for DataTables) -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<!-- DataTables JS -->
<script type="text/javascript" src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
<script type="text/javascript" src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>
<!-- Custom JS -->
<script>
    $(document).ready(function() {
        // Initialize DataTable with enhanced features
        $('#usersTable').DataTable({
            "paging": true,             // Enable pagination
            "lengthChange": true,       // Allow users to change the number of records per page
            "pageLength": 10,           // Default number of records per page
            "searching": true,          // Enable search/filter
            "ordering": true,           // Enable column sorting
            "info": true,               // Show info about current page
            "autoWidth": false,
            "responsive": true,
            "language": {
                "search": "Filter records:"
            }
        });

        // Handle Delete Button Click
        let deleteUserId = null;
        let deleteRole = null;

        $('.delete-btn').on('click', function() {
            deleteUserId = $(this).data('id');
            deleteRole = $(this).data('role');
            var deleteModal = new bootstrap.Modal(document.getElementById('deleteUserModal'));
            deleteModal.show();
        });

        // Confirm Delete
        $('#confirmDelete').on('click', function() {
            if (deleteUserId) {
                // Redirect to delete script with CSRF token
                window.location.href = `delete_user.php?id=${deleteUserId}&role=${deleteRole}&csrf_token=<?php echo $_SESSION['csrf_token']; ?>`;
            }
        });

        // Handle Edit Button Click
        $('.edit-btn').on('click', function() {
            const userId = $(this).data('id');
            const firstName = $(this).data('firstname');
            const lastName = $(this).data('lastname');
            const username = $(this).data('username');
            const email = $(this).data('email');
            const role = $(this).data('role');

            // Populate the edit form fields
            $('#editUserId').val(userId);
            $('#editFirstName').val(firstName);
            $('#editLastName').val(lastName);
            $('#editUsername').val(username);
            $('#editEmail').val(email);
            $('#editRole').val(role);

            var editModal = new bootstrap.Modal(document.getElementById('editUserModal'));
            editModal.show();
        });
    });

    // Toggle Fields Based on Role Selection in Register Admin Modal
    function toggleFields() {
        const role = document.getElementById('role').value;
        const pharmacyField = document.getElementById('pharmacy_field');
        const insuranceField = document.getElementById('insurance_field');

        if (role === 'pharmacy_admin') {
            pharmacyField.style.display = 'block';
            insuranceField.style.display = 'none';
            document.getElementById('pharmacy_id').required = true;
            document.getElementById('insurance_id').required = false;
        } else if (role === 'insurance_admin') {
            pharmacyField.style.display = 'none';
            insuranceField.style.display = 'block';
            document.getElementById('pharmacy_id').required = false;
            document.getElementById('insurance_id').required = true;
        } else {
            pharmacyField.style.display = 'none';
            insuranceField.style.display = 'none';
            document.getElementById('pharmacy_id').required = false;
            document.getElementById('insurance_id').required = false;
        }
    }

    // Password Validation in Register Admin Modal
    function validatePassword() {
        const password = document.getElementById("password").value;
        const confirmPassword = document.getElementById("confirm_password").value;
        if (password !== confirmPassword) {
            alert("Passwords do not match. Please try again.");
            return false; // Prevent form submission
        }
        return true; // Allow form submission if passwords match
    }
</script>

</body>
</html>
