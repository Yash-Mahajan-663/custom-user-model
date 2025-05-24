<?php
// includes/class-cui-importer.php

if (! defined('ABSPATH')) {
	exit; // Exit if accessed directly
}

class CUI_Importer
{

	const BATCH_SIZE = 100; // Adjust as needed, 500-1000k as per requirements.

	/**
	 * Handles the initial file upload and stores it temporarily.
	 *
	 * @param array $file_data The $_FILES array data for the uploaded file.
	 * @return array|WP_Error Array on success with file info, WP_Error on failure.
	 */
	public static function handle_upload($file_data)
	{
		if (! function_exists('wp_handle_upload')) {
			require_once(ABSPATH . 'wp-admin/includes/file.php');
		}

		$upload_overrides = array('test_form' => false);
		$moved_file       = wp_handle_upload($file_data, $upload_overrides);

		if ($moved_file && ! isset($moved_file['error'])) {
			// Store file information for later processing
			$file_info = array(
				'file'      => $moved_file['file'],
				'url'       => $moved_file['url'],
				'type'      => $moved_file['type'],
				'name'      => basename($moved_file['file']),
				'size'      => filesize($moved_file['file']),
				'extension' => pathinfo($moved_file['file'], PATHINFO_EXTENSION),
			);

			// Store in transient for batch processing
			$transient_key = 'cui_import_file_' . md5($moved_file['file']);
			set_transient($transient_key, $file_info, DAY_IN_SECONDS); // Keep for a day

			return array_merge($file_info, array('transient_key' => $transient_key));
		} else {
			return new WP_Error('upload_error', $moved_file['error']);
		}
	}

	/**
	 * Initializes the import process by counting total rows and setting up history.
	 *
	 * @param string $transient_key Key to retrieve file info from transient.
	 * @return array|WP_Error Array with total rows and import ID, WP_Error on failure.
	 */
	public static function initialize_import($transient_key)
	{
		$file_info = get_transient($transient_key);
		if (! $file_info) {
			return new WP_Error('file_not_found', __('Import file not found or expired.', 'custom-user-importer'));
		}

		$file_path = $file_info['file'];
		$extension = strtolower($file_info['extension']);
		$total_rows = 0;

		try {
			if ('csv' === $extension) {
				$total_rows = self::count_csv_rows($file_path);
			} elseif ('xml' === $extension) {
				$total_rows = self::count_xml_nodes($file_path);
			} else {
				return new WP_Error('invalid_file_type', __('Unsupported file type.', 'custom-user-importer'));
			}
		} catch (Exception $e) {
			return new WP_Error('file_parsing_error', sprintf(__('Error parsing file: %s', 'custom-user-importer'), $e->getMessage()));
		}

		if ($total_rows === 0) {
			return new WP_Error('empty_file', __('The selected file contains no data.', 'custom-user-importer'));
		}

		// Create initial history entry
		$import_id = CUI_History::add_history_entry($file_info['name'], $total_rows);

		// Store import state in a transient for batch processing
		$import_state_key = 'cui_import_state_' . $import_id;
		set_transient($import_state_key, array(
			'file_info'    => $file_info,
			'total_rows'   => $total_rows,
			'processed_rows' => 0,
			'import_id'    => $import_id,
			'current_batch' => 0,
		), DAY_IN_SECONDS);

		return array(
			'total_rows' => $total_rows,
			'import_id'  => $import_id,
		);
	}

