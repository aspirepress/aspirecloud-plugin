<?php
/**
 * Base Asset Information Model.
 *
 * @package aspire-cloud
 * @author  AspirePress
 */

namespace AspireCloud\Model;

/**
 * Class AssetInfo
 *
 * Base class for shared asset (plugin/theme) properties and methods.
 */
class AssetInfo {
	/**
	 * Data storage for all asset properties.
	 *
	 * @var array
	 */
	protected $data = [];

	/**
	 * AssetInfo constructor.
	 *
	 * @param array $data Optional array of asset fields to auto-populate.
	 */
	public function __construct( $data = [] ) {
		if ( is_array( $data ) ) {
			//Handle Download Links attribute as a special case as it appears as both downloadLink and download_link in the API responses
			if ( isset( $data['downloadLink'] ) && ! isset( $data['download_link'] ) ) {
				$data['download_link'] = $data['downloadLink'];
			}
			$this->data = $data;
		}
	}

	/**
	 * Get all properties available for this class and its children.
	 * Uses reflection to discover properties from getter methods in current class and parent classes.
	 *
	 * @return array List of all available properties.
	 */
	public static function get_all_properties() {
		$properties = [];
		$class_name = static::class;

		try {
			$reflection = new \ReflectionClass( $class_name );

			// Get all classes to check (current class + all parent classes)
			$classes_to_check = [ $reflection ];
			$parent           = $reflection->getParentClass();
			while ( $parent ) {
				$classes_to_check[] = $parent;
				$parent             = $parent->getParentClass();
			}

			// Extract properties from getter methods in all classes
			foreach ( $classes_to_check as $class_reflection ) {
				$methods = $class_reflection->getMethods( \ReflectionMethod::IS_PUBLIC );
				foreach ( $methods as $method ) {
					$method_name = $method->getName();
					// Extract property name from get_* methods, excluding utility methods
					if ( 0 === strpos( $method_name, 'get_' ) &&
						'get_best_icon' !== $method_name &&
						'get_all_properties' !== $method_name ) {
						$property     = substr( $method_name, 4 );
						$properties[] = $property;
					}
				}
			}
		} catch ( \Exception $e ) {
			$properties = [];
		}

		// Return unique properties
		return array_unique( $properties );
	}

	/**
	 * Magic getter for properties.
	 *
	 * @param string $name Property name.
	 * @return mixed|null
	 */
	public function __get( $name ) {
		if ( ! is_string( $name ) ) {
			return null;
		}

		$value = $this->data[ $name ] ?? null;

		// Apply appropriate validation based on property type
		switch ( $name ) {
			// String fields that should be trimmed and non-empty
			case 'name':
			case 'slug':
				return is_string( $value ) && trim( $value ) !== '' ? trim( $value ) : null;

			// String fields that can be empty after trimming
			case 'version':
			case 'requires':
			case 'tested':
			case 'requires_php':
			case 'last_updated':
			case 'added':
			case 'short_description':
			case 'description':
			case 'business_model':
			case 'template':
			case 'ac_origin':
			case 'ac_created':
				return is_string( $value ) ? trim( $value ) : null;

			// URL fields that should be validated
			case 'author_profile':
			case 'homepage':
			case 'download_link':
			case 'repository_url':
			case 'commercial_support_url':
			case 'donate_link':
			case 'screenshot_url':
			case 'preview_url':
				return filter_var( $value, FILTER_VALIDATE_URL ) ? $value : null;

			// Numeric fields
			case 'rating':
				return is_numeric( $value ) ? max( 0, min( 100, (int) $value ) ) : null;

			case 'num_ratings':
			case 'active_installs':
			case 'support_threads':
			case 'support_threads_resolved':
				return is_numeric( $value ) ? (int) $value : null;

			// Array fields
			case 'ratings':
			case 'sections':
			case 'tags':
			case 'versions':
			case 'banners':
			case 'icons':
			case 'requires_plugins':
				return is_array( $value ) ? $value : [];

			// Special handling for author (can be string or array)
			case 'author':
				if ( is_array( $value ) ) {
					$cleaned_author = [];
					foreach ( $value as $k => $v ) {
						if ( is_string( $v ) && '' !== trim( $v ) ) {
							$cleaned_author[ $k ] = trim( wp_strip_all_tags( $v ) );
						}
					}
					return ! empty( $cleaned_author ) ? $cleaned_author : null;
				}
				if ( is_string( $value ) && '' !== trim( $value ) ) {
					return trim( wp_strip_all_tags( $value ) );
				}
				return null;

			// Default: return value as-is
			default:
				return $value;
		}
	}

