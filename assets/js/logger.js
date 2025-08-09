/**
 * Logger Class
 *
 * Real-time operation logging with severity colors and interactive features.
 * Provides visual feedback for all AspireCloud operations with timestamp tracking.
 */

class Logger {
	constructor(containerSelector = '.aspirecloud-log-container') {
		this.containerSelector = containerSelector;

		// Element selectors
		this.selectors = {
			container: containerSelector,
			toggle: '.aspirecloud-log-toggle',
			content: '.aspirecloud-log-content',
			toggleText: '.aspirecloud-log-toggle-text',
			clear: '.aspirecloud-log-clear',
			entries: '.aspirecloud-log-entries'
		};

		// Log severity levels with colors
		this.logLevels = {
			INFO: { class: 'aspirecloud-log-info', color: '#2271b1', label: 'INFO' },
			SUCCESS: { class: 'aspirecloud-log-success', color: '#00a32a', label: 'SUCCESS' },
			WARNING: { class: 'aspirecloud-log-warning', color: '#dba617', label: 'WARNING' },
			ERROR: { class: 'aspirecloud-log-error', color: '#d63638', label: 'ERROR' },
			DEBUG: { class: 'aspirecloud-log-debug', color: '#646970', label: 'DEBUG' }
		};

		this.initialize();
	}

	initialize() {
		// Show log container (already rendered in PHP)
		jQuery(this.selectors.container).show();

		// Bind toggle and clear events
		this.bindEvents();

		// Initial log entry
		this.log('INFO', 'Logging system initialized');
	}

	bindEvents() {
		// Toggle log visibility
		jQuery(document).on('click', this.selectors.toggle, () => {
			const content = jQuery(this.selectors.content);
			const toggleText = jQuery(this.selectors.toggleText);

			if (content.is(':visible')) {
				content.hide();
				toggleText.text('Show');
			} else {
				content.show();
				toggleText.text('Hide');
			}
		});

		// Clear log entries
		jQuery(document).on('click', this.selectors.clear, () => {
			jQuery(this.selectors.entries).empty();
			this.log('INFO', 'Log cleared');
		});
	}

	log(level, message, details = null) {
		const timestamp = new Date().toLocaleTimeString();
		const logLevel = this.logLevels[level] || this.logLevels.INFO;

		let logEntry = `
			<div class="aspirecloud-log-entry ${logLevel.class}">
				<span class="aspirecloud-log-timestamp">[${timestamp}]</span>
				<span class="aspirecloud-log-level" style="color: ${logLevel.color}; font-weight: bold;">[${logLevel.label}]</span>
				<span class="aspirecloud-log-message">${message}</span>
		`;

		if (details) {
			logEntry += `<div class="aspirecloud-log-details">${details}</div>`;
		}

		logEntry += `</div>`;

		const logEntries = jQuery(this.selectors.entries);
		logEntries.append(logEntry);

		// Auto-scroll to bottom
		const logContent = logEntries.parent()[0];
		logContent.scrollTop = logContent.scrollHeight;

		// Also log to console for debugging
		console.log(`[AspireCloud ${logLevel.label}] ${message}`, details || '');
	}

	show() {
		jQuery(this.selectors.container).show();
	}

	hide() {
		jQuery(this.selectors.container).hide();
	}

	clear() {
		jQuery(this.selectors.entries).empty();
		this.log('INFO', 'Log cleared');
	}
}

// Export for use in other modules
if (typeof module !== 'undefined' && module.exports) {
	module.exports = Logger;
} else if (typeof window !== 'undefined') {
	window.Logger = Logger;
}
