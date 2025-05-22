<?php
// includes/class-cui-ajax-handler.php

if (! defined('ABSPATH')) {
	exit; // Exit if accessed directly
}

class CUI_AJAX_Handler
{

	public function __construct()
	{
		add_action('wp_ajax_cui_upload_file', array($this, 'upload_file'));
		add_action('wp_ajax_cui_start_import', array($this, 'start_import'));
		add_action('wp_ajax_cui_process_batch', array($this, 'process_batch'));
		add_action('wp_ajax_cui_refresh_history', array($this, 'refresh_history'));
	}

	/**
	 * AJAX handler for file upload (triggered by "Select file" button).
	 */
	public function upload_file()
	{
		check_ajax_referer('cui_upload_file_nonce', 'nonce');

		if (! current_user_can('manage_options')) {
			wp_send_json_error(array('message' => __('You do not have permission to upload files.', 'custom-user-importer')));
		}

		if (empty($_FILES['cui_import_file'])) {
			wp_send_json_error(array('message' => __('No file uploaded.', 'custom-user-importer')));
		}

		$file_data = $_FILES['cui_import_file'];
		$result = CUI_Importer::handle_upload($file_data);

		if (is_wp_error($result)) {
			wp_send_json_error(array('message' => $result->get_error_message()));
		} else {
			wp_send_json_success(array(
				'message'       => __('File uploaded successfully. Click Import to begin.', 'custom-user-importer'),
				'file_title'    => $result['name'],
				'file_url'      => $result['url'],
				'file_size'     => size_format($result['size'], 2),
				'transient_key' => $result['transient_key'],
			));
		}
	}

	/**
	 * AJAX handler to start the import process (triggered by "Import" button).
	 */
	public function start_import()
	{
		check_ajax_referer('cui_import_nonce', 'nonce');

		if (! current_user_can('manage_options')) {
			wp_send_json_error(array('message' => __('You do not have permission to start imports.', 'custom-user-importer')));
		}

		$transient_key = isset($_POST['transient_key']) ? sanitize_text_field($_POST['transient_key']) : '';
		if (empty($transient_key)) {
			wp_send_json_error(array('message' => __('Import file not specified.', 'custom-user-importer')));
		}

		$result = CUI_Importer::initialize_import($transient_key);

		if (is_wp_error($result)) {
			wp_send_json_error(array('message' => $result->get_error_message()));
		} else {
			wp_send_json_success(array(
				'message'    => __('Import initialized. Processing batches...', 'custom-user-importer'),
				'total_rows' => $result['total_rows'],
				'import_id'  => $result['import_id'],
			));
		}
	}

	/**
	 * AJAX handler to process a single batch of users.
	 */
	public function process_batch()
	{
		check_ajax_referer('cui_import_nonce', 'nonce');

		if (! current_user_can('manage_options')) {
			wp_send_json_error(array('message' => __('You do not have permission to process imports.', 'custom-user-importer')));
		}

		$import_id = isset($_POST['import_id']) ? intval($_POST['import_id']) : 0;
		if ($import_id <= 0) {
			wp_send_json_error(array('message' => __('Invalid import ID.', 'custom-user-importer')));
		}

		$result = CUI_Importer::process_batch($import_id);

		if (is_wp_error($result)) {
			wp_send_json_error(array('message' => $result->get_error_message()));
		} else {
			wp_send_json_success($result);
		}
	}

	/**
	 * AJAX handler to refresh the import history table.
	 */
	public function refresh_history()
	{
		check_ajax_referer('cui_import_nonce', 'nonce');

		if (! current_user_can('manage_options')) {
			wp_send_json_error(array('message' => __('You do not have permission to view history.', 'custom-user-importer')));
		}

		ob_start();
		CUI_History::display_history_table();
		$table_html = ob_get_clean();

		wp_send_json_success(array('table_html' => $table_html));
	}
}
