<?php
/**
 * Theme Information Model.
 *
 * @package aspire-cloud
 * @author  AspirePress
 */

namespace AspireCloud\Model;

/**
 * Class ThemeInfo
 *
 * Represents detailed metadata of a WordPress theme retrieved via the Themes API.
 * Provides explicit getters and setters for safe and structured access.
 * Also provides typed access to theme fields with validation.
 */
class ThemeInfo extends AssetInfo {

	// ------------------ GETTERS ------------------

	/**
	 * Get the screenshot image URL.
	 *
	 * @return string|null Screenshot URL or null.
	 */
	public function get_screenshot_url() {
		return $this->screenshot_url;
	}

	/**
	 * Get the theme preview URL.
	 *
	 * @return string|null Preview URL or null.
	 */
	public function get_preview_url() {
		return $this->preview_url;
	}

	/**
	 * Get the repository url.
	 *
	 * @return string|null Repository URL or null.
	 */
	public function get_repository_url() {
		return $this->repository_url;
	}

	/**
	 * Get the commercial support url.
	 *
	 * @return string|null Commercial support URL or null.
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
	 * Get the theme template (parent theme).
	 *
	 * @return string|null Template name or null.
	 */
	public function get_template() {
		return $this->template;
	}

	/**
	 * Check if this is a child theme.
	 *
	 * @return bool True if child theme, false otherwise.
	 */
	public function is_child_theme() {
		return null !== $this->get_template() && $this->get_template() !== $this->get_slug();
	}
}
