<?php
/**
 * Interac e-Transfer gateway (offline/manual) — built on WC_Payment_Gateway.
 * Customer sees instructions, order goes "on-hold", you mark paid on receipt.
 * Bank-to-bank: no card network, out of PCI scope, low chargeback risk.
 * Copy this pattern for API-based gateways (prefer hosted pages/tokenization).
 *
 * @package Peptide_Store
 */
namespace Peptide_Store\Gateways;

defined( 'ABSPATH' ) || exit;

class Etransfer_Gateway extends \WC_Payment_Gateway {
	/** @var string Payment instructions shown to the customer. */
	public $instructions;

	public function __construct() {
		$this->id                 = 'peptidestore_etransfer';
		$this->method_title       = __( 'Interac e-Transfer', 'peptidestore' );
		$this->method_description = __( 'Accept payment by Interac e-Transfer. Orders are placed on-hold until you confirm receipt of funds.', 'peptidestore' );
		$this->has_fields         = false;

		$this->init_form_fields();
		$this->init_settings();

		// Official Interac e-Transfer logo from the Media Library (resolved via
		// the uploads base URL so it stays correct across domains). Override any
		// time with the peptidestore_etransfer_icon filter.
		$this->icon         = apply_filters(
			'peptidestore_etransfer_icon',
			trailingslashit( wp_get_upload_dir()['baseurl'] ) . '2026/06/Interac_e-Transfer_logo.png'
		);
		$this->enabled      = $this->get_option( 'enabled' );
		$this->title        = $this->get_option( 'title' );
		$this->description  = $this->get_option( 'description' );
		$this->instructions = $this->get_option( 'instructions' );

		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
		add_action( 'woocommerce_before_thankyou', array( $this, 'before_thankyou_warning' ) );
		add_action( 'woocommerce_thankyou_' . $this->id, array( $this, 'thankyou_page' ) );
		add_action( 'woocommerce_email_before_order_table', array( $this, 'email_instructions' ), 10, 3 );
	}

	public function init_form_fields(): void {
		$this->form_fields = array(
			'enabled'      => array(
				'title'   => __( 'Enable/Disable', 'peptidestore' ),
				'type'    => 'checkbox',
				'label'   => __( 'Enable Interac e-Transfer', 'peptidestore' ),
				'default' => 'yes',
			),
			'title'        => array(
				'title'    => __( 'Title', 'peptidestore' ),
				'type'     => 'text',
				'default'  => __( 'Interac e-Transfer', 'peptidestore' ),
				'desc_tip' => true,
			),
			'description'  => array(
				'title'   => __( 'Description', 'peptidestore' ),
				'type'    => 'textarea',
				'default' => __( 'Pay securely by Interac e-Transfer from your Canadian bank. You will receive payment instructions after placing your order.', 'peptidestore' ),
			),
			'instructions' => array(
				'title'   => __( 'Instructions', 'peptidestore' ),
				'type'    => 'textarea',
				'default' => __( "Send your e-Transfer to: etransfer@brandstore.example\nUse your order number as the message/memo.\nYour order ships once payment is received.", 'peptidestore' ),
			),
		);
	}

	public function process_payment( $order_id ): array {
		$order = wc_get_order( $order_id );
		$order->update_status( 'on-hold', __( 'Awaiting Interac e-Transfer payment.', 'peptidestore' ) );
		wc_reduce_stock_levels( $order_id );
		WC()->cart->empty_cart();
		return array( 'result' => 'success', 'redirect' => $this->get_return_url( $order ) );
	}

	/** Extract the payment e-Transfer address from the instructions setting. */
	private function payment_email(): string {
		return preg_match( '/[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}/', (string) $this->instructions, $m ) ? $m[0] : '';
	}

	/**
	 * Render the five e-Transfer fields as a labeled table.
	 * Used by all three customer-facing render locations (top-of-page alert,
	 * order-summary block, and confirmation email).
	 *
	 * @param string $order_num  Raw order number (unescaped); escaped here.
	 */
	private function fields_block( string $order_num ): string {
		$email = $this->payment_email();
		$memo  = '' !== $order_num
			? '#' . esc_html( $order_num )
			: __( 'Your order number', 'peptidestore' );

		$entries = array(
			array( 'Recipient Name',    'Ebay Auctions' ),
			array( 'Email',             esc_html( $email ) ),
			array( 'Security Question', "What's your favourite sport?" ),
			array( 'Security Answer',   'Boxing77' ),
			array( 'Message / Memo',    $memo ),
		);

		$lbl = 'padding:.4rem 1rem .4rem 0;white-space:nowrap;font-weight:700;color:#1A2332;vertical-align:top;font-size:.97rem;';
		$val = "padding:.4rem 0;font-weight:800;color:#c0392b;font-size:1.05rem;font-family:'Space Grotesk',sans-serif;vertical-align:top;";

		$rows = '';
		$n    = 1;
		foreach ( $entries as $entry ) {
			$rows .= '<tr>'
				. '<td style="' . $lbl . '">' . esc_html( $n++ . '. ' . $entry[0] ) . '</td>'
				. '<td style="' . $val . '">' . $entry[1] . '</td>'
				. '</tr>';
		}

		return '<table style="border-collapse:collapse;width:100%;margin:.85rem 0 .5rem;">'
			. '<tbody>' . $rows . '</tbody>'
			. '</table>';
	}

