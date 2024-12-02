<?php
require '../include/connection.php';

// Handle medication submission and invoice generation
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['medications'])) {
    $clientID = $_POST['clientID'];
    $medications = $_POST['medications'];

    try {
        // Start a transaction
        $connection->beginTransaction();

        // Insert the transaction record (example)
        $stmt = $connection->prepare("INSERT INTO transactions (client_id, transaction_date) VALUES (:clientID, NOW())");
        $stmt->execute(['clientID' => $clientID]);
        $transactionID = $connection->lastInsertId();

        // Insert medication records
        foreach ($medications as $medication) {
            $medicationID = $medication['medicationID'];
            $quantity = $medication['quantity'];

            $stmt = $connection->prepare("INSERT INTO transaction_details (transaction_id, medication_id, quantity) VALUES (:transactionID, :medicationID, :quantity)");
            $stmt->execute(['transactionID' => $transactionID, 'medicationID' => $medicationID, 'quantity' => $quantity]);
        }

        // Commit the transaction
        $connection->commit();

        // Generate invoice
        $stmt = $connection->prepare("SELECT c.first_name, c.last_name, i.insurance_name, m.medication_name, td.quantity, m.unit_price 
                                      FROM transactions t
                                      JOIN clients c ON t.client_id = c.client_id
                                      JOIN transaction_details td ON t.transaction_id = td.transaction_id
                                      JOIN medications m ON td.medication_id = m.medication_id
                                      JOIN insurance_companies i ON c.insurance_id = i.insurance_id
                                      WHERE t.transaction_id = :transactionID");
        $stmt->execute(['transactionID' => $transactionID]);
        $invoice = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $connection->rollBack();
        echo '<div class="alert alert-danger">Error: ' . $e->getMessage() . '</div>';
    }
}

// Fetch client info if a client ID is provided
$client = null;
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['clientID'])) {
    $clientID = $_POST['clientID'];
    try {
        $stmt = $connection->prepare("SELECT c.client_id, c.first_name, c.last_name, i.insurance_name 
                                      FROM clients c 
                                      JOIN insurance_companies i ON c.insurance_id = i.insurance_id 
                                      WHERE c.client_id = :clientID");
        $stmt->execute(['clientID' => $clientID]);
        $client = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        echo '<div class="alert alert-danger">Error: ' . $e->getMessage() . '</div>';
    }
}

// Fetch medications covered by insurance
$medications = [];
try {
    $stmt = $connection->query("SELECT medication_id, medication_name, description, insurance_coverage, unit_price 
                                FROM medications 
                                WHERE insurance_coverage = 1 
                                ORDER BY medication_name ASC"); 
    while ($medication = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $medications[] = $medication;
    }
} catch (PDOException $e) {
    echo '<div class="alert alert-danger">Error: ' . $e->getMessage() . '</div>';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Medication Transaction System</title>
  <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
  <style>
    body { background-color: #f8f9fa; }
    .container { margin-top: 30px; }
    .card { margin-bottom: 20px; }
  </style>
</head>
<body>
  <div class="container">
    <h2 class="text-center mb-4">Medication Transaction System</h2>

    <!-- Client Information Section -->
    <div class="card">
      <div class="card-header bg-primary text-white">Client Information</div>
      <div class="card-body">
        <form method="POST" action="">
          <div class="form-row">
            <div class="form-group col-md-6">
              <label for="clientID">Client ID</label>
              <input type="number" class="form-control" name="clientID" placeholder="Enter client ID" required>
            </div>
            <div class="form-group col-md-6">
              <button type="submit" class="btn btn-success mt-4">Fetch Client Info</button>
            </div>
          </div>
        </form>

        <!-- Display client information if available -->
        <?php if ($client): ?>
        <div class="mt-4">
          <div class="form-group">
            <label for="clientId">Client ID</label>
            <input type="text" class="form-control" value="<?= htmlspecialchars($client['client_id']) ?>" readonly>
          </div>
          <div class="form-group">
            <label for="clientName">Client Name</label>
            <input type="text" class="form-control" value="<?= htmlspecialchars($client['first_name']) . ' ' . htmlspecialchars($client['last_name']) ?>" readonly>
          </div>
          <div class="form-group">
            <label for="clientInsurance">Insurance Name</label>
            <input type="text" class="form-control" value="<?= htmlspecialchars($client['insurance_name']) ?>" readonly>
          </div>
        </div>
        <?php else: ?>
          <div class="alert alert-warning">Client not found. Please check the client ID.</div>
        <?php endif; ?>
      </div>
    </div>

    <!-- Medication Information Section -->
    <div class="card">
      <div class="card-header bg-success text-white">Medications</div>
      <div class="card-body">
        <form method="POST" action="">
          <div id="medicationContainer">
            <div class="form-row mb-3 medicationRow">
              <div class="form-group col-md-4">
                <label for="medicationSelect0">Select Medication</label>
                <select class="form-control" name="medications[0][medicationID]" id="medicationSelect0" required>
                  <option value="">Select Medication</option>
                  <?php foreach ($medications as $medication): ?>
                    <option value="<?= htmlspecialchars($medication['medication_id']) ?>" 
                            data-unit-price="<?= htmlspecialchars($medication['unit_price']) ?>" 
                            data-insurance-coverage="<?= htmlspecialchars($medication['insurance_coverage']) ?>">
                      <?= htmlspecialchars($medication['medication_name']) ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="form-group col-md-2">
                <label for="medicationQuantity">Quantity</label>
                <input type="number" class="form-control" name="medications[0][quantity]" value="1" required>
              </div>
            </div>
          </div>

          <button type="button" class="btn btn-secondary" id="addMedicationBtn">Add Another Medication</button>
          <button type="submit" class="btn btn-primary mt-4">Submit Transaction</button>
        </form>
      </div>
    </div>

    <!-- Display Invoice if available -->
    <?php if (isset($invoice) && !empty($invoice)): ?>
    <div class="card">
      <div class="card-header bg-info text-white">Invoice</div>
      <div class="card-body">
        <h4>Invoice Details</h4>
        <table class="table table-striped">
          <thead>
            <tr>
              <th>Medication Name</th>
              <th>Quantity</th>
              <th>Unit Price</th>
              <th>Total Price</th>
            </tr>
          </thead>
          <tbody>
            <?php 
            $totalAmount = 0;
            foreach ($invoice as $item): 
              $totalPrice = $item['unit_price'] * $item['quantity'];
              $totalAmount += $totalPrice;
            ?>
              <tr>
                <td><?= htmlspecialchars($item['medication_name']) ?></td>
                <td><?= htmlspecialchars($item['quantity']) ?></td>
                <td><?= number_format($item['unit_price'], 2) ?></td>
                <td><?= number_format($totalPrice, 2) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
          <tfoot>
            <tr>
              <td colspan="3" class="text-right"><strong>Total Amount</strong></td>
              <td><strong><?= number_format($totalAmount, 2) ?></strong></td>
            </tr>
          </tfoot>
        </table>
      </div>
    </div>
    <?php endif; ?>
  </div>

  <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.6/dist/umd/popper.min.js"></script>
  <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
  <script>
    $(document).ready(function () {
      var medicationIndex = 1;

      $('#addMedicationBtn').click(function () {
        var newMedicationRow = `
          <div class="form-row mb-3 medicationRow">
            <div class="form-group col-md-4">
              <label for="medicationSelect${medicationIndex}">Select Medication</label>
              <select class="form-control" name="medications[${medicationIndex}][medicationID]" id="medicationSelect${medicationIndex}" required>
                <option value="">Select Medication</option>
                <?php foreach ($medications as $medication): ?>
                  <option value="<?= htmlspecialchars($medication['medication_id']) ?>" 
                          data-unit-price="<?= htmlspecialchars($medication['unit_price']) ?>" 
                          data-insurance-coverage="<?= htmlspecialchars($medication['insurance_coverage']) ?>">
                    <?= htmlspecialchars($medication['medication_name']) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="form-group col-md-2">
              <label for="medicationQuantity">Quantity</label>
              <input type="number" class="form-control" name="medications[${medicationIndex}][quantity]" value="1" required>
            </div>
            <div class="form-group col-md-1">
              <button type="button" class="btn btn-danger removeMedicationBtn">Remove</button>
            </div>
          </div>
        `;
        $('#medicationContainer').append(newMedicationRow);
        medicationIndex++;
      });

      $('#medicationContainer').on('click', '.removeMedicationBtn', function () {
        $(this).closest('.medicationRow').remove();
      });
    });
  </script>
</body>
</html>
