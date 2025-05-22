<?php
// includes/class-cui-admin.php

if (! defined('ABSPATH')) {
	exit; // Exit if accessed directly
}

class CUI_Admin
{

	public function __construct()
	{
		add_action('admin_menu', array($this, 'add_admin_menu'));
		add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));
	}

	public function add_admin_menu()
	{
		add_menu_page(
			__('User Importer', 'custom-user-importer'),
			__('User Importer', 'custom-user-importer'),
			'manage_options', // Capability required to access
			'custom-user-importer', // Menu slug
			array($this, 'import_page_content'), // Callback function to render the page
			'dashicons-upload', // Icon
			90 // Position
		);
	}

	public function enqueue_scripts($hook_suffix)
	{
		if ('toplevel_page_custom-user-importer' !== $hook_suffix) {
			return;
		}

		wp_enqueue_style('cui-admin-style', CUI_PLUGIN_URL . 'assets/css/admin.css');
		wp_enqueue_script('cui-admin-script', CUI_PLUGIN_URL . 'assets/js/admin.js', array('jquery'), null, true);
		wp_localize_script('cui-admin-script', 'cui_ajax_object', array(
			'ajax_url' => admin_url('admin-ajax.php'),
			'nonce'    => wp_create_nonce('cui_import_nonce'),
			'upload_nonce' => wp_create_nonce('cui_upload_file_nonce')
		));
	}

	public function import_page_content()
	{ ?>
		<?php
		if (!empty($_SESSION['import_error'])) {
			echo '<div class="notice notice-error"><p>' . esc_html($_SESSION['import_error']) . '</p></div>';
			unset($_SESSION['import_error']);
		}

		if (!empty($_SESSION['import_success'])) {
			echo '<div class="notice notice-success"><p>' . esc_html($_SESSION['import_success']) . '</p></div>';
			unset($_SESSION['import_success']);
		}
		?>
		<div class="wrap">
			<h1><?php _e('User Importer', 'custom-user-importer'); ?></h1>

			<h2 class="nav-tab-wrapper">
				<a href="#import" class="nav-tab nav-tab-active" id="import-tab"><?php _e('Import', 'custom-user-importer'); ?></a>
				<a href="#history" class="nav-tab" id="history-tab"><?php _e('History', 'custom-user-importer'); ?></a>
			</h2>

			<div id="import-section" class="tab-content active">
				<h3><?php _e('Import new CSV/XML', 'custom-user-importer'); ?></h3>
				<form id="cui-import-form" enctype="multipart/form-data" method="post">
					<table class="form-table">
						<tr>
							<th scope="row"><label for="cui_import_file"><?php _e('Select file:', 'custom-user-importer'); ?></label></th>
							<td>
								<input type="file" name="cui_import_file" id="cui_import_file" accept=".csv,.xml" required>
								<p class="description"><?php _e('Accepted formats: CSV, XML', 'custom-user-importer'); ?></p>
							</td>
						</tr>
					</table>

					<div id="cui-file-details" style="display: none;">
						<h4><?php _e('File Details:', 'custom-user-importer'); ?></h4>
						<p><strong><?php _e('Title:', 'custom-user-importer'); ?></strong> <span id="cui-file-title"></span></p>
						<p><strong><?php _e('Size:', 'custom-user-importer'); ?></strong> <span id="cui-file-size"></span></p>
						<p><strong><?php _e('URL:', 'custom-user-importer'); ?></strong> <span id="cui-file-url"></span></p>
					</div>

					<?php wp_nonce_field('cui_upload_file_nonce', 'cui_upload_file_nonce_field'); ?>
					<p class="submit">
						<button type="submit" id="cui-select-file-btn" class="button button-secondary"><?php _e('Select file', 'custom-user-importer'); ?></button>
						<button type="button" id="cui-import-btn" class="button button-primary" style="display: none;" disabled><?php _e('Import', 'custom-user-importer'); ?></button>
					</p>
				</form>

				<div id="cui-import-progress" style="display: none;">
					<p><strong><?php _e('Percentage Complete:', 'custom-user-importer'); ?> <span id="cui-progress-percentage">0</span>%</strong></p>
					<p><strong><?php _e('Processed:', 'custom-user-importer'); ?> <span id="cui-processed-rows">0</span> of <span id="cui-total-rows">0</span></strong></p>
					<p><strong><?php _e('File: ', 'custom-user-importer'); ?> <span id="cui-import-file-info"></span></strong></p>
					<div class="cui-loader">
						<div class="cui-spinner"></div>
					</div>
				</div>

				<div id="cui-import-status-message" class="notice" style="display: none;"></div>
			</div>

			<div id="history-section" class="tab-content" style="display: none;">
				<h3><?php _e('Import History', 'custom-user-importer'); ?></h3>
				<div id="cui-history-table-container">
					<?php CUI_History::display_history_table(); // Will render the table
					?>
				</div>
			</div>
		</div>
<?php
	}
}
