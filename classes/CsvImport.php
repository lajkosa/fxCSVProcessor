<?php

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

				if (empty($argv[2])) {
					echo "Filename must be given!\n\n";
					return;
				}

				$interval = $argv[3] ?? 'year';
				static::$instance->import($argv[2], $interval);
				break;
		}
	}

	private function create_table() {
		$this->PDO->query('
			CREATE TABLE IF NOT EXISTS ' . self::TABLE_NAME . ' (
				`id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
				`interval` VARCHAR(255),
				`currency` VARCHAR(8),
				`capital` FLOAT(9,2),
				`equity` FLOAT(9,2),
				`profit` FLOAT(8,2),
				`trades` INT,
				`profit_factor` FLOAT(4,2),
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

		$fh = fopen($filename, 'r');

		$insertCollection = [];

		while (!feof($fh)) {
			$line = fgets($fh);
			$columns = explode(';', $line);

			// skip the header and skip if it is already in table
			if ($this->isCSVHeader($columns) || $this->isInTable($columns, $interval)) {
				continue;
			}

			$this->addToCollection($insertCollection, $columns, $interval);
		}

		fclose($fh);

		if (count($insertCollection) > 0) {

			// insert into table
			$sql = '
			INSERT INTO ' . self::TABLE_NAME . ' (
				`interval`,
				`currency`,
				`capital`,
				`equity`,
				`profit`,
				`trades`,
				`profit_factor`,
				`params`
			) VALUES 
		';

			$this->PDO->query($sql . implode(',', $insertCollection));
		}

		echo count($insertCollection) . " row has been inserted!\n\n";
	}

	private function addToCollection(array &$collection, array $columns, $interval) {

		$currency     = $this->stripCurrency($columns[self::COLUMN_PARAMETERS]);
		$capital      = $this->toFloat($columns[self::COLUMN_CAPITAL]);
		$equity       = $this->toFloat($columns[self::COLUMN_EQUITY]);
		$profit       = $this->toFloat($columns[self::COLUMN_PROFIT]);
		$profitFactor = $this->toFloat($columns[self::COLUMN_PROFIT_FACTOR]);
		$trades       = (int) $columns[self::COLUMN_TRADES];
		$parameters   = $columns[self::COLUMN_PARAMETERS];

		$collection[] = '
		(
			"' . $interval . '",
			"' . $currency . '",
			' . $capital . ',
			' . $equity . ',
			' . $profit . ',
			' . $trades . ',
			' . $profitFactor . ',
			"' . $parameters . '"
		)';
	}

	/**
	 * @param $number
	 * @return float
	 */
	private function toFloat($number) {
		return (float) str_replace(',', '.', $number);
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
	 * @param string $interval
	 *
	 * @return bool
	 */
	private function isInTable(array $columns, $interval) {

		$query = $this->PDO->prepare('
			SELECT COUNT(*) AS num
				FROM ' . self::TABLE_NAME . '
				WHERE `params` = :parameters
				  AND `interval` = :interval
		');

		$query->bindParam(':parameters', $columns[self::COLUMN_PARAMETERS], PDO::PARAM_STR);
		$query->bindParam(':interval', $interval, PDO::PARAM_STR);
		$query->execute();

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