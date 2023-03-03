<?php
header('Content-Type: application/json');
include_once '../helpers/db_conn.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
//error_log("post and get\n".print_r([$_POST,$_GET],true));

// Establish MySQL Connection
try {
	$conn = $db_conn ?? db_conn::getInstance();
} catch(exception $e) {
	error_log("Connection Errors:\n\n" . print_r($e, true));
}

if (!$conn) {
	die("Connection Failed: " . $conn->getError());
}

// Get Request options
$method = $_SERVER['REQUEST_METHOD'];
$options = array();
$dt_options = array();
if ($method == 'GET') {
	$options = $_GET;
	$dt_options = array(
		'draw' => $options['draw'],
		'columns' => $options['columns'],
		'order' => $options['order'][0] ?? '',
		'start' => $options['start'],
		'length' => $options['length'],
		'search' => $options['search']
	);
} else if ($method == 'POST') {
	$options = $_POST;
} else {
	error_log("Error: API method is invalid!\n" . print_r($method, true));
	error_log("Dumping _SERVER:");
	var_dump($_SERVER);
	die("Error with API method. Check error_logs");
	exit;
}

// Helper function; validates options
$validate = function($ops) {
	$err = array(
		'error' => false,
		'sources' => array()
	);

	forEach($ops as $key => $val) {
		if (($val != 0 && empty($val)) || $val == '') {
			$err['error'] = true;
			$err['sources'][$key] = $key;
		}
	}

	return $err;
};

$mode = $options['mode'];
unset($options['mode']);
if (count($options) < 1) {
	die("Error: Request is missing data!");
}

$sql = "";
$person_id = 0;
$column_sql = "";
$values = array('');
if ($method == 'POST') {
	// Validate options
	$err = $validate($options);
	if ($err['error']) {
		$sources = implode(', ', $err['sources']);
		error_log("ERROR: The following fields are Null:\n$sources");
		die("Failed on Null values; One or more fields are NULL: $sources");
		exit;
	}

	// Begin building query
	$person_id = (int) str_pad(trim($options['person_id']), 7, '0');
	$first_name = trim($options['first_name']);
	$last_name = trim($options['last_name']);
	$email_address = trim($options['email_address']);
	$hire_date = trim($options['hire_date']);
	$job_title = trim($options['job_title']);
	$agency_num = trim($options['agency_num']);

	$column_sql .= "`first_name` = ?, `last_name` = ?, `email_address` = ?, `hire_date` = ?, `job_title` = ?";
	$column_val_types = 'sssss';
	$values[0] .= $column_val_types;
	$values[] = $first_name;
	$values[] = $last_name;
	$values[] = $email_address;
	$values[] = $hire_date;
	$values[] = $job_title;
	if ($agency_num == 'empty') {
		unset($agency_num);
	} else {
		$column_sql .= ", `agency_num` = ?";
		$values[0] .= 'i';
		$values[] = (int) $agency_num;
	}

	if ($mode != 'delete') {
		$sql2 = "SELECT * FROM `employees` WHERE `person_id` = ?";
		$values2 = array(
			'i',
			$person_id
		);
		$params2 = array(
			'sql' => $sql2,
			'values' => $values2
		);

		$result2 = $conn->query($params2);
		$num_rows = mysqli_num_rows($result2);
		error_log("!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!! $mode");
		if ($mode == 'insert' && $num_rows > 0) {
			error_log("insert and num rows is $num_rows");
			error_log("Error! Trying to insert with a duplicate person_id: $person_id");
			$error = array(
				'error' => true,
				'msg' => "Error! Trying to insert with a duplicate person_id: $person_id"
			);
			echo json_encode($error);
			die($error);
			exit;
		} else if ($mode == 'edit' && $num_rows < 1) {
			// Switch update to insertion
			$mode = 'insert';
		}

		switch($mode) {
			case 'insert':
				$sql .= "INSERT INTO `employees` SET `person_id` = $person_id, $column_sql, `registration_date` = SYSDATE()";
				break;

			case 'edit':
				$sql .= "UPDATE `employees` SET $column_sql WHERE `person_id` = ?";
				$values[0] .= 'i';
				$values[] = $person_id;
				break;

			default:
				// This shouldn't happen
				error_log("Unkown Error: Mode did not fit insert or edit case.\nMode:\n".print_r($mode,true));
				die("Unknown Error: mode did not fit insert or edit case. Mode = $mode");
				exit;
		}
	} else {
		$sql .= "DELETE FROM `employees` WHERE `person_id` = ?";
		$values = array(
			'i',
			$person_id
		);
	}
} else {
	$order_by_sql = "ORDER BY `person_id` ASC";
	$limit_sql = "LIMIT ".$dt_options['start'].", ".$dt_options['length'];
	$sql .= "SELECT * FROM `employees` $order_by_sql";
	if ($dt_options['start'] != '' && $dt_options['length'] != '') {
		$sql .= " $limit_sql";
	}
	$values = NULL;
}

// Build and send SQL query
$params = array(
	'sql' => $sql,
	'values' => $values
);

if ($values === NULL) {
	unset($params['values']);
}

$result = $conn->query($params); // Any errors with MySQL will be handled by the db_conn class
if ($method == 'POST') {
	$conn->close();
	echo 'Success';
	exit;
}

if (!$result) {
	http_response_code(404);
	die("MySQL ERROR in query: " . $conn->getError);
}

// Close mysql connection and build output
$conn->close();
$response = array();
$output = array();
while ($row = mysqli_fetch_assoc($result)) {
	$row['edit_btn'] = "<button class='button-small' onclick='editForm("
		. "\"" . $row['person_id'] . "\","
		. "\"" . $row['first_name'] . "\","
		. "\"" . $row['last_name'] . "\","
		. "\"" . $row['email_address'] . "\","
		. "\"" . $row['hire_date'] . "\","
		. "\"" . $row['job_title'] . "\","
		. "\"" . $row['agency_num'] . "\")'>Edit</button>";
	$row['delete_btn'] = "<button class='button-small' onclick='deleteRow(".$row['person_id'].")'>Delete</button>";
	$output[] = $row;
}

// Get totals for Datatables
$filtered_total = mysqli_num_rows($result);
$params2 = array('sql' => "SELECT COUNT(`person_id`) AS total FROM employees");
$result2 = $conn->query($params2);
$total = mysqli_fetch_assoc($result2)['total'];
$conn->close();

// Build response and send
$response['draw'] = $dt_options['draw'];
$response['recordsTotal'] = $total;
$response['recordsFiltered'] = $filtered_total;
$response['data'] = $output;

echo json_encode($response);
exit;

?>
