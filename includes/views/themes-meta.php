<?php
/**
 * Theme meta box view.
 *
 * @package aspirecloud
 * @var \WP_Post $post The post object.
 * @var array $properties The theme properties.
 * @var object $theme_instance The themes class instance.
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

wp_nonce_field( 'save_theme_meta', 'theme_meta_nonce' );
?>

<table class="form-table">
	<?php foreach ( $properties as $property ) : ?>
		<?php
		$meta_key = '__' . $property;
		$value    = get_post_meta( $post->ID, $meta_key, true );
		$label    = ucwords( str_replace( '_', ' ', $property ) );
		?>
		<tr>
			<th scope="row">
				<label for="<?php echo esc_attr( $meta_key ); ?>">
					<?php echo esc_html( $label ); ?>
				</label>
			</th>
			<td>
				<?php if ( in_array( $property, [ 'sections', 'tags', 'versions', 'banners', 'ratings' ], true ) ) : ?>
					<!-- Array fields - use textarea for JSON -->
					<textarea
						id="<?php echo esc_attr( $meta_key ); ?>"
						name="<?php echo esc_attr( $meta_key ); ?>"
						rows="4"
						cols="50"
						class="large-text"
					><?php echo esc_textarea( is_array( $value ) ? wp_json_encode( $value, JSON_PRETTY_PRINT ) : $value ); ?></textarea>
					<p class="description">Enter as JSON array</p>

				<?php elseif ( in_array( $property, [ 'description', 'short_description' ], true ) ) : ?>
					<!-- Long text fields -->
					<textarea
						id="<?php echo esc_attr( $meta_key ); ?>"
						name="<?php echo esc_attr( $meta_key ); ?>"
						rows="4"
						cols="50"
						class="large-text"
					><?php echo esc_textarea( $value ); ?></textarea>

				<?php elseif ( in_array( $property, [ 'rating', 'num_ratings', 'active_installs' ], true ) ) : ?>
					<!-- Numeric fields -->
					<input
						type="number"
						id="<?php echo esc_attr( $meta_key ); ?>"
						name="<?php echo esc_attr( $meta_key ); ?>"
						value="<?php echo esc_attr( $value ); ?>"
						class="regular-text"
					/>

				<?php else : ?>
					<!-- Regular text fields -->
					<input
						type="text"
						id="<?php echo esc_attr( $meta_key ); ?>"
						name="<?php echo esc_attr( $meta_key ); ?>"
						value="<?php echo esc_attr( $value ); ?>"
						class="regular-text"
					/>

				<?php endif; ?>
			</td>
		</tr>
	<?php endforeach; ?>
</table>
