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
			parallelBatches: 3,
			activeBatches: 0
		};

		// Retry configuration
		this.retryConfig = {
			maxRetries: 3,
			retryDelay: 2000, // 2 seconds delay between retries
			retryMap: new Map() // Track retry attempts per batch
		};

		// Loop protection safeguards
		this.loopProtection = {
			maxProcessCalls: 1000,           // Maximum calls to processNextBatch
			processCalls: 0,                 // Current call count
			lastProcessTime: 0,              // Last process timestamp
			stuckDetectionInterval: 30000,   // 30 seconds to detect stuck state
			maxActiveBatchTime: 300000,      // 5 minutes max for any batch
			activeBatchTimes: new Map()      // Track when each batch started
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

		// Check if metadata was imported or if we need to get count differently
		if (this.parent.config.importMetadata || this.parent.metadataImporter.getImportedCount() > 0) {
			// Normal case: metadata was imported, use that count
			const importedCount = parseInt(this.parent.metadataImporter.getImportedCount()) || 0;
			const batchSize = parseInt(this.config.batchSize) || 5;
			this.config.totalBatches = Math.max(1, Math.ceil(importedCount / batchSize));

			this.parent.logger.log('INFO', `Starting file download phase`,
				`${importedCount} files to download in ${this.config.totalBatches} batches`);

			if (this.progressBar) {
				this.progressBar.updateStatus(aspirecloud_ajax.strings.metadata_complete_starting_downloads || 'Metadata import complete. Starting file downloads...');
			}

			// Start downloading files after a short pause
			setTimeout(function () {
				self.processNextBatch();
			}, 2000);
		} else {
			// Files-only mode: get total count from API first
			this.parent.logger.log('INFO', 'Files-only mode: Getting total asset count for download planning');

			if (this.progressBar) {
				this.progressBar.updateStatus('Getting asset count for file downloads...');
			}

			this.parent.getTotalAssetsCount()
				.done(function (response) {
					if (response.success) {
						// Use the total count for batching with validation
						const totalCount = parseInt(response.data.total) || 0;
						const batchSize = parseInt(self.config.batchSize) || 5;
						self.config.totalBatches = Math.max(1, Math.ceil(totalCount / batchSize));

						self.parent.logger.log('SUCCESS', `Total asset count retrieved for file downloads: ${totalCount} items`,
							`Will process in ${self.config.totalBatches} download batches`);

						if (self.progressBar) {
							self.progressBar.updateStatus('Starting file downloads...');
						}

						// Start downloading files
						setTimeout(function () {
							self.processNextBatch();
						}, 2000);
					} else {
						self.parent.logger.log('ERROR', 'Failed to get total asset count for file downloads', response.data || aspirecloud_ajax.strings.error);
						self.parent.handleError(response.data || aspirecloud_ajax.strings.error);
					}
				})
				.fail(function () {
					self.parent.logger.log('ERROR', 'AJAX request failed while getting total asset count for file downloads');
					self.parent.handleError(aspirecloud_ajax.strings.error);
				});
		}
	}

	processNextBatch() {
		const self = this;

		// Loop protection - check for excessive calls
		this.loopProtection.processCalls++;
		const currentTime = Date.now();

		// Reset counter if enough time has passed
		if (currentTime - this.loopProtection.lastProcessTime > this.loopProtection.stuckDetectionInterval) {
			this.loopProtection.processCalls = 1;
		}
		this.loopProtection.lastProcessTime = currentTime;

		// Emergency brake for infinite loops
		if (this.loopProtection.processCalls > this.loopProtection.maxProcessCalls) {
			this.parent.logger.log('ERROR: Too many processNextBatch calls detected - emergency stop', 'error');
			this.handleEmergencyStop('MAX_PROCESS_CALLS_EXCEEDED');
			return;
		}

		// Check for stuck activeBatches
		this.cleanupStuckBatches();

		// Safety check for completion
		if (this.config.batch > this.config.totalBatches) {
			if (this.config.activeBatches === 0) {
				this.complete();
				return;
			} else {
				// Wait a bit longer for active batches to complete
				this.parent.logger.log(`Waiting for ${this.config.activeBatches} active batches to complete...`, 'warning');
				setTimeout(() => {
					this.processNextBatch();
				}, 1000);
				return;
			}
		}

		// Process parallel batches
		while (this.config.activeBatches < this.config.parallelBatches &&
			   this.config.batch <= this.config.totalBatches) {
			this.config.activeBatches++;
			this.processSingleBatch(this.config.batch);
			this.config.batch++;
		}
	}	processSingleBatch(batchNumber) {
		const self = this;
		const startTime = Date.now();

		// Track when this batch started for stuck detection
		this.loopProtection.activeBatchTimes.set(batchNumber, startTime);

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

				// Clean up batch tracking
				self.loopProtection.activeBatchTimes.delete(batchNumber);
				self.config.activeBatches = Math.max(0, self.config.activeBatches - 1);

				if (response.success) {
					// Remove from retry map on success
					self.retryConfig.retryMap.delete(batchNumber);

					// Update counts based on new response structure
					const downloadedCount = response.data.downloaded_count || 0;
					const skippedCount = response.data.skipped_count || 0;
					const processedCount = response.data.processed_count || response.data.downloaded_count || 0;

					self.config.downloadedCount += downloadedCount;

					// Build detailed log message
					let logDetails = `Downloaded: ${downloadedCount} files`;
					if (skippedCount > 0) {
						logDetails += `, Skipped: ${skippedCount} files (same domain)`;
					}

					// Safely handle average duration calculation
					const avgDuration = self.performanceTracker.getAverageDuration();
					const avgDurationText = isNaN(avgDuration) ? '0' : avgDuration.toFixed(0);
					logDetails += `, Total: ${self.config.downloadedCount}, Avg: ${avgDurationText}ms`;

					self.parent.logger.log('SUCCESS', `Download batch ${batchNumber} completed in ${duration}ms${retryText}`, logDetails);

					// Log skipped files if any
					if (skippedCount > 0) {
						self.parent.logger.log('INFO', `Batch ${batchNumber}: ${skippedCount} files skipped due to same domain policy`);
					}

					// Add errors if any
					if (response.data.errors && response.data.errors.length > 0) {
						const errorCount = response.data.errors.length;
						const warningType = errorCount > (downloadedCount + skippedCount) / 2 ? 'ERROR' : 'WARNING';

						self.parent.logger.log(warningType, `Download batch ${batchNumber} completed with ${errorCount} ${warningType.toLowerCase()}s`);
						self.parent.addError(response.data.errors);
					}

					self.updateProgress();

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

				// Clean up batch tracking
				self.loopProtection.activeBatchTimes.delete(batchNumber);
				self.config.activeBatches = Math.max(0, self.config.activeBatches - 1);

				// Categorize the error type for better handling
				const errorCategory = self.categorizeAjaxError(jqXHR, textStatus, errorThrown);
				const errorMsg = `${errorCategory.type}: ${errorCategory.message}`;

				self.parent.logger.log('ERROR', `AJAX request failed for download batch ${batchNumber} after ${duration}ms${retryText}`,
					`${errorMsg} (HTTP ${jqXHR.status})`);

				self.handleBatchFailure(batchNumber, errorMsg, errorCategory);
			});
	}

	/**
	 * Categorize AJAX errors for better handling and reporting
	 * @param {Object} jqXHR - jQuery XHR object
	 * @param {string} textStatus - Error status text
	 * @param {string} errorThrown - Error thrown
	 * @returns {Object} Error category information
	 */
	categorizeAjaxError(jqXHR, textStatus, errorThrown) {
		const httpStatus = jqXHR.status;

		if (httpStatus === 0) {
			return {
				type: 'Network Error',
				message: 'No connection to server (network/CORS issue)',
				retryable: true
			};
		} else if (httpStatus >= 500) {
			return {
				type: 'Server Error',
				message: `Server error ${httpStatus}: ${errorThrown}`,
				retryable: true
			};
		} else if (httpStatus === 404) {
			return {
				type: 'Not Found',
				message: 'Download endpoint not found',
				retryable: false
			};
		} else if (httpStatus === 403) {
			return {
				type: 'Permission Denied',
				message: 'Access forbidden (authentication issue)',
				retryable: false
			};
		} else if (httpStatus === 429) {
			return {
				type: 'Rate Limited',
				message: 'Too many requests - rate limited',
				retryable: true
			};
		} else if (textStatus === 'timeout') {
			return {
				type: 'Timeout',
				message: 'Request timed out',
				retryable: true
			};
		} else if (textStatus === 'abort') {
			return {
				type: 'Aborted',
				message: 'Request was aborted',
				retryable: false
			};
		} else {
			return {
				type: 'Unknown Error',
				message: `${textStatus}: ${errorThrown}`,
				retryable: true
			};
		}
	}

	/**
	 * Handle batch failure with retry logic and error categorization
	 * @param {number} batchNumber - The batch number that failed
	 * @param {string} errorMessage - The error message
	 * @param {Object} errorCategory - Error category information
	 */
	handleBatchFailure(batchNumber, errorMessage, errorCategory = {retryable: true}) {
		const self = this;
		const currentRetries = this.retryConfig.retryMap.get(batchNumber) || 0;

		// Circuit breaker: Check if too many failures are happening
		const recentFailures = Array.from(this.retryConfig.retryMap.values()).reduce((sum, count) => sum + count, 0);
		const maxTotalRetries = this.config.totalBatches * 2; // Allow max 2 retries per batch total

		if (recentFailures > maxTotalRetries) {
			this.parent.logger.log('ERROR', 'Circuit breaker triggered - too many failures across all batches', `${recentFailures} total retries exceed limit of ${maxTotalRetries}`);
			this.handleEmergencyStop('CIRCUIT_BREAKER_TRIGGERED');
			return;
		}

		// Check if error is retryable and we haven't exceeded max retries
		if (errorCategory.retryable && currentRetries < this.retryConfig.maxRetries) {
			// Increment retry count
			this.retryConfig.retryMap.set(batchNumber, currentRetries + 1);

			// Exponential backoff for retries
			const delayMultiplier = Math.pow(2, currentRetries);
			const retryDelay = Math.min(this.retryConfig.retryDelay * delayMultiplier, 30000); // Max 30 seconds

			this.parent.logger.log('WARNING', `Retrying download batch ${batchNumber} (attempt ${currentRetries + 1}/${this.retryConfig.maxRetries})`,
				`Will retry in ${retryDelay}ms - Error: ${errorCategory.type || errorMessage}`);

			// Retry after exponential backoff delay
			setTimeout(function() {
				self.processSingleBatch(batchNumber);
			}, retryDelay);
		} else {
			// Max retries exceeded or non-retryable error
			const reason = !errorCategory.retryable ? 'non-retryable error' : `${this.retryConfig.maxRetries} retries`;
			this.parent.logger.log('ERROR', `Download batch ${batchNumber} failed permanently due to ${reason}`,
				`Error: ${errorMessage}`);

			// Remove from retry map
			this.retryConfig.retryMap.delete(batchNumber);

			// Decrement active batches counter (was missing in retry path)
			this.config.activeBatches = Math.max(0, this.config.activeBatches - 1);

			// Continue with next batch instead of failing entire import
			setTimeout(function () {
				self.processNextBatch();
			}, 250);

			// Add error to parent but don't stop the import
			this.parent.addError(`Download batch ${batchNumber} failed permanently (${reason}): ${errorMessage}`);
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

		// Calculate progress based on which phases are selected
		let startProgress = 50; // Default start at 50% (after metadata)
		let maxProgress = 50;   // Default 50% range for file phase

		if (!this.parent.config.importMetadata && this.parent.config.importFiles) {
			// Only file phase selected, use full 100%
			startProgress = 0;
			maxProgress = 100;
		} else if (this.parent.config.importMetadata && this.parent.config.importFiles) {
			// Both phases selected, files get 50-100%
			startProgress = 50;
			maxProgress = 50;
		}

		// Ensure valid values for progress calculation
		const validBatch = Math.max(1, this.config.batch || 1);
		const validTotalBatches = Math.max(1, this.config.totalBatches || 1);
		const percentage = Math.round(startProgress + (validBatch - 1) / validTotalBatches * maxProgress);
		this.progressBar.updateProgress(percentage);

		// Calculate total files for progress display with proper validation
		let totalFilesToDownload = 0;
		if (this.parent.config.importMetadata && this.parent.metadataImporter) {
			// Use metadata imported count
			const importedCount = this.parent.metadataImporter.getImportedCount();
			totalFilesToDownload = parseInt(importedCount) || 0;
		} else {
			// Files-only mode: estimate from total batches and batch size
			const batches = parseInt(this.config.totalBatches) || 0;
			const batchSize = parseInt(this.config.batchSize) || 5;
			totalFilesToDownload = batches * batchSize;
		}

		// Ensure we have valid numbers
		const validDownloadedCount = parseInt(this.config.downloadedCount) || 0;
		const validTotalFiles = Math.max(1, totalFilesToDownload); // At least 1 to avoid division by zero

		const itemsText = aspirecloud_ajax.strings.files_downloaded || 'Files downloaded: %1$d of %2$d';
		const detailsText = itemsText
			.replace('%1$d', validDownloadedCount)
			.replace('%2$d', validTotalFiles);

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

	/**
	 * Clean up batches that have been active for too long
	 */
	cleanupStuckBatches() {
		const currentTime = Date.now();
		const stuckBatches = [];

		for (const [batchNumber, startTime] of this.loopProtection.activeBatchTimes) {
			if (currentTime - startTime > this.loopProtection.maxActiveBatchTime) {
				stuckBatches.push(batchNumber);
			}
		}

		if (stuckBatches.length > 0) {
			this.parent.logger.log(`Cleaning up ${stuckBatches.length} stuck batches: ${stuckBatches.join(', ')}`, 'warning');

			for (const batchNumber of stuckBatches) {
				this.loopProtection.activeBatchTimes.delete(batchNumber);
				this.config.activeBatches = Math.max(0, this.config.activeBatches - 1);

				// Log the stuck batch
				this.parent.logger.log(`Batch ${batchNumber} was stuck for ${this.loopProtection.maxActiveBatchTime/1000}s - force cleaned`, 'warning');
			}
		}
	}

	/**
	 * Handle emergency stop scenarios
	 */
	handleEmergencyStop(reason) {
		this.parent.logger.log(`Emergency stop triggered: ${reason}`, 'error');

		// Clear all tracking
		this.loopProtection.activeBatchTimes.clear();
		this.config.activeBatches = 0;

		// Add error to parent
		this.parent.addError(`Download process emergency stopped: ${reason}. Please try again or contact support.`);

		// Complete with error state
		this.parent.onPhaseComplete('download', {
			downloaded: this.config.downloadedCount,
			errors: this.parent.errors,
			emergencyStop: true,
			reason: reason
		});
	}
}

// Export for use in other modules
if (typeof module !== 'undefined' && module.exports) {
	module.exports = FileDownloader;
} else if (typeof window !== 'undefined') {
	window.FileDownloader = FileDownloader;
}
