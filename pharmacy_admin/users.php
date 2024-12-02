<?php
session_start();

// Generate CSRF token if not set
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Check if the user is authenticated and is a pharmacy_admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'pharmacy_admin') {
    header("Location: ../login.php"); // Redirect to login page
    exit();
}

$pharmacy_id = $_SESSION['pharmacy_id']; // Assuming pharmacy_id is stored in session

include '../include/connection.php'; // Include the database connection

// Function to fetch users (pharmacy_admin and pharmacist)
function getPharmacyUsers($connection, $pharmacy_id, $search = '', $limit = 10, $offset = 0) {
    $query = "SELECT * FROM pharmacy_users 
              WHERE pharmacy_id = :pharmacy_id  AND pharmacy_user_id != :user_id
              AND (first_name LIKE :search OR last_name LIKE :search OR email LIKE :search)
              ORDER BY first_name ASC 
              LIMIT :limit OFFSET :offset";
    $stmt = $connection->prepare($query);
    $search_param = "%$search%";
    $stmt->bindParam(':pharmacy_id', $pharmacy_id, PDO::PARAM_INT);
    $stmt->bindParam(':user_id', $_SESSION['user_id'], PDO::PARAM_INT);
    $stmt->bindParam(':search', $search_param, PDO::PARAM_STR);
    $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Function to count total pharmacy users for pagination
function countPharmacyUsers($connection, $pharmacy_id) {
    $query = "SELECT COUNT(*) FROM pharmacy_users 
              WHERE pharmacy_id = :pharmacy_id 
              ";
    $stmt = $connection->prepare($query);
    $stmt->bindParam(':pharmacy_id', $pharmacy_id, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchColumn();
}

// Function to fetch medications
function getMedications($connection) {
    $query = "SELECT * FROM medications ORDER BY medication_name ASC";
    $stmt = $connection->prepare($query);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Function to count total medications for pagination
function countMedications($connection) {
    $query = "SELECT * FROM medications ORDER BY medication_name ASC";
    $stmt = $connection->prepare($query);
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

// Pagination settings for users
$records_per_page = 10;
$current_page_users = isset($_GET['page_users']) ? (int)$_GET['page_users'] : 1;
if ($current_page_users < 1) $current_page_users = 1;
$search_users = isset($_GET['search_users']) ? trim($_GET['search_users']) : '';
$offset_users = ($current_page_users - 1) * $records_per_page;

// Pagination settings for medications
$current_page_medications = isset($_GET['page_medications']) ? (int)$_GET['page_medications'] : 1;
if ($current_page_medications < 1) $current_page_medications = 1;
$search_medications = isset($_GET['search_medications']) ? trim($_GET['search_medications']) : '';
$offset_medications = ($current_page_medications - 1) * $records_per_page;

// Fetch data
$pharmacy_users = getPharmacyUsers($connection, $pharmacy_id, $search_users, $records_per_page, $offset_users);
$total_users = countPharmacyUsers($connection, $pharmacy_id, $search_users);
$total_pages_users = ceil($total_users / $records_per_page);

$medications = getMedications($connection, $search_medications, $records_per_page, $offset_medications);
$total_medications = countMedications($connection, $search_medications);
$total_pages_medications = ceil($total_medications / $records_per_page);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pharmacy Admin Management</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- DataTables CSS -->
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap5.min.css">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">

    <link rel="icon" type="image/x-icon" href="../images/logo-removebg-preview.png">
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="../css/style.css">
</head>
<body>

<!-- Header with logo and title -->
<?php include './header.php'; ?>

<!-- Navigation -->


<!-- Main Content -->
<div class="container-fluid">

    <div class="row">


    <?php include './nav.php'; ?>
    <?php include './mobile.php'; ?>
        
    <main class="col-lg-10 ms-auto main-content p-4">
            
            <h1 class="mb-4">Welcome, <?php echo htmlspecialchars($_SESSION['first_name']); ?>!</h1>

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

            <!-- Navigation Links -->
           

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
                                <h5 class="modal-title" id="registerUserModalLabel">Register User</h5>
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
                                    <select id="role" name="role" class="form-select" required>
                                        <option value="" disabled selected>Choose User Role</option>
                                        <option value="pharmacy_admin">Pharmacy Admin</option>
                                        <option value="pharmacist">Pharmacist</option>
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

            <!-- Register Medication Button -->
            <div class="mb-4">
                <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#registerMedicationModal">
                    <i class="fas fa-pills"></i> Register Medication
                </button>
            </div>

            <!-- Register Medication Modal -->
            <div class="modal fade" id="registerMedicationModal" tabindex="-1" aria-labelledby="registerMedicationModalLabel" aria-hidden="true">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <form action="register_medication.php" method="POST" onsubmit="return validateMedicationForm()">
                            <div class="modal-header">
                                <h5 class="modal-title" id="registerMedicationModalLabel">Register Medication</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                <!-- CSRF Token -->
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                
                                <!-- Medication Name -->
                                <div class="mb-3">
                                    <label for="medication_name" class="form-label">Medication Name <span class="text-danger">*</span></label>
                                    <input type="text" id="medication_name" name="medication_name" class="form-control" required>
                                </div>

                                <!-- Description -->
                                <div class="mb-3">
                                    <label for="description" class="form-label">Description <span class="text-danger">*</span></label>
                                    <textarea id="description" name="description" class="form-control" rows="3" required></textarea>
                                </div>

                                <!-- Insurance Coverage -->
                                <div class="mb-3 bg-dark text-light">
                                    <label class="form-label">Insurance Coverage <span class="text-danger">*</span></label>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="insurance_coverage" id="coverageYes" value="1" required>
                                        <label class="form-check-label" for="coverageYes">
                                            Yes
                                        </label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="insurance_coverage" id="coverageNo" value="0" required>
                                        <label class="form-check-label" for="coverageNo">
                                            No
                                        </label>
                                    </div>
                                </div>

                                <!-- Unit Price -->
                                <div class="mb-3">
                                    <label for="unit_price" class="form-label">Unit Price (Rwf) <span class="text-danger">*</span></label>
                                    <input type="number" step="0.01" id="unit_price" name="unit_price" class="form-control" required min="0">
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                <button type="submit" class="btn btn-success">Register Medication</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Users Table -->
            <div class="container mt-5">
                <h2 class="text-center mb-4">Pharmacy Users</h2>

                

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
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            if ($pharmacy_users) {
                                $count = $offset_users + 1;
                                foreach ($pharmacy_users as $user) {
                                    if ($user['pharmacy_user_id'] != $_SESSION['user_id']){
                                    echo "<tr>
                                            <td>" . htmlspecialchars($count++) . "</td>
                                            <td>" . htmlspecialchars($user['first_name']) . "</td>
                                            <td>" . htmlspecialchars($user['last_name']) . "</td>
                                            <td>" . htmlspecialchars($user['username']) . "</td>
                                            <td>" . htmlspecialchars($user['email']) . "</td>
                                            <td>" . htmlspecialchars(ucfirst(str_replace('_', ' ', $user['role']))) . "</td>
                                            <td>
                                                <button class='btn btn-primary btn-sm edit-user-btn' 
                                                    data-bs-toggle='modal' 
                                                    data-bs-target='#editUserModal' 
                                                    data-id='" . htmlspecialchars($user['pharmacy_user_id']) . "' 
                                                    data-firstname='" . htmlspecialchars($user['first_name']) . "' 
                                                    data-lastname='" . htmlspecialchars($user['last_name']) . "' 
                                                    data-username='" . htmlspecialchars($user['username']) . "' 
                                                    data-email='" . htmlspecialchars($user['email']) . "' 
                                                    data-role='" . htmlspecialchars($user['role']) . "'>
                                                    <i class='fas fa-edit'></i> Edit
                                                </button>
                                                <button class='btn btn-danger btn-sm delete-user-btn' 
                                                    data-bs-toggle='modal' 
                                                    data-bs-target='#deleteUserModal' 
                                                    data-id='" . htmlspecialchars($user['pharmacy_user_id']) . "' 
                                                    data-name='" . htmlspecialchars($user['username']) . "'>
                                                    <i class='fas fa-trash-alt'></i> Delete
                                                </button>
                                            </td>
                                        </tr>";
                                    }
                                }
                            } else {
                                echo "<tr><td colspan='7' class='text-center'>No users found.</td></tr>";
                            }
                            ?>
                        </tbody>
                    </table>
                </div>

                <!-- Users Pagination -->
                <?php if ($total_pages_users > 1): ?>
                    <nav aria-label="User pagination">
                        <ul class="pagination justify-content-center">
                            <!-- Previous Button -->
                            <li class="page-item <?php if ($current_page_users <= 1) echo 'disabled'; ?>">
                                <a class="page-link" href="?page_users=<?php echo $current_page_users - 1; ?>&search_users=<?php echo urlencode($search_users); ?>" aria-label="Previous">
                                    <span aria-hidden="true">&laquo;</span>
                                </a>
                            </li>
                            
                            <!-- Page Numbers -->
                            <?php for ($i = 1; $i <= $total_pages_users; $i++): ?>
                                <li class="page-item <?php if ($current_page_users == $i) echo 'active'; ?>">
                                    <a class="page-link" href="?page_users=<?php echo $i; ?>&search_users=<?php echo urlencode($search_users); ?>"><?php echo $i; ?></a>
                                </li>
                            <?php endfor; ?>

                            <!-- Next Button -->
                            <li class="page-item <?php if ($current_page_users >= $total_pages_users) echo 'disabled'; ?>">
                                <a class="page-link" href="?page_users=<?php echo $current_page_users + 1; ?>&search_users=<?php echo urlencode($search_users); ?>" aria-label="Next">
                                    <span aria-hidden="true">&raquo;</span>
                                </a>
                            </li>
                        </ul>
                    </nav>
                <?php endif; ?>
            </div>

            <!-- Medications Table -->
            <div class="container mt-5">
                <h2 class="text-center mb-4">Medications</h2>

                

                <!-- Medications Table -->
                <div class="table-responsive">
                    <table id="medicationsTable" class="table table-striped table-bordered">
                        <thead class="table-dark">
                            <tr>
                                <th>#</th>
                                <th>Medication Name</th>
                                <th>Description</th>
                                <th>Insurance Coverage</th>
                                <th>Unit Price (Rwf)</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            if ($medications) {
                                $count = $offset_medications + 1;
                                foreach ($medications as $medication) {
                                    echo "<tr>
                                            <td>" . htmlspecialchars($count++) . "</td>
                                            <td>" . htmlspecialchars($medication['medication_name']) . "</td>
                                            <td>" . htmlspecialchars($medication['description']) . "</td>
                                            <td>" . ($medication['insurance_coverage'] ? 'Yes' : 'No') . "</td>
                                            <td>" . htmlspecialchars(number_format($medication['unit_price'], 2)) . "</td>
                                            <td>
                                                <button class='btn btn-primary btn-sm edit-medication-btn' 
                                                    data-bs-toggle='modal' 
                                                    data-bs-target='#editMedicationModal' 
                                                    data-id='" . htmlspecialchars($medication['medication_id']) . "' 
                                                    data-name='" . htmlspecialchars($medication['medication_name']) . "' 
                                                    data-description='" . htmlspecialchars($medication['description']) . "' 
                                                    data-coverage='" . htmlspecialchars($medication['insurance_coverage']) . "' 
                                                    data-price='" . htmlspecialchars($medication['unit_price']) . "'>
                                                    <i class='fas fa-edit'></i> Edit
                                                </button>
                                                <button class='btn btn-danger btn-sm delete-medication-btn my-2' 
                                                    data-bs-toggle='modal' 
                                                    data-bs-target='#deleteMedicationModal' 
                                                    data-id='" . htmlspecialchars($medication['medication_id']) . "' 
                                                    data-name='" . htmlspecialchars($medication['medication_name']) . "'>
                                                    <i class='fas fa-trash-alt'></i> Delete
                                                </button>
                                            </td>
                                        </tr>";
                                }
                            } else {
                                echo "<tr><td colspan='6' class='text-center'>No medications found.</td></tr>";
                            }
                            ?>
                        </tbody>
                    </table>
                </div>

                <!-- Medications Pagination -->
                <?php if ($total_pages_medications > 1): ?>
                    
                <?php endif; ?>
            </div>

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
                                <input type="hidden" name="pharmacy_user_id" id="editUserId">
    
                                <!-- First Name -->
                                <div class="mb-3">
                                    <label for="editFirstName" class="form-label">First Name <span class="text-danger">*</span></label>
                                    <input type="text" id="editFirstName" name="first_name" class="form-control" required>
                                </div>

                                <!-- Last Name -->
                                <div class="mb-3">
                                    <label for="editLastName" class="form-label">Last Name <span class="text-danger">*</span></label>
                                    <input type="text" id="editLastName" name="last_name" class="form-control" required>
                                </div>

                                <!-- Username (Read-Only) -->
                                <div class="mb-3">
                                    <label for="editUsername" class="form-label">Username <span class="text-danger">*</span></label>
                                    <input type="text" id="editUsername" name="username" class="form-control bg-dark text-light" readonly required>
                                </div>

                                <!-- Email -->
                                <div class="mb-3">
                                    <label for="editEmail" class="form-label">Email <span class="text-danger">*</span></label>
                                    <input type="email" id="editEmail" name="email" class="form-control" required>
                                </div>

                                <!-- Role (Read-Only) -->
                                <div class="mb-3">
                                    <label for="editRole" class="form-label">Role <span class="text-danger">*</span></label>
                                    <input type="text" id="editRole" name="role" class="form-control bg-dark text-light" readonly required>
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
                                <input type="hidden" name="pharmacy_user_id" id="deleteUserId">
    
                                <p>Are you sure you want to delete <strong id="deleteUserName"></strong>?</p>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                <button type="submit" class="btn btn-danger">Delete</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Edit Medication Modal -->
            <div class="modal fade" id="editMedicationModal" tabindex="-1" aria-labelledby="editMedicationModalLabel" aria-hidden="true">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <form action="edit_medication.php" method="POST" onsubmit="return validateEditMedicationForm()">
                            <div class="modal-header">
                                <h5 class="modal-title" id="editMedicationModalLabel">Edit Medication</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                <!-- CSRF Token -->
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                
                                <!-- Medication ID -->
                                <input type="hidden" name="medication_id" id="editMedicationId">

                                <!-- Medication Name -->
                                <div class="mb-3">
                                    <label for="editMedicationName" class="form-label">Medication Name <span class="text-danger">*</span></label>
                                    <input type="text" id="editMedicationName" name="medication_name" class="form-control" required>
                                </div>

                                <!-- Description -->
                                <div class="mb-3">
                                    <label for="editDescription" class="form-label">Description <span class="text-danger">*</span></label>
                                    <textarea id="editDescription" name="description" class="form-control" rows="3" required></textarea>
                                </div>

                                <!-- Insurance Coverage -->
                                <div class="mb-3">
                                    <label class="form-label">Insurance Coverage <span class="text-danger">*</span></label>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="insurance_coverage" id="editCoverageYes" value="1" required>
                                        <label class="form-check-label" for="editCoverageYes">
                                            Yes
                                        </label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="insurance_coverage" id="editCoverageNo" value="0" required>
                                        <label class="form-check-label" for="editCoverageNo">
                                            No
                                        </label>
                                    </div>
                                </div>

                                <!-- Unit Price -->
                                <div class="mb-3">
                                    <label for="editUnitPrice" class="form-label">Unit Price (Rwf) <span class="text-danger">*</span></label>
                                    <input type="number" step="0.01" id="editUnitPrice" name="unit_price" class="form-control" required min="0">
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

            <!-- Delete Medication Modal -->
            <div class="modal fade" id="deleteMedicationModal" tabindex="-1" aria-labelledby="deleteMedicationModalLabel" aria-hidden="true">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <form action="delete_medication.php" method="POST">
                            <div class="modal-header">
                                <h5 class="modal-title" id="deleteMedicationModalLabel">Delete Medication</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                <!-- CSRF Token -->
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                
                                <!-- Medication ID -->
                                <input type="hidden" name="medication_id" id="deleteMedicationId">
    
                                <p>Are you sure you want to delete <strong id="deleteMedicationName"></strong>?</p>
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
        // Initialize DataTables for Users
        $('#usersTable').DataTable({
            "paging": true, // Disable DataTables pagination since we're using server-side pagination
            "searching": true, // Disable DataTables search since we're using server-side search
            "ordering": true,
            "lengthMenu": [5, 10, 25, 50, 100],  // Page length options
            "pageLength": 5, 
            "info": true,
            "autoWidth": true,
            "responsive": true
        });

        // Initialize DataTables for Medications
        $('#medicationsTable').DataTable({
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

        // Handle Edit User Button Click
        $('.edit-user-btn').on('click', function() {
            const userId = $(this).data('id');
            const firstName = $(this).data('firstname');
            const lastName = $(this).data('lastname');
            const username = $(this).data('username');
            const email = $(this).data('email');
            const role = $(this).data('role');

            $('#editUserId').val(userId);
            $('#editFirstName').val(firstName);
            $('#editLastName').val(lastName);
            $('#editUsername').val(username);
            $('#editEmail').val(email);
            $('#editRole').val(role);
        });

        // Handle Delete User Button Click
        $('.delete-user-btn').on('click', function() {
            const userId = $(this).data('id');
            const username = $(this).data('name');

            $('#deleteUserId').val(userId);
            $('#deleteUserName').text(username);
        });

        // Handle Edit Medication Button Click
        $('.edit-medication-btn').on('click', function() {
            const medicationId = $(this).data('id');
            const medicationName = $(this).data('name');
            const description = $(this).data('description');
            const coverage = $(this).data('coverage');
            const unitPrice = $(this).data('price');

            $('#editMedicationId').val(medicationId);
            $('#editMedicationName').val(medicationName);
            $('#editDescription').val(description);
            if (coverage == '1') {
                $('#editCoverageYes').prop('checked', true);
            } else {
                $('#editCoverageNo').prop('checked', true);
            }
            $('#editUnitPrice').val(unitPrice);
        });

        // Handle Delete Medication Button Click
        $('.delete-medication-btn').on('click', function() {
            const medicationId = $(this).data('id');
            const medicationName = $(this).data('name');

            $('#deleteMedicationId').val(medicationId);
            $('#deleteMedicationName').text(medicationName);
        });
    });

    // Form Validation for User Registration
    function validateUserForm() {
        const password = document.getElementById("password").value;
        const confirmPassword = document.getElementById("confirm_password").value;
        if (password !== confirmPassword) {
            alert("Passwords do not match.");
            return false;
        }
        return true;
    }

    // Form Validation for Medication Registration
    function validateMedicationForm() {
        const unitPrice = document.getElementById("unit_price").value;
        if (unitPrice < 0) {
            alert("Unit price cannot be negative.");
            return false;
        }
        return true;
    }

    // Form Validation for Edit User
    function validateEditUserForm() {
        // Add any specific validation if needed
        return true;
    }

    // Form Validation for Edit Medication
    function validateEditMedicationForm() {
        const unitPrice = document.getElementById("editUnitPrice").value;
        if (unitPrice < 0) {
            alert("Unit price cannot be negative.");
            return false;
        }
        return true;
    }
</script>

</body>
</html>
