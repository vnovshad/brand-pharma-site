<?php
/**
 * NewBrand Azure — Botiga child theme functions.
 *
 * Visual layer only (V4 Banded Azure, token-driven). ALL store functionality
 * lives in the peptidestore-core plugin; the functional hooks below are config
 * carried over from the previous child theme (shipping, per-page, compliance
 * toggles), never store logic. Brand identity is rendered from get_bloginfo()
 * so the naming gate is a wp search-replace, never a code edit.
 *
 * @package NewBrand_Azure
 */
defined( 'ABSPATH' ) || exit;

/* =========================================================================
   VISUAL LAYER — fonts + tokens + components
   ========================================================================= */
add_action( 'wp_enqueue_scripts', 'nbazure_fonts', 5 );
function nbazure_fonts(): void {
	wp_enqueue_style(
		'nbazure-fonts',
		'https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&family=IBM+Plex+Mono:wght@400;500&display=swap',
		array(),
		null
	);
}

add_action( 'wp_enqueue_scripts', 'nbazure_styles', 20 );
function nbazure_styles(): void {
	$dir = get_stylesheet_directory_uri();
	$ver = wp_get_theme()->get( 'Version' );
	// tokens first, then components (which reference the tokens).
	wp_enqueue_style( 'nbazure-tokens', $dir . '/tokens.css', array( 'botiga-style-min', 'nbazure-fonts' ), $ver );
	wp_enqueue_style( 'nbazure-azure', $dir . '/azure.css', array( 'nbazure-tokens' ), $ver );
}

add_action( 'after_setup_theme', 'nbazure_setup' );
function nbazure_setup(): void {
	add_theme_support( 'wc-product-gallery-zoom' );
	add_theme_support( 'wc-product-gallery-lightbox' );
	add_theme_support( 'wc-product-gallery-slider' );
}

/* =========================================================================
   PRODUCT IMAGERY — Banded Azure image frames + neutral placeholder
   -------------------------------------------------------------------------
   The layout HOLDS product images on every surface (shop loop, single
   gallery, cart, checkout). There is no real product media in the library
   yet, so every product image falls back to the neutral in-design
   placeholder (img/placeholder.svg) framed by azure.css. The fallback is
   keyed on the image FILE being missing, so the moment a real brand image is
   uploaded and set as the product image it shows with no code change — swap
   H3 is a pure drop-in, never a redesign. All rendering is native
   WooCommerce; peptidestore-core is untouched.
   ========================================================================= */

// Neutral in-design placeholder for any "no image" product surface — shop
// loop, single gallery, and the cart + checkout blocks via the Store API
// (which localises this same src to the block placeholder).
add_filter( 'woocommerce_placeholder_img_src', 'nbazure_placeholder_src' );
function nbazure_placeholder_src( $src ) {
	return get_stylesheet_directory_uri() . '/img/placeholder.svg';
}

// The src filter alone is bypassed when WooCommerce has a placeholder
// ATTACHMENT set (its stock grey webp), so override the built <img> HTML too.
// This is the loop / single gallery / homepage path; the Store API cart +
// checkout blocks read the src filter above.
add_filter( 'woocommerce_placeholder_img', 'nbazure_placeholder_html', 10, 3 );
function nbazure_placeholder_html( $html, $size, $dimensions ) {
	$src = get_stylesheet_directory_uri() . '/img/placeholder.svg';
	$w   = ( is_array( $dimensions ) && ! empty( $dimensions['width'] ) ) ? (int) $dimensions['width'] : 0;
	$h   = ( is_array( $dimensions ) && ! empty( $dimensions['height'] ) ) ? (int) $dimensions['height'] : 0;
	return sprintf(
		'<img src="%s" alt="%s" class="woocommerce-placeholder wp-post-image"%s%s decoding="async" />',
		esc_url( $src ),
		esc_attr__( 'Product image', 'newbrand-azure' ),
		$w ? ' width="' . $w . '"' : '',
		$h ? ' height="' . $h . '"' : ''
	);
}

