/**
 * Asset Sync JavaScript
 *
 * Handles the client-side functionality for syncing plugins and themes.
 */

(function($) {
	'use strict';

	/**
	 * Sync functionality
	 */
	const AssetResync = {
		init: function() {
			this.bindEvents();
		},

		bindEvents: function() {
			$(document).on('click', '#resync-plugin-btn, #resync-theme-btn', this.handleResyncClick.bind(this));
		},

		handleResyncClick: function(e) {
			e.preventDefault();

			const $button = $(e.currentTarget);
			const postId = $button.data('post-id');
			const slug = $button.data('slug');
			const assetType = $button.data('asset-type');

			if (!postId || !slug || !assetType) {
				this.showStatus('error', aspirecloud_resync.strings.network_error);
				return;
			}

			this.startResync($button, postId, slug, assetType);
		},

		startResync: function($button, postId, slug, assetType) {
			// Disable button and show loading state
			$button.addClass('loading');
			$button.prop('disabled', true);

			// Show progress
			this.showProgress(0, aspirecloud_resync.strings.checking_version);

			// Make AJAX request
			$.ajax({
				url: aspirecloud_resync.ajax_url,
				type: 'POST',
				data: {
					action: 'resync_' + assetType,
					post_id: postId,
					slug: slug,
					nonce: aspirecloud_resync.nonce
				},
				success: this.handleResyncSuccess.bind(this, $button),
				error: this.handleResyncError.bind(this, $button)
			});
		},

		handleResyncSuccess: function($button, response) {
			// Re-enable button
			$button.removeClass('loading');
			$button.prop('disabled', false);

			if (response.success) {
				const data = response.data;

				if (data.action === 'no_update') {
					this.showStatus('info', data.message);
					this.hideProgress();
				} else if (data.action === 'updated') {
					// Update the current version display
					$('#current-version').text(data.latest_version);

					// Show success message
					this.showStatus('success', data.message);
					this.showProgress(100, aspirecloud_resync.strings.update_complete);

					// Auto-hide progress and show refresh message after 3 seconds
					setTimeout(() => {
						this.hideProgress();
						this.showRefreshMessage();
					}, 3000);
				}
			} else {
				this.showStatus('error', response.data || aspirecloud_resync.strings.network_error);
				this.hideProgress();
			}
		},

		handleResyncError: function($button, jqXHR, textStatus, errorThrown) {
			// Re-enable button
			$button.removeClass('loading');
			$button.prop('disabled', false);

			let errorMessage = aspirecloud_resync.strings.network_error;

			if (jqXHR.responseJSON && jqXHR.responseJSON.data) {
				errorMessage = jqXHR.responseJSON.data;
			} else if (errorThrown) {
				errorMessage = errorThrown;
			}

			this.showStatus('error', errorMessage);
			this.hideProgress();
		},

		showStatus: function(type, message) {
			const $container = $('#resync-status');
			const $message = $container.find('.resync-message');

			// Remove existing classes
			$container.removeClass('success error info');

			// Add appropriate class and message
			$container.addClass(type);
			$message.text(message);
			$container.show();
		},

		showRefreshMessage: function() {
			const $container = $('#resync-status');
			const $message = $container.find('.resync-message');

			// Create refresh message with reload link
			const refreshMessage = aspirecloud_resync.strings.refresh_message + ' ';
			const reloadLink = '<a href="#" class="aspirecloud-reload-page" style="text-decoration: underline; font-weight: bold;">' + 
							   aspirecloud_resync.strings.reload_link_text + '</a>';

			// Remove existing classes and add success class
			$container.removeClass('success error info');
			$container.addClass('success');
			
			// Set HTML content instead of text to include the link
			$message.html(refreshMessage + reloadLink);
			$container.show();

			// Bind click event to reload link
			$(document).off('click.aspirecloud-reload').on('click.aspirecloud-reload', '.aspirecloud-reload-page', function(e) {
				e.preventDefault();
				window.location.reload();
			});
		},

		hideStatus: function() {
			$('#resync-status').hide();
		},

		showProgress: function(percentage, text) {
			const $container = $('#resync-progress');
			const $fill = $container.find('.progress-fill');
			const $text = $container.find('.progress-text');

			$fill.css('width', percentage + '%');
			$text.text(text);
			$container.show();
		},

		hideProgress: function() {
			$('#resync-progress').hide();
		}
	};

	// Initialize when document is ready
	$(document).ready(function() {
		AssetResync.init();
	});

})(jQuery);
