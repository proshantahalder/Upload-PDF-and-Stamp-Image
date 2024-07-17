<?php
require_once('vendor/autoload.php');

use setasign\Fpdi\Fpdi;

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Create uploads directory if it doesn't exist
$uploads_dir = __DIR__ . '/uploads';
if (!file_exists($uploads_dir)) {
    mkdir($uploads_dir, 0777, true);
}

// Debug: Check write permissions
$test_file = $uploads_dir . '/test.txt';
if (file_put_contents($test_file, 'test')) {
    // echo "Successfully wrote to $test_file<br>";
    unlink($test_file);
} else {
    echo "Failed to write to $test_file<br>";
}

// Debug: Check PHP settings

// echo "upload_max_filesize: " . ini_get('upload_max_filesize') . "<br>";
// echo "post_max_size: " . ini_get('post_max_size') . "<br>";
// echo "upload_tmp_dir: " . ini_get('upload_tmp_dir') . "<br>";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Check if both files were uploaded without errors
    if (isset($_FILES['pdf_file'], $_FILES['stamp_image']) && 
        $_FILES['pdf_file']['error'] == 0 && 
        $_FILES['stamp_image']['error'] == 0) {

        $pdf_file = $_FILES['pdf_file']['tmp_name'];
        $pdf_filename = $_FILES['pdf_file']['name'];
        
        // Move the uploaded stamp image to the uploads directory
        $stamp_filename = uniqid() . '_' . $_FILES['stamp_image']['name'];
        $stamp_file = $uploads_dir . '/' . $stamp_filename;
        
        if (!move_uploaded_file($_FILES['stamp_image']['tmp_name'], $stamp_file)) {
            die("Failed to move uploaded file. Check permissions.");
        }

        // Debug: Image information
        $image_info = getimagesize($stamp_file);
        if ($image_info === false) {
            die("Invalid image file or unable to get image information.");
        }

        // echo "Image type: " . image_type_to_mime_type($image_info[2]) . "<br>";
        // echo "Image dimensions: " . $image_info[0] . "x" . $image_info[1] . "<br>";

        // Create new PDF document
        $pdf = new Fpdi();

        // Get the number of pages in the uploaded PDF
        $pageCount = $pdf->setSourceFile($pdf_file);

        // Iterate through all pages
        for ($pageNo = 1; $pageNo <= $pageCount; $pageNo++) {
            // Import a page
            $templateId = $pdf->importPage($pageNo);
            
            // Get the size of the imported page
            $size = $pdf->getTemplateSize($templateId);
            
            // Create a page with the same size
            $pdf->AddPage($size['orientation'], array($size['width'], $size['height']));
            
            // Use the imported page
            $pdf->useTemplate($templateId);

            // Add signature (text)
            $pdf->SetFont('Helvetica', 'B', 12);
            $pdf->SetXY(10, 10);
            $pdf->Cell(0, 10, 'Signed by: Shanto Halder', 0, 1, 'L');

            // Add stamp (image)
            $stamp_width = 40;
            $stamp_height = 40;
            $x = 50;  // Fixed X coordinate
            $y = 50;  // Fixed Y coordinate
            try {
                $pdf->Image($stamp_file, $x, $y, $stamp_width, $stamp_height);
                // echo "Image added successfully at coordinates ($x, $y) with dimensions ${stamp_width}x${stamp_height}<br>";
            } catch (Exception $e) {
                echo "Error adding image: " . $e->getMessage() . "<br>";
            }
        }

        // Output the new PDF to a file
        $output_filename = 'signed_' . $pdf_filename;
        $pdf->Output('F', $output_filename);

        // Provide download link
        echo "<a href='{$output_filename}' download>Download Signed PDF</a>";

        // Clean up: delete the stamp file
        unlink($stamp_file);
    } else {
        echo "Error uploading files. Please ensure both PDF and stamp image are provided.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PDF Signing and Stamping</title>
</head>
<body>
    <h1>Upload PDF and Stamp Image</h1>
    <form method="post" enctype="multipart/form-data">
        <div>
            <label for="pdf_file">Select PDF file:</label>
            <input type="file" name="pdf_file" id="pdf_file" accept=".pdf" required>
        </div>
        <div>
            <label for="stamp_image">Select stamp image:</label>
            <input type="file" name="stamp_image" id="stamp_image" accept="image/*" required>
        </div>
        <div>
            <input type="submit" value="Upload, Sign, and Stamp PDF">
        </div>
    </form>
</body>
</html>