<?php
// includes/class-cui-history.php

if (! defined('ABSPATH')) {
	exit; // Exit if accessed directly
}

class CUI_History
{

	private static $table_name;

	public static function get_table_name()
	{
		global $wpdb;
		if (! isset(self::$table_name)) {
			self::$table_name = $wpdb->prefix . 'cui_import_history';
		}
		return self::$table_name;
	}

	/**
	 * Creates the database table for import history on plugin activation.
	 */
	public static function create_table()
	{
		global $wpdb;
		$charset_collate = $wpdb->get_charset_collate();
		$table_name      = self::get_table_name();

		$sql = "CREATE TABLE $table_name (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            file_name varchar(255) NOT NULL,
            post_type varchar(50) DEFAULT 'rao' NOT NULL,
            total_rows int(11) DEFAULT 0 NOT NULL,
            processed_rows int(11) DEFAULT 0 NOT NULL,
            skipped_rows int(11) DEFAULT 0 NOT NULL,
            status varchar(50) DEFAULT 'new' NOT NULL,
            import_date datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            PRIMARY KEY  (id)
        ) $charset_collate;";

		require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
		dbDelta($sql);
	}

	/**
	 * Adds a new entry to the import history.
	 *
	 * @param string $file_name
	 * @param int $total_rows
	 * @return int The ID of the inserted history entry.
	 */
	public static function add_history_entry($file_name, $total_rows)
	{
		global $wpdb;
		$table_name = self::get_table_name();

		$wpdb->insert(
			$table_name,
			array(
				'file_name'    => sanitize_file_name($file_name),
				'total_rows'   => (int) $total_rows,
				'import_date'  => current_time('mysql'),
				'status'       => 'new',
				'post_type'    => 'rao', // As seen in screenshot
			),
			array('%s', '%d', '%s', '%s', '%s')
		);

		return $wpdb->insert_id;
	}

	/**
	 * Updates the status and processed rows of an import history entry.
	 *
	 * @param int $id The history entry ID.
	 * @param string $status The new status (e.g., 'in_progress', 'completed', 'failed').
	 * @param int $processed_rows The number of processed rows.
	 */
	public static function update_history_status($id, $status, $processed_rows)
	{
		global $wpdb;
		$table_name = self::get_table_name();

		$wpdb->update(
			$table_name,
			array(
				'status'         => sanitize_text_field($status),
				'processed_rows' => (int) $processed_rows,
			),
			array('id' => (int) $id),
			array('%s', '%d'),
			array('%d')
		);
	}

	/**
	 * Retrieves all import history entries.
	 *
	 * @return array
	 */
	public static function get_all_history()
	{
		global $wpdb;
		$table_name = self::get_table_name();
		return $wpdb->get_results("SELECT * FROM $table_name ORDER BY import_date DESC", ARRAY_A);
	}

	/**
	 * Displays the import history table.
	 */
	public static function display_history_table()
	{
		$history = self::get_all_history();
?>
		<table class="wp-list-table widefat fixed striped">
			<thead>
				<tr>
					<th scope="col" class="manage-column column-id">ID</th>
					<th scope="col" class="manage-column column-file">File</th>
					<th scope="col" class="manage-column column-post-type">Post type</th>
					<th scope="col" class="manage-column column-processed">Processed</th>
					<th scope="col" class="manage-column column-skipped">Skipped</th>
					<th scope="col" class="manage-column column-status">Status</th>
					<th scope="col" class="manage-column column-import-date">Import Date</th>
				</tr>
			</thead>
			<tbody id="the-list">
				<?php if (! empty($history)) : ?>
					<?php foreach ($history as $entry) : ?>
						<tr <?php if ($entry['status'] === 'in_progress') echo 'class="cui-in-progress-row"'; ?>>
							<td><?php echo esc_html($entry['id']); ?></td>
							<td><?php echo esc_html($entry['file_name']); ?></td>
							<td><?php echo esc_html($entry['post_type']); ?></td>
							<td><?php echo esc_html($entry['processed_rows']); ?> of <?php echo esc_html($entry['total_rows']); ?></td>
							<td><?php echo esc_html($entry['skipped_rows']); ?></td>
							<td><?php echo esc_html(ucfirst(str_replace('_', ' ', $entry['status']))); ?></td>
							<td><?php echo esc_html(date('Y-m-d H:i:s', strtotime($entry['import_date']))); ?></td>
						</tr>
					<?php endforeach; ?>
				<?php else : ?>
					<tr>
						<td colspan="7"><?php _e('No import history found.', 'custom-user-importer'); ?></td>
					</tr>
				<?php endif; ?>
			</tbody>
		</table>
<?php
	}
}
