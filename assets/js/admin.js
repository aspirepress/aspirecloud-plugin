/**
 * AspireCloud Admin JavaScript
 * Main entry point that initializes the separate classes for import, clear, and progress functionality
 * Uses modern JavaScript class structure without legacy compatibility layer
 */

(function(jQuery) {
	'use strict';

	// Main AspireCloud Admin class that coordinates the separate managers
	class AspireCloudAdmin {
		constructor() {
			// Element selectors
			this.selectors = {
				progressContainer: '.aspirecloud-progress-container',
				importThemesBtn: '#import-themes-btn',
				importPluginsBtn: '#import-plugins-btn',
				restoreDbBtn: '#restore-database-btn',
				importButtons: '#import-themes-btn, #import-plugins-btn, #restore-database-btn',
				importOption: '.aspirecloud-import-option',
				metadataCheckbox: '#import-metadata-checkbox',
				filesCheckbox: '#import-files-checkbox',
				bulkImportCheckbox: '#bulk-import-checkbox',
				bulkImportOptions: '#bulk-import-options',
				selectiveImportOptions: '#selective-import-options',
				importSlugsTextarea: '#import-slugs-textarea',
				importPage: '.aspirecloud-import-page'
			};

			// Initialize the progress bar
			this.progressBar = new ProgressBar(this.selectors.progressContainer);

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

			// Bind import button events and initialize button state
			this.bindImportEvents();
			this.updateImportButtonState();
			this.initializeBulkImportToggle();
		}

		// Detect whether we're on themes or plugins page
		detectAssetType() {
			return jQuery(this.selectors.importThemesBtn).length ? 'themes' :
				   jQuery(this.selectors.importPluginsBtn).length ? 'plugins' : null;
		}

		// Bind import button click events
		bindImportEvents() {
			jQuery(document).on('click', this.selectors.importButtons, (e) => {
				e.preventDefault();

				const target = jQuery(e.target);
				const buttonId = target.attr('id');

				if (!this.importAssets) return;

				if (buttonId === 'restore-database-btn') {
					this.importAssets.handleDatabaseRecovery();
				} else if (!this.importAssets.isRunning()) {
					this.importAssets.start();
				}
			});

			// Handle import option checkboxes
			jQuery(document).on('change', this.selectors.importOption, () => {
				this.updateImportButtonState();
			});
		}

		// Update import button state based on checkbox selections
		updateImportButtonState() {
			const bulkImportEnabled = jQuery(this.selectors.bulkImportCheckbox).is(':checked');
			const importButton = jQuery(`#import-${this.assetType}-btn`);

			if (bulkImportEnabled) {
				// Bulk import mode - check metadata/files checkboxes
				const checkboxes = jQuery(`${this.selectors.metadataCheckbox}, ${this.selectors.filesCheckbox}`);
				const isAnyChecked = checkboxes.is(':checked');
				importButton.prop('disabled', !isAnyChecked).toggleClass('disabled', !isAnyChecked);
			} else {
				// Selective import mode - check if slugs are provided
				const slugsText = jQuery(this.selectors.importSlugsTextarea).val().trim();
				const hasSlugs = slugsText.length > 0;
				importButton.prop('disabled', !hasSlugs).toggleClass('disabled', !hasSlugs);
			}
		}

		// Initialize bulk import toggle functionality
		initializeBulkImportToggle() {
			// Handle bulk import checkbox change
			jQuery(document).on('change', this.selectors.bulkImportCheckbox, () => {
				this.toggleImportMode();
			});

			// Handle textarea input for selective import
			jQuery(document).on('input', this.selectors.importSlugsTextarea, () => {
				this.updateImportButtonState();
			});

			// Initialize the toggle state
			this.toggleImportMode();
		}

		// Toggle between bulk and selective import modes
		toggleImportMode() {
			const bulkImportEnabled = jQuery(this.selectors.bulkImportCheckbox).is(':checked');

			if (bulkImportEnabled) {
				// Show bulk import options, hide selective import options
				jQuery(this.selectors.bulkImportOptions).show();
				jQuery(this.selectors.selectiveImportOptions).hide();
			} else {
				// Hide bulk import options, show selective import options
				jQuery(this.selectors.bulkImportOptions).hide();
				jQuery(this.selectors.selectiveImportOptions).show();
			}

			// Update button state
			this.updateImportButtonState();
		}

		// Check if any operation is running
		isRunning() {
			return (this.importAssets?.isRunning()) || this.clearAssets.isRunning();
		}

		// Initialize database state checking
		initializeDatabaseState() {
			this.importAssets?.checkDatabaseOptimizationState();
		}
	}

	// Initialize when document is ready
	jQuery(document).ready(() => {
		// Only initialize on import pages
		if (jQuery('.aspirecloud-import-page').length) {
			const aspireCloudAdmin = new AspireCloudAdmin();
			aspireCloudAdmin.initializeDatabaseState();

			// Expose instance to global scope for debugging
			window.aspireCloudAdmin = aspireCloudAdmin;
		}
	});

	// Expose main class to global scope
	window.AspireCloudAdmin = AspireCloudAdmin;

})(jQuery);
