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
			
			// Initialize the managers
			this.importAssets = new ImportAssets();
			this.clearAssets = new ClearAssets();
			
			// Set the progress bar for both managers
			this.importAssets.setProgressBar(this.progressBar);
			this.clearAssets.setProgressBar(this.progressBar);
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
			return this.importAssets.isRunning() || this.clearAssets.isRunning();
		}
	}

	// Global instance
	let aspireCloudAdmin = null;

	// Initialize when document is ready
	$(document).ready(function() {
		// Only initialize on import pages
		if ($('.aspirecloud-import-page').length) {
			aspireCloudAdmin = new AspireCloudAdmin();
		}
	});

	// Expose main class and instance to global scope for debugging
	window.AspireCloudAdmin = AspireCloudAdmin;
	
	// Expose the global instance
	window.aspireCloudAdmin = aspireCloudAdmin;

})(jQuery);
