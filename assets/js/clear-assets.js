/**
 * AspireCloud Clear Assets
 * Handles the clear data functionality for plugins and themes
 */

class ClearAssets {
	constructor() {
		this.config = {
			currentBatch: 1,
			totalBatches: 0,
			totalItems: 0,
			clearedCount: 0,
			isRunning: false,
			assetType: '', // 'plugins' or 'themes'
			errors: []
		};

		this.progressBar = null;
		this.bindEvents();
		this.detectClearType();
	}

	// Set progress bar instance
	setProgressBar(progressBar) {
		this.progressBar = progressBar;
	}

	// Detect if we're on plugins or themes clear page
	detectClearType() {
		if (jQuery('#clear-plugins-data-btn').length) {
			this.config.assetType = 'plugins';
		} else if (jQuery('#clear-themes-data-btn').length) {
			this.config.assetType = 'themes';
		}
	}

	// Bind event handlers
	bindEvents() {
		const self = this;

		// Plugin clear data button
		jQuery(document).on('click', '#clear-plugins-data-btn', function(e) {
			e.preventDefault();
			if (!self.config.isRunning) {
				self.config.assetType = 'plugins';
				self.clearData();
			}
		});

		// Theme clear data button
		jQuery(document).on('click', '#clear-themes-data-btn', function(e) {
			e.preventDefault();
			if (!self.config.isRunning) {
				self.config.assetType = 'themes';
				self.clearData();
			}
		});
	}

	// Clear all data for the current item type
	clearData() {
		const self = this;

		// Confirm with user
		if (!confirm(aspirecloud_ajax.strings.confirm_clear)) {
			return;
		}

		// Reset configuration for clearing
		this.resetConfig();

		// Update UI to show clearing in progress
		if (this.progressBar) {
			this.progressBar.show();
			this.progressBar.updateStatus(aspirecloud_ajax.strings.getting_clear_count || aspirecloud_ajax.strings.clearing_data);
		}
		this.disableClearButton();

		// Get total count first
		this.getClearCount()
			.done(function(response) {
				if (response.success) {
					self.config.totalItems = response.data.total;
					self.config.totalBatches = response.data.total_batches;

					if (self.config.totalItems === 0) {
						self.completeClear();
						return;
					}

					// Start clearing batches
					self.clearNextBatch();
				} else {
					self.handleError(response.data || 'Failed to get clear count');
				}
			})
			.fail(function() {
				self.handleError('Failed to get clear count');
			});
	}

	// Get total count of items for clearing
	getClearCount(assetType) {
		assetType = assetType || this.config.assetType;
		const action = assetType === 'plugins' ? 'get_plugins_clear_count' : 'get_themes_clear_count';

		return jQuery.ajax({
			url: aspirecloud_ajax.ajax_url,
			type: 'POST',
			data: {
				action: action,
				nonce: aspirecloud_ajax.nonce
			}
		});
	}

	// Clear the next batch
	clearNextBatch() {
		const self = this;

		// Update status
		let statusText = aspirecloud_ajax.strings.clearing_data;
		if (this.config.totalItems > 0) {
			const percentage = Math.round((this.config.clearedCount / this.config.totalItems) * 100);
			statusText = 'Clearing data... (' + percentage + '% complete)';
		}

		if (this.progressBar) {
			this.progressBar.updateStatus(statusText);
		}

		// Clear current batch
		this.clearBatch()
			.done(function(response) {
				if (response.success) {
					self.config.clearedCount += response.data.deleted_count;

					// Add any errors to our collection
					if (response.data.errors && response.data.errors.length > 0) {
						self.config.errors = self.config.errors.concat(response.data.errors);
					}

					// Update progress
					self.updateClearProgress();

					// Check if there are more items to clear
					if (response.data.has_more) {
						// Continue with next batch after a short delay
						setTimeout(function() {
							self.clearNextBatch();
						}, 100);
					} else {
						// All done
						self.completeClear();
					}

				} else {
					self.handleError(response.data || aspirecloud_ajax.strings.error);
				}
			})
			.fail(function() {
				self.handleError(aspirecloud_ajax.strings.error);
			});
	}

