<?php
// Ensure the session is started if needed
session_start();

// Include the Composer autoloader to load PhpSpreadsheet classes
require_once '../vendor/autoload.php'; // Adjust the path to your autoload.php file

// Use PhpSpreadsheet classes
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

try {
    // Create a new Spreadsheet object
    $spreadsheet = new Spreadsheet();

    // Get the active sheet (first sheet)
    $sheet = $spreadsheet->getActiveSheet();

    // Set cell value (A1)
    $sheet->setCellValue('A1', 'Hello World! This is a test to check PhpSpreadsheet functionality.');

    // Write the file to an XLSX format (Excel 2007+)
    $writer = new Xlsx($spreadsheet);

    // Set file name
    $filename = 'hello_world.xlsx';

    // Save the file to the server (in the current directory)
    $writer->save($filename);

    // Provide feedback to the user
    echo "Excel file created successfully. You can download it <a href='$filename'>here</a>.";

} catch (Exception $e) {
    // Catch any exceptions and display the error message
    echo "Error: " . $e->getMessage();
}
?>
