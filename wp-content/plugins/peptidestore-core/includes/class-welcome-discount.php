<?php
/**
 * Welcome program: automatic first-order discount + welcome email.
 *
 * Two parts, both keyed to a customer's account:
 *
 *   1. First-order discount. A logged-in customer who has no prior completed or
 *      processing orders automatically gets a percentage off, applied as a
 *      negative fee on the cart fee calculation. Because it is server-side it
 *      sets the real order total and cannot be spoofed; because eligibility does
 *      not depend on any checkout selection, the discount line simply appears in
 *      the cart and checkout totals with no code to enter and no JS needed.
 *
 *   2. Welcome email. When a new customer account is created (My Account or
 *      checkout), a short branded email tells them their first order gets the
 *      discount automatically.
 *
 * @package Peptide_Store
 */
namespace Peptide_Store;

defined( 'ABSPATH' ) || exit;

class Welcome_Discount {

	/** Discount rate (0.10 = 10%). */
	const RATE = 0.10;

	public function __construct() {
		add_action( 'woocommerce_cart_calculate_fees', array( $this, 'maybe_apply_discount' ) );
		add_action( 'woocommerce_created_customer', array( $this, 'send_welcome_email' ), 10, 3 );
	}

	// ── First-order discount ────────────────────────────────────────────────────

	/**
	 * Has this customer placed an order before? Counts on-hold and pending too
	 * (not just completed/processing) so the discount is consumed the moment the
	 * customer places their first order, including unpaid e-Transfer orders that
	 * sit on-hold. If not, the order they are placing now is their first. Cached
	 * per request because the fee calc can run several times on one page load.
	 */
	private function is_first_order( int $user_id ): bool {
		if ( $user_id <= 0 ) {
			return false;
		}
		static $cache = array();
		if ( isset( $cache[ $user_id ] ) ) {
			return $cache[ $user_id ];
		}
		$existing = wc_get_orders(
			array(
				'customer_id' => $user_id,
				'status'      => array( 'completed', 'processing', 'on-hold', 'pending' ),
				'limit'       => 1,
				'return'      => 'ids',
			)
		);
		$cache[ $user_id ] = empty( $existing );
		return $cache[ $user_id ];
	}

	/** Add the negative fee for a logged-in customer's first order. */
	public function maybe_apply_discount( $cart ): void {
		// Run during frontend + AJAX + Store API (REST); skip wp-admin screens.
		if ( is_admin() && ! wp_doing_ajax() && ! ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
			return;
		}
		if ( ! $cart instanceof \WC_Cart ) {
			return;
		}
		if ( ! is_user_logged_in() ) {
			return;
		}
		if ( ! $this->is_first_order( get_current_user_id() ) ) {
			return;
		}

		$subtotal = (float) $cart->get_subtotal(); // product subtotal, ex tax
		$discount = round( $subtotal * self::RATE, 2 );
		if ( $discount <= 0 ) {
			return;
		}

		$label = sprintf(
			/* translators: %s = discount percentage */
			__( 'Welcome Discount (%s%%)', 'peptidestore-core' ),
			(string) round( self::RATE * 100 )
		);
		// Negative, non-taxable fee = a clean discount line in the summary.
		$cart->add_fee( $label, -$discount, false );
	}

	// ── Welcome email ───────────────────────────────────────────────────────────

	/** Force HTML for the welcome email only (added and removed around the send). */
	public function html_content_type(): string {
		return 'text/html';
	}

	/**
	 * Send the welcome email when a new customer account is created.
	 *
	 * @param int $customer_id New customer user ID.
	 */
	public function send_welcome_email( $customer_id, $new_customer_data = array(), $password_generated = false ): void {
		$user = get_userdata( (int) $customer_id );
		if ( ! $user || ! is_email( $user->user_email ) ) {
			return;
		}

		$first    = $user->first_name ? $user->first_name : 'there';
		$pct       = (string) round( self::RATE * 100 );
		$shop_url  = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : home_url( '/shop/' );
		$subject   = sprintf( 'Welcome to Brand Pharma. Your first order gets %s%% off.', $pct );

		ob_start();
		?>
		<div style="background:#f4f6f9;padding:32px 0;font-family:Arial,Helvetica,sans-serif;">
			<table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="max-width:560px;margin:0 auto;background:#ffffff;border-radius:12px;overflow:hidden;border:1px solid #e3e8ef;">
				<tr>
					<td style="background:#1A2332;padding:28px 32px;">
						<div style="font-size:22px;font-weight:700;letter-spacing:1px;color:#ffffff;">BRAND<span style="color:#67A8D2;">PHARMA</span></div>
					</td>
				</tr>
				<tr>
					<td style="padding:32px;">
						<p style="margin:0 0 16px;font-size:18px;font-weight:700;color:#1A2332;">Welcome, <?php echo esc_html( $first ); ?>.</p>
						<p style="margin:0 0 16px;font-size:15px;line-height:1.7;color:#3A4A5C;">Thanks for creating your account. As a welcome, your first order automatically receives <strong style="color:#1A2332;"><?php echo esc_html( $pct ); ?>% off</strong>.</p>
						<p style="margin:0 0 24px;font-size:15px;line-height:1.7;color:#3A4A5C;">There is no code to enter and nothing to remember. Just log in, add your research compounds to the cart, and the discount appears at checkout.</p>
						<a href="<?php echo esc_url( $shop_url ); ?>" style="display:inline-block;background:#336AAD;color:#ffffff;text-decoration:none;font-size:15px;font-weight:600;padding:13px 28px;border-radius:8px;">Start shopping</a>
					</td>
				</tr>
				<tr>
					<td style="padding:20px 32px;border-top:1px solid #e3e8ef;">
						<p style="margin:0;font-size:12px;line-height:1.6;color:#8a97a8;">Brand Pharma. Canadian-made research compounds, held to a pharmaceutical standard. For laboratory and research use only. Not for human or animal consumption.</p>
					</td>
				</tr>
			</table>
		</div>
		<?php
		$body = ob_get_clean();

		add_filter( 'wp_mail_content_type', array( $this, 'html_content_type' ) );
		wp_mail( $user->user_email, $subject, $body );
		remove_filter( 'wp_mail_content_type', array( $this, 'html_content_type' ) );
	}
}
