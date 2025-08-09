/**
 * FileDownloader Class
 *
 * Handles Phase 2 of the import process: Download files in parallel batches
 * with adaptive performance monitoring, robust error handling, and automatic
 * retry logic for optimal throughput and reliability.
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

class FileDownloader {
	constructor(parent) {
		this.parent = parent;
		this.progressBar = null;
		this.config = {
			batch: 1,
			totalBatches: 0,
			batchSize: aspirecloud_ajax.download_batch_size || 5,
			downloadedCount: 0,
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
			context: 'download'
		});
	}

	setProgressBar(progressBar) {
		this.progressBar = progressBar;
	}

	reset() {
		this.config.batch = 1;
		this.config.totalBatches = 0;
		this.config.downloadedCount = 0;
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

	getDownloadedCount() {
		return this.config.downloadedCount;
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

		// Calculate total download batches
		this.config.totalBatches = Math.ceil(this.parent.metadataImporter.getImportedCount() / this.config.batchSize);

		this.parent.logger.log('INFO', `Starting file download phase`,
			`${this.parent.metadataImporter.getImportedCount()} files to download in ${this.config.totalBatches} batches`);

		if (this.progressBar) {
			this.progressBar.updateStatus(aspirecloud_ajax.strings.metadata_complete_starting_downloads || 'Metadata import complete. Starting file downloads...');
		}

		// Start downloading files after a short pause
		setTimeout(function () {
			self.processNextBatch();
		}, 2000);
	}

	processNextBatch() {
		const self = this;

		// Start parallel download batches
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
		const statusText = (aspirecloud_ajax.strings.downloading_files_batch || 'Downloading files batch %1$d of %2$d...')
			.replace('%1$d', batchNumber)
			.replace('%2$d', this.config.totalBatches);

		this.parent.logger.log('INFO', `Processing download batch ${batchNumber}/${this.config.totalBatches}${retryText}`,
			`Parallel batches active: ${this.config.activeBatches}, Max: ${this.config.parallelBatches}`);

		if (this.progressBar) {
			this.progressBar.updateStatus(statusText + ` (Processing ${this.config.activeBatches} batches in parallel, Max: ${this.config.parallelBatches})`);
		}

		// Download batch
		this.downloadBatch(batchNumber)
			.done(function (response) {
				const duration = Date.now() - startTime;
				self.performanceTracker.recordCallDuration(duration);

				if (response.success) {
					// Remove from retry map on success
					self.retryConfig.retryMap.delete(batchNumber);

					self.config.downloadedCount += response.data.processed_count;

					self.parent.logger.log('SUCCESS', `Download batch ${batchNumber} completed in ${duration}ms${retryText}`,
						`Downloaded: ${response.data.processed_count} files, Total: ${self.config.downloadedCount}, Avg: ${self.performanceTracker.getAverageDuration().toFixed(0)}ms`);

					// Add errors if any
					if (response.data.errors && response.data.errors.length > 0) {
						self.parent.logger.log('WARNING', `Download batch ${batchNumber} completed with ${response.data.errors.length} warnings`);
						self.parent.addError(response.data.errors);
					}

					self.updateProgress();
					self.config.activeBatches--;

					// Continue with smaller delay for downloads
					setTimeout(function () {
						self.processNextBatch();
					}, 250);
				} else {
					self.parent.logger.log('ERROR', `Download batch ${batchNumber} failed after ${duration}ms${retryText}`, response.data || aspirecloud_ajax.strings.error);
					self.handleBatchFailure(batchNumber, response.data || aspirecloud_ajax.strings.error);
				}
			})
			.fail(function (jqXHR, textStatus, errorThrown) {
				const duration = Date.now() - startTime;
				self.performanceTracker.recordCallDuration(duration);

				const errorMsg = `AJAX request failed: ${textStatus} - ${errorThrown}`;
				self.parent.logger.log('ERROR', `AJAX request failed for download batch ${batchNumber} after ${duration}ms${retryText}`, errorMsg);
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

			this.parent.logger.log('WARNING', `Retrying download batch ${batchNumber} (attempt ${currentRetries + 1}/${this.retryConfig.maxRetries})`,
				`Will retry in ${this.retryConfig.retryDelay}ms`);

			// Retry after delay
			setTimeout(function() {
				self.processSingleBatch(batchNumber);
			}, this.retryConfig.retryDelay);
		} else {
			// Max retries exceeded, give up on this batch
			this.parent.logger.log('ERROR', `Download batch ${batchNumber} failed permanently after ${this.retryConfig.maxRetries} retries`,
				`Error: ${errorMessage}`);

			// Remove from retry map
			this.retryConfig.retryMap.delete(batchNumber);

			// Continue with next batch instead of failing entire import
			this.config.activeBatches--;
			setTimeout(function () {
				self.processNextBatch();
			}, 250);

			// Add error to parent but don't stop the import
			this.parent.addError(`Download batch ${batchNumber} failed permanently: ${errorMessage}`);
		}
	}

	downloadBatch(batch) {
		const action = this.parent.config.assetType === 'plugins' ? 'download_plugin_assets_batch' : 'download_theme_assets_batch';

		return jQuery.ajax({
			url: aspirecloud_ajax.ajax_url,
			type: 'POST',
			data: {
				action: action,
				batch: batch,
				per_batch: this.config.batchSize,
				nonce: aspirecloud_ajax.nonce
			}
		});
	}

	updateProgress() {
		if (!this.progressBar) return;

		const percentage = Math.round(50 + (this.config.batch - 1) / this.config.totalBatches * 50);
		this.progressBar.updateProgress(percentage);

		// Use the imported count as the total for file downloads
		const totalFilesToDownload = this.parent.metadataImporter.getImportedCount();
		const itemsText = aspirecloud_ajax.strings.files_downloaded || 'Files downloaded: %1$d of %2$d';
		const detailsText = itemsText
			.replace('%1$d', this.config.downloadedCount)
			.replace('%2$d', totalFilesToDownload);

		this.progressBar.updateDetails(detailsText);
	}

	complete() {
		// Log completion summary with retry statistics
		const retryStats = this.getRetryStatistics();
		const totalRetries = Array.from(retryStats.retryMap.values()).reduce((sum, count) => sum + count, 0);

		this.parent.logger.log('SUCCESS', 'File download phase completed',
			`Total downloaded: ${this.config.downloadedCount}, Failed batches: ${this.parent.errors.length}, Total retries: ${totalRetries}`);

		if (retryStats.activeRetries > 0) {
			this.parent.logger.log('WARNING', `${retryStats.activeRetries} batches have retry attempts in progress`);
		}

		// Notify parent that file download is complete
		this.parent.onFileDownloadComplete();
	}
}

// Export for use in other modules
if (typeof module !== 'undefined' && module.exports) {
	module.exports = FileDownloader;
} else if (typeof window !== 'undefined') {
	window.FileDownloader = FileDownloader;
}
