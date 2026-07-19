<?php
/**
 * Content Seeder.
 *
 * One-time, idempotent routine that scaffolds the store's structural pages
 * and the primary navigation menu — the same pattern WooCommerce uses to
 * create its Cart/Checkout/My-Account pages on activation.
 *
 * Creates (only if missing — never overwrites edited content):
 *   - Learning Hub, Shipping Policy, Return Policy, Payment Instructions,
 *     Contact Us, FAQs pages.
 * Rebuilds the live header menu into:
 *   Shop · Learn ▸ (Learning Hub, Blog) · Peptide Calculator ·
 *   Help & Support ▸ (FAQs, Shipping Policy, Return Policy,
 *   Payment Instructions, Contact Us)
 *
 * Re-run by bumping SEED_VERSION. All copy is original and research-framed —
 * no health/therapeutic claims, no third-party text reproduced verbatim.
 *
 * @package Peptide_Store
 */
namespace Peptide_Store;

defined( 'ABSPATH' ) || exit;

class Content_Seeder {

	/** Bump this string to force the seeder to run again. */
	const SEED_VERSION = '4';

	const OPTION_KEY = 'peptidestore_content_seed_version';

	public function __construct() {
		add_action( 'init', array( $this, 'maybe_seed' ), 20 );
	}

	public function maybe_seed(): void {
		if ( get_option( self::OPTION_KEY ) === self::SEED_VERSION ) {
			return;
		}
		try {
			$pages = $this->ensure_pages();
			$this->maybe_repair_learning_hub( $pages );
			$this->maybe_repair_shipping( $pages );
			$this->build_menu( $pages );
		} catch ( \Throwable $e ) {
			if ( function_exists( 'error_log' ) ) {
				error_log( '[peptidestore] content seeder error: ' . $e->getMessage() );
			}
		}
		// Set the flag regardless so we never loop on every front-end request.
		update_option( self::OPTION_KEY, self::SEED_VERSION );
	}

	// ── Pages ───────────────────────────────────────────────────────────────

	/** @return array<string,int> slug => page ID */
	private function ensure_pages(): array {
		$ids = array();

		// FAQs + Calculator may already exist (driven by plugin shortcodes).
		$ids['faqs'] = $this->find_page_with_shortcode( 'peptidestore_faqs' );
		if ( ! $ids['faqs'] ) {
			$ids['faqs'] = $this->ensure_page( 'faqs', 'FAQs', $this->content_faqs() );
		}

		$ids['calculator'] = $this->find_page_with_shortcode( 'peptidestore_calculator' );
		if ( ! $ids['calculator'] ) {
			$ids['calculator'] = $this->ensure_page( 'peptide-calculator', 'Peptide Calculator', '[peptidestore_calculator]' );
		}

		$faq_url  = $ids['faqs'] ? get_permalink( $ids['faqs'] ) : home_url( '/faq/' );
		$calc_url = $ids['calculator'] ? get_permalink( $ids['calculator'] ) : home_url( '/peptide-calculator/' );
		$blog_url = $this->blog_url();

		$ids['learning-hub']        = $this->ensure_page( 'learning-hub', 'Learning Hub', $this->content_learning_hub( $faq_url, $calc_url, $blog_url ) );
		$ids['shipping-policy']     = $this->ensure_page( 'shipping-policy', 'Shipping Policy', $this->content_shipping() );
		$ids['return-policy']       = $this->ensure_page( 'return-policy', 'Return Policy', $this->content_returns() );
		$ids['payment-instructions'] = $this->ensure_page( 'payment-instructions', 'Payment Instructions', $this->content_payment() );
		$ids['contact']             = $this->ensure_page( 'contact', 'Contact Us', $this->content_contact() );

		return $ids;
	}

	/** Return an existing published page by slug, or create it. */
	private function ensure_page( string $slug, string $title, string $content ): int {
		$existing = get_page_by_path( $slug );
		if ( $existing instanceof \WP_Post ) {
			return (int) $existing->ID;
		}
		$id = wp_insert_post( array(
			'post_title'   => $title,
			'post_name'    => $slug,
			'post_content' => $content,
			'post_status'  => 'publish',
			'post_type'    => 'page',
			'comment_status' => 'closed',
		) );
		return is_wp_error( $id ) ? 0 : (int) $id;
	}

	/** Find the first published page whose content contains a given shortcode. */
	private function find_page_with_shortcode( string $shortcode ): int {
		$pages = get_posts( array(
			'post_type'   => 'page',
			'post_status' => 'publish',
			'numberposts' => -1,
		) );
		foreach ( $pages as $p ) {
			if ( has_shortcode( $p->post_content, $shortcode ) ) {
				return (int) $p->ID;
			}
		}
		return 0;
	}

