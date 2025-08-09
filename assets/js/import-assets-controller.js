/**
 * ImportAssets Class
 *
 * Main import controller that orchestrates the two-phase import process:
 * Phase 1: Import metadata in batches (MetadataImporter)
 * Phase 2: Download files in batches (FileDownloader)
 *
 * Dependencies: Logger, MetadataImporter, FileDownloader, DatabaseManager classes
 */

class ImportAssets {
	constructor(assetType, progressBar) {
		this.config = {
			assetType: assetType, // 'themes' or 'plugins'
			isRunning: false
		};

		this.progressBar = progressBar;
		this.errors = [];

		// UI Selectors - Centralized selector property
		this.selectors = {
			importButton: `#import-${assetType}-btn`,
			restoreButton: '#restore-database-btn',
			progressContainer: '.aspirecloud-progress-container',
			logContainer: '.aspirecloud-log-container',
			metadataCheckbox: '#import-metadata-checkbox',
			filesCheckbox: '#import-files-checkbox',
			bulkImportCheckbox: '#bulk-import-checkbox',
			importSlugsTextarea: '#import-slugs-textarea'
		};

		// Initialize logging system
		this.logger = new Logger(this.selectors.logContainer);

		// Initialize sub-managers
		this.metadataImporter = new MetadataImporter(this);
		this.fileDownloader = new FileDownloader(this);
		this.databaseManager = new DatabaseManager(this);

		// Set progress bars for sub-managers
		this.metadataImporter.setProgressBar(progressBar);
		this.fileDownloader.setProgressBar(progressBar);
	}

	// Set progress bar instance
	setProgressBar(progressBar) {
		this.progressBar = progressBar;
		this.metadataImporter.setProgressBar(progressBar);
		this.fileDownloader.setProgressBar(progressBar);
	}

	// Main import orchestration methods
	start() {
		if (this.config.isRunning) {
			this.logger.log('WARNING', 'Import already running, ignoring start request');
			return;
		}

		// Check if bulk import is enabled
		const bulkImportEnabled = jQuery(this.selectors.bulkImportCheckbox).is(':checked');

		if (bulkImportEnabled) {
			// Bulk import mode
			this.startBulkImport();
		} else {
			// Selective import mode
			this.startSelectiveImport();
		}
	}

	// Start bulk import (original functionality)
	startBulkImport() {
		// Check which phases are selected
		const importMetadata = jQuery(this.selectors.metadataCheckbox).is(':checked');
		const importFiles = jQuery(this.selectors.filesCheckbox).is(':checked');

		// Validate that at least one phase is selected
		if (!importMetadata && !importFiles) {
			this.logger.log('ERROR', 'No import phases selected', 'Please select at least one import option');
			alert('Please select at least one import option (Import Metadata or Import Files)');
			return;
		}

		this.logger.log('INFO', `Starting ${this.config.assetType} bulk import process`,
			`Selected phases: ${importMetadata ? 'Metadata' : ''}${importMetadata && importFiles ? ' + ' : ''}${importFiles ? 'Files' : ''}`);

		this.config.isRunning = true;
		this.config.importMetadata = importMetadata;
		this.config.importFiles = importFiles;
		this.config.bulkImport = true;
		this.errors = [];

		// Reset all sub-managers
		this.metadataImporter.reset();
		this.fileDownloader.reset();

		this.logger.log('DEBUG', 'Sub-managers reset completed');

		// Disable import button
		this.disableImportButton();
		this.logger.log('DEBUG', 'Import button disabled');

		// Reset UI
		if (this.progressBar) {
			this.progressBar.reset();
			this.progressBar.show();
			this.logger.log('DEBUG', 'Progress bar reset and shown');
		}

		// Show log container along with progress
		jQuery(this.selectors.logContainer).show();

		// Start the appropriate phase(s)
		if (importMetadata) {
			// Start Phase 1: Metadata Import
			this.logger.log('INFO', 'Phase 1: Starting metadata import');
			this.metadataImporter.start();
		} else if (importFiles) {
			// Skip to Phase 2: File Downloads (metadata phase not selected)
			this.logger.log('INFO', 'Skipping metadata phase, starting file downloads directly');
			this.onMetadataImportComplete();
		}
	}

	// Start selective import by slugs (CSV only)
	startSelectiveImport() {
		const slugsInput = jQuery(this.selectors.importSlugsTextarea).val().trim();

		if (!slugsInput) {
			this.logger.log('ERROR', 'No slugs provided', 'Please enter asset slugs to import');
			alert(`Please enter ${this.config.assetType} slugs to import`);
			return;
		}

		// All selective imports now use CSV bulk import workflow
		this.startCsvBulkImport(slugsInput);
	}

