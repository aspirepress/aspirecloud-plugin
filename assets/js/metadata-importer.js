/**
 * MetadataImporter Class
 *
 * Handles Phase 1 of the import process: Import metadata in configurable batches
 * with adaptive performance monitoring, parallel processing capabilities, and
 * robust error handling with automatic retry logic.
 *
 * Features:
 * - Parallel batch processing with adaptive performance monitoring
 * - Automatic retry mechanism (up to 3 attempts per failed batch)
 * - Individual batch failure does not stop the entire import process
 * - Comprehensive logging of all operations, retries, and failures
 * - Progress tracking and statistics reporting
 *
 * Dependencies: PerformanceTracker, Logger classes
 */

class MetadataImporter {
	constructor(parent) {
		this.parent = parent;
		this.progressBar = null;
		this.config = {
			batch: 1,
			totalBatches: 0,
			totalAssets: 0, // Store total assets count
			batchSize: aspirecloud_ajax.metadata_batch_size || 25,
			restTime: 10000, // 10 seconds
			importedCount: 0,
			parallelBatches: 5,
			activeBatches: 0
		};

		// Retry configuration
		this.retryConfig = {
			maxRetries: 3,
			retryDelay: 2000, // 2 seconds delay between retries
			retryMap: new Map() // Track retry attempts per batch
		};

		// Initialize performance tracker
		this.performanceTracker = new PerformanceTracker({
			maxParallelBatches: this.config.parallelBatches,
			logger: this.parent.logger,
			context: 'metadata'
		});
	}

	setProgressBar(progressBar) {
		this.progressBar = progressBar;
	}

	reset() {
		this.config.batch = 1;
		this.config.totalBatches = 0;
		this.config.totalAssets = 0;
		this.config.importedCount = 0;
		this.config.activeBatches = 0;

		// Reset retry tracking
		this.retryConfig.retryMap.clear();

		// Reset performance tracking
		this.performanceTracker.reset();
		this.config.parallelBatches = this.performanceTracker.getMaxParallelBatches();
	}

	getConfig() {
		return this.config;
	}

	getImportedCount() {
		return this.config.importedCount;
	}

	getTotalAssetsCount() {
		return this.config.totalAssets;
	}

	/**
	 * Get retry statistics for monitoring
	 * @returns {Object} Retry statistics
	 */
	getRetryStatistics() {
		return {
			activeRetries: this.retryConfig.retryMap.size,
			retryMap: new Map(this.retryConfig.retryMap), // Return a copy
			maxRetries: this.retryConfig.maxRetries
		};
	}

	start() {
		const self = this;

		this.parent.logger.log('INFO', 'Getting total asset count for metadata import');

		if (this.progressBar) {
			this.progressBar.show();
			this.progressBar.updateStatus(aspirecloud_ajax.strings.getting_total_count || 'Getting total count...');
		}

		// First get the total count
		this.parent.getTotalAssetsCount()
			.done(function (response) {
				if (response.success) {
					self.config.totalAssets = response.data.total;
					self.config.totalBatches = Math.ceil(response.data.total / self.config.batchSize);

					self.parent.logger.log('SUCCESS', `Total asset count retrieved: ${response.data.total} items`,
						`Will process in ${self.config.totalBatches} batches of ${self.config.batchSize} items each`);

					if (self.progressBar) {
						self.progressBar.updateStatus(aspirecloud_ajax.strings.starting_metadata_import || 'Starting metadata import...');
					}

					// Start importing metadata
					self.processNextBatch();
				} else {
					self.parent.logger.log('ERROR', 'Failed to get total asset count', response.data || aspirecloud_ajax.strings.error);
					self.parent.handleError(response.data || aspirecloud_ajax.strings.error);
				}
			})
			.fail(function () {
				self.parent.logger.log('ERROR', 'AJAX request failed while getting total asset count');
				self.parent.handleError(aspirecloud_ajax.strings.error);
			});
	}

	processNextBatch() {
		const self = this;

		// Start parallel batches
		while (self.config.activeBatches < self.config.parallelBatches &&
			self.config.batch <= self.config.totalBatches) {

			const currentBatch = self.config.batch;
			self.config.batch++;
			self.config.activeBatches++;

			self.processSingleBatch(currentBatch);
		}

		// Check if all batches are complete
		if (self.config.batch > self.config.totalBatches &&
			self.config.activeBatches === 0) {
			self.complete();
		}
	}

