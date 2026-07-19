<?php
/**
 * Storefront display tweaks.
 *
 *  - Strips parenthetical chemical names from product titles on the front end
 *    ("Semax (Met-Glu-His-Phe-Pro-Gly-Pro)" → "Semax"). Non-destructive: the
 *    stored post_title is untouched, so admin/orders keep the full name.
 *  - Outputs a small category badge in the shop loop (above the title). The
 *    badge's colour per category is styled in the theme (brand.css).
 *
 * @package Peptide_Store
 */
namespace Peptide_Store;

defined( 'ABSPATH' ) || exit;

class Storefront {

	public function __construct() {
		add_filter( 'the_title', array( $this, 'clean_product_title' ), 10, 2 );
		// Fires after the product image/link closes and just before the title,
		// placing the badge underneath the picture and above the product name.
		add_action( 'woocommerce_shop_loop_item_title', array( $this, 'render_category_badge' ), 9 );
	}

	/** Remove "(...)" groups from product titles on the front end only. */
	public function clean_product_title( $title, $post_id = 0 ) {
		if ( is_admin() || ! $post_id ) {
			return $title;
		}
		if ( 'product' !== get_post_type( $post_id ) ) {
			return $title;
		}
		if ( false === strpos( $title, '(' ) ) {
			return $title;
		}
		$clean = trim( (string) preg_replace( '/\s*\([^)]*\)/', '', $title ) );
		return '' !== $clean ? $clean : $title;
	}

	/** Print a colour-coded category pill for the current loop product. */
	public function render_category_badge(): void {
		global $product;
		if ( ! $product instanceof \WC_Product ) {
			return;
		}
		$terms = get_the_terms( $product->get_id(), 'product_cat' );
		if ( ! $terms || is_wp_error( $terms ) ) {
			return;
		}

		$term = null;
		foreach ( $terms as $t ) {
			if ( 'uncategorized' !== $t->slug ) {
				$term = $t;
				break;
			}
		}
		if ( ! $term ) {
			return;
		}

		// Shorten "Recovery & Repair Research" → "Recovery & Repair" for the pill;
		// a couple of verbose categories get an explicit shorter label.
		$short = array(
			'gh-secretagogue-research' => 'Growth Hormone',
		);
		$label = $short[ $term->slug ] ?? trim( (string) preg_replace( '/\s*Research$/i', '', $term->name ) );

		printf(
			'<span class="psc-cat-badge psc-cat-%1$s">%2$s</span>',
			esc_attr( $term->slug ),
			esc_html( $label )
		);
	}
}
