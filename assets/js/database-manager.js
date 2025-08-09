/**
 * DatabaseManager Class
 *
 * Handles database optimization and recovery operations for AspireCloud imports.
 * Manages bulk import mode state and provides database restoration capabilities.
 *
 * Dependencies: Logger class
 */

class DatabaseManager {
	constructor(parent) {
		this.parent = parent;
	}

	handleRecovery() {
		const self = this;

		if (!confirm(aspirecloud_ajax.strings.confirm_restore)) {
			this.parent.logger.log('INFO', 'Database recovery cancelled by user');
			return;
		}

		this.parent.logger.log('INFO', 'Starting database recovery operation');

		const $button = jQuery(this.parent.selectors.restoreButton);
		const originalText = $button.text();

		$button.prop('disabled', true).text(aspirecloud_ajax.strings.restoring_database);

		jQuery.ajax({
			url: aspirecloud_ajax.ajax_url,
			type: 'POST',
			dataType: 'json',
			data: {
				action: 'aspirecloud_restore_database',
				nonce: aspirecloud_ajax.nonce
			},
			success: function (response) {
				if (response.success) {
					self.parent.logger.log('SUCCESS', 'Database recovery completed successfully');
					$button.hide();
					alert(aspirecloud_ajax.strings.database_restored);
				} else {
					self.parent.logger.log('ERROR', 'Database recovery failed', response.data?.message || response.data || 'Unknown error');
					alert('Error: ' + (response.data?.message || response.data || 'Unknown error'));
					$button.prop('disabled', false).text(originalText);
				}
			},
			error: function (xhr, status, error) {
				self.parent.logger.log('ERROR', 'Database recovery AJAX request failed', `Status: ${status}, Error: ${error}`);

				console.error('AJAX Error Details:', {
					status: status,
					error: error,
					responseText: xhr.responseText,
					statusCode: xhr.status
				});

				let errorMessage = 'AJAX Error: ' + error;
				if (xhr.responseText) {
					errorMessage += '\\n\\nResponse: ' + xhr.responseText.substring(0, 500);
				}

				alert(errorMessage);
				$button.prop('disabled', false).text(originalText);
			}
		});
	}

	checkOptimizationState() {
		const self = this;

		this.parent.logger.log('DEBUG', 'Checking database Bulk Import Mode');

		jQuery.ajax({
			url: aspirecloud_ajax.ajax_url,
			type: 'POST',
			dataType: 'json',
			data: {
				action: 'aspirecloud_check_db_optimization',
				nonce: aspirecloud_ajax.nonce
			},
			success: function (response) {
				if (response.success && response.data.optimized) {
					self.parent.logger.log('INFO', 'Database is in Bulk Import Mode - showing restore button');
					jQuery(self.parent.selectors.restoreButton).show();
				} else {
					self.parent.logger.log('DEBUG', 'Database is not in Bulk Import Mode - hiding restore button');
					jQuery(self.parent.selectors.restoreButton).hide();
				}
			},
			error: function (xhr, status, error) {
				self.parent.logger.log('WARNING', 'Failed to check database Bulk Import Mode', `Status: ${status}, Error: ${error}`);

				console.error('Database Bulk Import Mode check error:', {
					status: status,
					error: error,
					responseText: xhr.responseText,
					statusCode: xhr.status
				});

				// On error, assume not optimized and hide button
				jQuery(self.parent.selectors.restoreButton).hide();
			}
		});
	}
}

// Export for use in other modules
if (typeof module !== 'undefined' && module.exports) {
	module.exports = DatabaseManager;
} else if (typeof window !== 'undefined') {
	window.DatabaseManager = DatabaseManager;
}
