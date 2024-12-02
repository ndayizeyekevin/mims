<?php
$db = 'mims';
$host = 'localhost';
$username = 'root';
$password = '';
$dsn = "mysql:host=$host;dbname=$db";
try {
    $connection = new PDO($dsn, $username, $password);
    $connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    if($connection == TRUE){
        // echo "Connected to database";
    }
    else {
        echo "Not connected to database";
    }
} catch( PDOException $e ) {
    echo "Error connecting to database: " . $e->getMessage();
}

?>