<?php

require_once __DIR__ . '/config.ini.php';
require_once __DIR__ . '/classes/CsvImport.php';

try {
	$conn = new PDO("mysql:host=$db_host;dbname=$db_name", $db_user, $db_pass);
	// set the PDO error mode to exception
	$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

	CsvImport::run($argv, $conn);

} catch (PDOException $e) {
	echo "Connection failed: " . $e->getMessage();
}