	// ── Menu ────────────────────────────────────────────────────────────────

	/** @param array<string,int> $pages */
	private function build_menu( array $pages ): void {
		$menu_id = $this->resolve_target_menu();
		if ( ! $menu_id ) {
			return;
		}

		// Capture the existing Blog/Research-Library destination before we clear,
		// so the new "Blog" item points wherever it already pointed.
		$blog_url = $this->detect_blog_url( $menu_id );

		// Resolve top-level link targets.
		$shop_id  = function_exists( 'wc_get_page_id' ) ? (int) wc_get_page_id( 'shop' ) : 0;

		// Clear all existing items from this menu (deterministic rebuild).
		$existing = wp_get_nav_menu_items( $menu_id );
		if ( $existing ) {
			foreach ( $existing as $item ) {
				wp_delete_post( $item->ID, true );
			}
		}

		$pos = 1;

		// Shop.
		if ( $shop_id > 0 ) {
			$this->add_page_item( $menu_id, 'Shop', $shop_id, 0, $pos++ );
		} else {
			$this->add_link_item( $menu_id, 'Shop', home_url( '/shop/' ), 0, $pos++ );
		}

		// Peptide Calculator.
		$this->add_page_item( $menu_id, 'Peptide Calculator', $pages['calculator'], 0, $pos++ );

		// Learn ▸ Learning Hub, Blog.
		$learn = $this->add_link_item( $menu_id, 'Learn', '#', 0, $pos++ );
		$this->add_page_item( $menu_id, 'Learning Hub', $pages['learning-hub'], $learn, 1 );
		$this->add_link_item( $menu_id, 'Blog', $blog_url, $learn, 2 );

		// Help & Support ▸ FAQs, Shipping, Return, Payment, Contact.
		$help = $this->add_link_item( $menu_id, 'Help & Support', '#', 0, $pos++ );
		$this->add_page_item( $menu_id, 'FAQs', $pages['faqs'], $help, 1 );
		$this->add_page_item( $menu_id, 'Shipping Policy', $pages['shipping-policy'], $help, 2 );
		$this->add_page_item( $menu_id, 'Return Policy', $pages['return-policy'], $help, 3 );
		$this->add_page_item( $menu_id, 'Payment Instructions', $pages['payment-instructions'], $help, 4 );
		$this->add_page_item( $menu_id, 'Contact Us', $pages['contact'], $help, 5 );
	}

	/**
	 * Find the menu actually shown in the header by looking for the live items
	 * ("Shop" / "Research Library"). Falls back to the 'primary' location, then
	 * to creating a menu.
	 */
	private function resolve_target_menu(): int {
		$menus = wp_get_nav_menus();
		foreach ( $menus as $menu ) {
			$items = wp_get_nav_menu_items( $menu->term_id );
			if ( ! $items ) {
				continue;
			}
			foreach ( $items as $item ) {
				$title = strtolower( trim( $item->title ) );
				if ( 'shop' === $title || false !== strpos( $title, 'research library' ) ) {
					return (int) $menu->term_id;
				}
			}
		}

		$locations = get_nav_menu_locations();
		if ( ! empty( $locations['primary'] ) ) {
			return (int) $locations['primary'];
		}

		// Nothing to work with — create a menu and assign it to 'primary'.
		$menu_id = wp_create_nav_menu( 'Primary' );
		if ( is_wp_error( $menu_id ) ) {
			return 0;
		}
		$locations['primary'] = (int) $menu_id;
		set_theme_mod( 'nav_menu_locations', $locations );
		return (int) $menu_id;
	}

	/** Reuse the existing Blog / Research Library destination if we can find one. */
	private function detect_blog_url( int $menu_id ): string {
		$items = wp_get_nav_menu_items( $menu_id );
		if ( $items ) {
			foreach ( $items as $item ) {
				$title = strtolower( trim( $item->title ) );
				if ( false !== strpos( $title, 'research library' ) || 'blog' === $title || false !== strpos( $title, 'news' ) ) {
					if ( ! empty( $item->url ) && '#' !== $item->url ) {
						return $item->url;
					}
				}
			}
		}
		// Fallbacks: static posts page, else site root (blog on front).
		$posts_page = (int) get_option( 'page_for_posts' );
		if ( $posts_page > 0 ) {
			return get_permalink( $posts_page );
		}
		return home_url( '/' );
	}

	private function add_page_item( int $menu_id, string $title, int $page_id, int $parent, int $position ): int {
		if ( $page_id <= 0 ) {
			return 0;
		}
		$id = wp_update_nav_menu_item( $menu_id, 0, array(
			'menu-item-title'     => $title,
			'menu-item-object'    => 'page',
			'menu-item-object-id' => $page_id,
			'menu-item-type'      => 'post_type',
			'menu-item-status'    => 'publish',
			'menu-item-parent-id' => $parent,
			'menu-item-position'  => $position,
		) );
		return is_wp_error( $id ) ? 0 : (int) $id;
	}

