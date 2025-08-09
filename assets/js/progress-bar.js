/**
 * AspireCloud Progress Bar
 * Handles progress bar display and updates
 */

class ProgressBar {
	constructor(containerSelector) {
		this.containerSelector = containerSelector || '.aspirecloud-progress-container';
		this.container = jQuery(this.containerSelector);

		// Element selectors
		this.selectors = {
			container: this.containerSelector,
			progressBar: '.aspirecloud-progress-bar',
			progressFill: '.aspirecloud-progress-fill',
			status: '#progress-status',
			details: '#progress-details',
			errors: '.aspirecloud-import-errors',
			completeClass: 'aspirecloud-import-complete',
			errorClass: 'aspirecloud-import-error'
		};

		// Ensure we have the basic structure
		this.initializeStructure();
	}

	// Initialize the basic progress bar structure if it doesn't exist
	initializeStructure() {
		if (this.container.length === 0) {
			// Create container if it doesn't exist
			jQuery('body').append('<div class="' + this.containerSelector.replace('.', '') + '"></div>');
			this.container = jQuery(this.containerSelector);
		}

		// Ensure we have the required elements
		if (this.container.find(this.selectors.progressFill).length === 0) {
			const progressHtml = `
				<div class="aspirecloud-progress-bar">
					<div class="aspirecloud-progress-fill"></div>
				</div>
				<div id="progress-status" class="progress-status"></div>
				<div id="progress-details" class="progress-details"></div>
			`;
			this.container.html(progressHtml);
		}
	}

	// Show the progress container
	show() {
		this.container.show();
		return this;
	}

	// Hide the progress container
	hide() {
		this.container.hide();
		return this;
	}

	// Update progress percentage (0-100)
	updateProgress(percentage) {
		percentage = Math.max(0, Math.min(100, percentage)); // Clamp between 0 and 100
		this.container.find(this.selectors.progressFill).css('width', percentage + '%');
		return this;
	}

	// Update status text
	updateStatus(statusText) {
		this.container.find(this.selectors.status).text(statusText);
		return this;
	}

	// Update details text
	updateDetails(detailsText) {
		this.container.find(this.selectors.details).text(detailsText);
		return this;
	}

	// Set progress bar to complete state
	setComplete() {
		this.container
			.removeClass(this.selectors.errorClass)
			.addClass(this.selectors.completeClass);
		return this;
	}

	// Set progress bar to error state
	setError() {
		this.container
			.removeClass(this.selectors.completeClass)
			.addClass(this.selectors.errorClass);
		return this;
	}

	// Reset the progress bar to initial state
	reset() {
		this.container
			.removeClass(`${this.selectors.completeClass} ${this.selectors.errorClass}`)
			.find(this.selectors.errors).remove();

		this.updateProgress(0);
		this.updateStatus('');
		this.updateDetails('');
		return this;
	}

	// Get current progress percentage
	getProgress() {
		const width = this.container.find(this.selectors.progressFill).css('width');
		const containerWidth = this.container.find(this.selectors.progressFill).parent().width();
		return containerWidth > 0 ? Math.round((parseFloat(width) / containerWidth) * 100) : 0;
	}

	// Get current status text
	getStatus() {
		return this.container.find(this.selectors.status).text();
	}

	// Get current details text
	getDetails() {
		return this.container.find(this.selectors.details).text();
	}

	// Check if progress bar is in complete state
	isComplete() {
		return this.container.hasClass(this.selectors.completeClass);
	}

	// Check if progress bar is in error state
	isError() {
		return this.container.hasClass(this.selectors.errorClass);
	}

	// Check if progress bar is visible
	isVisible() {
		return this.container.is(':visible');
	}

	// Add custom HTML content to the progress container
	addContent(html) {
		this.container.append(html);
		return this;
	}

	// Remove custom content from the progress container
	removeContent(selector) {
		this.container.find(selector).remove();
		return this;
	}

	// Set custom CSS styles
	setStyles(styles) {
		if (styles.container) {
			this.container.css(styles.container);
		}
		if (styles.progressBar) {
			this.container.find(this.selectors.progressBar).css(styles.progressBar);
		}
		if (styles.progressFill) {
			this.container.find(this.selectors.progressFill).css(styles.progressFill);
		}
		if (styles.status) {
			this.container.find(this.selectors.status).css(styles.status);
		}
		if (styles.details) {
			this.container.find(this.selectors.details).css(styles.details);
		}
		return this;
	}

	// Get the container element
	getContainer() {
		return this.container;
	}

	// Animate progress to a specific percentage
	animateProgress(targetPercentage, duration) {
		duration = duration || 500; // Default 500ms
		const currentPercentage = this.getProgress();
		const progressFill = this.container.find(this.selectors.progressFill);

		// Use jQuery animate
		jQuery({percentage: currentPercentage}).animate(
			{percentage: targetPercentage},
			{
				duration: duration,
				step: function(now) {
					progressFill.css('width', now + '%');
				}
			}
		);
		return this;
	}

	// Set progress with animation
	setProgressAnimated(percentage, duration) {
		this.animateProgress(percentage, duration);
		return this;
	}

	// Pulse animation for the progress bar
	pulse(duration, iterations) {
		duration = duration || 1000;
		iterations = iterations || 3;

		const progressFill = this.container.find(this.selectors.progressFill);
		let count = 0;

		const doPulse = () => {
			if (count < iterations) {
				progressFill.fadeOut(duration / 2).fadeIn(duration / 2, doPulse);
				count++;
			}
		};

		doPulse();
		return this;
	}

	// Destroy the progress bar (remove from DOM)
	destroy() {
		this.container.remove();
	}
}

// Export to global scope
window.ProgressBar = ProgressBar;
