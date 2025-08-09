/**
 * PerformanceTracker Class
 *
 * Provides adaptive performance monitoring for AJAX batch operations.
 * Automatically adjusts parallel batch counts based on call duration metrics
 * to maintain optimal performance under varying server load conditions.
 */

class PerformanceTracker {
	/**
	 * Initialize the performance tracker
	 *
	 * @param {Object} options Configuration options
	 * @param {number} options.maxParallelBatches Maximum number of parallel batches allowed
	 * @param {number} options.baselineCallCount Number of initial calls to establish baseline (default: 5)
	 * @param {number} options.adjustmentThreshold Threshold for triggering batch count changes (default: 0.5 = 50%)
	 * @param {number} options.adjustmentCooldown Cooldown period between adjustments in milliseconds (default: 10000)
	 * @param {number} options.minParallelBatches Minimum number of parallel batches (default: 1)
	 * @param {Logger} options.logger Logger instance for recording performance events
	 * @param {string} options.context Context identifier for logging (e.g., 'metadata', 'download')
	 */
	constructor(options = {}) {
		this.maxParallelBatches = options.maxParallelBatches || 3;
		this.currentParallelBatches = this.maxParallelBatches;
		this.logger = options.logger || null;
		this.context = options.context || 'batch';

		// Performance tracking configuration
		this.config = {
			baselineCallCount: options.baselineCallCount || 10,
			adjustmentThreshold: options.adjustmentThreshold || 0.3,
			adjustmentCooldown: options.adjustmentCooldown || 10000,
			minParallelBatches: options.minParallelBatches || 1
		};

		// Performance metrics
		this.metrics = {
			durations: [],
			averageDuration: 0,
			baseline: null,
			lastAdjustmentTime: 0
		};
	}

	/**
	 * Reset all performance metrics and restore maximum parallel batches
	 */
	reset() {
		this.metrics.durations = [];
		this.metrics.averageDuration = 0;
		this.metrics.baseline = null;
		this.metrics.lastAdjustmentTime = 0;
		this.currentParallelBatches = this.maxParallelBatches;

		if (this.logger) {
			this.logger.log('INFO', `${this.context} performance tracker reset`,
				`Max parallel batches restored to ${this.maxParallelBatches}`);
		}
	}

	/**
	 * Record the duration of a completed AJAX call
	 *
	 * @param {number} duration Duration in milliseconds
	 */
	recordCallDuration(duration) {
		this.metrics.durations.push(duration);
		this.metrics.averageDuration = this.metrics.durations.reduce((a, b) => a + b, 0) / this.metrics.durations.length;

		// Establish baseline after initial calls
		if (this.metrics.durations.length === this.config.baselineCallCount && !this.metrics.baseline) {
			this.metrics.baseline = this.metrics.averageDuration;

			if (this.logger) {
				this.logger.log('INFO', `${this.context} performance baseline established: ${this.metrics.baseline.toFixed(0)}ms`);
			}
		}

		// Only check for adjustments after baseline is established
		if (this.metrics.baseline && this.metrics.durations.length > this.config.baselineCallCount) {
			this.adjustParallelBatchCount();
		}
	}

	/**
	 * Adjust parallel batch count based on performance metrics
	 *
	 * @returns {boolean} True if adjustment was made, false otherwise
	 */
	adjustParallelBatchCount() {
		const now = Date.now();

		// Don't adjust too frequently
		if (now - this.metrics.lastAdjustmentTime < this.config.adjustmentCooldown) {
			return false;
		}

		const currentAverage = this.metrics.averageDuration;
		const baseline = this.metrics.baseline;
		const deviation = (currentAverage - baseline) / baseline;
		const threshold = this.config.adjustmentThreshold;

		// If performance is significantly worse than baseline, reduce parallel batches
		if (deviation > threshold && this.currentParallelBatches > this.config.minParallelBatches) {
			this.currentParallelBatches = Math.max(this.config.minParallelBatches, this.currentParallelBatches - 1);
			this.metrics.lastAdjustmentTime = now;

			if (this.logger) {
				this.logger.log('WARNING',
					`${this.context} performance degraded (${(deviation * 100).toFixed(1)}%), reducing parallel batches to ${this.currentParallelBatches}`,
					`Average: ${currentAverage.toFixed(0)}ms, Baseline: ${baseline.toFixed(0)}ms`);
			}
			return true;
		}
		// If performance is significantly better than baseline and we're below max, increase batches
		else if (deviation < -threshold && this.currentParallelBatches < this.maxParallelBatches) {
			this.currentParallelBatches = Math.min(this.maxParallelBatches, this.currentParallelBatches + 1);
			this.metrics.lastAdjustmentTime = now;

			if (this.logger) {
				this.logger.log('SUCCESS',
					`${this.context} performance improved (${(Math.abs(deviation) * 100).toFixed(1)}%), increasing parallel batches to ${this.currentParallelBatches}`,
					`Average: ${currentAverage.toFixed(0)}ms, Baseline: ${baseline.toFixed(0)}ms`);
			}
			return true;
		}

		return false;
	}

	/**
	 * Get current parallel batch count
	 *
	 * @returns {number} Current number of parallel batches
	 */
	getCurrentParallelBatches() {
		return this.currentParallelBatches;
	}

	/**
	 * Get maximum parallel batch count
	 *
	 * @returns {number} Maximum number of parallel batches
	 */
	getMaxParallelBatches() {
		return this.maxParallelBatches;
	}

	/**
	 * Get current average duration
	 *
	 * @returns {number} Average duration in milliseconds
	 */
	getAverageDuration() {
		return this.metrics.averageDuration;
	}

	/**
	 * Get baseline duration
	 *
	 * @returns {number|null} Baseline duration in milliseconds, or null if not established
	 */
	getBaseline() {
		return this.metrics.baseline;
	}

	/**
	 * Check if baseline has been established
	 *
	 * @returns {boolean} True if baseline is established
	 */
	hasBaseline() {
		return this.metrics.baseline !== null;
	}

	/**
	 * Get performance statistics
	 *
	 * @returns {Object} Performance statistics object
	 */
	getStats() {
		return {
			callCount: this.metrics.durations.length,
			averageDuration: this.metrics.averageDuration,
			baseline: this.metrics.baseline,
			currentParallelBatches: this.currentParallelBatches,
			maxParallelBatches: this.maxParallelBatches,
			hasBaseline: this.hasBaseline()
		};
	}

	/**
	 * Set the logger instance
	 *
	 * @param {Logger} logger Logger instance
	 */
	setLogger(logger) {
		this.logger = logger;
	}

	/**
	 * Set the context identifier for logging
	 *
	 * @param {string} context Context identifier
	 */
	setContext(context) {
		this.context = context;
	}
}

// Export for use in other modules
if (typeof module !== 'undefined' && module.exports) {
	module.exports = PerformanceTracker;
} else if (typeof window !== 'undefined') {
	window.PerformanceTracker = PerformanceTracker;
}