	/**
	 * High-visibility alert at the TOP of the order-received page (e-Transfer
	 * orders only). Fires for every order, scoped to this gateway.
	 */
	public function before_thankyou_warning( $order_id ): void {
		$order = wc_get_order( $order_id );
		if ( ! $order || $this->id !== $order->get_payment_method() ) {
			return;
		}
		if ( '' === $this->payment_email() ) {
			return;
		}
		$num = $order->get_order_number();

		echo '<div class="psc-etransfer-alert" role="alert" style="border:2px solid #c0392b;background:#fdecea;border-radius:10px;padding:1.25rem 1.5rem;margin:0 0 1.5rem;">';
		echo '<p style="margin:0 0 .5rem;font-weight:800;color:#c0392b;font-size:1.1rem;font-family:\'Space Grotesk\',sans-serif;">&#9888; Action required: your order is not paid yet</p>';
		echo '<p style="margin:0 0 .25rem;color:#1A2332;line-height:1.6;">Your order will <strong>not be shipped</strong> until your Interac e-Transfer is received. Fill in these five fields in your banking app exactly as shown:</p>';
		echo $this->fields_block( $num ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped within helper
		echo '<p style="margin:.5rem 0 0;color:#c0392b;font-weight:600;line-height:1.6;">An e-Transfer sent to a different email address, or without your order number in the memo field, may not be matched to your order and the funds could be lost.</p>';
		echo '</div>';
	}

	/**
	 * The payment details block in the order summary on the thank-you page.
	 * Falls back to the plain instructions text if no email address is configured.
	 */
	public function thankyou_page( $order_id = 0 ): void {
		if ( '' === $this->payment_email() ) {
			if ( $this->instructions ) {
				echo wp_kses_post( wpautop( wptexturize( $this->instructions ) ) );
			}
			return;
		}
		$order = $order_id ? wc_get_order( $order_id ) : false;
		$num   = $order ? $order->get_order_number() : '';

		echo '<div class="psc-etransfer-pay" style="border:2px solid #c0392b;background:#fdecea;border-radius:10px;padding:1.25rem 1.5rem;margin:1.25rem 0;">';
		echo '<p style="margin:0 0 .5rem;font-weight:800;color:#c0392b;text-transform:uppercase;letter-spacing:.05em;font-family:\'Space Grotesk\',sans-serif;">Send your Interac e-Transfer now</p>';
		echo '<p style="margin:0 0 .25rem;color:#1A2332;line-height:1.6;">Fill in these five fields in your banking app exactly as shown:</p>';
		echo $this->fields_block( $num ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped within helper
		echo '<p style="margin:.5rem 0 0;color:#3A4A5C;font-size:.92rem;line-height:1.6;">Your order ships once payment is received. An e-Transfer sent without your order number in the memo, or to any other email address, may not be matched and the funds could be lost.</p>';
		echo '</div>';
	}

	/**
	 * Payment instructions injected into the WooCommerce order confirmation email.
	 * Fires only for e-Transfer on-hold orders sent to the customer (not admin).
	 */
	public function email_instructions( $order, $sent_to_admin, $plain_text = false ): void {
		if ( $sent_to_admin || $this->id !== $order->get_payment_method() || 'on-hold' !== $order->get_status() ) {
			return;
		}

		$num   = $order->get_order_number();
		$email = $this->payment_email();
		$memo  = '' !== $num ? 'Order #' . esc_html( $num ) : __( 'Your order number', 'peptidestore' );

		if ( $plain_text ) {
			echo "\nSend your Interac e-Transfer\n";
			echo "-----------------------------\n";
			echo "Fill in these five fields in your banking app exactly as shown:\n\n";
			echo '1. Recipient Name:    Ebay Auctions' . "\n";
			echo '2. Email:             ' . esc_html( $email ) . "\n";
			echo "3. Security Question: What's your favourite sport?\n";
			echo '4. Security Answer:   Boxing77' . "\n";
			echo '5. Message / Memo:    ' . $memo . "\n\n";
			echo "Your order ships once payment is received. An e-Transfer sent to a different email address, or without your order number in the memo, may not be matched and the funds could be lost.\n\n";
			return;
		}

		// HTML email
		echo '<div style="border:2px solid #c0392b;background:#fdecea;border-radius:8px;padding:1.1rem 1.4rem;margin:1.25rem 0 0;">';
		echo '<p style="margin:0 0 .5rem;font-weight:800;color:#c0392b;font-size:1rem;">Send your Interac e-Transfer</p>';
		echo '<p style="margin:0 0 .25rem;color:#1A2332;line-height:1.55;font-size:.95rem;">Fill in these five fields in your banking app exactly as shown:</p>';
		echo $this->fields_block( $num ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped within helper
		echo '<p style="margin:.5rem 0 0;color:#c0392b;font-weight:600;font-size:.9rem;line-height:1.55;">Your order ships once payment is received. An e-Transfer sent to a different email address, or without your order number in the memo, may not be matched and the funds could be lost.</p>';
		echo '</div>' . PHP_EOL;
	}
}