	// Start CSV bulk import for all selective imports
	startCsvBulkImport(csvInput) {
		// Extract all slugs from CSV input
		const allSlugs = this.extractSlugsFromCsv(csvInput);

		if (allSlugs.length === 0) {
			this.logger.log('ERROR', 'No valid slugs found in CSV', 'Please check your CSV format and ensure it contains valid asset slugs');
			alert('No valid asset slugs found in the CSV input. Please check your format.');
			return;
		}

		this.logger.log('INFO', `Starting ${this.config.assetType} CSV bulk import process`,
			`Found ${allSlugs.length} assets to import`);

		this.config.isRunning = true;
		this.config.bulkImport = false;
		this.config.csvBulkImport = true;
		this.config.csvSlugs = [...allSlugs]; // Store copy of all slugs
		this.config.remainingSlugs = [...allSlugs]; // Working copy that gets modified
		this.errors = [];

		// Disable import button
		this.disableImportButton();
		this.logger.log('DEBUG', 'Import button disabled');

		// Reset UI
		if (this.progressBar) {
			this.progressBar.reset();
			this.progressBar.show();
			this.progressBar.updateStatus(`Starting CSV bulk import of ${allSlugs.length} assets...`);
			this.logger.log('DEBUG', 'Progress bar reset and shown');
		}

		// Show log container along with progress
		jQuery(this.selectors.logContainer).show();

		// Start CSV bulk import processing
		this.processCsvBulkImport();
	}

	// Extract slugs from CSV input
	extractSlugsFromCsv(csvInput) {
		const slugs = [];
		const lines = csvInput.split('\n');

		for (const line of lines) {
			if (line.trim()) {
				// Split by comma and extract slugs
				const lineItems = line.split(',').map(item => item.trim());
				for (const item of lineItems) {
					// Basic slug validation
					if (item && /^[a-zA-Z0-9\-_]+$/.test(item) && !slugs.includes(item)) {
						slugs.push(item);
					}
				}
			}
		}

		this.logger.log('INFO', `Extracted ${slugs.length} unique slugs from CSV input`);
		return slugs;
	}

	// Process CSV bulk import in batches
	processCsvBulkImport() {
		const batchSize = 25; // Process 25 slugs at a time for CSV bulk import
		const totalSlugs = this.config.csvSlugs.length;
		const processedSlugs = totalSlugs - this.config.remainingSlugs.length;

		if (this.config.remainingSlugs.length === 0) {
			// All slugs processed
			this.completeCsvBulkImport();
			return;
		}

		// Get next batch
		const batchSlugs = this.config.remainingSlugs.splice(0, batchSize);

		this.logger.log('INFO', `Processing CSV batch: ${batchSlugs.length} assets (${processedSlugs + batchSlugs.length}/${totalSlugs})`);

		// Update progress
		const progress = Math.round(((processedSlugs + batchSlugs.length) / totalSlugs) * 100);
		if (this.progressBar) {
			this.progressBar.updateProgress(progress);
			this.progressBar.updateDetails(`Processing ${processedSlugs + batchSlugs.length} of ${totalSlugs} assets`);
		}

		// Import this batch using CSV batch handler
		jQuery.ajax({
			url: aspirecloud_ajax.ajax_url,
			type: 'POST',
			data: {
				action: 'aspirecloud_import_csv_batch',
				slugs: batchSlugs.join(','),
				nonce: aspirecloud_ajax.nonce
			}
		})
			.done((response) => {
				if (response.success) {
					// Log batch results
					if (response.data.imported.length > 0) {
						this.logger.log('SUCCESS', `Batch imported: ${response.data.imported.length} assets`);
					}
					if (response.data.skipped.length > 0) {
						this.logger.log('WARNING', `Batch skipped: ${response.data.skipped.length} assets`);
					}
					if (response.data.errors.length > 0) {
						this.logger.log('ERROR', `Batch errors: ${response.data.errors.length} assets`);
						response.data.errors.forEach(item => {
							this.addError(`${item.slug}: ${item.message}`);
						});
					}

					// Continue with next batch after a short delay
					setTimeout(() => this.processCsvBulkImport(), 500);
				} else {
					this.logger.log('ERROR', `CSV batch import failed: ${response.data || 'Unknown error'}`);
					this.handleError(response.data || 'CSV batch import failed');
				}
			})
			.fail((jqXHR, textStatus, errorThrown) => {
				this.logger.log('ERROR', `CSV batch AJAX request failed: ${textStatus} - ${errorThrown}`);
				this.handleError(`Network error during CSV import: ${textStatus}`);
			});
	}

	// Complete CSV bulk import process
	completeCsvBulkImport() {
		this.config.isRunning = false;

		const totalSlugs = this.config.csvSlugs.length;
		this.logger.log('SUCCESS', `CSV bulk import completed`, `Processed ${totalSlugs} assets`);

		if (this.progressBar) {
			this.progressBar.updateStatus('CSV bulk import complete!');
			this.progressBar.updateProgress(100);
			this.progressBar.setComplete();
			this.progressBar.updateDetails(`CSV import complete: ${totalSlugs} assets processed`);
		}

		// Re-enable button
		this.enableImportButton();
		this.logger.log('DEBUG', 'Import button re-enabled');

		// Show errors if any
		if (this.errors.length > 0) {
			this.logger.log('WARNING', `CSV import completed with ${this.errors.length} errors`);
			this.showErrors();
		} else {
			this.logger.log('SUCCESS', 'CSV import completed with no errors');
		}
	}