	/**
	 * Magic setter for properties.
	 *
	 * @param string $name Property name.
	 * @param mixed $value Value to set.
	 */
	public function __set( $name, $value ) {
		if ( ! is_string( $name ) ) {
			return;
		}

		// Apply appropriate validation/sanitization based on property type
		switch ( $name ) {
			case 'name':
			case 'slug':
			case 'version':
			case 'requires':
			case 'tested':
			case 'requires_php':
			case 'last_updated':
			case 'added':
			case 'short_description':
			case 'description':
			case 'business_model':
			case 'template':
			case 'ac_origin':
			case 'ac_created':
				$this->data[ $name ] = is_string( $value ) ? trim( $value ) : null;
				break;

			case 'author':
				// Author can be string or array
				$this->data[ $name ] = ( is_string( $value ) || is_array( $value ) ) ? $value : null;
				break;

			case 'author_profile':
			case 'homepage':
			case 'download_link':
			case 'repository_url':
			case 'commercial_support_url':
			case 'donate_link':
			case 'screenshot_url':
			case 'preview_url':
				$this->data[ $name ] = filter_var( $value, FILTER_VALIDATE_URL ) ? $value : null;
				break;

			case 'rating':
			case 'num_ratings':
			case 'active_installs':
			case 'support_threads':
			case 'support_threads_resolved':
				$this->data[ $name ] = is_numeric( $value ) ? (int) $value : null;
				break;

			case 'ratings':
			case 'sections':
			case 'tags':
			case 'versions':
			case 'banners':
			case 'icons':
			case 'requires_plugins':
				$this->data[ $name ] = is_array( $value ) ? $value : [];
				break;

			default:
				$this->data[ $name ] = $value;
				break;
		}
	}

	/**
	 * Check if a property is set.
	 *
	 * @param string $name Property name.
	 * @return bool
	 */
	public function __isset( $name ) {
		return isset( $this->data[ $name ] );
	}

	/**
	 * Unset a property (resets to null).
	 *
	 * @param string $name Property name.
	 */
	public function __unset( $name ) {
		unset( $this->data[ $name ] );
	}

	// ------------------ GETTERS ------------------

	/**
	 * Get the asset name.
	 *
	 * @return string|null Asset display name or null if invalid.
	 */
	public function get_name() {
		return $this->name;
	}

	/**
	 * Get the asset slug.
	 *
	 * @return string|null Asset slug or null if invalid.
	 */
	public function get_slug() {
		return $this->slug;
	}

	/**
	 * Get the asset version.
	 *
	 * @return string|null Asset version or null.
	 */
	public function get_version() {
		return $this->version;
	}

	/**
	 * Get the author's plain name without any HTML or markup.
	 *
	 * @param string $parameter Specific parameter to retrieve from author array (e.g., 'display_name').
	 *                          If null, returns the plain text author name.
	 * @return string|array|null Cleaned author array, author name or null.
	 */
	public function get_author( $parameter = null ) {
		if ( null !== $parameter && ! is_string( $parameter ) ) {
			return null;
		}

		$author = $this->author;

		if ( is_array( $author ) ) {
			if ( null === $parameter ) {
				$cleaned_author = [];
				foreach ( $author as $key => $value ) {
					if ( is_string( $value ) && '' !== trim( $value ) ) {
						$cleaned_author[ $key ] = trim( wp_strip_all_tags( $value ) );
					}
				}
				return ! empty( $cleaned_author ) ? $cleaned_author : null;
			}

			if ( null !== $parameter && isset( $author[ $parameter ] ) ) {
				$clean = wp_strip_all_tags( $author[ $parameter ] );
				return trim( $clean ) !== '' ? $clean : null;
			}
		}

		if ( is_string( $author ) && '' !== trim( $author ) ) {
			return trim( wp_strip_all_tags( $author ) );
		}

		return null;
	}