// A product image id only counts if its file is actually on disk. Pruned
// media leaves dead thumbnail pointers behind; those resolve to 0 so
// WooCommerce renders the placeholder above, while a real uploaded image
// passes straight through. Runs on the frontend/REST 'view' path only, so
// the admin product editor still shows and manages the real image ids.
add_filter( 'woocommerce_product_get_image_id', 'nbazure_image_id_if_present', 10, 2 );
function nbazure_image_id_if_present( $image_id, $product ) {
	return nbazure_attachment_file_exists( $image_id ) ? $image_id : 0;
}

add_filter( 'woocommerce_product_get_gallery_image_ids', 'nbazure_gallery_ids_if_present', 10, 2 );
function nbazure_gallery_ids_if_present( $ids, $product ) {
	if ( empty( $ids ) || ! is_array( $ids ) ) {
		return $ids;
	}
	return array_values( array_filter( $ids, 'nbazure_attachment_file_exists' ) );
}

/* -------------------------------------------------------------------------
   The same missing-file fallback for the BRANDING surfaces (QA span
   2026-07-19). The strip pruned upload files but left attachment pointers:
   custom_logo -> logo_blue.png and site_icon -> brand-favicon.png both
   404'd on every page (broken tab icon; a dead logo fetch hidden by CSS).
   Each filter passes a REAL file straight through, so swap A2 is a pure
   drop-in: upload logo/favicon, set them in the Customizer, done.
   ------------------------------------------------------------------------- */

// Header logo: a custom-logo id counts only if its file exists; a dead
// pointer resolves to 0 and botiga renders the styled TEXT site-title.
add_filter( 'theme_mod_custom_logo', 'nbazure_logo_if_present' );
function nbazure_logo_if_present( $id ) {
	return ( $id && nbazure_attachment_file_exists( (int) $id ) ) ? $id : 0;
}

// Site icon (favicon): dead pointer -> the in-design placeholder SVG, so the
// tab icon is never broken; a real site icon passes through untouched.
add_filter( 'get_site_icon_url', 'nbazure_site_icon_if_present', 10, 3 );
function nbazure_site_icon_if_present( $url, $size, $blog_id ) {
	$id = (int) get_option( 'site_icon' );
	if ( $id && nbazure_attachment_file_exists( $id ) ) {
		return $url;
	}
	return get_stylesheet_directory_uri() . '/img/placeholder.svg';
}

// e-Transfer gateway icon: peptidestore-core points it at an Interac logo in
// uploads that the strip pruned (404 on checkout). The plugin's OWN filter is
// the extension point: empty icon while the file is missing, auto-restored
// the moment the file exists. Covers classic AND blocks checkout; plugin untouched.
add_filter( 'peptidestore_etransfer_icon', 'nbazure_etransfer_icon_if_present' );
function nbazure_etransfer_icon_if_present( $url ) {
	$up = wp_get_upload_dir();
	if ( is_string( $url ) && 0 === strpos( $url, $up['baseurl'] ) ) {
		$path = $up['basedir'] . substr( $url, strlen( $up['baseurl'] ) );
		if ( ! file_exists( $path ) ) {
			return '';
		}
	}
	return $url;
}

// Belt for OTHER gateways' classic-page icons: drop any icon <img> whose
// uploads file is missing (blocks checkout serializes the raw property and
// bypasses this, hence the source-level filter above for e-Transfer).
add_filter( 'woocommerce_gateway_icon', 'nbazure_gateway_icon_if_present', 10, 2 );
function nbazure_gateway_icon_if_present( $icon_html, $gateway_id ) {
	if ( ! $icon_html || ! preg_match_all( '/src="([^"]+)"/', $icon_html, $m ) ) {
		return $icon_html;
	}
	$up = wp_get_upload_dir();
	foreach ( $m[1] as $src ) {
		if ( 0 === strpos( $src, $up['baseurl'] ) ) {
			$path = $up['basedir'] . substr( $src, strlen( $up['baseurl'] ) );
			if ( ! file_exists( $path ) ) {
				return '';
			}
		}
	}
	return $icon_html;
}

/** True only when the attachment's underlying file exists on disk (memoised). */
function nbazure_attachment_file_exists( $attachment_id ): bool {
	$attachment_id = (int) $attachment_id;
	if ( $attachment_id <= 0 ) {
		return false;
	}
	static $cache = array();
	if ( ! array_key_exists( $attachment_id, $cache ) ) {
		$path                    = get_attached_file( $attachment_id );
		$cache[ $attachment_id ] = ( $path && file_exists( $path ) );
	}
	return $cache[ $attachment_id ];
}

