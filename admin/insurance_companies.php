<?php
session_start();

// Generate CSRF token if not set
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Check if the user is authenticated
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php"); // Redirect to login page
    exit();
}

include '../include/connection.php'; // Include the database connection

// Fetch insurance companies from the database
function getInsuranceCompanies($connection, $search = '', $limit = 10, $offset = 0) {
    $query = "SELECT * FROM insurance_companies WHERE insurance_name LIKE :search ORDER BY insurance_name ASC LIMIT :limit OFFSET :offset";
    $stmt = $connection->prepare($query);
    $search_param = "%$search%";
    $stmt->bindParam(':search', $search_param, PDO::PARAM_STR);
    $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Count total insurance companies for pagination
function countInsuranceCompanies($connection, $search = '') {
    $query = "SELECT COUNT(*) FROM insurance_companies WHERE insurance_name LIKE :search";
    $stmt = $connection->prepare($query);
    $search_param = "%$search%";
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
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$offset = ($current_page - 1) * $records_per_page;

// Fetch insurance companies based on search and pagination
$insurance_companies = getInsuranceCompanies($connection, $search, $records_per_page, $offset);
$total_records = countInsuranceCompanies($connection, $search);
$total_pages = ceil($total_records / $records_per_page);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Insurance Companies Management</title>
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
           



            <h1 class="mb-4">Manage Insurance Companies</h1>

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

            <!-- Register Insurance Company Button -->
            <div class="mb-4">
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#registerInsuranceModal">
                    <i class="fas fa-plus-circle"></i> Register Insurance Company
                </button>
            </div>

            <!-- Register Insurance Company Modal -->
            <div class="modal fade" id="registerInsuranceModal" tabindex="-1" aria-labelledby="registerInsuranceModalLabel" aria-hidden="true">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <form action="register_insurance.php" method="POST">
                            <div class="modal-header">
                                <h5 class="modal-title" id="registerInsuranceModalLabel">Register Insurance Company</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                <!-- CSRF Token -->
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                
                                <!-- Insurance Name -->
                                <div class="mb-3">
                                    <label for="insurance_name" class="form-label">Insurance Company Name <span class="text-danger">*</span></label>
                                    <input type="text" id="insurance_name" name="insurance_name" class="form-control" required>
                                </div>

                                <!-- Coverage Details -->
                                <div class="mb-3">
                                    <label for="coverage_details" class="form-label">Coverage Details <span class="text-danger">*</span></label>
                                    <textarea id="coverage_details" name="coverage_details" class="form-control" rows="3" required></textarea>
                                </div>

                                <!-- Email -->
                                <div class="mb-3">
                                    <label for="email" class="form-label">Email <span class="text-danger">*</span></label>
                                    <input type="email" id="email" name="email" class="form-control" required>
                                </div>

                                <!-- Phone Number -->
                                <div class="mb-3">
                                    <label for="phonenumber" class="form-label">Phone Number <span class="text-danger">*</span></label>
                                    <input type="tel" id="phonenumber" name="phonenumber" class="form-control" required pattern="([0-9]{10}|[0-9]{4})" title="Enter 10 or 4-digit number">
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                <button type="submit" class="btn btn-primary">Register Insurance Company</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Insurance Companies Table -->
            <div class="table-responsive">
                <table id="insuranceTable" class="table table-striped table-bordered">
                    <thead class="table-dark">
                        <tr>
                            <th>#</th>
                            <th>Insurance Name</th>
                            <th>Coverage Details</th>
                            <th>Email</th>
                            <th>Phone Number</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($insurance_companies): ?>
                            <?php $count = $offset + 1; ?>
                            <?php foreach ($insurance_companies as $insurance): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($count++); ?></td>
                                    <td><?php echo htmlspecialchars($insurance['insurance_name']); ?></td>
                                    <td><?php echo htmlspecialchars($insurance['coverage_percentage']); ?></td>
                                    <td><?php echo htmlspecialchars($insurance['email']); ?></td>
                                    <td><?php echo htmlspecialchars($insurance['phonenumber']); ?></td>
                                    <td>
                                        <button class="btn btn-primary btn-sm edit-btn" data-bs-toggle="modal" data-bs-target="#editInsuranceModal" 
                                            data-id="<?php echo htmlspecialchars($insurance['insurance_id']); ?>"
                                            data-name="<?php echo htmlspecialchars($insurance['insurance_name']); ?>"
                                            data-coverage="<?php echo htmlspecialchars($insurance['coverage_percentage']); ?>"
                                            data-email="<?php echo htmlspecialchars($insurance['email']); ?>"
                                            data-phone="<?php echo htmlspecialchars($insurance['phonenumber']); ?>">
                                            <i class="fas fa-edit"></i> Edit
                                        </button>
                                        <button class="btn btn-danger btn-sm delete-btn" data-bs-toggle="modal" data-bs-target="#deleteInsuranceModal" 
                                            data-id="<?php echo htmlspecialchars($insurance['insurance_id']); ?>"
                                            data-name="<?php echo htmlspecialchars($insurance['insurance_name']); ?>">
                                            <i class="fas fa-trash-alt"></i> Delete
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="text-center">No insurance companies found.</td>
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

            <!-- Edit Insurance Company Modal -->
            <div class="modal fade" id="editInsuranceModal" tabindex="-1" aria-labelledby="editInsuranceModalLabel" aria-hidden="true">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <form action="edit_insurance.php" method="POST">
                            <div class="modal-header">
                                <h5 class="modal-title" id="editInsuranceModalLabel">Edit Insurance Company</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                <!-- CSRF Token -->
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                
                                <!-- Insurance ID -->
                                <input type="hidden" name="insurance_id" id="editInsuranceId">

                                <!-- Insurance Name -->
                                <div class="mb-3">
                                    <label for="editInsuranceName" class="form-label">Insurance Company Name <span class="text-danger">*</span></label>
                                    <input type="text" id="editInsuranceName" name="insurance_name" class="form-control" required>
                                </div>

                                <!-- Coverage Details -->
                                <div class="mb-3">
                                    <label for="editCoverageDetails" class="form-label">Coverage Details <span class="text-danger">*</span></label>
                                    <textarea id="editCoverageDetails" name="coverage_details" class="form-control" rows="3" required></textarea>
                                </div>

                                <!-- Email -->
                                <div class="mb-3">
                                    <label for="editEmail" class="form-label">Email <span class="text-danger">*</span></label>
                                    <input type="email" id="editEmail" name="email" class="form-control" required>
                                </div>

                                <!-- Phone Number -->
                                <div class="mb-3">
                                    <label for="editPhoneNumber" class="form-label">Phone/Toll-Free Number (10 or 4 digits) <span class="text-danger">*</span></label>
                                    <input type="tel" id="editPhoneNumber" name="phonenumber" class="form-control" required pattern="([0-9]{10}|[0-9]{4})" title="Enter 10 or 4-digit number">
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

            <!-- Delete Insurance Company Modal -->
            <div class="modal fade" id="deleteInsuranceModal" tabindex="-1" aria-labelledby="deleteInsuranceModalLabel" aria-hidden="true">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <form action="delete_insurance.php" method="POST">
                            <div class="modal-header">
                                <h5 class="modal-title" id="deleteInsuranceModalLabel">Delete Insurance Company</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                <!-- CSRF Token -->
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                
                                <!-- Insurance ID -->
                                <input type="hidden" name="insurance_id" id="deleteInsuranceId">

                                <p>Are you sure you want to delete <strong id="deleteInsuranceName"></strong>?</p>
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
<!-- Bootstrap 5 JS Bundle (Includes Popper) -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

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
        $('#insuranceTable').DataTable({
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
            const insuranceId = $(this).data('id');
            const insuranceName = $(this).data('name');
            const coverageDetails = $(this).data('coverage');
            const email = $(this).data('email');
            const phoneNumber = $(this).data('phone');

            // Populate the edit form fields
            $('#editInsuranceId').val(insuranceId);
            $('#editInsuranceName').val(insuranceName);
            $('#editCoverageDetails').val(coverageDetails);
            $('#editEmail').val(email);
            $('#editPhoneNumber').val(phoneNumber);

            // Show the edit modal
            var editModal = new bootstrap.Modal(document.getElementById('editInsuranceModal'));
            editModal.show();
        });

        // Handle Delete Button Click
        $('.delete-btn').on('click', function() {
            const insuranceId = $(this).data('id');
            const insuranceName = $(this).data('name');

            // Populate the delete form fields
            $('#deleteInsuranceId').val(insuranceId);
            $('#deleteInsuranceName').text(insuranceName);

            // Show the delete modal
            var deleteModal = new bootstrap.Modal(document.getElementById('deleteInsuranceModal'));
            deleteModal.show();
        });
    });

    // Form Validation for Registration and Update
    function validateInsuranceForm() {
        // Add custom validation if needed
        return true;
    }
</script>

</body>
</html>