	/**
	 * Processes a batch of users.
	 *
	 * @param int $import_id The ID of the current import.
	 * @return array|WP_Error Array with processed count, percentage, and completion status.
	 */
	public static function process_batch($import_id)
	{
		$import_state_key = 'cui_import_state_' . $import_id;
		$state            = get_transient($import_state_key);

		if (! $state) {
			return new WP_Error('import_state_not_found', __('Import state not found or expired.', 'custom-user-importer'));
		}

		$file_info       = $state['file_info'];
		$total_rows      = $state['total_rows'];
		$processed_rows  = $state['processed_rows'];
		$current_batch   = $state['current_batch'];
		$file_path       = $file_info['file'];
		$extension       = strtolower($file_info['extension']);

		$users_to_import = array();
		$start_row_index = $processed_rows; // For CSV
		$skip_rows       = $processed_rows; // For XML iteration

		// Read batch of users
		try {
			if ('csv' === $extension) {
				$users_to_import = self::read_csv_batch($file_path, $start_row_index, self::BATCH_SIZE);
			} elseif ('xml' === $extension) {
				$users_to_import = self::read_xml_batch($file_path, $skip_rows, self::BATCH_SIZE);
			} else {
				return new WP_Error('invalid_file_type', __('Unsupported file type.', 'custom-user-importer'));
			}
		} catch (Exception $e) {
			return new WP_Error('file_reading_error', sprintf(__('Error reading file batch: %s', 'custom-user-importer'), $e->getMessage()));
		}

		$batch_processed_count = 0;
		$errors = [];
		$success_count = 0;
		foreach ($users_to_import as $user_data) {
			$result = self::create_or_update_user($user_data);
			if (is_wp_error($result)) {
				$errors[] = $result->get_error_message();
				break;
			}
			$success_count++;
			$batch_processed_count++;
		}
		if (!empty($errors)) {
			// 👇 If AJAX
			wp_send_json_error(['message' => $errors[0]]);

			// 👇 If non-AJAX (standard page flow)
			$_SESSION['import_error'] = $errors[0]; // Or use transient
			wp_redirect(admin_url('admin.php?page=custom-user-importer'));
			exit;
		} else {
			$_SESSION['import_success'] = "$success_count users imported successfully.";
			wp_redirect(admin_url('admin.php?page=custom-user-importer'));
			exit;
		}

		$processed_rows += $batch_processed_count;
		$percentage      = ($total_rows > 0) ? round(($processed_rows / $total_rows) * 100) : 0;
		$is_completed    = ($processed_rows >= $total_rows);

		// Update import state
		$state['processed_rows'] = $processed_rows;
		$state['current_batch']++;
		set_transient($import_state_key, $state, DAY_IN_SECONDS); // Update transient to reflect progress

		if ($is_completed) {
			delete_transient($import_state_key); // Clean up transient
			CUI_History::update_history_status($import_id, 'completed', $processed_rows);
			// Optional: delete the uploaded file after successful import
			// unlink( $file_path );
		} else {
			CUI_History::update_history_status($import_id, 'in_progress', $processed_rows);
		}

		return array(
			'processed_rows' => $processed_rows,
			'total_rows'     => $total_rows,
			'percentage'     => $percentage,
			'completed'      => $is_completed,
			'file_name'      => $file_info['name'],
			'file_id'        => $import_id, // Using import_id as a pseudo-file ID
		);
	}

	/**
	 * Counts rows in a CSV file.
	 *
	 * @param string $file_path
	 * @return int
	 */
	private static function count_csv_rows($file_path)
	{
		$count = 0;
		if (($handle = fopen($file_path, 'r')) !== false) {
			while (($data = fgetcsv($handle)) !== false) {
				$count++;
			}
			fclose($handle);
		}
		return max(0, $count - 1); // Subtract 1 for header row
	}

	/**
	 * Reads a batch of rows from a CSV file.
	 *
	 * @param string $file_path
	 * @param int $offset
	 * @param int $limit
	 * @return array
	 */
	private static function read_csv_batch($file_path, $offset, $limit)
	{
		$data = array();
		if (($handle = fopen($file_path, 'r')) !== false) {
			$row_index = 0;
			$headers   = array();
			while (($row = fgetcsv($handle)) !== false) {
				if ($row_index === 0) {
					$headers = array_map('sanitize_key', $row); // Assuming first row is headers
				} elseif ($row_index >= $offset + 1 && count($data) < $limit) { // +1 for header
					$user_data = array();
					foreach ($headers as $index => $header) {
						$user_data[$header] = isset($row[$index]) ? $row[$index] : '';
					}
					$data[] = $user_data;
				}
				$row_index++;
			}
			fclose($handle);
		}
		return $data;
	}

