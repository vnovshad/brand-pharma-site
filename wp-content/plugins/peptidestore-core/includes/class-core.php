<?php
/**
 * Core loader. Loads + boots feature modules. Split into multiple plugins
 * later if this grows large.
 *
 * @package Peptide_Store
 */
namespace Peptide_Store;

defined( 'ABSPATH' ) || exit;

final class Core {
	private static $instance = null;

	public static function instance(): Core {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {}

	public function init(): void {
		$this->includes();
		$this->boot_modules();
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	private function includes(): void {
		require_once PEPTIDE_STORE_PATH . 'includes/class-compliance.php';
		require_once PEPTIDE_STORE_PATH . 'includes/class-schema.php';
		require_once PEPTIDE_STORE_PATH . 'includes/class-gateways.php';
		require_once PEPTIDE_STORE_PATH . 'includes/class-coa.php';
		require_once PEPTIDE_STORE_PATH . 'includes/class-calculator.php';
		require_once PEPTIDE_STORE_PATH . 'includes/class-faq.php';
		require_once PEPTIDE_STORE_PATH . 'includes/class-blog.php';
		require_once PEPTIDE_STORE_PATH . 'includes/class-contact.php';
		require_once PEPTIDE_STORE_PATH . 'includes/class-content-seeder.php';
		require_once PEPTIDE_STORE_PATH . 'includes/class-storefront.php';
		require_once PEPTIDE_STORE_PATH . 'includes/class-product-page.php';
		require_once PEPTIDE_STORE_PATH . 'includes/class-blog-importer.php';
		require_once PEPTIDE_STORE_PATH . 'includes/class-gateway-compat.php';
		require_once PEPTIDE_STORE_PATH . 'includes/class-blocks-support.php';
		require_once PEPTIDE_STORE_PATH . 'includes/class-checkout-logos.php';
		require_once PEPTIDE_STORE_PATH . 'includes/class-crypto-discount.php';
		require_once PEPTIDE_STORE_PATH . 'includes/class-welcome-discount.php';
		require_once PEPTIDE_STORE_PATH . 'includes/class-account-promo.php';
		require_once PEPTIDE_STORE_PATH . 'includes/email/class-provider-base.php';
		require_once PEPTIDE_STORE_PATH . 'includes/email/class-provider-local.php';
		require_once PEPTIDE_STORE_PATH . 'includes/email/class-provider-mailchimp.php';
		require_once PEPTIDE_STORE_PATH . 'includes/email/class-provider-klaviyo.php';
		require_once PEPTIDE_STORE_PATH . 'includes/class-email-capture.php';
	}

	private function boot_modules(): void {
		new Compliance();
		new Schema();
		new Gateways();
		new COA();
		new Calculator();
		new FAQ();
		new Blog();
		new Contact();
		new Content_Seeder();
		new Storefront();
		new Product_Page();
		new Blog_Importer();
		new Gateway_Compat();
		new Blocks_Support();
		new Checkout_Logos();
		new Crypto_Discount();
		new Welcome_Discount();
		new Account_Promo();
		new Email_Capture();
	}

	public function enqueue_assets(): void {
		wp_enqueue_style(
			'peptidestore-compliance',
			PEPTIDE_STORE_URL . 'assets/css/compliance.css',
			array(), PEPTIDE_STORE_VERSION
		);
		wp_enqueue_script(
			'peptidestore-age-gate',
			PEPTIDE_STORE_URL . 'assets/js/age-gate.js',
			array(), PEPTIDE_STORE_VERSION, true
		);
	}
}