	private function add_link_item( int $menu_id, string $title, string $url, int $parent, int $position ): int {
		$id = wp_update_nav_menu_item( $menu_id, 0, array(
			'menu-item-title'     => $title,
			'menu-item-url'       => $url,
			'menu-item-type'      => 'custom',
			'menu-item-status'    => 'publish',
			'menu-item-parent-id' => $parent,
			'menu-item-position'  => $position,
		) );
		return is_wp_error( $id ) ? 0 : (int) $id;
	}

	// ── Page content (original, compliant, research-framed) ───────────────────

	/** Best-effort blog index URL (menu-independent), used for in-content links. */
	private function blog_url(): string {
		$rl = get_page_by_path( 'research-library' );
		if ( $rl instanceof \WP_Post ) {
			return get_permalink( $rl );
		}
		$posts_page = (int) get_option( 'page_for_posts' );
		if ( $posts_page > 0 ) {
			return get_permalink( $posts_page );
		}
		return home_url( '/' );
	}

	/**
	 * One-time repair: the first seed run wrote the Learning Hub with guessed
	 * slugs (/faqs/, /peptide-calculator/) that 404. If the page is still in
	 * that unedited state, rewrite it with correctly-resolved links. Once the
	 * stale markers are gone we never touch it again, so owner edits are safe.
	 *
	 * @param array<string,int> $pages
	 */
	private function maybe_repair_learning_hub( array $pages ): void {
		$page_id = $pages['learning-hub'] ?? 0;
		if ( $page_id <= 0 ) {
			return;
		}
		$post = get_post( $page_id );
		if ( ! $post ) {
			return;
		}
		$stale = ( false !== strpos( $post->post_content, '"/faqs/"' ) || false !== strpos( $post->post_content, '"/peptide-calculator/"' ) );
		if ( ! $stale ) {
			return;
		}
		$faq_url  = $pages['faqs'] ? get_permalink( $pages['faqs'] ) : home_url( '/faq/' );
		$calc_url = $pages['calculator'] ? get_permalink( $pages['calculator'] ) : home_url( '/peptide-calculator/' );
		wp_update_post( array(
			'ID'           => $page_id,
			'post_content' => $this->content_learning_hub( $faq_url, $calc_url, $this->blog_url() ),
		) );
	}

	/**
	 * One-time restructure of the Shipping Policy from the earlier 6-header
	 * draft to a compact intro + 2-header layout. Only rewrites if the page
	 * still contains the old auto-generated headers (so owner edits are safe).
	 *
	 * @param array<string,int> $pages
	 */
	private function maybe_repair_shipping( array $pages ): void {
		$page_id = $pages['shipping-policy'] ?? 0;
		if ( $page_id <= 0 ) {
			return;
		}
		$post = get_post( $page_id );
		if ( ! $post ) {
			return;
		}
		$stale = ( false !== strpos( $post->post_content, 'Shipping Destinations' ) || false !== strpos( $post->post_content, 'Transfer of Risk' ) );
		if ( ! $stale ) {
			return;
		}
		wp_update_post( array(
			'ID'           => $page_id,
			'post_content' => $this->content_shipping(),
		) );
	}

	private function content_faqs(): string {
		return '<p>Answers to common questions about ordering, shipping, payment, storage, and Certificates of Analysis. If your question is not answered here, please <a href="/contact/">contact us</a>.</p>'
			. "\n\n[peptidestore_faqs]";
	}

	private function content_learning_hub( string $faq_url, string $calc_url, string $blog_url ): string {
		$faq  = esc_url( $faq_url );
		$calc = esc_url( $calc_url );
		$blog = esc_url( $blog_url );
		return <<<HTML
<p>Welcome to the Brand Pharma Learning Hub — a growing collection of plain-language, research-framed resources for qualified researchers. Everything here is educational in nature and is not medical, veterinary, or legal advice.</p>

<h2>Start Here</h2>
<ul>
<li><a href="{$faq}">Frequently Asked Questions</a> — ordering, shipping, payment, storage, and Certificates of Analysis.</li>
<li><a href="{$calc}">Peptide Reconstitution Calculator</a> — a tool for working through reconstitution volumes in a research setting.</li>
</ul>

<h2>Quality &amp; Testing</h2>
<p>Every batch we supply is independently analysed by HPLC-MS, and a Certificate of Analysis (COA) confirming identity and purity is available. Learning how to read a COA — identity, batch number, measured purity, and the issuing laboratory — is one of the most useful skills for evaluating any research compound.</p>

<h2>Storage Basics</h2>
<p>Lyophilized (freeze-dried) research peptides are generally most stable when stored frozen, sealed, and away from light and humidity. Once reconstituted, handling and stability vary by compound — always consult the peer-reviewed literature specific to the material you are working with.</p>

<h2>From the Blog</h2>
<p>Longer educational articles are published on our <a href="{$blog}">blog</a>.</p>

<p><em>All products are supplied for laboratory and research use only. Not for human or animal consumption.</em></p>
HTML;
	}