	/**
	 * Counts user nodes in an XML file. Assumes a consistent structure.
	 *
	 * @param string $file_path
	 * @return int
	 */
	private static function count_xml_nodes($file_path)
	{
		// This can be memory-intensive for very large XML files.
		// For extremely large XML files, consider using XMLReader for memory efficiency.
		$xml = simplexml_load_file($file_path, 'SimpleXMLElement', LIBXML_NOCDATA);
		if ($xml === false) {
			throw new Exception('Failed to load XML file.');
		}
		// Assuming 'user' is the root node for each user, adjust if your XML is different
		return count($xml->user);
	}

	/**
	 * Reads a batch of nodes from an XML file.
	 *
	 * @param string $file_path
	 * @param int $offset
	 * @param int $limit
	 * @return array
	 */
	private static function read_xml_batch($file_path, $offset, $limit)
	{
		$data = array();
		// For large XML files, XMLReader is preferred to avoid memory issues.
		$reader = new XMLReader();
		if (! $reader->open($file_path)) {
			throw new Exception('Failed to open XML file for reading.');
		}

		$count = 0;
		$batch_count = 0;
		while ($reader->read()) {
			if ($reader->nodeType == XMLReader::ELEMENT && $reader->name == 'user') { // Assuming 'user' is the element name for each user
				if ($count >= $offset && $batch_count < $limit) {
					$node = simplexml_load_string($reader->readOuterXML());
					if ($node !== false) {
						$user_data = array();
						// Map XML nodes to user data fields. Adjust these as per your XML structure.
						$user_data['user_login']    = (string)$node->user_login;
						$user_data['user_email']    = (string)$node->user_email;
						$user_data['first_name']    = (string)$node->first_name;
						$user_data['last_name']     = (string)$node->last_name;
						$user_data['user_pass']     = (string)$node->user_pass; // You might want to generate a strong password if not provided
						$user_data['role']          = (string)$node->role;

						$data[] = $user_data;
						$batch_count++;
					}
				}
				$count++;
			}
		}
		$reader->close();
		return $data;
	}

	/**
	 * Creates or updates a WordPress user.
	 *
	 * @param array $user_data Array of user data (e.g., user_login, user_email, first_name, last_name, role, etc.).
	 * @return int|WP_Error User ID on success, WP_Error on failure.
	 */
	private static function create_or_update_user($user_data)
	{
		// Sanitize and validate data
		$user_login = sanitize_user($user_data['user_login']);
		$user_email = sanitize_email($user_data['user_email']);
		$first_name = sanitize_text_field($user_data['first_name']);
		$last_name  = sanitize_text_field($user_data['last_name']);
		$role       = isset($user_data['role']) ? sanitize_key($user_data['role']) : get_option('default_role');

		if (empty($user_login) || empty($user_email)) {
			return new WP_Error('missing_data', __('User login and email are required.', 'custom-user-importer'));
		}

		if (! is_email($user_email)) {
			return new WP_Error('invalid_email', __('Invalid email address.', 'custom-user-importer'));
		}

		// Check if user already exists by login or email
		$user = get_user_by('email', $user_email);
		if ($user) {
			// ❌ Stop if email already exists
			return new WP_Error('duplicate_email', sprintf(__('Email "%s" already exists. Import process halted.', 'custom-user-importer'), $user_email));
		}

		// User does not exist, create new
		$password = ! empty($user_data['user_pass']) ? $user_data['user_pass'] : wp_generate_password(12, true);
		$insert_args = array(
			'user_login'    => $user_login,
			'user_email'    => $user_email,
			'user_pass'     => $password,
			'first_name'    => $first_name,
			'last_name'     => $last_name,
			'role'          => $role,
			'display_name'  => $first_name . ' ' . $last_name,
		);
		$user_id = wp_insert_user(apply_filters('cui_user_insert_args', $insert_args, $user_data));

		if (is_wp_error($user_id)) {
			error_log('Custom User Importer Error: ' . $user_id->get_error_message());
			return $user_id;
		}

		return $user_id;
	}
}
