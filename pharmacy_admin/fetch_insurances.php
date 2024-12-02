<?php
include('../include/connection.php');

if (isset($_GET['query'])) {
    $query = trim($_GET['query']);

    $sql = "SELECT insurance_id, insurance_name FROM insurance_companies WHERE insurance_name LIKE :query LIMIT 10";

    try {
        $stmt = $connection->prepare($sql);
        $stmt->execute([':query' => $query . '%']);
        $insurances = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Check if any insurances were found
        if ($insurances) {
            echo json_encode($insurances);
        } else {
            echo json_encode([]); // Return an empty array if no results
        }

    } catch (PDOException $e) {
        // Return an error response in case of a query failure
        echo json_encode(['error' => $e->getMessage()]);
    }
} else {
    echo json_encode(['error' => 'No query parameter provided.']); // Return error if no query
}
?>
