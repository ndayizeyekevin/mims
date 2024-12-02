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

// Function to fetch all pharmacies with search and pagination
function getPharmacies($connection, $search = '', $limit = 10, $offset = 0) {
    $query = "SELECT * FROM pharmacies WHERE pharmacy_name LIKE :search OR email LIKE :search OR phone_number LIKE :search ORDER BY pharmacy_name ASC LIMIT :limit OFFSET :offset";
    $stmt = $connection->prepare($query);
    $search_param = "%$search%";
    $stmt->bindParam(':search', $search_param, PDO::PARAM_STR);
    $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Function to count total pharmacies for pagination
function countPharmacies($connection, $search = '') {
    $query = "SELECT COUNT(*) FROM pharmacies WHERE pharmacy_name LIKE :search OR email LIKE :search OR phone_number LIKE :search";
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
if ($current_page < 1) $current_page = 1;
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$offset = ($current_page - 1) * $records_per_page;

// Fetch pharmacies based on search and pagination
$pharmacies = getPharmacies($connection, $search, $records_per_page, $offset);
$total_records = countPharmacies($connection, $search);
$total_pages = ceil($total_records / $records_per_page);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pharmacies Management</title>
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
            
            <h1 class="mb-4">Manage Pharmacies</h1>

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

            <!-- Register Pharmacy Button -->
            <div class="mb-4">
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#registerPharmacyModal">
                    <i class="fas fa-plus-circle"></i> Register Pharmacy
                </button>
            </div>

            <!-- Register Pharmacy Modal -->
            <div class="modal fade" id="registerPharmacyModal" tabindex="-1" aria-labelledby="registerPharmacyModalLabel" aria-hidden="true">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <form action="register_pharmacy.php" method="POST" onsubmit="return validatePharmacyForm()">
                            <div class="modal-header">
                                <h5 class="modal-title" id="registerPharmacyModalLabel">Register Pharmacy</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                <!-- CSRF Token -->
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                
                                <!-- Pharmacy Name -->
                                <div class="mb-3">
                                    <label for="pharmacy_name" class="form-label">Pharmacy Name <span class="text-danger">*</span></label>
                                    <input type="text" id="pharmacy_name" name="pharmacy_name" class="form-control" required>
                                </div>

                                <!-- Email -->
                                <div class="mb-3">
                                    <label for="email" class="form-label">Email <span class="text-danger">*</span></label>
                                    <input type="email" id="email" name="email" class="form-control" required>
                                </div>

                                <!-- Phone Number -->
                                <div class="mb-3">
                                    <label for="phone_number" class="form-label">Phone Number <span class="text-danger">*</span></label>
                                    <input type="tel" id="phone_number" name="phone_number" class="form-control" required pattern="[0-9]{10}" title="Enter a 10-digit phone number">
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                <button type="submit" class="btn btn-primary">Register Pharmacy</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Pharmacies Table -->
            <div class="table-responsive">
                <table id="pharmaciesTable" class="table table-striped table-bordered">
                    <thead class="table-dark">
                        <tr>
                            <th>#</th>
                            <th>Pharmacy Name</th>
                            <th>Email</th>
                            <th>Phone Number</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        if ($pharmacies) {
                            $count = $offset + 1;
                            foreach ($pharmacies as $pharmacy) {
                                echo "<tr>
                                        <td>" . htmlspecialchars($count++) . "</td>
                                        <td>" . htmlspecialchars($pharmacy['pharmacy_name']) . "</td>
                                        <td>" . htmlspecialchars($pharmacy['email']) . "</td>
                                        <td>" . htmlspecialchars($pharmacy['phone_number']) . "</td>
                                        <td>
                                            <button class='btn btn-primary btn-sm edit-btn' 
                                                data-bs-toggle='modal' 
                                                data-bs-target='#editPharmacyModal' 
                                                data-id='" . htmlspecialchars($pharmacy['pharmacy_id']) . "' 
                                                data-name='" . htmlspecialchars($pharmacy['pharmacy_name']) . "' 
                                                data-email='" . htmlspecialchars($pharmacy['email']) . "' 
                                                data-phone='" . htmlspecialchars($pharmacy['phone_number']) . "'>
                                                <i class='fas fa-edit'></i> Edit
                                            </button>
                                            <button class='btn btn-danger btn-sm delete-btn' 
                                                data-bs-toggle='modal' 
                                                data-bs-target='#deletePharmacyModal' 
                                                data-id='" . htmlspecialchars($pharmacy['pharmacy_id']) . "' 
                                                data-name='" . htmlspecialchars($pharmacy['pharmacy_name']) . "'>
                                                <i class='fas fa-trash-alt'></i> Delete
                                            </button>
                                        </td>
                                    </tr>";
                            }
                        } else {
                            echo "<tr><td colspan='5' class='text-center'>No pharmacies found.</td></tr>";
                        }
                        ?>
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

            <!-- Edit Pharmacy Modal -->
            <div class="modal fade" id="editPharmacyModal" tabindex="-1" aria-labelledby="editPharmacyModalLabel" aria-hidden="true">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <form action="edit_pharmacy.php" method="POST" onsubmit="return validateEditPharmacyForm()">
                            <div class="modal-header">
                                <h5 class="modal-title" id="editPharmacyModalLabel">Edit Pharmacy</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                <!-- CSRF Token -->
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                
                                <!-- Pharmacy ID -->
                                <input type="hidden" name="pharmacy_id" id="editPharmacyId">
    
                                <!-- Pharmacy Name -->
                                <div class="mb-3">
                                    <label for="editPharmacyName" class="form-label">Pharmacy Name <span class="text-danger">*</span></label>
                                    <input type="text" id="editPharmacyName" name="pharmacy_name" class="form-control" required>
                                </div>

                                <!-- Email -->
                                <div class="mb-3">
                                    <label for="editEmail" class="form-label">Email <span class="text-danger">*</span></label>
                                    <input type="email" id="editEmail" name="email" class="form-control" required>
                                </div>

                                <!-- Phone Number -->
                                <div class="mb-3">
                                    <label for="editPhoneNumber" class="form-label">Phone Number <span class="text-danger">*</span></label>
                                    <input type="tel" id="editPhoneNumber" name="phone_number" class="form-control" required pattern="[0-9]{10}" title="Enter a 10-digit phone number">
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

            <!-- Delete Pharmacy Modal -->
            <div class="modal fade" id="deletePharmacyModal" tabindex="-1" aria-labelledby="deletePharmacyModalLabel" aria-hidden="true">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <form action="delete_pharmacy.php" method="POST">
                            <div class="modal-header">
                                <h5 class="modal-title" id="deletePharmacyModalLabel">Delete Pharmacy</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                <!-- CSRF Token -->
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                
                                <!-- Pharmacy ID -->
                                <input type="hidden" name="pharmacy_id" id="deletePharmacyId">
    
                                <p>Are you sure you want to delete <strong id="deletePharmacyName"></strong>?</p>
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
        $('#pharmaciesTable').DataTable({
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
            const pharmacyId = $(this).data('id');
            const pharmacyName = $(this).data('name');
            const email = $(this).data('email');
            const phoneNumber = $(this).data('phone');

            // Populate the edit form fields
            $('#editPharmacyId').val(pharmacyId);
            $('#editPharmacyName').val(pharmacyName);
            $('#editEmail').val(email);
            $('#editPhoneNumber').val(phoneNumber);
        });

        // Handle Delete Button Click
        $('.delete-btn').on('click', function() {
            const pharmacyId = $(this).data('id');
            const pharmacyName = $(this).data('name');

            // Populate the delete form fields
            $('#deletePharmacyId').val(pharmacyId);
            $('#deletePharmacyName').text(pharmacyName);
        });
    });

    // Form Validation for Registration
    function validatePharmacyForm() {
        const pharmacyName = document.getElementById("pharmacy_name").value.trim();
        const email = document.getElementById("email").value.trim();
        const phoneNumber = document.getElementById("phone_number").value.trim();

        if (pharmacyName === "" || email === "" || phoneNumber === "") {
            alert("All fields are required.");
            return false;
        }

        const phonePattern = /^[0-9]{10}$/;
        if (!phonePattern.test(phoneNumber)) {
            alert("Please enter a valid 10-digit phone number.");
            return false;
        }

        return true; // Allow form submission
    }

    // Form Validation for Edit
    function validateEditPharmacyForm() {
        const pharmacyName = document.getElementById("editPharmacyName").value.trim();
        const email = document.getElementById("editEmail").value.trim();
        const phoneNumber = document.getElementById("editPhoneNumber").value.trim();

        if (pharmacyName === "" || email === "" || phoneNumber === "") {
            alert("All fields are required.");
            return false;
        }

        const phonePattern = /^[0-9]{10}$/;
        if (!phonePattern.test(phoneNumber)) {
            alert("Please enter a valid 10-digit phone number.");
            return false;
        }

        return true; // Allow form submission
    }
</script>

</body>
</html>
