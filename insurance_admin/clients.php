<?php
session_start();
require_once '../include/connection.php'; // Include your PDO connection file

// Ensure only insurance admins can access this page
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'insurance_admin') {
    header("Location: ../login.php");
    exit;
}

// Generate CSRF token if not set
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$current_insurance_id = $_SESSION['insurance_id'];

// Fetch the insurance clients to display in the table
$clientQuery = $connection->prepare("SELECT * FROM clients WHERE insurance_id = :insurance_id");
$clientQuery->bindParam(':insurance_id', $current_insurance_id, PDO::PARAM_INT);
$clientQuery->execute();
$clients = $clientQuery->fetchAll(PDO::FETCH_ASSOC);

// Fetch insurance companies for the dropdown
$insuranceQuery = $connection->prepare("SELECT * FROM insurance_companies WHERE insurance_id = :insurance_id");
$insuranceQuery->bindParam(':insurance_id', $current_insurance_id, PDO::PARAM_INT);
$insuranceQuery->execute();
$insurances = $insuranceQuery->fetchAll(PDO::FETCH_ASSOC);

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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Insurance Clients Management</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- DataTables CSS -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap5.min.css">
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


        <!-- Navigation -->
        <?php include './nav.php'; ?>
        <?php include './mobile.php'; ?>
        <!-- End Navigation -->

        <main class="col-lg-10 ms-auto main-content p-4">
            <h2 class="mb-4">Insurance Clients</h2>

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

            <!-- Register Client Button -->
            <button class="btn btn-primary mb-3" data-bs-toggle="modal" data-bs-target="#registerClientModal">
                <i class="fas fa-user-plus"></i> Register Client
            </button>

            <!-- Clients Table -->
            <table id="clientsTable" class="table table-striped table-bordered">
                <thead class="table-dark">
                    <tr>
                        <th>#</th>
                        <th>First Name</th>
                        <th>Last Name</th>
                        <th>Phone Number</th>
                        <th>Email</th>
                        <th>Insurance</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($clients): ?>
                        <?php foreach ($clients as $index => $client): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($index + 1); ?></td>
                                <td><?php echo htmlspecialchars($client['first_name']); ?></td>
                                <td><?php echo htmlspecialchars($client['last_name']); ?></td>
                                <td><?php echo htmlspecialchars($client['phone_number']); ?></td>
                                <td><?php echo htmlspecialchars($client['email']); ?></td>
                                <td>
                                    <?php
                                        // Fetch insurance company name
                                        $insuranceName = 'N/A';
                                        foreach ($insurances as $insurance) {
                                            if ($insurance['insurance_id'] == $client['insurance_id']) {
                                                $insuranceName = htmlspecialchars($insurance['insurance_name']);
                                                break;
                                            }
                                        }
                                        echo $insuranceName;
                                    ?>
                                </td>
                                <td>
                                    <button class="btn btn-primary btn-sm edit-btn"
                                        data-bs-toggle="modal"
                                        data-bs-target="#editClientModal"
                                        data-id="<?php echo htmlspecialchars($client['client_id']); ?>"
                                        data-firstname="<?php echo htmlspecialchars($client['first_name']); ?>"
                                        data-lastname="<?php echo htmlspecialchars($client['last_name']); ?>"
                                        data-phonenumber="<?php echo htmlspecialchars($client['phone_number']); ?>"
                                        data-email="<?php echo htmlspecialchars($client['email']); ?>"
                                        data-insuranceid="<?php echo htmlspecialchars($client['insurance_id']); ?>">
                                        <i class="fas fa-edit"></i> Edit
                                    </button>
                                    <button class="btn btn-danger btn-sm delete-btn"
                                        data-bs-toggle="modal"
                                        data-bs-target="#deleteClientModal"
                                        data-id="<?php echo htmlspecialchars($client['client_id']); ?>"
                                        data-firstname="<?php echo htmlspecialchars($client['first_name']); ?>"
                                        data-lastname="<?php echo htmlspecialchars($client['last_name']); ?>">
                                        <i class="fas fa-trash-alt"></i> Delete
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" class="text-center">No clients found.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>

            <!-- Register Client Modal -->
            <div class="modal fade" id="registerClientModal" tabindex="-1" aria-labelledby="registerClientModalLabel" aria-hidden="true">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <form action="register_client.php" method="POST" onsubmit="return validateClientForm()">
                            <div class="modal-header">
                                <h5 class="modal-title" id="registerClientModalLabel">Register Client</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                <!-- CSRF Token -->
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                
                                <!-- First Name -->
                                <div class="mb-3">
                                    <label for="first_name" class="form-label">First Name <span class="text-danger">*</span></label>
                                    <input type="text" id="first_name" name="first_name" class="form-control" required maxlength="100">
                                </div>

                                <!-- Last Name -->
                                <div class="mb-3">
                                    <label for="last_name" class="form-label">Last Name <span class="text-danger">*</span></label>
                                    <input type="text" id="last_name" name="last_name" class="form-control" required maxlength="100">
                                </div>

                                <!-- Phone Number -->
                                <div class="mb-3">
                                    <label for="phone_number" class="form-label">Phone Number <span class="text-danger">*</span></label>
                                    <input type="tel" id="phone_number" name="phone_number" class="form-control" required pattern="\d{10}" title="Enter a 10-digit phone number">
                                </div>

                                <!-- Email -->
                                <div class="mb-3">
                                    <label for="email" class="form-label">Email <span class="text-danger">*</span></label>
                                    <input type="email" id="email" name="email" class="form-control" required maxlength="255">
                                </div>

                                <!-- Insurance -->
                                <div class="mb-3">
                                    <label for="insurance_id" class="form-label">Insurance <span class="text-danger">*</span></label>
                                    <select id="insurance_id" name="insurance_id" class="form-control" required>
                                        <?php foreach ($insurances as $insurance) : ?>
                                            <option value="<?php echo htmlspecialchars($insurance['insurance_id']); ?>">
                                                <?php echo htmlspecialchars($insurance['insurance_name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                <button type="submit" class="btn btn-primary">Register Client</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Edit Client Modal -->
            <div class="modal fade" id="editClientModal" tabindex="-1" aria-labelledby="editClientModalLabel" aria-hidden="true">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <form action="update_client.php" method="POST" onsubmit="return validateEditClientForm()">
                            <div class="modal-header">
                                <h5 class="modal-title" id="editClientModalLabel">Edit Client</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                <!-- CSRF Token -->
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                
                                <!-- Client ID -->
                                <input type="hidden" name="client_id" id="editClientId">
        
                                <!-- First Name -->
                                <div class="mb-3">
                                    <label for="editFirstName" class="form-label">First Name <span class="text-danger">*</span></label>
                                    <input type="text" id="editFirstName" name="first_name" class="form-control" required maxlength="100">
                                </div>
        
                                <!-- Last Name -->
                                <div class="mb-3">
                                    <label for="editLastName" class="form-label">Last Name <span class="text-danger">*</span></label>
                                    <input type="text" id="editLastName" name="last_name" class="form-control" required maxlength="100">
                                </div>
        
                                <!-- Phone Number -->
                                <div class="mb-3">
                                    <label for="editPhoneNumber" class="form-label">Phone Number <span class="text-danger">*</span></label>
                                    <input type="tel" id="editPhoneNumber" name="phone_number" class="form-control" required pattern="\d{10}" title="Enter a 10-digit phone number">
                                </div>
        
                                <!-- Email -->
                                <div class="mb-3">
                                    <label for="editEmail" class="form-label">Email <span class="text-danger">*</span></label>
                                    <input type="email" id="editEmail" name="email" class="form-control" required maxlength="255">
                                </div>
        
                                <!-- Insurance ID (Read-Only) -->
                                <div class="mb-3">
                                    <label for="editInsuranceId" class="form-label">Insurance ID <span class="text-danger">*</span></label>
                                    <input type="number" id="editInsuranceId" name="insurance_id" class="form-control bg-dark text-light" required min="1" readonly>
                                </div>
        
                                <!-- Additional Fields as Needed -->
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                <button type="submit" class="btn btn-primary">Update Client</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Delete Client Modal -->
            <div class="modal fade" id="deleteClientModal" tabindex="-1" aria-labelledby="deleteClientModalLabel" aria-hidden="true">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <form action="delete_client.php" method="POST">
                            <div class="modal-header">
                                <h5 class="modal-title" id="deleteClientModalLabel">Delete Client</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                <!-- CSRF Token -->
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                
                                <!-- Client ID -->
                                <input type="hidden" name="client_id" id="deleteClientId">
        
                                <p>Are you sure you want to delete <strong id="deleteClientName"></strong>?</p>
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
<script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>
<!-- Font Awesome JS (For Icons) -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/js/all.min.js"></script>
<!-- Custom JS -->
<script>
    $(document).ready(function() {
        // Initialize DataTable
        $('#clientsTable').DataTable({
            "paging": true,
            "searching": true,
            "ordering": true,
            "info": true,
            "responsive": true
        });

        // Handle Edit Button Click
        $('.edit-btn').on('click', function() {
            const clientId = $(this).data('id');
            const firstName = $(this).data('firstname');
            const lastName = $(this).data('lastname');
            const phoneNumber = $(this).data('phonenumber');
            const email = $(this).data('email');
            const insuranceId = $(this).data('insuranceid');

            // Populate the modal fields
            $('#editClientId').val(clientId);
            $('#editFirstName').val(firstName);
            $('#editLastName').val(lastName);
            $('#editPhoneNumber').val(phoneNumber);
            $('#editEmail').val(email);
            $('#editInsuranceId').val(insuranceId);
        });

        // Handle Delete Button Click
        $('.delete-btn').on('click', function() {
            const clientId = $(this).data('id');
            const firstName = $(this).data('firstname');
            const lastName = $(this).data('lastname');
            const fullName = firstName + ' ' + lastName;

            // Populate the modal fields
            $('#deleteClientId').val(clientId);
            $('#deleteClientName').text(fullName);
        });
    });

    // Form Validation for Register Client
    function validateClientForm() {
        const phoneNumber = document.getElementById('phone_number').value.trim();
        const phonePattern = /^\d{10}$/;
        if (!phonePattern.test(phoneNumber)) {
            alert('Please enter a valid 10-digit phone number.');
            return false;
        }

        // Additional validations can be added here

        return true; // Allow form submission
    }

    // Form Validation for Edit Client
    function validateEditClientForm() {
        const phoneNumber = document.getElementById('editPhoneNumber').value.trim();
        const phonePattern = /^\d{10}$/;
        if (!phonePattern.test(phoneNumber)) {
            alert('Please enter a valid 10-digit phone number.');
            return false;
        }

        // Additional validations can be added here

        return true; // Allow form submission
    }
</script>

</body>
</html>
