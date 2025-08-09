/**
 * Asset Sync JavaScript
 *
 * Handles the client-side functionality for syncing plugins and themes.
 */

(function(jQuery) {
	'use strict';

	/**
	 * Sync functionality
	 */
	const AssetResync = {
		// Element selectors
		selectors: {
			resyncButtons: '#resync-plugin-btn, #resync-theme-btn',
			resyncStatus: '#resync-status',
			resyncMessage: '.resync-message',
			resyncProgress: '#resync-progress',
			progressFill: '.progress-fill',
			progressText: '.progress-text',
			currentVersion: '#current-version',
			reloadPageLink: '.aspirecloud-reload-page'
		},

		init() {
			this.bindEvents();
		},

		bindEvents() {
			jQuery(document).on('click', this.selectors.resyncButtons, this.handleResyncClick.bind(this));
		},

		handleResyncClick(e) {
			e.preventDefault();

			const button = jQuery(e.currentTarget);
			const postId = button.data('post-id');
			const slug = button.data('slug');
			const assetType = button.data('asset-type');

			if (!postId || !slug || !assetType) {
				this.showStatus('error', aspirecloud_resync.strings.network_error);
				return;
			}

			this.startResync(button, postId, slug, assetType);
		},

		startResync(button, postId, slug, assetType) {
			// Disable button and show loading state
			button.addClass('loading').prop('disabled', true);

			// Show progress
			this.showProgress(0, aspirecloud_resync.strings.checking_version);

			// Make AJAX request
			jQuery.ajax({
				url: aspirecloud_resync.ajax_url,
				type: 'POST',
				data: {
					action: 'resync_' + assetType,
					post_id: postId,
					slug: slug,
					nonce: aspirecloud_resync.nonce
				},
				success: (response) => this.handleResyncSuccess(button, response),
				error: (jqXHR, textStatus, errorThrown) => this.handleResyncError(button, jqXHR, textStatus, errorThrown)
			});
		},

		handleResyncSuccess(button, response) {
			// Re-enable button
			button.removeClass('loading').prop('disabled', false);

			if (response.success) {
				const data = response.data;

				if (data.action === 'no_update') {
					this.showStatus('info', data.message);
					this.hideProgress();
				} else if (data.action === 'updated') {
					// Update the current version display
					jQuery(this.selectors.currentVersion).text(data.latest_version);

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

		handleResyncError(button, jqXHR, textStatus, errorThrown) {
			// Re-enable button
			button.removeClass('loading').prop('disabled', false);

			const errorMessage = jqXHR.responseJSON?.data || errorThrown || aspirecloud_resync.strings.network_error;
			this.showStatus('error', errorMessage);
			this.hideProgress();
		},

		showStatus(type, message) {
			const container = jQuery(this.selectors.resyncStatus);
			const messageElement = container.find(this.selectors.resyncMessage);

			// Update classes and message
			container.removeClass('success error info').addClass(type);
			messageElement.text(message);
			container.show();
		},

		showRefreshMessage() {
			const container = jQuery(this.selectors.resyncStatus);
			const messageElement = container.find(this.selectors.resyncMessage);

			// Create refresh message with reload link
			const refreshMessage = aspirecloud_resync.strings.refresh_message + ' ';
			const reloadLink = '<a href="#" class="aspirecloud-reload-page" style="text-decoration: underline; font-weight: bold;">' +
							   aspirecloud_resync.strings.reload_link_text + '</a>';

			// Update container and set HTML content
			container.removeClass('success error info').addClass('success');
			messageElement.html(refreshMessage + reloadLink);
			container.show();

			// Bind click event to reload link
			jQuery(document).off('click.aspirecloud-reload').on('click.aspirecloud-reload', this.selectors.reloadPageLink, (e) => {
				e.preventDefault();
				window.location.reload();
			});
		},

		hideStatus() {
			jQuery(this.selectors.resyncStatus).hide();
		},

		showProgress(percentage, text) {
			const container = jQuery(this.selectors.resyncProgress);
			container.find(this.selectors.progressFill).css('width', percentage + '%');
			container.find(this.selectors.progressText).text(text);
			container.show();
		},

		hideProgress() {
			jQuery(this.selectors.resyncProgress).hide();
		}
	};

	// Initialize when document is ready
	jQuery(document).ready(() => {
		AssetResync.init();
	});

})(jQuery);