	private function content_shipping(): string {
		return <<<'HTML'
<p>Orders are prepared and dispatched Monday through Friday, excluding statutory holidays, and ship within Canada. Orders placed before 2:00&nbsp;PM&nbsp;ET are typically processed the same business day; orders received after that time are processed the next business day.</p>
<p>Estimated transit time is approximately 2–10 business days. These are carrier estimates and are not guaranteed; delivery may occasionally fall outside the estimated window for reasons beyond our control, such as carrier delays.</p>
<p>Packages are handed to the carrier on business days and are usually collected in the afternoon. Tracking may remain in a "label created" status until the carrier physically scans the package into its network. A tracking number is emailed once your order has been dispatched.</p>
<p>Once an order has been handed to the carrier, responsibility for loss, delay, damage, or misdelivery passes to the customer.</p>

<h2>Address Accuracy</h2>
<p>Customers are responsible for providing a complete and accurate delivery address. If a shipment is returned to us because of an incorrect or incomplete address, it can be re-shipped only after any applicable return and re-ship costs have been paid.</p>

<h2>Lost, Missing, or Misdelivered Packages</h2>
<p>If tracking does not show delivery confirmation, we will open an inquiry with the carrier. Any replacement or refund is contingent on the outcome of that investigation.</p>
<p>Where tracking shows the package was delivered to the address provided, we cannot accept responsibility for theft or misdelivery occurring after delivery, and no automatic replacement or refund applies.</p>

<p><em>All products are supplied for laboratory and research use only. Not for human or animal consumption.</em></p>
HTML;
	}

	private function content_returns(): string {
		return <<<'HTML'
<h2>All Sales Are Final</h2>
<p>Because our products are research-grade compounds whose stability and integrity depend on proper storage, handling, and environmental conditions, we are unable to accept returns, exchanges, or refunds once an order has left our facility.</p>

<h2>Order Changes &amp; Cancellations</h2>
<p>Orders cannot be modified or cancelled once payment has been accepted, including prior to shipment.</p>

<h2>Damaged or Defective Items</h2>
<p>A limited exception applies to a verified manufacturing defect, or to damage that occurred before shipment. To be considered, contact us within 72 hours of delivery and include:</p>
<ul>
<li>Your order number</li>
<li>A clear description of the issue</li>
<li>Clear photographs of the product and its packaging</li>
<li>Photographs of the shipping box, if the damage occurred in transit</li>
</ul>
<p>Requests received after the 72-hour window cannot be reviewed.</p>

<h2>Resolution</h2>
<p>If a claim is approved, we may provide a replacement, store credit, or refund at our discretion. Approval is not guaranteed.</p>

<h2>Situations Not Eligible</h2>
<p>Returns are not available for reasons including improper storage or handling, shipping delays, ordering errors, or change of mind.</p>

<p><em>All products are supplied for laboratory and research use only. Not for human or animal consumption.</em></p>
HTML;
	}

	private function content_payment(): string {
		return <<<'HTML'
<h2>Interac e-Transfer (Canada)</h2>
<p>Our primary payment method is Interac e-Transfer — a secure bank-to-bank transfer available through most Canadian online banking platforms.</p>

<h2>How to Pay</h2>
<ol>
<li>Select <strong>Interac e-Transfer</strong> as your payment method at checkout and place your order.</li>
<li>You will receive a confirmation email containing your order details and the recipient e-Transfer address.</li>
<li>Log in to your online banking and send the e-Transfer to the address provided, making sure the amount matches your order total exactly.</li>
<li>Enter your <strong>order number</strong> in the message/memo field so we can match your payment to your order.</li>
</ol>

<h2>Processing Time</h2>
<p>Payments are verified on receipt. Orders are typically processed within 1–2 business days of payment confirmation, and tracking information is emailed once your order ships.</p>

<h2>Need Help?</h2>
<p>If you have a question about payment, or did not receive your confirmation email, please <a href="/contact/">contact us</a>.</p>
HTML;
	}

	private function content_contact(): string {
		return <<<'HTML'
<p>Have a question about an order, a product, or a Certificate of Analysis? Send us a message using the form below and our team will respond, typically within one business day.</p>

[peptidestore_contact_form]
HTML;
	}
}