	// Clear a specific batch
	clearBatch(assetType) {
		assetType = assetType || this.config.assetType;
		const action = assetType === 'plugins' ? 'clear_plugins_data' : 'clear_themes_data';

		return jQuery.ajax({
			url: aspirecloud_ajax.ajax_url,
			type: 'POST',
			data: {
				action: action,
				nonce: aspirecloud_ajax.nonce
			}
		});
	}

	// Update clear progress display
	updateClearProgress() {
		if (!this.progressBar) return;

		const percentage = this.config.totalItems > 0
			? Math.round((this.config.clearedCount / this.config.totalItems) * 100)
			: 0;

		// Update progress bar
		this.progressBar.updateProgress(percentage);

		// Update details text - show actual cleared count
		const itemType = this.config.assetType === 'plugins' ? 'plugins' : 'themes';
		let detailsText = 'Cleared ' + this.config.clearedCount + ' ' + itemType;

		// If we know the total, show it
		if (this.config.totalItems > 0) {
			detailsText += ' of ' + this.config.totalItems;
		}

		this.progressBar.updateDetails(detailsText);
	}

	// Complete the clear process
	completeClear() {
		this.config.isRunning = false;

		// Show final status message with actual count cleared
		const itemType = this.config.assetType === 'plugins' ? 'plugins' : 'themes';
		let finalMessage = aspirecloud_ajax.strings.data_cleared;
		if (this.config.clearedCount > 0) {
			finalMessage += ' (' + this.config.clearedCount + ' ' + itemType + ' cleared)';
		} else {
			finalMessage = 'No ' + itemType + ' found to clear.';
		}

		if (this.progressBar) {
			this.progressBar.updateStatus(finalMessage);
			// Final progress update - set to 100% since we're done
			this.progressBar.updateProgress(100);
			this.progressBar.updateDetails('Cleared ' + this.config.clearedCount + ' ' + itemType);
			this.progressBar.setComplete();
		}

		// Re-enable button
		this.enableClearButton();

		// Show errors if any
		if (this.config.errors.length > 0) {
			this.showErrors();
		}
	}

	// Handle errors
	handleError(errorMessage) {
		this.config.isRunning = false;

		if (this.progressBar) {
			this.progressBar.updateStatus(aspirecloud_ajax.strings.error);
			this.progressBar.setError();
			this.progressBar.updateDetails(errorMessage);
		}

		// Re-enable button
		this.enableClearButton();

		// Log error to console
		console.error('AspireCloud Clear Error:', errorMessage);
	}

	// Show errors in a collapsible section
	showErrors() {
		if (this.config.errors.length === 0) return;

		let errorHtml = '<div class="aspirecloud-import-errors" style="margin-top: 20px;">';
		errorHtml += '<h4>Clear Warnings (' + this.config.errors.length + ')</h4>';
		errorHtml += '<div class="aspirecloud-error-list" style="max-height: 200px; overflow-y: auto; background: #fff; padding: 10px; border: 1px solid #ddd; border-radius: 4px;">';

		this.config.errors.forEach(function(error) {
			errorHtml += '<div style="margin-bottom: 5px; font-size: 12px; color: #666;">' + error + '</div>';
		});

		errorHtml += '</div></div>';

		jQuery('.aspirecloud-progress-container').append(errorHtml);
	}

	// Disable clear button
	disableClearButton() {
		jQuery('#clear-' + this.config.assetType + '-data-btn').prop('disabled', true);
	}

	// Enable clear button
	enableClearButton() {
		jQuery('#clear-' + this.config.assetType + '-data-btn').prop('disabled', false);
	}

	// Reset configuration for clearing
	resetConfig() {
		this.config.currentBatch = 1;
		this.config.totalBatches = 0;
		this.config.totalItems = 0;
		this.config.clearedCount = 0;
		this.config.isRunning = true;
		this.config.errors = [];

		// Reset UI
		if (this.progressBar) {
			this.progressBar.reset();
			this.progressBar.updateDetails('Preparing to clear data...');
		}
	}

	// Get current configuration (for debugging)
	getConfig() {
		return this.config;
	}

	// Check if clearing is running
	isRunning() {
		return this.config.isRunning;
	}
}

// Export to global scope
window.ClearAssets = ClearAssets;
