/**
 * AspireCloud Import Assets
 * Handles the import functionality for plugins and themes
 */

class ImportAssets {
	constructor() {
		this.config = {
			currentBatch: 1,
			totalBatches: 0,
			totalItems: 0,
			importedCount: 0,
			isRunning: false,
			assetType: '', // 'plugins' or 'themes'
			errors: [],
			assetSlugs: [], // Store all plugin or theme slugs
			batchSize: 10,
			restTime: 30000, // 30 seconds in milliseconds
			// Slug fetching state
			slugFetchPage: 1,
			totalSlugPages: 0,
			slugFetchComplete: false
		};

		this.progressBar = null;
		this.bindEvents();
		this.detectImportType();
	}

	// Set progress bar instance
	setProgressBar(progressBar) {
		this.progressBar = progressBar;
	}

	// Detect if we're on plugins or themes import page
	detectImportType() {
		if (jQuery('#import-plugins-btn').length) {
			this.config.assetType = 'plugins';
		} else if (jQuery('#import-themes-btn').length) {
			this.config.assetType = 'themes';
		}
	}

	// Bind event handlers
	bindEvents() {
		const self = this;

		// Plugin import button
		jQuery(document).on('click', '#import-plugins-btn', function(e) {
			e.preventDefault();
			if (!self.config.isRunning) {
				self.config.assetType = 'plugins';
				self.startImport();
			}
		});

		// Theme import button
		jQuery(document).on('click', '#import-themes-btn', function(e) {
			e.preventDefault();
			if (!self.config.isRunning) {
				self.config.assetType = 'themes';
				self.startImport();
			}
		});
	}

	// Start the import process
	startImport() {
		// Reset configuration
		this.resetConfig();

		// Update UI
		if (this.progressBar) {
			this.progressBar.show();
			this.progressBar.updateStatus(aspirecloud_ajax.strings.getting_slugs);
		}
		this.disableImportButton();

		if (this.config.assetType === 'plugins') {
			// Start fetching plugin slugs in batches
			this.fetchNextSlugBatch();
		} else if (this.config.assetType === 'themes') {
			// Start fetching theme slugs in batches
			this.fetchNextSlugBatch();
		}
	}

	// Fetch the next batch of slugs (plugins or themes)
	fetchNextSlugBatch() {
		const self = this;

		// Update status
		let statusText = aspirecloud_ajax.strings.getting_slugs;
		if (this.config.totalSlugPages > 0) {
			statusText += ' (' + this.config.slugFetchPage + '/' + this.config.totalSlugPages + ')';
		}
		if (this.progressBar) {
			this.progressBar.updateStatus(statusText);
		}

		const ajaxCall = this.getSlugBatch(this.config.slugFetchPage, this.config.assetType);

		ajaxCall
			.done(function(response) {
				if (response.success) {
					// Add slugs to our collection
					if (self.config.assetType === 'plugins') {
						self.config.assetSlugs = self.config.assetSlugs.concat(response.data.plugin_slugs);
					} else {
						self.config.assetSlugs = self.config.assetSlugs.concat(response.data.theme_slugs);
					}

					// Set totals on first page
					if (self.config.slugFetchPage === 1) {
						if (self.config.assetType === 'plugins') {
							self.config.totalItems = response.data.total_plugins;
							self.config.totalSlugPages = response.data.total_pages;
						} else {
							self.config.totalItems = response.data.total_themes;
							self.config.totalSlugPages = response.data.total_pages;
						}
					}

					// Check if we have more slugs to fetch
					if (response.data.has_more) {
						self.config.slugFetchPage++;
						// Continue fetching after a short delay
						setTimeout(function() {
							self.fetchNextSlugBatch();
						}, 100);
					} else {
						// All slugs fetched, start importing
						self.config.slugFetchComplete = true;
						self.config.totalBatches = Math.ceil(self.config.assetSlugs.length / self.config.batchSize);
						self.importNextBatch();
					}
				} else {
					self.handleError(response.data || aspirecloud_ajax.strings.error);
				}
			})
			.fail(function() {
				self.handleError(aspirecloud_ajax.strings.error);
			});
	}

	// Get a batch of slugs (plugins or themes)
	getSlugBatch(page, assetType) {
		assetType = assetType || this.config.assetType;

		const data = {
			action: assetType === 'plugins' ? 'get_all_plugin_slugs' : 'get_all_theme_slugs',
			page: page,
			nonce: aspirecloud_ajax.nonce
		};

		// Include total values for subsequent pages if we have them
		if (this.config.totalItems > 0) {
			if (assetType === 'plugins') {
				data.total_plugins = this.config.totalItems;
			} else {
				data.total_themes = this.config.totalItems;
			}
		}
		if (this.config.totalSlugPages > 0) {
			data.total_pages = this.config.totalSlugPages;
		}

		return jQuery.ajax({
			url: aspirecloud_ajax.ajax_url,
			type: 'POST',
			data: data
		});
	}

	// Get all slugs for a specific asset type
	getAllSlugs(assetType) {
		assetType = assetType || this.config.assetType;

		return jQuery.ajax({
			url: aspirecloud_ajax.ajax_url,
			type: 'POST',
			data: {
				action: assetType === 'plugins' ? 'get_all_plugin_slugs' : 'get_all_theme_slugs',
				nonce: aspirecloud_ajax.nonce
			}
		});
	}

	// Get total count of items
	getTotalCount(assetType) {
		assetType = assetType || this.config.assetType;
		const action = assetType === 'plugins' ? 'get_plugins_count' : 'get_themes_count';

		return jQuery.ajax({
			url: aspirecloud_ajax.ajax_url,
			type: 'POST',
			data: {
				action: action,
				nonce: aspirecloud_ajax.nonce
			}
		});
	}

