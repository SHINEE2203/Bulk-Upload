<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

$servername = "localhost";
$username = "root";
$password = "";
$dbname = "data";
$uploadDir = 'uploaded_files/';

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$response = [
    'status' => 'success',
    'message' => '',
    'records' => []
];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['fileUpload'])) {
    $fileTmpPath = $_FILES['fileUpload']['tmp_name'];
    $fileName = $_FILES['fileUpload']['name'];
    $fileNameCmps = explode(".", $fileName);
    $fileExtension = strtolower(end($fileNameCmps));
    $dest_path = $uploadDir . $fileName;

    if (move_uploaded_file($fileTmpPath, $dest_path)) {
        if ($fileExtension === 'csv') {
            if (($handle = fopen($dest_path, "r")) !== FALSE) {
                $header = fgetcsv($handle, 1000, ",");
                $requiredColumns = ['STATE - DB', 'CITY - DB', 'POP Name', 'POP ID', 'POP Address', 'City', 'State', 'Pincode'];

                // Validate CSV header against required columns
                if (count($header) === count($requiredColumns) && empty(array_diff($requiredColumns, $header))) {
                    try {
                        $conn->begin_transaction();

                        // Prepare statement for inserting data into sample table
                        $stmtInsert = $conn->prepare("INSERT INTO sample (`ID`, `STATE - DB`, `CITY - DB`, `POP Name`, `POP ID`, `POP Address`, `City`, `State`, `Pincode`) VALUES (NULL, ?, ?, ?, ?, ?, ?, ?, ?)");
                        if (!$stmtInsert) {
                            throw new Exception($conn->error);
                        }

                        // Read each row from CSV file
                        while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
                            $isValid = false; // Flag to track validity of the current row
                            $errorMessages = []; // Array to collect error messages for the current row

                            // Check if all required columns are present in the current row
                            foreach ($requiredColumns as $column) {
                                if (!in_array($column, $header)) {
                                    $errorMessages[] = "Missing column: $column";
                                }
                            }

                            if (!empty($errorMessages)) {
                                $response['records'][] = [
                                    'data' => $data,
                                    'message' => implode(", ", $errorMessages),
                                    'isValid' => false,
                                    'class' => 'invalid-row'
                                ];
                                continue; // Skip to next row if columns are missing
                            }

                            // Check STATE - DB against users table
                            $stmtCheckState = $conn->prepare("SELECT COUNT(*) FROM users WHERE `STATE - DB` = ?");
                            if (!$stmtCheckState) {
                                throw new Exception($conn->error);
                            }
                            $stmtCheckState->bind_param("s", $data[array_search('STATE - DB', $header)]);
                            $stmtCheckState->execute();
                            $stmtCheckState->bind_result($stateCount);
                            $stmtCheckState->fetch();
                            $stmtCheckState->close();

                            if ($stateCount > 0) {
                                // Check CITY - DB against users table
                                $stmtCheckCity = $conn->prepare("SELECT COUNT(*) FROM users WHERE `CITY - DB` = ?");
                                if (!$stmtCheckCity) {
                                    throw new Exception($conn->error);
                                }
                                $stmtCheckCity->bind_param("s", $data[array_search('CITY - DB', $header)]);
                                $stmtCheckCity->execute();
                                $stmtCheckCity->bind_result($cityCount);
                                $stmtCheckCity->fetch();
                                $stmtCheckCity->close();

                                if ($cityCount > 0) {
                                    // Check Pincode against users table
                                    $stmtCheckPincode = $conn->prepare("SELECT COUNT(*) FROM users WHERE `Pincode` = ?");
                                    if (!$stmtCheckPincode) {
                                        throw new Exception($conn->error);
                                    }
                                    $stmtCheckPincode->bind_param("s", $data[array_search('Pincode', $header)]);
                                    $stmtCheckPincode->execute();
                                    $stmtCheckPincode->bind_result($pincodeCount);
                                    $stmtCheckPincode->fetch();
                                    $stmtCheckPincode->close();

                                    if ($pincodeCount > 0) {
                                        // All conditions met, insert into sample table
                                        $stmtInsert->bind_param("ssssssss",
                                            $data[array_search('STATE - DB', $header)],
                                            $data[array_search('CITY - DB', $header)],
                                            $data[array_search('POP Name', $header)],
                                            $data[array_search('POP ID', $header)],
                                            $data[array_search('POP Address', $header)],
                                            $data[array_search('City', $header)],
                                            $data[array_search('State', $header)],
                                            $data[array_search('Pincode', $header)]
                                        );

                                        if ($stmtInsert->execute()) {
                                            $response['records'][] = [
                                                'data' => $data,
                                                'message' => 'Inserted successfully',
                                                'isValid' => true
                                            ];
                                        } else {
                                            $response['records'][] = [
                                                'data' => $data,
                                                'message' => 'Failed to insert: ' . $stmtInsert->error,
                                                'isValid' => false
                                            ];
                                        }
                                    } else {
                                        // Pincode mismatch
                                        $response['records'][] = [
                                            'data' => $data,
                                            'message' => 'Error in Pincode: ' . $data[array_search('Pincode', $header)],
                                            'isValid' => false,
                                            'class' => 'invalid-row'
                                        ];
                                    }
                                } else {
                                    // City - DB mismatch
                                    $response['records'][] = [
                                        'data' => $data,
                                        'message' => 'Error in CITY - DB: ' . $data[array_search('CITY - DB', $header)],
                                        'isValid' => false,
                                        'class' => 'invalid-row'
                                    ];
                                }
                            } else {
                                // STATE - DB mismatch
                                $response['records'][] = [
                                    'data' => $data,
                                    'message' => 'Error in STATE - DB: ' . $data[array_search('STATE - DB', $header)],
                                    'isValid' => false,
                                    'class' => 'invalid-row'
                                ];
                            }
                        }

                        $conn->commit();
                    } catch (Exception $e) {
                        $conn->rollback();
                        $response['status'] = 'error';
                        $response['message'] = 'Failed to insert records: ' . $e->getMessage();
                    } finally {
                        if ($stmtInsert) {
                            $stmtInsert->close();
                        }
                        fclose($handle);
                        unlink($dest_path);
                    }
                } else {
                    fclose($handle);
                    unlink($dest_path);
                    $response['status'] = 'error';
                    $response['message'] = 'Required columns are missing or mismatched in the file: ' . implode(", ", array_diff($requiredColumns, $header));
                }
            } else {
                $response['status'] = 'error';
                $response['message'] = 'Error opening the file.';
            }
        } else {
            $response['status'] = 'error';
            $response['message'] = 'The uploaded file is not a CSV file.';
        }
    } else {
        $response['status'] = 'error';
        $response['message'] = 'There was an error uploading the file.';
    }
} else {
    $response['status'] = 'error';
    $response['message'] = 'No file uploaded or invalid request method.';
}

$conn->close();

header('Content-Type: application/json');
echo json_encode($response);
?>
