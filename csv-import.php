<?php

require_once __DIR__ . '/config.ini.php';

try {
	$conn = new PDO("mysql:host=$db_host;dbname=$db_name", $db_user, $db_pass);
	// set the PDO error mode to exception
	$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

	CsvImport::run($argv, $conn);

} catch (PDOException $e) {
	echo "Connection failed: " . $e->getMessage();
}

class CsvImport {

	/**
	 * @var int
	 * @const
	 */
	const CREATETABLE = 0;

	/**
	 * @var int
	 * @const
	 */
	const IMPORT = 1;

	/**
	 * @var string
	 * @const
	 */
	const TABLE_NAME = 'fx_results';

	const COLUMN_PASS			= 0;
	const COLUMN_CAPITAL		= 1;
	const COLUMN_PROFIT			= 2;
	const COLUMN_EQUITY			= 3;
	const COLUMN_TRADES			= 4;
	const COLUMN_PROFIT_FACTOR	= 5;
	const COLUMN_PARAMETERS		= 6;

	/**
	 * @var PDO
	 */
	private $PDO;

	/**
	 * @var self
	 */
	private static $instance;

	private static $arguments = [
		self::CREATETABLE => 'create_table',
		self::IMPORT      => 'import',
	];

	public static function run($argv, $PDO) {

		static::$instance = new static();

		echo "\n";

		if (count($argv) < 2 || ! in_array($argv[1], static::$arguments)) {
			static::$instance->show_help();
			return;
		}

		static::$instance->PDO = $PDO;

		// Handle the input
		switch ($argv[1]) {

			case static::$arguments[self::CREATETABLE]:
				static::$instance->create_table();
				break;

			case static::$arguments[self::IMPORT]:
				break;
		}
	}

	private function create_table() {
		$this->PDO->query('
			CREATE TABLE IF NOT EXISTS ' . self::TABLE_NAME . ' (
				`id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
				`interval` VARCHAR(255),
				`currency` VARCHAR(8),
				`capital` FLOAT(6,2),
				`equity` FLOAT(6,2),
				`profit` FLOAT(5,2),
				`trades` INT,
				`profit_factor` FLOAT(2,2),
				`params` TEXT
			)
		');

		echo "Table successfully created!\n\n";
	}

	private function import($filename, $interval) {
		if (!file_exists($filename)) {
			echo "$filename not found!\n\n";
			return;
		}

		$interval = $interval ?: 'year';

		$fh = fopen($filename, 'r');

		while (!feof($fh)) {
			$line = fgets($fh);
			$columns = explode(';', $line);

			// skip the header and skip if it is already in table
			if ($this->isCSVHeader($columns) || $this->isInTable($columns)) {
				continue;
			}

			$currency = $this->stripCurrency($columns[self::COLUMN_PARAMETERS]);

		}

		fclose($fh);
	}

	private function isCSVHeader(array $columns) {
		// The first column of the header is a STRING
		return (empty($columns) || !is_numeric($columns[self::COLUMN_PASS]));
	}

	private function stripCurrency($parameters) {

		$currency = null;

		if (preg_match('/(?<=instrument=)[^\s]+/', $parameters, $matches)) {
			$currency = $matches[0];
		}

		return $currency;
	}

	/**
	 * Check if current columns already in table.
	 *
	 * @param array $columns
	 *
	 * @return bool
	 */
	private function isInTable(array $columns) {

		$query = $this->PDO->prepare('
			SELECT COUNT(*) AS num
				FROM ' . self::TABLE_NAME . '
				WHERE `parameters` = :parameters
		');

		$query->bindParam(':parameters', $columns[self::COLUMN_PARAMETERS], PDO::PARAM_STR);

		$num = $query->fetchColumn();

		return (int)$num !== 0;
	}

	private function show_help() {
		$arguments = static::$arguments;
		echo <<<EOT
csv-import usage
================

ARGUMENTS:
 - {$arguments[0]}		
 - {$arguments[1]} [name_of_csv_file]


EOT;
	}
}