/* =========================================================================
   FUNCTIONAL LAYER — carried over config (NOT store logic; that is the plugin)
   ========================================================================= */

// Show all products on shop/archive (no pagination).
add_filter( 'loop_shop_per_page', function () { return 100; }, 20 );
add_action( 'pre_get_posts', function ( $query ) {
	if ( ! is_admin() && $query->is_main_query() &&
		( $query->is_post_type_archive( 'product' ) || $query->is_tax( get_object_taxonomies( 'product' ) ) ) ) {
		$query->set( 'posts_per_page', 100 );
		$query->set( 'nopaging', true );
	}
} );

// Show all blog posts on one page.
add_action( 'pre_get_posts', function ( $query ) {
	if ( ! is_admin() && $query->is_main_query() && $query->is_home() ) {
		$query->set( 'posts_per_page', -1 );
		$query->set( 'nopaging', true );
	}
} );

// Core sitemaps off (Rank Math owns sitemaps).
add_filter( 'wp_sitemaps_enabled', '__return_false' );

// Compliance: age gate stays disabled (carried config).
add_filter( 'peptidestore_enable_age_gate', '__return_false' );

// Remove "My account" from the primary nav (redundant with the header icon).
add_filter( 'wp_nav_menu_objects', 'nbazure_remove_my_account_item', 10, 2 );
function nbazure_remove_my_account_item( $items, $args ) {
	if ( empty( $args->theme_location ) || 'primary' !== $args->theme_location ) {
		return $items;
	}
	foreach ( $items as $key => $item ) {
		if ( ! empty( $item->url ) && false !== strpos( $item->url, '/my-account' ) ) {
			unset( $items[ $key ] );
		}
	}
	return $items;
}

// Show each product's size (pa_size, e.g. "10 mg") on shop/loop cards.
add_action( 'woocommerce_after_shop_loop_item_title', 'nbazure_card_size', 9 );
function nbazure_card_size(): void {
	global $product;
	if ( ! $product instanceof WC_Product ) {
		return;
	}
	$sizes = wc_get_product_terms( $product->get_id(), 'pa_size', array( 'fields' => 'names' ) );
	if ( ! empty( $sizes ) ) {
		echo '<div class="nb-card-size">' . esc_html( $sizes[0] ) . '</div>';
	}
}

// Free shipping takes over above the threshold (hide paid rates when free is available).
add_filter( 'woocommerce_package_rates', 'nbazure_free_shipping_takes_over', 100 );
function nbazure_free_shipping_takes_over( $rates ) {
	$free = array();
	foreach ( $rates as $rate_id => $rate ) {
		if ( 'free_shipping' === $rate->get_method_id() ) {
			$free[ $rate_id ] = $rate;
		}
	}
	return ! empty( $free ) ? $free : $rates;
}

// Canada-only notice above the checkout block.
add_filter( 'render_block', 'nbazure_canada_only_notice', 10, 2 );
function nbazure_canada_only_notice( $content, $block ) {
	if ( ( $block['blockName'] ?? '' ) !== 'woocommerce/checkout' ) {
		return $content;
	}
	$msg = esc_html__( 'We ship within Canada only. Orders with a delivery address outside Canada cannot be fulfilled and will not be shipped.', 'newbrand-azure' );
	return '<div class="nb-checkout-notice">' . $msg . '</div>' . $content;
}

/* =========================================================================
   CHROME — announcement bar + footer (Azure styling lives in azure.css)
   Brand name is pulled from get_bloginfo(); no hardcoded brand string.
   ========================================================================= */
add_action( 'wp_body_open', 'nbazure_announcement_bar' );
function nbazure_announcement_bar(): void {
	$messages = array(
		"Canada's most trusted source for research peptides",
		'Third-party HPLC-MS tested, every batch',
		'Free shipping on orders $300 & up',
	);
	$group = '';
	foreach ( $messages as $msg ) {
		$group .= '<span class="nb-ann__item">' . esc_html( $msg ) . '</span>';
		$group .= '<span class="nb-ann__sep" aria-hidden="true">●</span>';
	}
	echo '<div class="nb-ann" role="region" aria-label="' . esc_attr__( 'Site announcements', 'newbrand-azure' ) . '">';
	echo '<div class="nb-ann__track"><div class="nb-ann__group">' . $group . '</div><div class="nb-ann__group" aria-hidden="true">' . $group . '</div></div>';
	echo '</div>';
}