	processSingleBatch(batchNumber) {
		const self = this;
		const startTime = Date.now();

		// Update parallel batch count from performance tracker
		this.config.parallelBatches = this.performanceTracker.getCurrentParallelBatches();

		// Check retry count for this batch
		const retryCount = this.retryConfig.retryMap.get(batchNumber) || 0;
		const retryText = retryCount > 0 ? ` (Retry ${retryCount}/${this.retryConfig.maxRetries})` : '';

		// Update status
		const statusText = (aspirecloud_ajax.strings.importing_metadata_batch || 'Importing metadata batch %1$d of %2$d...')
			.replace('%1$d', batchNumber)
			.replace('%2$d', this.config.totalBatches);

		this.parent.logger.log('INFO', `Processing metadata batch ${batchNumber}/${this.config.totalBatches}${retryText}`,
			`Parallel batches active: ${this.config.activeBatches}, Max: ${this.config.parallelBatches}`);

		if (this.progressBar) {
			this.progressBar.updateStatus(statusText + ` (Processing ${this.config.activeBatches} batches in parallel, Max: ${this.config.parallelBatches})`);
		}

		// Import metadata batch
		this.importBatch(batchNumber)
			.done(function (response) {
				const duration = Date.now() - startTime;
				self.performanceTracker.recordCallDuration(duration);

				if (response.success) {
					// Remove from retry map on success
					self.retryConfig.retryMap.delete(batchNumber);

					self.config.importedCount += response.data.imported_count;

					self.parent.logger.log('SUCCESS', `Metadata batch ${batchNumber} completed in ${duration}ms${retryText}`,
						`Imported: ${response.data.imported_count} items, Total: ${self.config.importedCount}, Avg: ${self.performanceTracker.getAverageDuration().toFixed(0)}ms`);

					// Add errors if any
					if (response.data.errors && response.data.errors.length > 0) {
						self.parent.logger.log('WARNING', `Batch ${batchNumber} completed with ${response.data.errors.length} warnings`);
						self.parent.addError(response.data.errors);
					}

					self.updateProgress();
					self.config.activeBatches--;

					// Check if we should rest (every 50 batches)
					if (batchNumber % 50 === 0) {
						self.parent.logger.log('INFO', `Resting after batch ${batchNumber} (every 50 batches)`);
						if (self.progressBar) {
							self.progressBar.updateStatus(aspirecloud_ajax.strings.resting_metadata || 'Resting before next metadata batch...');
						}
						setTimeout(function () {
							self.processNextBatch();
						}, self.config.restTime);
					} else {
						setTimeout(function () {
							self.processNextBatch();
						}, 100);
					}
				} else {
					self.parent.logger.log('ERROR', `Metadata batch ${batchNumber} failed after ${duration}ms${retryText}`, response.data || aspirecloud_ajax.strings.error);
					self.handleBatchFailure(batchNumber, response.data || aspirecloud_ajax.strings.error);
				}
			})
			.fail(function (jqXHR, textStatus, errorThrown) {
				const duration = Date.now() - startTime;
				self.performanceTracker.recordCallDuration(duration);

				const errorMsg = `AJAX request failed: ${textStatus} - ${errorThrown}`;
				self.parent.logger.log('ERROR', `AJAX request failed for metadata batch ${batchNumber} after ${duration}ms${retryText}`, errorMsg);
				self.handleBatchFailure(batchNumber, errorMsg);
			});
	}

	/**
	 * Handle batch failure with retry logic
	 * @param {number} batchNumber - The batch number that failed
	 * @param {string} errorMessage - The error message
	 */
	handleBatchFailure(batchNumber, errorMessage) {
		const self = this;
		const currentRetries = this.retryConfig.retryMap.get(batchNumber) || 0;

		if (currentRetries < this.retryConfig.maxRetries) {
			// Increment retry count
			this.retryConfig.retryMap.set(batchNumber, currentRetries + 1);

			this.parent.logger.log('WARNING', `Retrying metadata batch ${batchNumber} (attempt ${currentRetries + 1}/${this.retryConfig.maxRetries})`,
				`Will retry in ${this.retryConfig.retryDelay}ms`);

			// Retry after delay
			setTimeout(function() {
				self.processSingleBatch(batchNumber);
			}, this.retryConfig.retryDelay);
		} else {
			// Max retries exceeded, give up on this batch
			this.parent.logger.log('ERROR', `Metadata batch ${batchNumber} failed permanently after ${this.retryConfig.maxRetries} retries`,
				`Error: ${errorMessage}`);

			// Remove from retry map
			this.retryConfig.retryMap.delete(batchNumber);

			// Continue with next batch instead of failing entire import
			this.config.activeBatches--;
			setTimeout(function () {
				self.processNextBatch();
			}, 100);

			// Add error to parent but don't stop the import
			this.parent.addError(`Batch ${batchNumber} failed permanently: ${errorMessage}`);
		}
	}

	importBatch(page) {
		const action = this.parent.config.assetType === 'plugins' ? 'import_plugin_metadata_batch' : 'import_theme_metadata_batch';

		return jQuery.ajax({
			url: aspirecloud_ajax.ajax_url,
			type: 'POST',
			data: {
				action: action,
				page: page,
				per_page: this.config.batchSize,
				nonce: aspirecloud_ajax.nonce
			}
		});
	}

	updateProgress() {
		if (!this.progressBar) return;

		const percentage = Math.round((this.config.batch - 1) / this.config.totalBatches * 50);
		this.progressBar.updateProgress(percentage);

		// Use the actual total assets count
		const itemsText = aspirecloud_ajax.strings.metadata_imported || 'Metadata imported: %1$d of %2$d';
		const detailsText = itemsText
			.replace('%1$d', this.config.importedCount)
			.replace('%2$d', this.config.totalAssets);

		this.progressBar.updateDetails(detailsText);
	}

	complete() {
		// Log completion summary with retry statistics
		const retryStats = this.getRetryStatistics();
		const totalRetries = Array.from(retryStats.retryMap.values()).reduce((sum, count) => sum + count, 0);

		this.parent.logger.log('SUCCESS', 'Metadata import phase completed',
			`Total imported: ${this.config.importedCount}, Failed batches: ${this.parent.errors.length}, Total retries: ${totalRetries}`);

		if (retryStats.activeRetries > 0) {
			this.parent.logger.log('WARNING', `${retryStats.activeRetries} batches have retry attempts in progress`);
		}

		// Notify parent that metadata import is complete
		this.parent.onMetadataImportComplete();
	}
}

// Export for use in other modules
if (typeof module !== 'undefined' && module.exports) {
	module.exports = MetadataImporter;
} else if (typeof window !== 'undefined') {
	window.MetadataImporter = MetadataImporter;
}
