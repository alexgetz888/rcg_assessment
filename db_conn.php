<?php

/**
 * Interacts with MySQL DB
 */
class db_conn {
	private $_conn = null;
	private static $_instance = null;
	private $mysql_conn_info = array(
		'host' => "localhost",
		'user' => 'root',
		'password' => "root",
		'db' => "emp"
	);

	private $_sql = "";
	private $_error = false;

	/**
	 *
	 * @return boolean
	 */
	private function __construct() {
		// Connect to database.
		$connection = $this->connect();
		return $connection;
	}

	/**
	 * Make a new connection to the db.
	 *
	 * @return boolean   true on success; false otherwise.
	 */
	private function connect() {
		$conn = new mysqli(
			$this->mysql_conn_info['host'],
			$this->mysql_conn_info['user'],
			$this->mysql_conn_info['password'],
			$this->mysql_conn_info['db']
		);

		if ($conn->connect_error) {
			$this->_conn = NULL;

			// Set error and return.
			$this->_error = $conn->connect_error;
			return false; // fail.
		}

		// Set connection and return.
		$this->_conn = $conn;
		return true; // ok.
	}

	public static function getInstance() {
		if (!self::$_instance) {
			self::$_instance = new db_conn();
		}
		return self::$_instance;
	}

	/**
	 * Close current MySQL connection.
	 */
	public function close() {
		$this->_conn->close();
		$this->_conn = NULL;
		db_conn::$_instance = NULL;
	}

	/**
	 *
	 * Returns the number of rows in the result of SELECT query.
	 *
	 * @param mysqli_result $result
	 * @return number
	 */
	public function getNumRows($result) {
		return $result->num_rows;
	}

	/**
	 *
	 * Returns the error status/message.
	 *
	 * @return boolean|string
	 */
	public function getError() {
		return $this->_error;
	}

	/**
	 * Binds myqsli stmt with values for SQL query
	 *
	 * @return true on success; false otherwise
	 */
	private function bind(&$stmt, &$values) {
		try {
			$ok = $stmt->bind_param(...$values);
		} catch(exception $error) {
			error_log("Errors in mysqli bind_param operation:\n".print_r([$stmt,$values,$error],true));
			var_dump($ok);
		} finally {
			if (!$ok) {
				$data = array($stmt, $values);
				$this->handleError('Bind params failed', $data,$this->_conn);
				return false;
			}
		}
		
		return true;
	}

	/**
	 * Prepare and execute query.
	 *
	 * @return mysqli_stmt on success; NULL otherwise.
	 */
	private function execute($params) {
		if (!isset($params['sql'])) {
			$this->handleError('No SQL Statement');
			return NULL;
		}

		$this->_sql = trim($params['sql']);

		$values = array();
		if (isset($params['values'])) {
			foreach ($params['values'] as $k => $v) {
				$values[$k] = stripcslashes($v);
			}
		}
		
		$tries = 0;
		$stmt = NULL;
		do {
			if (!$this->_conn) {
				$this->connect();
				$tries++;
				continue;
			}
			
			try {
				$stmt = $this->_conn->prepare(trim($params['sql']));
			} catch(exception $error) {
				error_log("Errors in mysqli prepare operation:\n".print_r([$params['sql'],$error],true));
				var_dump($error);
				var_dump($stmt);
			}

			if (!$stmt) {
				if ($this->_conn->errno == 2006) {
					// Connection lost; try to reconnect
					$this->connect();
					$tries++;
					continue;
				} else {
					$this->handleError(
						$this->_conn->error,
						$this->_conn->error_list,
						$this->_conn
					);
					return NULL; // Fail
				}
			}

			if (!empty($values)) {
				$ok = $this->bind($stmt, $values); // Bind function will catch errors
				if (!$ok) {
					return NULL; // fail.
				}
			}

			// Execute query.
			try {
				$stmt->execute();
			} catch(exception $error) {
				error_log("Errors in mysqli execute operation:\n".print_r([$stmt,$error],true));
			}

			if ($stmt->error) {
				if ($stmt->errno == 2006) {
					// Connection lost; try to reconnect
					$this->connect();
					$tries++;
					continue;
				} else {
					$this->handleError(
						$stmt->error,
						$stmt->error_list,
						$this->_conn
					);
					return NULL;
				}
			}

			return $stmt; // ok.
		} while ($tries < 3);

		return NULL; // fail.
	}

	/**
	 *
	 * Prepares SQL statement for execution, and then executes query
	 * Logs any errors
	 *
	 * @param array $params
	 *        Key-value array; first is the SQL statement, and
	 *        the second, if needed, is the values array to be
	 *        bound to the SQL statement.
	 *
	 * @return $result | false.
	 *         Result is the mysqli results of the query.
	 *         false is returned on any failure.
	 */
	public function query($params = array ()) {
		// Clear error if there was one saved
		$this->_error = false;

		if (!isset($params['sql'])) {
			$this->handleError('No SQL Statement', $params, $this->_conn);
			return false;
		}

		// Execute query.
		$stmt = $this->execute($params);

		if (!$stmt) {
			$data = array($stmt, $params);
			$this->handleError('SQL Execute failed.', $data,$this->_conn);
			return false;
		}
		
		preg_match('/SELECT/', $this->_sql, $action_matches);
		$action = $action_matches[0] ?? NULL; // First match is the main SQL keyword to target
		$result = false;
		if ($action == 'SELECT') {
			$result = $stmt->get_result();
			if ($result === false) {
				$data = array($stmt, $params);
				$this->handleError('Get Result has failed', $data, $this->_conn);
			}
		}

		return $result;
	}

	private function handleError($err, $data=NULL, $conn) {
		$data[] = $this->_sql;

		if (isset($conn)) {
			$data[] = mysqli_error($conn);
		}
		
		$this->_error = $err;
		$this->logError($err, $data);
		
		echo print_r([$err, $data], true);
		exit(1);
	}
	
	private function logError($err, $data=NULL) {
		error_log(print_r(['db_conn logError', $err, $data], true));
	}
}

$db_conn = db_conn::getInstance();

?>