add_action( 'botiga_footer_before', 'nbazure_render_footer' );
function nbazure_render_footer(): void {
	$explore = array(
		'Shop'               => nbazure_shop_url(),
		'Peptide Calculator' => nbazure_page_url( array( 'dosage-calculator' ) ),
		'Learning Hub'       => nbazure_page_url( array( 'learning-hub' ) ),
		'Blog'               => nbazure_page_url( array( 'research-library' ) ),
	);
	$help = array(
		'FAQs'                 => nbazure_page_url( array( 'faq', 'faqs' ) ),
		'Shipping Policy'      => nbazure_page_url( array( 'shipping-policy' ) ),
		'Return Policy'        => nbazure_page_url( array( 'return-policy' ) ),
		'Privacy Policy'       => nbazure_page_url( array( 'privacy-policy' ) ),
		'Terms and Conditions' => nbazure_page_url( array( 'terms-and-conditions' ) ),
		'Payment Instructions' => nbazure_page_url( array( 'payment-instructions' ) ),
		'Contact Us'           => nbazure_page_url( array( 'contact' ) ),
	);
	$brand = get_bloginfo( 'name' );
	?>
	<footer class="nb-footer" role="contentinfo">
		<div class="nb-footer__inner">
			<div class="nb-footer__col nb-footer__brand">
				<div class="nb-footer__wordmark"><?php echo esc_html( $brand ); ?><span>.</span></div>
				<p class="nb-footer__tagline">Canadian-made research compounds, held to a pharmaceutical standard.</p>
				<p class="nb-footer__trust">Made in Canada · Third-party tested · HPLC-MS verified</p>
			</div>
			<nav class="nb-footer__col" aria-label="Explore">
				<h2 class="nb-footer__heading">Explore</h2>
				<ul><?php foreach ( $explore as $label => $url ) : ?><li><a href="<?php echo esc_url( $url ); ?>"><?php echo esc_html( $label ); ?></a></li><?php endforeach; ?></ul>
			</nav>
			<nav class="nb-footer__col" aria-label="Help and Support">
				<h2 class="nb-footer__heading">Help &amp; Support</h2>
				<ul><?php foreach ( $help as $label => $url ) : ?><li><a href="<?php echo esc_url( $url ); ?>"><?php echo esc_html( $label ); ?></a></li><?php endforeach; ?></ul>
			</nav>
			<div class="nb-footer__col">
				<h2 class="nb-footer__heading">Contact</h2>
				<p class="nb-footer__contact-text">Questions about an order or a Certificate of Analysis? Email us and we&rsquo;ll respond, typically within one business day.</p>
				<a class="nb-footer__email" href="<?php echo esc_url( 'mailto:' . get_option( 'admin_email' ) ); ?>"><?php echo esc_html( get_option( 'admin_email' ) ); ?></a>
			</div>
		</div>
		<div class="nb-footer__bottom">
			<p>&copy; <?php echo esc_html( gmdate( 'Y' ) ); ?> <?php echo esc_html( $brand ); ?>. All rights reserved.</p>
			<p class="nb-footer__disclaimer">For laboratory and research use only. Not for human or animal consumption.</p>
		</div>
	</footer>
	<?php
}

/** Shop page URL with a sensible fallback. */
function nbazure_shop_url(): string {
	if ( function_exists( 'wc_get_page_id' ) ) {
		$id = (int) wc_get_page_id( 'shop' );
		if ( $id > 0 ) {
			return get_permalink( $id );
		}
	}
	return home_url( '/shop/' );
}

/** First matching published page (by slug) → permalink, else site root. */
function nbazure_page_url( array $slugs ): string {
	foreach ( $slugs as $slug ) {
		$page = get_page_by_path( $slug );
		if ( $page instanceof WP_Post ) {
			return get_permalink( $page );
		}
	}
	return home_url( '/' );
}
