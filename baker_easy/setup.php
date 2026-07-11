<?php
// setup.php - Automatic Database and Library Installer

header('Content-Type: text/plain; charset=utf-8');
echo "=== BakerEase Initialization Setup ===\n\n";

$db_host = '127.0.0.1';
$db_user = 'root';
$db_pass = '';
$db_name = 'bakerease_db';

// 1. Database Initialization
try {
    echo "Connecting to MySQL server...\n";
    $pdo = new PDO("mysql:host=$db_host", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "Creating database if not exists...\n";
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `$db_name` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;");
    $pdo->exec("USE `$db_name`;");
    
    echo "Reading schema.sql...\n";
    $sql_file = __DIR__ . '/schema.sql';
    if (!file_exists($sql_file)) {
        throw new Exception("schema.sql file not found!");
    }
    
    $sql_content = file_get_contents($sql_file);
    echo "Executing schema.sql...\n";
    $pdo->exec($sql_content);
    echo "Database setup completed successfully!\n\n";

    // 1b. Running Database Migrations for Dining Module (Alter Existing Tables)
    echo "Running Database Migrations for Dining Module...\n";
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS tables (
            table_id INT AUTO_INCREMENT PRIMARY KEY,
            table_number VARCHAR(10) NOT NULL UNIQUE
        );");
        
        $stmt = $pdo->query("SELECT COUNT(*) FROM tables");
        if ($stmt->fetchColumn() == 0) {
            $pdo->exec("INSERT INTO tables (table_number) VALUES ('Table 1'), ('Table 2'), ('Table 3'), ('Table 4'), ('Table 5'), ('Table 6'), ('Table 7'), ('Table 8'), ('Table 9'), ('Table 10'), ('Table 11'), ('Table 12');");
            echo "Seeded default tables: Table 1 to Table 12.\n";
        } else {
            // Seed any missing tables up to Table 12
            for ($i = 1; $i <= 12; $i++) {
                $tbl_name = "Table $i";
                $check = $pdo->prepare("SELECT 1 FROM tables WHERE table_number = ?");
                $check->execute([$tbl_name]);
                if (!$check->fetch()) {
                    $ins = $pdo->prepare("INSERT INTO tables (table_number) VALUES (?)");
                    $ins->execute([$tbl_name]);
                    echo "Added missing table: $tbl_name.\n";
                }
            }
        }
        
        // Check and add dining_type column to orders
        $q = $pdo->query("SHOW COLUMNS FROM orders LIKE 'dining_type'");
        if (!$q->fetch()) {
            $pdo->exec("ALTER TABLE orders ADD COLUMN dining_type ENUM('Dine-In', 'Takeaway') NOT NULL DEFAULT 'Takeaway';");
            echo "Added dining_type column to orders.\n";
        }
        
        // Check and add table_id column to orders
        $q = $pdo->query("SHOW COLUMNS FROM orders LIKE 'table_id'");
        if (!$q->fetch()) {
            $pdo->exec("ALTER TABLE orders ADD COLUMN table_id INT NULL, ADD FOREIGN KEY (table_id) REFERENCES tables(table_id) ON DELETE SET NULL;");
            echo "Added table_id column to orders.\n";
        }
        
        // Check and add tax_amount column to order_items
        $q = $pdo->query("SHOW COLUMNS FROM order_items LIKE 'tax_amount'");
        if (!$q->fetch()) {
            $pdo->exec("ALTER TABLE order_items ADD COLUMN tax_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00;");
            echo "Added tax_amount column to order_items.\n";
        }
        echo "Dining module migration check completed successfully!\n\n";
    } catch (Exception $mig_err) {
        echo "MIGRATION WARNING: " . $mig_err->getMessage() . "\n\n";
    }
} catch (Exception $e) {
    echo "DATABASE ERROR: " . $e->getMessage() . "\n\n";
    exit(1);
}

// 2. Directory Creation
$dirs = [
    __DIR__ . '/assets/css',
    __DIR__ . '/assets/js',
    __DIR__ . '/assets/images',
    __DIR__ . '/libs/fpdf'
];

foreach ($dirs as $dir) {
    if (!file_exists($dir)) {
        if (mkdir($dir, 0777, true)) {
            echo "Created directory: $dir\n";
        } else {
            echo "ERROR: Failed to create directory: $dir\n";
        }
    }
}
echo "Directory verification complete.\n\n";

// 3. FPDF Library Download and Setup
$fpdf_dir = __DIR__ . '/libs/fpdf';
$fpdf_file = $fpdf_dir . '/fpdf.php';

if (!file_exists($fpdf_file)) {
    echo "FPDF library not found. Downloading FPDF v1.86...\n";
    $url = 'http://www.fpdf.org/download/fpdf186.zip';
    $zip_path = __DIR__ . '/libs/fpdf_temp.zip';
    
    // Download zip using file_get_contents or cURL
    $ch = curl_init($url);
    $fp = fopen($zip_path, 'wb');
    curl_setopt($ch, CURLOPT_FILE, $fp);
    curl_setopt($ch, CURLOPT_HEADER, 0);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    $success = curl_exec($ch);
    curl_close($ch);
    fclose($fp);
    
    if ($success && file_exists($zip_path) && filesize($zip_path) > 0) {
        echo "Download complete. Extracting files...\n";
        
        $zip = new ZipArchive();
        if ($zip->open($zip_path) === TRUE) {
            // Find files in zip and extract them
            // The zip content usually has a folder like fpdf186/
            // We want to extract it and move the contents of fpdf186/ directly to libs/fpdf/
            $temp_extract = __DIR__ . '/libs/temp_extract';
            if (!file_exists($temp_extract)) {
                mkdir($temp_extract, 0777, true);
            }
            $zip->extractTo($temp_extract);
            $zip->close();
            
            // Move files from fpdf186/ (or similar subfolder) to libs/fpdf/
            $subfolders = glob($temp_extract . '/*', GLOB_ONLYDIR);
            $source_dir = $temp_extract;
            if (!empty($subfolders)) {
                $source_dir = $subfolders[0]; // Get the first subfolder e.g. fpdf186
            }
            
            // Move fpdf.php
            if (file_exists($source_dir . '/fpdf.php')) {
                rename($source_dir . '/fpdf.php', $fpdf_dir . '/fpdf.php');
                echo "Extracted fpdf.php\n";
            }
            
            // Move font directory
            if (file_exists($source_dir . '/font')) {
                if (!file_exists($fpdf_dir . '/font')) {
                    mkdir($fpdf_dir . '/font', 0777, true);
                }
                $files = glob($source_dir . '/font/*');
                foreach ($files as $file) {
                    $file_name = basename($file);
                    rename($file, $fpdf_dir . '/font/' . $file_name);
                }
                echo "Extracted font/ directory\n";
            }
            
            // Cleanup
            // Recursive helper to remove directories
            function rmdir_recursive($dir) {
                foreach(scandir($dir) as $file) {
                    if ('.' === $file || '..' === $file) continue;
                    if (is_dir("$dir/$file")) rmdir_recursive("$dir/$file");
                    else unlink("$dir/$file");
                }
                rmdir($dir);
            }
            
            rmdir_recursive($temp_extract);
            unlink($zip_path);
            echo "FPDF extraction and cleanup completed successfully!\n";
        } else {
            echo "ERROR: Failed to open zip file.\n";
        }
    } else {
        echo "ERROR: Failed to download FPDF from $url.\n";
    }
} else {
    echo "FPDF library already exists. Skipping download.\n";
}

echo "\n=== Setup Completed Successfully! ===\n";
?>
