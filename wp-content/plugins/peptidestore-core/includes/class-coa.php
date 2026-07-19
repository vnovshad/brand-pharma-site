<?php
/**
 * COA (Certificate of Analysis) module.
 *
 * - Stores _psc_coa_attachment_id on each product that has a matching COA.
 * - Embeds the COA image inline at the bottom of the Description tab.
 *   No link wrapping — image displays directly on the page.
 * - Adds the COA image to the product's WooCommerce image gallery so it
 *   appears as a thumbnail alongside product photos.
 * - Feeds the COA URL into the Product JSON-LD via peptidestore_schema_product.
 * - Products with no COA: no output, no broken state.
 *
 * Handles both image attachments (JPEG/PNG) and PDF attachments automatically.
 *
 * @package Peptide_Store
 */
namespace Peptide_Store;

defined( 'ABSPATH' ) || exit;

class COA {

	public function __construct() {
		add_filter( 'woocommerce_product_tabs',    array( $this, 'inject_into_description_tab' ), 98 );
		add_filter( 'peptidestore_schema_product', array( $this, 'add_to_schema' ), 10, 2 );
	}

	// ── Public API ────────────────────────────────────────────────────────────

	public static function attachment_id( int $product_id ): int {
		return (int) get_post_meta( $product_id, '_psc_coa_attachment_id', true );
	}

	// ── Description tab ───────────────────────────────────────────────────────

	public function inject_into_description_tab( array $tabs ): array {
		if ( ! isset( $tabs['description'] ) ) {
			return $tabs;
		}

		$original = $tabs['description']['callback'] ?? 'woocommerce_product_description_tab';

		$tabs['description']['callback'] = static function () use ( $original ) {
			if ( is_callable( $original ) ) {
				call_user_func( $original );
			}

			$product_id = get_the_ID();
			if ( ! $product_id ) {
				return;
			}
			$coa_id = COA::attachment_id( $product_id );
			if ( ! $coa_id ) {
				return;
			}
			$url  = wp_get_attachment_url( $coa_id );
			$mime = get_post_mime_type( $coa_id );
			if ( ! $url ) {
				return;
			}

			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			echo COA::embed_html( esc_url( $url ), (string) $mime );
		};

		return $tabs;
	}

	public static function embed_html( string $escaped_url, string $mime = '' ): string {
		ob_start();
		?>
		<div class="psc-coa-section">
			<h3 class="psc-coa-heading"><?php esc_html_e( 'Certificate of Analysis', 'peptidestore' ); ?></h3>
			<?php if ( str_starts_with( $mime, 'image/' ) ) : ?>
				<img
					src="<?php echo $escaped_url; ?>"
					alt="<?php esc_attr_e( 'Certificate of Analysis', 'peptidestore' ); ?>"
					class="psc-coa-image"
				/>
			<?php else : ?>
				<div class="psc-coa-wrapper">
					<iframe
						src="<?php echo $escaped_url; ?>"
						class="psc-coa-frame"
						title="<?php esc_attr_e( 'Certificate of Analysis', 'peptidestore' ); ?>"
					></iframe>
				</div>
			<?php endif; ?>
		</div>
		<?php
		return ob_get_clean();
	}

	// ── Schema ────────────────────────────────────────────────────────────────

	public function add_to_schema( array $data, \WC_Product $product ): array {
		$coa_id = self::attachment_id( $product->get_id() );
		if ( ! $coa_id ) {
			return $data;
		}
		$url = wp_get_attachment_url( $coa_id );
		if ( ! $url ) {
			return $data;
		}

		if ( ! isset( $data['additionalProperty'] ) ) {
			$data['additionalProperty'] = array();
		}
		$data['additionalProperty'][] = array(
			'@type' => 'PropertyValue',
			'name'  => 'Certificate of Analysis',
			'value' => $url,
		);

		return $data;
	}
}
