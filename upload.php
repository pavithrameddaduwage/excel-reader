<?php
require 'vendor/autoload.php';
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\IOFactory;

$host = 'localhost';
$port = '5432';
$dbname = 'iler';
$user = 'postgres';
$password = '0006';
$dsn = "pgsql:host=$host;port=$port;dbname=$dbname";

try {
    $pdo = new PDO($dsn, $user, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    echo 'Database connection failed: ' . $e->getMessage();
    exit;
}

function ensureTableColumnsExist($pdo) {
    $requiredColumns = [
        "Department" => "TEXT",
        "Year" => "TEXT",
        "Week" => "TEXT",
        "Upload_Timestamp" => "TIMESTAMP DEFAULT CURRENT_TIMESTAMP"
    ];

    foreach ($requiredColumns as $column => $type) {
        $stmt = $pdo->prepare("SELECT column_name FROM information_schema.columns WHERE table_name='excel_data' AND column_name=:column");
        $stmt->execute([':column' => $column]);
        if (!$stmt->fetch()) {
            $pdo->exec("ALTER TABLE excel_data ADD COLUMN \"$column\" $type");
        }
    }
}

function updateTableSchema($pdo, $columns) {
    ensureTableColumnsExist($pdo);
    $pdo->exec("ALTER TABLE excel_data DROP COLUMN IF EXISTS \"Vndr_name\"");
    
    foreach ($columns as $column) {
        $stmt = $pdo->prepare("SELECT column_name FROM information_schema.columns WHERE table_name='excel_data' AND column_name=:column");
        $stmt->execute([':column' => $column]);
        if (!$stmt->fetch()) {
            $pdo->exec("ALTER TABLE excel_data ADD COLUMN \"$column\" TEXT");
        }
    }
}

function quoteColumnName($columnName) {
    return '"' . str_replace('"', '""', $columnName) . '"';
}

function recordExists($pdo, $department, $year, $week, $old_nbr) {
    $sql = 'SELECT 1 FROM excel_data WHERE "Department" = ? AND "Year" = ? AND "Week" = ? AND "old_nbr" = ? LIMIT 1';
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$department, $year, $week, $old_nbr]);
    return $stmt->fetchColumn() !== false;
}

function getUploadError($errorCode) {
    $errors = [
        UPLOAD_ERR_OK => 'There is no error, the file uploaded with success.',
        UPLOAD_ERR_INI_SIZE => 'The uploaded file exceeds the upload_max_filesize directive in php.ini.',
        UPLOAD_ERR_FORM_SIZE => 'The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form.',
        UPLOAD_ERR_PARTIAL => 'The uploaded file was only partially uploaded.',
        UPLOAD_ERR_NO_FILE => 'No file was uploaded.',
        UPLOAD_ERR_NO_TMP_DIR => 'Missing a temporary folder.',
        UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk.',
        UPLOAD_ERR_EXTENSION => 'A PHP extension stopped the file upload.',
    ];

    return $errors[$errorCode] ?? 'Unknown upload error.';
}

if (isset($_POST['upload'])) {
    if (isset($_FILES['file']) && $_FILES['file']['error'] == UPLOAD_ERR_OK) {

        $fileTmpPath = $_FILES['file']['tmp_name'];
        $fileName = $_FILES['file']['name'];
        $fileExtension = pathinfo($fileName, PATHINFO_EXTENSION);

        $department = $_POST['department'];
        $year = $_POST['year'];
        $week = $_POST['week'];

        try {
            $pdo->beginTransaction();

            if ($fileExtension === 'csv') {
                if (($handle = fopen($fileTmpPath, 'r')) !== FALSE) {
                    $header = fgetcsv($handle);
                    if ($header !== FALSE) {
                        $header = array_filter($header, function($column) {
                            return strtolower($column) !== 'vndr_name';
                        });
                        if (count($header) > 270) {
                            $header = array_slice($header, 0, 270);
                        }

                        updateTableSchema($pdo, $header);

                        $sql = 'INSERT INTO excel_data ("Department", "Year", "Week", ' . implode(', ', array_map('quoteColumnName', $header)) . ') VALUES (?, ?, ?, ' . implode(', ', array_fill(0, count($header), '?')) . ')';
                        $stmt = $pdo->prepare($sql);

                        while (($data = fgetcsv($handle)) !== FALSE) {
                            $data = array_filter($data, function($value, $index) use ($header) {
                                return array_key_exists($index, $header);
                            }, ARRAY_FILTER_USE_BOTH);
                            if (count($data) > 270) {
                                $data = array_slice($data, 0, 270);
                            }

                            $data = array_map(function($value) {
                                return $value === '' ? null : $value;
                            }, $data);

                            if (!recordExists($pdo, $department, $year, $week, $data[0])) {
                                $stmt->execute(array_merge([$department, $year, $week], $data));
                            }
                        }

                        fclose($handle);
                    }
                }
            } else {
                $spreadsheet = IOFactory::load($fileTmpPath);
                $worksheet = $spreadsheet->getActiveSheet();
                $header = $worksheet->toArray(null, true, true, true)[1];
                $header = array_map('trim', $header);

                $header = array_filter($header, function($column) {
                    return strtolower($column) !== 'vndr_name';
                });

                if (count($header) > 270) {
                    $header = array_slice($header, 0, 270);
                }

                updateTableSchema($pdo, $header);

                $sql = 'INSERT INTO excel_data ("Department", "Year", "Week", ' . implode(', ', array_map('quoteColumnName', $header)) . ') VALUES (?, ?, ?, ' . implode(', ', array_fill(0, count($header), '?')) . ')';
                $stmt = $pdo->prepare($sql);

                foreach ($worksheet->getRowIterator(2) as $row) {
                    $data = [];
                    foreach ($row->getCellIterator() as $index => $cell) {
                        if (isset($header[$index])) {
                            $data[] = $cell->getValue() === '' ? null : $cell->getValue();
                        }
                    }

                    if (count($data) > 270) {
                        $data = array_slice($data, 0, 270);
                    }

                    if (!recordExists($pdo, $department, $year, $week, $data[0])) {
                        $stmt->execute(array_merge([$department, $year, $week], $data));
                    }
                }
            }

            $pdo->commit();
            echo 'File uploaded successfully!';
        } catch (Exception $e) {
            $pdo->rollBack();
            echo '<p style="color: red;">Error: ' . htmlspecialchars($e->getMessage()) . '</p>';
        }
    } else {
        $error_message = getUploadError($_FILES['file']['error']);
        echo '<p style="color: red;">File upload error: ' . $error_message . '</p>';
    }
}
?>
