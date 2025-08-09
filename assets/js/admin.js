/**
 * AspireCloud Admin JavaScript
 * Main entry point that initializes the separate classes for import, clear, and progress functionality
 * Uses modern JavaScript class structure without legacy compatibility layer
 */

(function($) {
	'use strict';

	// Main AspireCloud Admin class that coordinates the separate managers
	class AspireCloudAdmin {
		constructor() {
			// Initialize the progress bar
			this.progressBar = new ProgressBar('.aspirecloud-progress-container');

			// Detect asset type and initialize import assets accordingly
			this.assetType = this.detectAssetType();

			// Initialize the managers
			this.importAssets = this.assetType ? new ImportAssets(this.assetType, this.progressBar) : null;
			this.clearAssets = new ClearAssets();

			// Set the progress bar for managers
			if (this.importAssets) {
				this.importAssets.setProgressBar(this.progressBar);
			}
			this.clearAssets.setProgressBar(this.progressBar);

			// Bind import button events
			this.bindImportEvents();

			// Initialize button state based on default checkbox values
			this.updateImportButtonState();
		}

		// Detect whether we're on themes or plugins page
		detectAssetType() {
			if (jQuery('#import-themes-btn').length) {
				return 'themes';
			} else if (jQuery('#import-plugins-btn').length) {
				return 'plugins';
			}
			return null;
		}

		// Bind import button click events
		bindImportEvents() {
			const self = this;

			// Import themes button
			jQuery(document).on('click', '#import-themes-btn', function(e) {
				e.preventDefault();
				if (self.importAssets && !self.importAssets.isRunning()) {
					self.importAssets.start();
				}
			});

			// Import plugins button
			jQuery(document).on('click', '#import-plugins-btn', function(e) {
				e.preventDefault();
				if (self.importAssets && !self.importAssets.isRunning()) {
					self.importAssets.start();
				}
			});

			// Database restore button
			jQuery(document).on('click', '#restore-database-btn', function(e) {
				e.preventDefault();
				if (self.importAssets) {
					self.importAssets.handleDatabaseRecovery();
				}
			});

			// Handle import option checkboxes
			jQuery(document).on('change', '.aspirecloud-import-option', function() {
				self.updateImportButtonState();
			});
		}

		// Update import button state based on checkbox selections
		updateImportButtonState() {
			const importMetadata = jQuery('#import-metadata-checkbox').is(':checked');
			const importFiles = jQuery('#import-files-checkbox').is(':checked');
			const importButton = jQuery(`#import-${this.assetType}-btn`);

			if (!importMetadata && !importFiles) {
				// No options selected, disable button
				importButton.prop('disabled', true).addClass('disabled');
			} else {
				// At least one option selected, enable button
				importButton.prop('disabled', false).removeClass('disabled');
			}
		}

		// Get the import assets
		getImportAssets() {
			return this.importAssets;
		}

		// Get the clear assets
		getClearAssets() {
			return this.clearAssets;
		}

		// Get the progress bar
		getProgressBar() {
			return this.progressBar;
		}

		// Check if any operation is running
		isRunning() {
			return (this.importAssets && this.importAssets.isRunning()) || this.clearAssets.isRunning();
		}

		// Initialize database state checking
		initializeDatabaseState() {
			if (this.importAssets) {
				this.importAssets.checkDatabaseOptimizationState();
			}
		}
	}

	// Global instance
	let aspireCloudAdmin = null;

	// Initialize when document is ready
	$(document).ready(function() {
		// Only initialize on import pages
		if ($('.aspirecloud-import-page').length) {
			aspireCloudAdmin = new AspireCloudAdmin();

			// Initialize database state checking
			aspireCloudAdmin.initializeDatabaseState();
		}
	});

	// Expose main class and instance to global scope for debugging
	window.AspireCloudAdmin = AspireCloudAdmin;
	// Expose the global instance
	window.aspireCloudAdmin = aspireCloudAdmin;

})(jQuery);