	// Called when metadata import phase is complete
	onMetadataImportComplete() {
		this.logger.log('SUCCESS', 'Phase 1 completed: Metadata import finished');

		// Check if file download phase is selected
		if (this.config.importFiles) {
			this.logger.log('INFO', 'Phase 2: Starting file downloads');
			// Start Phase 2: File Downloads
			this.fileDownloader.start();
		} else {
			// Skip file download phase
			this.logger.log('INFO', 'Skipping file download phase (not selected)');
			this.onFileDownloadComplete();
		}
	}

	// Called when file download phase is complete
	onFileDownloadComplete() {
		this.logger.log('SUCCESS', 'Phase 2 completed: File downloads finished');
		this.completeImport();
	}

	// Complete the import process
	completeImport() {
		this.config.isRunning = false;

		const importedCount = this.metadataImporter.getImportedCount();
		const downloadedCount = this.fileDownloader.getDownloadedCount();

		this.logger.log('SUCCESS', `Import process completed successfully`,
			`Imported: ${importedCount} items, Downloaded: ${downloadedCount} files`);

		if (this.progressBar) {
			this.progressBar.updateStatus(aspirecloud_ajax.strings.complete);
			this.progressBar.updateProgress(100);
			this.progressBar.setComplete();

			// Final summary
			const summaryText = (aspirecloud_ajax.strings.import_complete_summary || 'Import complete: %1$d items imported, %2$d files downloaded')
				.replace('%1$d', importedCount)
				.replace('%2$d', downloadedCount);

			this.progressBar.updateDetails(summaryText);
		}

		// Re-enable button
		this.enableImportButton();
		this.logger.log('DEBUG', 'Import button re-enabled');

		// Check database optimization state
		this.databaseManager.checkOptimizationState();

		// Show errors if any
		if (this.errors.length > 0) {
			this.logger.log('WARNING', `Import completed with ${this.errors.length} warnings`);
			this.showErrors();
		} else {
			this.logger.log('SUCCESS', 'Import completed with no warnings');
		}
	}

	// Error handling
	handleError(errorMessage) {
		this.config.isRunning = false;

		this.logger.log('ERROR', 'Import process failed', errorMessage);

		if (this.progressBar) {
			this.progressBar.updateStatus(aspirecloud_ajax.strings.error);
			this.progressBar.setError();
			this.progressBar.updateDetails(errorMessage);
		}

		// Re-enable button
		this.enableImportButton();
		this.logger.log('DEBUG', 'Import button re-enabled after error');

		// Log error to console
		console.error('AspireCloud Import Error:', errorMessage);
	}

	addError(errors) {
		if (Array.isArray(errors)) {
			this.errors = this.errors.concat(errors);
		} else {
			this.errors.push(errors);
		}
	}

	// Show errors in a collapsible section
	showErrors() {
		if (this.errors.length === 0) return;

		let errorHtml = '<div class="aspirecloud-import-errors">';
		errorHtml += '<h4>Import Warnings (' + this.errors.length + ')</h4>';
		errorHtml += '<div class="aspirecloud-error-list">';

		this.errors.forEach((error) => {
			errorHtml += '<div class="aspirecloud-error-item">' + error + '</div>';
		});

		errorHtml += '</div></div>';

		jQuery(this.selectors.progressContainer).append(errorHtml);
	}

	// UI control methods
	disableImportButton() {
		jQuery(this.selectors.importButton).prop('disabled', true);
	}

	enableImportButton() {
		jQuery(this.selectors.importButton).prop('disabled', false);
	}

	// Utility methods
	getConfig() {
		return {
			main: this.config,
			metadata: this.metadataImporter.getConfig(),
			download: this.fileDownloader.getConfig()
		};
	}

	isRunning() {
		return this.config.isRunning;
	}

	// Database recovery delegation
	handleDatabaseRecovery() {
		this.databaseManager.handleRecovery();
	}

	checkDatabaseOptimizationState() {
		this.databaseManager.checkOptimizationState();
	}

	// Get total assets count for metadata import
	getTotalAssetsCount() {
		const action = this.config.assetType === 'plugins' ? 'get_total_plugins_count' : 'get_total_themes_count';

		return jQuery.ajax({
			url: aspirecloud_ajax.ajax_url,
			type: 'POST',
			data: {
				action: action,
				nonce: aspirecloud_ajax.nonce
			}
		});
	}
}

// Export for use in other modules
if (typeof module !== 'undefined' && module.exports) {
	module.exports = ImportAssets;
} else if (typeof window !== 'undefined') {
	window.ImportAssets = ImportAssets;
}
