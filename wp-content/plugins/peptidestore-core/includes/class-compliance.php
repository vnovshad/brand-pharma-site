<?php
/**
 * Compliance module (HARD GUARDRAIL AREA). Centralizes "research use only"
 * framing + tools to keep human-outcome/therapeutic claims out of copy.
 * Risk-reduction tooling, NOT legal advice/cover.
 *
 * @package Peptide_Store
 */
namespace Peptide_Store;

defined( 'ABSPATH' ) || exit;

class Compliance {
	private array $claim_flags = array(
		'cure', 'cures', 'treat', 'treats', 'heal', 'heals', 'healing',
		'weight loss', 'fat loss', 'anti-aging', 'anti aging', 'dosage',
		'dose', 'inject', 'prescription', 'diagnose', 'prevents', 'remedy',
		'side effects', 'clinically proven', 'results',
	);

	public function __construct() {
		add_filter( 'woocommerce_short_description', array( $this, 'append_disclaimer' ), 20 );
		add_action( 'woocommerce_before_main_content', array( $this, 'render_store_notice' ), 5 );
		add_action( 'admin_notices', array( $this, 'admin_claim_linter' ) );
		add_action( 'wp_footer', array( $this, 'maybe_render_age_gate' ) );
	}

	public static function disclaimer_text(): string {
		return __( 'For laboratory and research use only. Not for human or animal consumption, diagnosis, or therapeutic use.', 'peptidestore' );
	}

	public static function disclaimer_html(): string {
		ob_start();
		$template = PEPTIDE_STORE_PATH . 'templates/disclaimer.php';
		if ( file_exists( $template ) ) {
			include $template;
		}
		return ob_get_clean();
	}

	public function append_disclaimer( string $description ): string {
		return $description . self::disclaimer_html();
	}

	public function render_store_notice(): void {
		// Keep the big notice off the shop/archives and single product pages —
		// the cleaner inline disclaimer (appended to the short description) and
		// the sitewide footer disclaimer cover those. Still renders on cart,
		// checkout, and other WooCommerce pages.
		if ( function_exists( 'is_shop' ) && ( is_shop() || is_product_taxonomy() || is_product() ) ) {
			return;
		}
		echo '<div class="peptidestore-ruo-notice" role="note">' . esc_html( self::disclaimer_text() ) . '</div>';
	}

	public function admin_claim_linter(): void {
		$screen = get_current_screen();
		if ( ! $screen || 'post' !== $screen->base ) {
			return;
		}
		global $post;
		if ( ! $post instanceof \WP_Post ) {
			return;
		}
		$haystack = strtolower( $post->post_title . ' ' . $post->post_content . ' ' . $post->post_excerpt );
		$hits = array();
		foreach ( $this->claim_flags as $term ) {
			if ( false !== strpos( $haystack, $term ) ) {
				$hits[] = $term;
			}
		}
		if ( empty( $hits ) ) {
			return;
		}
		echo '<div class="notice notice-warning"><p><strong>';
		esc_html_e( 'Compliance check:', 'peptidestore' );
		echo '</strong> ';
		printf(
			esc_html__( 'Potential human-outcome/claim language detected (%s). Review against research-use-only framing before publishing. This is a heuristic warning, not legal sign-off.', 'peptidestore' ),
			esc_html( implode( ', ', $hits ) )
		);
		echo '</p></div>';
	}

	public function maybe_render_age_gate(): void {
		$enabled = apply_filters( 'peptidestore_enable_age_gate', false );
		if ( ! $enabled ) {
			return;
		}
		?>
		<div id="peptidestore-age-gate" class="peptidestore-age-gate" hidden>
			<div class="peptidestore-age-gate__panel" role="dialog" aria-modal="true" aria-labelledby="peptidestore-age-gate__title">
				<h2 id="peptidestore-age-gate__title"><?php esc_html_e( 'Confirmation required', 'peptidestore' ); ?></h2>
				<p><?php echo esc_html( self::disclaimer_text() ); ?></p>
				<p><?php esc_html_e( 'By entering, you confirm you are of legal age and that products are purchased for research use only.', 'peptidestore' ); ?></p>
				<button type="button" data-peptidestore-age-confirm><?php esc_html_e( 'I confirm', 'peptidestore' ); ?></button>
			</div>
		</div>
		<?php
	}
}