	/**
	 * Get the author profile URL.
	 *
	 * @return string|null Valid author profile URL or null.
	 */
	public function get_author_profile() {
		return $this->author_profile;
	}

	/**
	 * Get the required WordPress version.
	 *
	 * @return string|null WordPress version requirement or null.
	 */
	public function get_requires() {
		return $this->requires;
	}

	/**
	 * Get the tested WordPress version.
	 *
	 * @return string|null WordPress version tested or null.
	 */
	public function get_tested() {
		return $this->tested;
	}

	/**
	 * Get the required PHP version.
	 *
	 * @return string|null PHP version requirement or null.
	 */
	public function get_requires_php() {
		return $this->requires_php;
	}

	/**
	 * Get the rating as a percentage.
	 *
	 * @return int|null Rating percentage (0-100) or null.
	 */
	public function get_rating() {
		return $this->rating;
	}

	/**
	 * Get ratings distribution.
	 *
	 * @return array Ratings array or empty array.
	 */
	public function get_ratings() {
		return $this->ratings;
	}

	/**
	 * Get number of ratings.
	 *
	 * @return int|null Number of ratings or null.
	 */
	public function get_num_ratings() {
		return $this->num_ratings;
	}

	/**
	 * Get active installations count.
	 *
	 * @return int|null Active installations or null.
	 */
	public function get_active_installs() {
		return $this->active_installs;
	}

	/**
	 * Get the last updated date.
	 *
	 * @return string|null Last updated date or null.
	 */
	public function get_last_updated() {
		return $this->last_updated;
	}

	/**
	 * Get the date added.
	 *
	 * @return string|null Date added or null.
	 */
	public function get_added() {
		return $this->added;
	}

	/**
	 * Get the homepage URL.
	 *
	 * @return string|null Valid homepage URL or null.
	 */
	public function get_homepage() {
		return $this->homepage;
	}

	/**
	 * Get the short description.
	 *
	 * @return string|null Short description or null.
	 */
	public function get_short_description() {
		return $this->short_description;
	}

	/**
	 * Get the full description.
	 *
	 * @return string|null Full description or null.
	 */
	public function get_description() {
		return $this->description;
	}

	/**
	 * Get the download link.
	 *
	 * @return string|null Valid download URL or null.
	 */
	public function get_download_link() {
		return $this->download_link;
	}

	/**
	 * Get sections array.
	 *
	 * @return array Sections array or empty array.
	 */
	public function get_sections() {
		return $this->sections;
	}

	/**
	 * Get a specific section.
	 *
	 * @param string $section Section key.
	 * @return string|null Section content or null.
	 */
	public function get_section( $section ) {
		$sections = $this->sections;
		return isset( $sections[ $section ] ) ? $sections[ $section ] : null;
	}

	/**
	 * Get tags array.
	 *
	 * @return array Tags array or empty array.
	 */
	public function get_tags() {
		return $this->tags;
	}

	/**
	 * Get versions array.
	 *
	 * @return array Versions array or empty array.
	 */
	public function get_versions() {
		return $this->versions;
	}

	/**
	 * Get banners array.
	 *
	 * @param string|null $size Optional banner size ('low', 'high').
	 * @return array|string|null All banners or specific size or null.
	 */
	public function get_banners( $size = null ) {
		$banners = $this->banners;
		if ( ! is_array( $banners ) ) {
			return null === $size ? [] : null;
		}
		return null === $size ? $banners : ( $banners[ $size ] ?? null );
	}

	/**
	 * Get the asset origin.
	 *
	 * @return string|null Asset origin or null.
	 */
	public function get_ac_origin() {
		return $this->ac_origin;
	}

	/**
	 * Get the asset creation date.
	 *
	 * @return string|null Asset creation date or null.
	 */
	public function get_ac_created() {
		return $this->ac_created;
	}

	/**
	 * Convert object to array.
	 *
	 * @return array Object as associative array.
	 */
	public function to_array() {
		$data       = [];
		$reflection = new \ReflectionClass( $this );
		$properties = $reflection->getProperties();

		foreach ( $properties as $property ) {
			if ( $property->isProtected() || $property->isPublic() ) {
				$property->setAccessible( true );
				$data[ $property->getName() ] = $property->getValue( $this );
			}
		}

		return $data;
	}
}