	// Import the next batch of plugins or themes
	importNextBatch() {
		const self = this;

		if (this.config.currentBatch > this.config.totalBatches) {
			this.completeImport();
			return;
		}

		// Update status
		const statusText = aspirecloud_ajax.strings.importing_batch
			.replace('%1$d', this.config.currentBatch)
			.replace('%2$d', this.config.totalBatches);

		if (this.progressBar) {
			this.progressBar.updateStatus(statusText);
		}

		// Get slugs for current batch
		const startIndex = (this.config.currentBatch - 1) * this.config.batchSize;
		const endIndex = startIndex + this.config.batchSize;
		const batchSlugs = this.config.assetSlugs.slice(startIndex, endIndex);

		// Import batch
		this.importBatch(batchSlugs, this.config.assetType)
			.done(function(response) {
				self.handleBatchResponse(response);
			})
			.fail(function() {
				self.handleError(aspirecloud_ajax.strings.error);
			});
	}

	// Handle batch response (common for both plugins and themes)
	handleBatchResponse(response) {
		const self = this;

		if (response.success) {
			self.config.importedCount += response.data.imported_count;

			// Add any errors to our collection
			if (response.data.errors && response.data.errors.length > 0) {
				self.config.errors = self.config.errors.concat(response.data.errors);
			}

			// Update progress
			this.updateProgress();

			// Move to next batch
			self.config.currentBatch++;

			// Rest for 30 seconds before next batch
			if (this.progressBar) {
				this.progressBar.updateStatus(aspirecloud_ajax.strings.resting);
			}
			setTimeout(function() {
				self.importNextBatch();
			}, self.config.restTime);

		} else {
			self.handleError(response.data || aspirecloud_ajax.strings.error);
		}
	}

	// Import a batch of assets (plugins or themes)
	importBatch(slugs, assetType) {
		assetType = assetType || this.config.assetType;

		const data = {
			nonce: aspirecloud_ajax.nonce
		};

		if (assetType === 'plugins') {
			data.action = 'import_plugin_batch';
			data.plugin_slugs = slugs;
		} else {
			data.action = 'import_theme_batch';
			data.theme_slugs = slugs;
		}

		return jQuery.ajax({
			url: aspirecloud_ajax.ajax_url,
			type: 'POST',
			data: data
		});
	}

	// Import a specific page
	importPage(page, assetType) {
		assetType = assetType || this.config.assetType;
		const action = assetType === 'plugins' ? 'import_plugins' : 'import_themes';

		return jQuery.ajax({
			url: aspirecloud_ajax.ajax_url,
			type: 'POST',
			data: {
				action: action,
				page: page,
				nonce: aspirecloud_ajax.nonce
			}
		});
	}

	// Update progress display
	updateProgress() {
		if (!this.progressBar) return;

		const percentage = Math.round((this.config.importedCount / this.config.totalItems) * 100);

		// Update progress bar
		this.progressBar.updateProgress(percentage);

		// Update details text
		const itemsText = this.config.assetType === 'plugins'
			? aspirecloud_ajax.strings.imported_plugins
			: aspirecloud_ajax.strings.imported_themes;

		const detailsText = itemsText
			.replace('%1$d', this.config.importedCount)
			.replace('%2$d', this.config.totalItems);

		this.progressBar.updateDetails(detailsText);
	}

	// Complete the import process
	completeImport() {
		this.config.isRunning = false;

		if (this.progressBar) {
			this.progressBar.updateStatus(aspirecloud_ajax.strings.complete);
			this.updateProgress(); // Final progress update
			this.progressBar.setComplete();
		}

		// Re-enable button
		this.enableImportButton();

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
		this.enableImportButton();

		// Log error to console
		console.error('AspireCloud Import Error:', errorMessage);
	}

	// Show errors in a collapsible section
	showErrors() {
		if (this.config.errors.length === 0) return;

		let errorHtml = '<div class="aspirecloud-import-errors" style="margin-top: 20px;">';
		errorHtml += '<h4>Import Warnings (' + this.config.errors.length + ')</h4>';
		errorHtml += '<div class="aspirecloud-error-list" style="max-height: 200px; overflow-y: auto; background: #fff; padding: 10px; border: 1px solid #ddd; border-radius: 4px;">';

		this.config.errors.forEach(function(error) {
			errorHtml += '<div style="margin-bottom: 5px; font-size: 12px; color: #666;">' + error + '</div>';
		});

		errorHtml += '</div></div>';

		jQuery('.aspirecloud-progress-container').append(errorHtml);
	}

	// Disable import button
	disableImportButton() {
		jQuery('#import-' + this.config.assetType + '-btn').prop('disabled', true);
	}

	// Enable import button
	enableImportButton() {
		jQuery('#import-' + this.config.assetType + '-btn').prop('disabled', false);
	}

	// Reset configuration
	resetConfig() {
		this.config.currentBatch = 1;
		this.config.totalBatches = 0;
		this.config.totalItems = 0;
		this.config.importedCount = 0;
		this.config.isRunning = true;
		this.config.errors = [];
		this.config.assetSlugs = [];
		// Reset slug fetching state
		this.config.slugFetchPage = 1;
		this.config.totalSlugPages = 0;
		this.config.slugFetchComplete = false;

		// Reset UI
		if (this.progressBar) {
			this.progressBar.reset();
		}
	}

	// Get current configuration (for debugging)
	getConfig() {
		return this.config;
	}

	// Check if import is running
	isRunning() {
		return this.config.isRunning;
	}
}

// Export to global scope
window.ImportAssets = ImportAssets;
