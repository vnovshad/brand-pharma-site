<?php
/**
 * Single product page enhancements.
 *
 *  - Trust signal list (In Stock + quality/shipping markers) in the summary.
 *  - Adds "Reconstitution" and "Storage" tabs and renames Description →
 *    "General Info". All copy is research-framed (no human-use guidance).
 *
 * Visual styling lives in the theme (brand.css).
 *
 * @package Peptide_Store
 */
namespace Peptide_Store;

defined( 'ABSPATH' ) || exit;

class Product_Page {

	public function __construct() {
		add_action( 'woocommerce_single_product_summary', array( $this, 'render_trust_signals' ), 25 );
		add_filter( 'woocommerce_product_tabs', array( $this, 'product_tabs' ) );
	}

	// ── Trust signals ─────────────────────────────────────────────────────────

	public function render_trust_signals(): void {
		global $product;
		if ( ! $product instanceof \WC_Product ) {
			return;
		}
		$in_stock = $product->is_in_stock();

		echo '<ul class="psc-trust">';

		printf(
			'<li class="psc-trust__item psc-trust__stock %1$s"><span class="psc-trust__dot" aria-hidden="true"></span>%2$s</li>',
			esc_attr( $in_stock ? 'is-in' : 'is-out' ),
			esc_html( $in_stock ? __( 'In Stock', 'peptidestore' ) : __( 'Out of Stock', 'peptidestore' ) )
		);

		$items = array(
			'flask' => __( 'Independently HPLC-MS tested', 'peptidestore' ),
			'truck' => __( 'Ships from Canada', 'peptidestore' ),
			'doc'   => __( 'Certificate of Analysis available', 'peptidestore' ),
		);
		foreach ( $items as $icon => $label ) {
			echo '<li class="psc-trust__item">' . $this->icon( $icon ) . esc_html( $label ) . '</li>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- icon() returns fixed inline SVG markup
		}

		echo '</ul>';
	}

	/** Fixed inline SVG icons (stroke uses currentColor; coloured via CSS). */
	private function icon( string $key ): string {
		$paths = array(
			'flask' => '<path d="M9 3h6M10 3v6l-4.5 8.5A2 2 0 0 0 7.3 21h9.4a2 2 0 0 0 1.8-3.5L14 9V3"/>',
			'truck' => '<path d="M3 6h11v9H3zM14 9h4l3 3v3h-7zM7.5 19a1.8 1.8 0 1 0 0-3.6 1.8 1.8 0 0 0 0 3.6zM17.5 19a1.8 1.8 0 1 0 0-3.6 1.8 1.8 0 0 0 0 3.6z"/>',
			'doc'   => '<path d="M7 3h7l4 4v14H7zM14 3v4h4M9.5 12h5M9.5 16h5"/>',
		);
		$path = $paths[ $key ] ?? '';
		return '<svg class="psc-trust__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $path . '</svg>';
	}

	// ── Tabs ────────────────────────────────────────────────────────────────────

	public function product_tabs( array $tabs ): array {
		if ( isset( $tabs['description'] ) ) {
			$tabs['description']['title']    = __( 'General Info', 'peptidestore' );
			$tabs['description']['priority'] = 5;
		}
		if ( isset( $tabs['additional_information'] ) ) {
			$tabs['additional_information']['priority'] = 20;
		}

		$tabs['psc_reconstitution'] = array(
			'title'    => __( 'Reconstitution', 'peptidestore' ),
			'priority' => 10,
			'callback' => array( $this, 'tab_reconstitution' ),
		);
		$tabs['psc_storage'] = array(
			'title'    => __( 'Storage', 'peptidestore' ),
			'priority' => 15,
			'callback' => array( $this, 'tab_storage' ),
		);

		return $tabs;
	}

	public function tab_reconstitution(): void {
		$calc = get_page_by_path( 'dosage-calculator' );
		$calc_url = $calc instanceof \WP_Post ? get_permalink( $calc ) : home_url( '/dosage-calculator/' );
		?>
		<h2><?php esc_html_e( 'Reconstitution', 'peptidestore' ); ?></h2>
		<p><?php esc_html_e( 'Reconstitution is the laboratory process of dissolving a lyophilized (freeze-dried) research compound in a suitable solvent, most commonly bacteriostatic water, to prepare a solution for research use.', 'peptidestore' ); ?></p>
		<p><?php
			printf(
				/* translators: %s: calculator link */
				wp_kses_post( __( 'Use our <a href="%s">Peptide Reconstitution Calculator</a> to work out the solvent volume and concentration for your research parameters.', 'peptidestore' ) ),
				esc_url( $calc_url )
			);
		?></p>
		<p><em><?php esc_html_e( 'For laboratory and research use only. Not for human or animal consumption.', 'peptidestore' ); ?></em></p>
		<?php
	}

	public function tab_storage(): void {
		?>
		<h2><?php esc_html_e( 'Storage', 'peptidestore' ); ?></h2>
		<p><?php esc_html_e( 'Lyophilized research peptides are generally most stable when stored frozen (around -20 °C), sealed, and protected from light and humidity. Refer to the Certificate of Analysis and product documentation for any compound-specific handling notes.', 'peptidestore' ); ?></p>
		<p><?php esc_html_e( 'Once reconstituted, stability and handling vary by compound. Consult the peer-reviewed literature specific to the material you are working with. Minimise repeated freeze-thaw cycles to preserve sample integrity.', 'peptidestore' ); ?></p>
		<p><em><?php esc_html_e( 'For laboratory and research use only. Not for human or animal consumption.', 'peptidestore' ); ?></em></p>
		<?php
	}
}
