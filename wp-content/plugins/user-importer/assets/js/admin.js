/* assets/js/admin.js */

jQuery(document).ready(function ($) {
	const importForm = $('#cui-import-form');
	const selectFileBtn = $('#cui-select-file-btn');
	const importBtn = $('#cui-import-btn');
	const fileInput = $('#cui_import_file');
	const fileDetails = $('#cui-file-details');
	const fileTitleSpan = $('#cui-file-title');
	const fileSizeSpan = $('#cui-file-size');
	const fileUrlSpan = $('#cui-file-url');
	const importProgress = $('#cui-import-progress');
	const progressPercentage = $('#cui-progress-percentage');
	const processedRows = $('#cui-processed-rows');
	const totalRows = $('#cui-total-rows');
	const importFileInfo = $('#cui-import-file-info');
	const importStatusMessage = $('#cui-import-status-message');
	const historyTableContainer = $('#cui-history-table-container');

	let currentImportId = 0; // To store the ID of the ongoing import

	// Tab switching logic
	$('.nav-tab-wrapper a').on('click', function (e) {
		e.preventDefault();
		$('.nav-tab').removeClass('nav-tab-active');
		$(this).addClass('nav-tab-active');
		$('.tab-content').hide();
		$($(this).attr('href')).show();
		// If switching to history tab, refresh the history table
		if ($(this).attr('id') === 'history-tab') {
			$('#history-section').css('display', 'block')
			refreshHistoryTable();
		}
		if ($(this).attr('id') === 'import-tab') {
			$('#import-section').css('display', 'block')
			// const ongoingImportId = localStorage.getItem('currentImportId');
			// if (ongoingImportId) {
			// 	resumeImportProgress(ongoingImportId); // This will trigger progress bar again
			// }
		}
	});

	// Handle file selection and display details
	fileInput.on('change', function () {
		const file = this.files[0];
		if (file) {
			// Display basic file details immediately
			fileTitleSpan.text(file.name);
			fileSizeSpan.text(formatBytes(file.size));
			fileUrlSpan.text('Not available until uploaded'); // URL will be provided by backend after upload
			fileDetails.show();
			importBtn.prop('disabled', false).show(); // Enable and show Import button
			selectFileBtn.hide(); // Hide Select File button
			importStatusMessage.hide(); // Hide previous status messages
		} else {
			fileDetails.hide();
			importBtn.prop('disabled', true).hide();
			selectFileBtn.show();
		}
	});

	// Handle "Select file" button click - this will trigger the upload via AJAX
	selectFileBtn.on('click', function (e) {
		e.preventDefault();
		fileInput.trigger('click'); // Programmatically click the hidden file input
	});

	// Handle "Import" button click - this will trigger the initial upload via AJAX
	importBtn.on('click', function (e) {
		e.preventDefault();

		if (!fileInput[0].files.length) {
			displayStatusMessage('error', 'Please select a file to import.');
			return;
		}

		const formData = new FormData(importForm[0]);
		formData.append('action', 'cui_upload_file');
		formData.append('nonce', cui_ajax_object.upload_nonce);

		importBtn.prop('disabled', true).text('Uploading...'); // Disable and change text
		importProgress.hide();
		displayStatusMessage('info', 'Uploading file...');

		$.ajax({
			url: cui_ajax_object.ajax_url,
			type: 'POST',
			data: formData,
			processData: false,
			contentType: false,
			success: function (response) {
				if (response.success) {
					displayStatusMessage('success', response.data.message);
					// Update file details with URL from backend
					fileUrlSpan.text(response.data.file_url);

					// Now initialize import (count rows, create history entry)
					startImport(response.data.transient_key);
				} else {
					displayStatusMessage('error', response.data.message);
					importBtn.prop('disabled', false).text('Import').show();
					selectFileBtn.show();
				}
			},
			error: function (jqXHR, textStatus, errorThrown) {
				displayStatusMessage('error', 'File upload failed: ' + textStatus + ' - ' + errorThrown);
				importBtn.prop('disabled', false).text('Import').show();
				selectFileBtn.show();
			}
		});
	});

	function startImport(transientKey) {
		importBtn.hide(); // Hide import button after initialization
		importProgress.show(); // Show progress bar
		displayStatusMessage('info', 'Initializing import...');

		$.ajax({
			url: cui_ajax_object.ajax_url,
			type: 'POST',
			data: {
				action: 'cui_start_import',
				nonce: cui_ajax_object.nonce,
				transient_key: transientKey
			},
			success: function (response) {
				if (response.success) {
					currentImportId = response.data.import_id; // Store import ID
					localStorage.setItem('currentImportId', currentImportId);
					totalRows.text(response.data.total_rows);
					importFileInfo.text(fileTitleSpan.text()); // Use the displayed file name
					processedRows.text(0);
					progressPercentage.text(0);
					displayStatusMessage('success', response.data.message);
					processNextBatch(); // Start batch processing
				} else {
					displayStatusMessage('error', response.data.message);
					importBtn.prop('disabled', false).text('Import').show(); // Show Import button on error
					importProgress.hide();
				}
			},
			error: function (jqXHR, textStatus, errorThrown) {
				displayStatusMessage('error', 'Import initialization failed: ' + textStatus + ' - ' + errorThrown);
				importBtn.prop('disabled', false).text('Import').show();
				importProgress.hide();
			}
		});
	}

	function processNextBatch() {
		if (currentImportId === 0) {
			displayStatusMessage('error', 'No active import to process.');
			resetImportState();
			return;
		}

		$.ajax({
			url: cui_ajax_object.ajax_url,
			type: 'POST',
			data: {
				action: 'cui_process_batch',
				nonce: cui_ajax_object.nonce,
				import_id: currentImportId
			},
			success: function (response) {
				if (response.success) {
					processedRows.text(response.data.processed_rows);
					totalRows.text(response.data.total_rows);
					progressPercentage.text(response.data.percentage);
					importFileInfo.text(`(id: ${response.data.file_id}) ${response.data.file_name} ${new Date().toLocaleString()}`);

					if (response.data.completed) {
						displayStatusMessage('success', 'Import completed successfully!');
						resetImportState();
						refreshHistoryTable(); // Refresh history table
					} else {
						// Continue processing next batch after a short delay
						setTimeout(processNextBatch, 500); // Adjust delay as needed
					}
				} else {
					displayStatusMessage('error', response.data.message);
					resetImportState();
				}
			},
			error: function (jqXHR, textStatus, errorThrown) {
				displayStatusMessage('error', 'Batch processing failed: ' + textStatus + ' - ' + errorThrown);
				resetImportState();
			}
		});
	}

	function resetImportState() {
		importBtn.prop('disabled', false).text('Import').show();
		selectFileBtn.show();
		fileInput.val(''); // Clear selected file
		fileDetails.hide();
		importProgress.hide();
		currentImportId = 0; // Reset import ID
	}

	function displayStatusMessage(type, message) {
		importStatusMessage.removeClass('notice-error notice-success notice-info').hide();
		importStatusMessage.addClass('notice-' + type).html('<p>' + message + '</p>').show();
	}

	function formatBytes(bytes, decimals = 2) {
		if (bytes === 0) return '0 Bytes';
		const k = 1024;
		const dm = decimals < 0 ? 0 : decimals;
		const sizes = ['Bytes', 'KB', 'MB', 'GB', 'TB'];
		const i = Math.floor(Math.log(bytes) / Math.log(k));
		return parseFloat((bytes / Math.pow(k, i)).toFixed(dm)) + ' ' + sizes[i];
	}

	function refreshHistoryTable() {
		$.ajax({
			url: cui_ajax_object.ajax_url,
			type: 'POST',
			data: {
				action: 'cui_refresh_history',
				nonce: cui_ajax_object.nonce
			},
			success: function (response) {
				if (response.success) {
					historyTableContainer.html(response.data.table_html);
				} else {
					console.error('Failed to refresh history:', response.data.message);
				}
			},
			error: function (jqXHR, textStatus, errorThrown) {
				console.error('AJAX error refreshing history:', textStatus, errorThrown);
			}
		});
	}

	// function resumeImportProgress(importId) {
	// 	$.ajax({
	// 		url: cui_ajax_object.ajax_url,
	// 		type: 'POST',
	// 		data: {
	// 			action: 'cui_check_import_status',
	// 			import_id: importId,
	// 			nonce: cui_ajax_object.status_nonce
	// 		},
	// 		success: function (response) {
	// 			if (response.success && response.data.in_progress) {
	// 				$('#import-progress').show();
	// 				$('#import-progress .progress-bar')
	// 					.css('width', response.data.percentage + '%')
	// 					.text(response.data.inserted_rows + '/' + response.data.total_rows);

	// 				setTimeout(function () {
	// 					resumeImportProgress(importId);
	// 				}, 2000);
	// 			} else {
	// 				localStorage.removeItem('currentImportId');
	// 				$('#import-progress').hide();
	// 				$('#import-btn').show();
	// 				refreshHistoryTable();
	// 			}
	// 		}
	// 	});
	// }

	// Initial refresh of history table when the page loads if history tab is active (or set it up to do so)
	// For now, it will only refresh when the history tab is clicked.
});