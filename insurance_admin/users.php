<?php
session_start();
// Generate CSRF token if not set
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}


// Check if the user is authenticated and has the 'insurance_admin' role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'insurance_admin') {
    header("Location: ../login.php"); // Redirect to login page
    exit();
}

// Check if 'insurance_id' is set in the session
if (!isset($_SESSION['insurance_id'])) {
    // Handle the case where insurance_id is not set
    // This could involve fetching it from the database based on user_id
    // For simplicity, we'll assume it's already set
    header("Location: ../login.php"); // Redirect to login if not set
    exit();
}

require_once '../include/connection.php'; // Include your PDO connection file


// Function to fetch users based on insurance_id with search and pagination
function getUsers($connection, $insurance_id, $search = '', $limit = 10, $offset = 0) {
    $query = "SELECT * FROM insurance_users 
              WHERE insurance_id = :insurance_id AND insurance_user_id != :user_id
              AND (first_name LIKE :search 
                   OR last_name LIKE :search 
                   OR username LIKE :search 
                   OR email LIKE :search)
              ORDER BY first_name ASC 
              LIMIT :limit OFFSET :offset";
    $stmt = $connection->prepare($query);
    $search_param = "%$search%";
    $stmt->bindParam(':insurance_id', $insurance_id, PDO::PARAM_INT);
    $stmt->bindParam(':user_id', $_SESSION['user_id'], PDO::PARAM_INT);
    $stmt->bindParam(':search', $search_param, PDO::PARAM_STR);
    $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Function to count total users for pagination
function countUsers($connection, $insurance_id, $search = '') {
    $query = "SELECT COUNT(*) FROM insurance_users 
              WHERE insurance_id = :insurance_id 
              AND (first_name LIKE :search 
                   OR last_name LIKE :search 
                   OR username LIKE :search 
                   OR email LIKE :search)";
    $stmt = $connection->prepare($query);
    $search_param = "%$search%";
    $stmt->bindParam(':insurance_id', $insurance_id, PDO::PARAM_INT);
    $stmt->bindParam(':search', $search_param, PDO::PARAM_STR);
    $stmt->execute();
    return $stmt->fetchColumn();
}

// Handle success and error messages
$success = '';
$error = '';
if (isset($_SESSION['success'])) {
    $success = $_SESSION['success'];
    unset($_SESSION['success']);
}
if (isset($_SESSION['error'])) {
    $error = $_SESSION['error'];
    unset($_SESSION['error']);
}

// Pagination settings
$records_per_page = 10;
$current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($current_page < 1) $current_page = 1;
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$offset = ($current_page - 1) * $records_per_page;

// Fetch users based on search and pagination
$users = getUsers($connection, $_SESSION['insurance_id'], $search, $records_per_page, $offset);
$total_records = countUsers($connection, $_SESSION['insurance_id'], $search);
$total_pages = ceil($total_records / $records_per_page);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Insurance Admin - Manage Users</title>
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

<!-- Main Content -->
<div class="container-fluid">
    <div class="row">
        <!-- Sidebar is assumed to be part of nav.php -->
         <?php

            include './nav.php'; // Include navigation
            include './mobile.php'; // Include mobile navigation
?>
        <main class="col-lg-10 ms-auto main-content p-4">
            
            <h1 class="mb-4">Manage Users</h1>

            <!-- Display Success and Error Messages -->
            <?php if ($success): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?php echo htmlspecialchars($success); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?php echo htmlspecialchars($error); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <!-- Register User Button -->
            <div class="mb-4">
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#registerUserModal">
                    <i class="fas fa-user-plus"></i> Register User
                </button>
            </div>

            <!-- Register User Modal -->
            <div class="modal fade" id="registerUserModal" tabindex="-1" aria-labelledby="registerUserModalLabel" aria-hidden="true">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <form action="register_user.php" method="POST" onsubmit="return validateUserForm()">
                            <div class="modal-header">
                                <h5 class="modal-title" id="registerUserModalLabel">Register New User</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                <!-- CSRF Token -->
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                
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
                                    <input type="text" id="username" name="username" class="form-control" required>
                                </div>

                                <!-- Email -->
                                <div class="mb-3">
                                    <label for="email" class="form-label">Email <span class="text-danger">*</span></label>
                                    <input type="email" id="email" name="email" class="form-control" required>
                                </div>

                                <!-- Password -->
                                <div class="mb-3">
                                    <label for="password" class="form-label">Password <span class="text-danger">*</span></label>
                                    <input type="password" id="password" name="password" class="form-control" required>
                                </div>

                                <!-- Role -->
                                <div class="mb-3">
                                    <label for="role" class="form-label">Role <span class="text-danger">*</span></label>
                                    <select id="role" name="role" class="form-control" required>
                                        <option value="">Select Role</option>
                                        <option value="insurance_admin">Insurance Admin</option>
                                        <option value="insurance_employee">Insurance Employee</option>
                                    </select>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                <button type="submit" class="btn btn-primary">Register User</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Users Data Table -->
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
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($users): ?>
                            <?php $count = $offset + 1; ?>
                            <?php foreach ($users as $user): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($count++); ?></td>
                                    <td><?php echo htmlspecialchars($user['first_name']); ?></td>
                                    <td><?php echo htmlspecialchars($user['last_name']); ?></td>
                                    <td><?php echo htmlspecialchars($user['username']); ?></td>
                                    <td><?php echo htmlspecialchars($user['email']); ?></td>
                                    <td><?php echo htmlspecialchars(ucfirst($user['role'])); ?></td>
                                    <td>
                                        <button class="btn btn-primary btn-sm edit-btn" data-bs-toggle="modal" data-bs-target="#editUserModal" 
                                            data-id="<?php echo htmlspecialchars($user['insurance_user_id']); ?>"
                                            data-firstname="<?php echo htmlspecialchars($user['first_name']); ?>"
                                            data-lastname="<?php echo htmlspecialchars($user['last_name']); ?>"
                                            data-username="<?php echo htmlspecialchars($user['username']); ?>"
                                            data-email="<?php echo htmlspecialchars($user['email']); ?>"
                                            data-role="<?php echo htmlspecialchars($user['role']); ?>">
                                            <i class="fas fa-edit"></i> Edit
                                        </button>
                                        <button class="btn btn-danger btn-sm delete-btn" data-bs-toggle="modal" data-bs-target="#deleteUserModal" 
                                            data-id="<?php echo htmlspecialchars($user['insurance_user_id']); ?>"
                                            data-username="<?php echo htmlspecialchars($user['username']); ?>">
                                            <i class="fas fa-trash-alt"></i> Delete
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="text-center">No users found.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination Links -->
            <?php if ($total_pages > 1): ?>
                <nav aria-label="Page navigation">
                    <ul class="pagination justify-content-center">
                        <!-- Previous Button -->
                        <li class="page-item <?php if ($current_page <= 1) echo 'disabled'; ?>">
                            <a class="page-link" href="?page=<?php echo $current_page - 1; ?>&search=<?php echo urlencode($search); ?>" aria-label="Previous">
                                <span aria-hidden="true">&laquo;</span>
                            </a>
                        </li>
                        
                        <!-- Page Numbers -->
                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <li class="page-item <?php if ($current_page == $i) echo 'active'; ?>">
                                <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>"><?php echo $i; ?></a>
                            </li>
                        <?php endfor; ?>

                        <!-- Next Button -->
                        <li class="page-item <?php if ($current_page >= $total_pages) echo 'disabled'; ?>">
                            <a class="page-link" href="?page=<?php echo $current_page + 1; ?>&search=<?php echo urlencode($search); ?>" aria-label="Next">
                                <span aria-hidden="true">&raquo;</span>
                            </a>
                        </li>
                    </ul>
                </nav>
            <?php endif; ?>

            <!-- Edit User Modal -->
            <div class="modal fade" id="editUserModal" tabindex="-1" aria-labelledby="editUserModalLabel" aria-hidden="true">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <form action="edit_user.php" method="POST" onsubmit="return validateEditUserForm()">
                            <div class="modal-header">
                                <h5 class="modal-title" id="editUserModalLabel">Edit User</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                <!-- CSRF Token -->
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                
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

                                <!-- Role -->
                                <div class="mb-3">
                                    <label for="editRole" class="form-label">Role <span class="text-danger">*</span></label>
                                    <input type="text" id="editRole" name="role" class="form-control bg-dark text-light" required readonly>
                                    
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                <button type="submit" class="btn btn-primary">Save Changes</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Delete User Modal -->
            <div class="modal fade" id="deleteUserModal" tabindex="-1" aria-labelledby="deleteUserModalLabel" aria-hidden="true">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <form action="delete_user.php" method="POST">
                            <div class="modal-header">
                                <h5 class="modal-title" id="deleteUserModalLabel">Delete User</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                <!-- CSRF Token -->
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                
                                <!-- User ID -->
                                <input type="hidden" name="user_id" id="deleteUserId">

                                <p>Are you sure you want to delete <strong id="deleteUsername"></strong>?</p>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                <button type="submit" class="btn btn-danger">Delete</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

        </main>
    </div>
</div>

<!-- Footer -->
<?php include '../include/footer.php'; ?>

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
        });

        // Handle Delete Button Click
        $('.delete-btn').on('click', function() {
            const userId = $(this).data('id');
            const username = $(this).data('username');

            // Populate the delete form fields
            $('#deleteUserId').val(userId);
            $('#deleteUsername').text(username);
        });
    });

    // Form Validation for Registration
    function validateUserForm() {
        const firstName = document.getElementById("first_name").value.trim();
        const lastName = document.getElementById("last_name").value.trim();
        const username = document.getElementById("username").value.trim();
        const email = document.getElementById("email").value.trim();
        const password = document.getElementById("password").value;
        const role = document.getElementById("role").value;

        if (!firstName || !lastName || !username || !email || !password || !role) {
            alert("All fields are required.");
            return false;
        }

        // Additional validation can be added here (e.g., email format, password strength)

        return true; // Allow form submission
    }

    // Form Validation for Edit
    function validateEditUserForm() {
        const firstName = document.getElementById("editFirstName").value.trim();
        const lastName = document.getElementById("editLastName").value.trim();
        const email = document.getElementById("editEmail").value.trim();
        const role = document.getElementById("editRole").value;

        if (!firstName || !lastName || !email || !role) {
            alert("All fields are required.");
            return false;
        }

        // Additional validation can be added here (e.g., email format)

        return true; // Allow form submission
    }
</script>

</body>
</html>
