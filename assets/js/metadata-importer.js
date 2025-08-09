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

		// Loop protection safeguards
		this.loopProtection = {
			maxProcessCalls: 500,           // Maximum calls to processNextBatch
			processCalls: 0,                // Current call count
			lastProcessTime: 0,             // Last process timestamp
			stuckDetectionInterval: 30000,  // 30 seconds to detect stuck state
			maxBatchTime: 180000,           // 3 minutes max for any batch
			activeBatchTimes: new Map()     // Track when each batch started
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

		// Check for stuck batches
		this.cleanupStuckBatches();

		// Start parallel batches
		while (self.config.activeBatches < self.config.parallelBatches &&
			self.config.batch <= self.config.totalBatches) {

			const currentBatch = self.config.batch;
			self.config.batch++;
			self.config.activeBatches++;

			self.processSingleBatch(currentBatch);
		}

		// Check if all batches are complete
		if (self.config.batch > self.config.totalBatches) {
			if (self.config.activeBatches === 0) {
				self.complete();
			} else {
				// Wait a bit longer for active batches to complete
				this.parent.logger.log(`Waiting for ${self.config.activeBatches} active metadata batches to complete...`, 'warning');
				setTimeout(() => {
					self.processNextBatch();
				}, 1000);
			}
		}
	}

	processSingleBatch(batchNumber) {
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

				// Clean up batch tracking
				self.loopProtection.activeBatchTimes.delete(batchNumber);
				self.config.activeBatches = Math.max(0, self.config.activeBatches - 1);

				if (response.success) {
					// Remove from retry map on success
					self.retryConfig.retryMap.delete(batchNumber);

					self.config.importedCount += response.data.imported_count;

					// Safely handle average duration calculation
					const avgDuration = self.performanceTracker.getAverageDuration();
					const avgDurationText = isNaN(avgDuration) ? '0' : avgDuration.toFixed(0);

					self.parent.logger.log('SUCCESS', `Metadata batch ${batchNumber} completed in ${duration}ms${retryText}`,
						`Imported: ${response.data.imported_count} items, Total: ${self.config.importedCount}, Avg: ${avgDurationText}ms`);

					// Add errors if any
					if (response.data.errors && response.data.errors.length > 0) {
						self.parent.logger.log('WARNING', `Batch ${batchNumber} completed with ${response.data.errors.length} warnings`);
						self.parent.addError(response.data.errors);
					}

					self.updateProgress();

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

				// Clean up batch tracking
				self.loopProtection.activeBatchTimes.delete(batchNumber);
				self.config.activeBatches = Math.max(0, self.config.activeBatches - 1);

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

		// Circuit breaker: Check if too many failures are happening
		const recentFailures = Array.from(this.retryConfig.retryMap.values()).reduce((sum, count) => sum + count, 0);
		const maxTotalRetries = this.config.totalBatches * 2; // Allow max 2 retries per batch total

		if (recentFailures > maxTotalRetries) {
			this.parent.logger.log('ERROR', 'Circuit breaker triggered - too many metadata failures across all batches', `${recentFailures} total retries exceed limit of ${maxTotalRetries}`);
			this.handleEmergencyStop('CIRCUIT_BREAKER_TRIGGERED');
			return;
		}

		if (currentRetries < this.retryConfig.maxRetries) {
			// Increment retry count
			this.retryConfig.retryMap.set(batchNumber, currentRetries + 1);

			// Exponential backoff for retries
			const delayMultiplier = Math.pow(2, currentRetries);
			const retryDelay = Math.min(this.retryConfig.retryDelay * delayMultiplier, 20000); // Max 20 seconds

			this.parent.logger.log('WARNING', `Retrying metadata batch ${batchNumber} (attempt ${currentRetries + 1}/${this.retryConfig.maxRetries})`,
				`Will retry in ${retryDelay}ms`);

			// Retry after exponential backoff delay
			setTimeout(function() {
				self.processSingleBatch(batchNumber);
			}, retryDelay);
		} else {
			// Max retries exceeded, give up on this batch
			this.parent.logger.log('ERROR', `Metadata batch ${batchNumber} failed permanently after ${this.retryConfig.maxRetries} retries`,
				`Error: ${errorMessage}`);

			// Remove from retry map
			this.retryConfig.retryMap.delete(batchNumber);

			// Decrement active batches counter (was missing in retry path)
			this.config.activeBatches = Math.max(0, this.config.activeBatches - 1);

			// Continue with next batch instead of failing entire import
			setTimeout(function () {
				self.processNextBatch();
			}, 100);

			// Add error to parent but don't stop the import
			this.parent.addError(`Metadata batch ${batchNumber} failed permanently: ${errorMessage}`);
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

		// Calculate progress based on which phases are selected
		let maxProgress = 50; // Default 50% for metadata phase
		if (this.parent.config.importMetadata && !this.parent.config.importFiles) {
			// Only metadata phase selected, use full 100%
			maxProgress = 100;
		} else if (this.parent.config.importMetadata && this.parent.config.importFiles) {
			// Both phases selected, metadata gets 50%
			maxProgress = 50;
		}

		const percentage = Math.round((this.config.batch - 1) / this.config.totalBatches * maxProgress);
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

	/**
	 * Clean up batches that have been active for too long
	 */
	cleanupStuckBatches() {
		const currentTime = Date.now();
		const stuckBatches = [];

		for (const [batchNumber, startTime] of this.loopProtection.activeBatchTimes) {
			if (currentTime - startTime > this.loopProtection.maxBatchTime) {
				stuckBatches.push(batchNumber);
			}
		}

		if (stuckBatches.length > 0) {
			this.parent.logger.log(`Cleaning up ${stuckBatches.length} stuck metadata batches: ${stuckBatches.join(', ')}`, 'warning');

			for (const batchNumber of stuckBatches) {
				this.loopProtection.activeBatchTimes.delete(batchNumber);
				this.config.activeBatches = Math.max(0, this.config.activeBatches - 1);

				// Log the stuck batch
				this.parent.logger.log(`Metadata batch ${batchNumber} was stuck for ${this.loopProtection.maxBatchTime/1000}s - force cleaned`, 'warning');
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
		this.parent.addError(`Metadata import process emergency stopped: ${reason}. Please try again or contact support.`);

		// Complete with error state
		this.parent.onPhaseComplete('metadata', {
			imported: this.config.importedCount,
			errors: this.parent.errors,
			emergencyStop: true,
			reason: reason
		});
	}
}

// Export for use in other modules
if (typeof module !== 'undefined' && module.exports) {
	module.exports = MetadataImporter;
} else if (typeof window !== 'undefined') {
	window.MetadataImporter = MetadataImporter;
}
