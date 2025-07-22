<?php
/**
 * Plugin Information Model.
 *
 * @package aspire-cloud
 * @author  AspirePress
 */

namespace AspireCloud\Model;

/**
 * Class PluginInfo
 *
 * Represents detailed metadata of a WordPress plugin retrieved via the Plugin API.
 * Provides explicit getters and setters for safe and structured access.
 * Also Provides typed access to plugin fields with validation.
 */
class PluginInfo extends AssetInfo {

	// ------------------ GETTERS ------------------

	/**
	 * Get the array of required plugin slugs.
	 *
	 * @return array List of plugin slugs or empty array.
	 */
	public function get_requires_plugins() {
		return $this->requires_plugins;
	}

	/**
	 * Get number of open support threads.
	 *
	 * @return int|null Open thread count.
	 */
	public function get_support_threads() {
		return $this->support_threads;
	}

	/**
	 * Get number of resolved support threads.
	 *
	 * @return int|null Resolved thread count.
	 */
	public function get_support_threads_resolved() {
		return $this->support_threads_resolved;
	}

	/**
	 * Get the business model.
	 *
	 * @return string|null business model or null.
	 */
	public function get_business_model() {
		return $this->business_model;
	}

	/**
	 * Get the repository url.
	 *
	 * @return string|null repository url or null.
	 */
	public function get_repository_url() {
		return $this->repository_url;
	}

	/**
	 * Get the commercial support url.
	 *
	 * @return string|null repository url or null.
	 */
	public function get_commercial_support_url() {
		return $this->commercial_support_url;
	}

	/**
	 * Get the donation link.
	 *
	 * @return string|null Donation URL or null.
	 */
	public function get_donate_link() {
		return $this->donate_link;
	}

	/**
	 * Get all icons or a specific size.
	 *
	 * @param string|null $size Optional: 'svg', '2x', '1x'
	 * @return array|string|null All icons or specific.
	 */
	public function get_icons( $size = null ) {
		$icons = $this->icons;
		if ( null === $size ) {
			return $icons;
		}
		return $icons[ $size ] ?? null;
	}

	/**
	 * Get the best available icon URL by priority (svg > 2x > 1x).
	 *
	 * @return string|null The best icon URL or null.
	 */
	public function get_best_icon() {
		foreach ( [ 'svg', '2x', '1x' ] as $size ) {
			$icon = $this->get_icons( $size );
			if ( $icon && filter_var( $icon, FILTER_VALIDATE_URL ) ) {
				return $icon;
			}
		}
		return null;
	}
}
