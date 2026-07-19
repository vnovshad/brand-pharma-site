<?php
/**
 * Schema / GEO module. JSON-LD for SEO + AI-citation visibility.
 *
 * Emits a single @graph per page containing all relevant entities,
 * cross-referenced by @id. WooCommerce's own structured data is suppressed
 * on product pages to prevent duplicate Product/BreadcrumbList output.
 *
 * Page type → entities in @graph:
 *  Product page : Organization + BreadcrumbList + Product
 *  Homepage     : Organization + WebSite
 *  Blog post    : Organization + BreadcrumbList + BlogPosting
 *  Category     : Organization + BreadcrumbList + CollectionPage
 *  Other        : Organization  (+ FAQPage appended when FAQs are registered)
 *
 * Keep schema copy in research-use framing — no health claims.
 *
 * @package Peptide_Store
 */
namespace Peptide_Store;

defined( 'ABSPATH' ) || exit;

class Schema {

	/** @id fragment shared across all entity cross-references. */
	private const ORG_ID = '/#organization';

	public function __construct() {
		add_action( 'wp_head', array( $this, 'output_graph' ), 20 );
		add_filter( 'woocommerce_structured_data_type_for_page', array( $this, 'suppress_wc_schema_on_products' ) );
	}

	/**
	 * Suppress WooCommerce's own standalone structured data on product single
	 * and archive pages (single product, product category, product tag, shop).
	 * Our @graph already emits Organization plus Product / BreadcrumbList /
	 * CollectionPage on those pages, so this leaves exactly one JSON-LD block.
	 *
	 * @param array $types
	 * @return array
	 */
	public function suppress_wc_schema_on_products( array $types ): array {
		if ( ! function_exists( 'is_product' ) ) {
			return $types;
		}
		if ( is_product() || is_product_category() || is_product_tag() || is_shop() ) {
			// Returning [] is falsy in PHP, which causes WooCommerce's get_structured_data()
			// to bypass its type filter and output everything. Return a non-matching sentinel
			// instead: WC finds no queued data matching '__none__' and outputs nothing.
			return array( '__none__' );
		}
		return $types;
	}

	// ── Main entry point ─────────────────────────────────────────────────────

	public function output_graph(): void {
		$entities = array( $this->organization() );

		if ( function_exists( 'is_product' ) && is_product() ) {
			$product = wc_get_product( get_the_ID() );
			if ( $product instanceof \WC_Product ) {
				$entities[] = $this->breadcrumbs_for_product( $product );
				$entities[] = $this->product( $product );
			}
		} elseif ( is_front_page() ) {
			$entities[] = $this->website();
		} elseif ( is_singular( 'post' ) ) {
			$entities[] = $this->breadcrumbs_for_post();
			$entities[] = $this->article();
		} elseif ( is_tax( 'product_cat' ) || is_category() ) {
			$bc = $this->breadcrumbs_for_archive();
			$cp = $this->collection_page();
			if ( $bc ) {
				$entities[] = $bc;
			}
			if ( $cp ) {
				$entities[] = $cp;
			}
		} elseif ( function_exists( 'is_shop' ) && is_shop() ) {
			// Shop is a post-type archive, not a taxonomy, so build a
			// Home > Shop breadcrumb + CollectionPage directly. (product_tag is
			// intentionally left Organization-only: those archives are noindex.)
			$shop_id   = function_exists( 'wc_get_page_id' ) ? (int) wc_get_page_id( 'shop' ) : 0;
			$shop_url  = $shop_id > 0 ? get_permalink( $shop_id ) : home_url( '/shop/' );
			$shop_name = $shop_id > 0 ? html_entity_decode( get_the_title( $shop_id ), ENT_QUOTES | ENT_HTML5, 'UTF-8' ) : 'Shop';
			$entities[] = array(
				'@type'           => 'BreadcrumbList',
				'@id'             => $shop_url . '#breadcrumb',
				'itemListElement' => array(
					array( '@type' => 'ListItem', 'position' => 1, 'name' => 'Home', 'item' => home_url( '/' ) ),
					array( '@type' => 'ListItem', 'position' => 2, 'name' => $shop_name, 'item' => $shop_url ),
				),
			);
			$entities[] = array(
				'@type' => 'CollectionPage',
				'@id'   => $shop_url . '#collection',
				'name'  => $shop_name,
				'url'   => $shop_url,
			);
		}

		$faq = $this->maybe_faq();
		if ( $faq ) {
			$entities[] = $faq;
		}

		$this->print_jsonld( array(
			'@context' => 'https://schema.org',
			'@graph'   => array_values( array_filter( $entities ) ),
		) );
	}

	// ── Shared entities ───────────────────────────────────────────────────────

	private function organization(): array {
		return apply_filters( 'peptidestore_schema_organization', array(
			'@type'        => 'Organization',
			'@id'          => home_url( self::ORG_ID ),
			'name'         => get_bloginfo( 'name' ),
			'url'          => home_url( '/' ),
			'logo'         => array(
				'@type' => 'ImageObject',
				'url'   => home_url( '/wp-content/uploads/2026/06/logo_blue.png' ),
			),
			'description'  => __( 'Canadian supplier of research-use-only peptides, held to a pharmaceutical standard. Every batch is independently verified by HPLC-MS at a third-party laboratory, with a Certificate of Analysis available for each product.', 'peptidestore' ),
			'areaServed'   => 'CA',
			'contactPoint' => array(
				'@type'       => 'ContactPoint',
				'contactType' => 'customer support',
				'url'         => home_url( '/contact/' ),
			),
			'knowsAbout'   => array(
				'Research peptides',
				'Metabolic peptides',
				'GLP-1 and GIP receptor agonists',
				'Tissue repair and recovery peptides',
				'Growth hormone secretagogues',
				'Longevity peptides',
				'Cosmetic and skin peptides',
				'Nootropic peptides',
				'Reproductive and hormonal peptides',
				'Peptide purity testing',
				'HPLC-MS analysis',
				'Certificate of Analysis (COA)',
				'Peptide reconstitution',
			),
		) );
	}

	private function website(): array {
		return array(
			'@type'     => 'WebSite',
			'@id'       => home_url( '/#website' ),
			'url'       => home_url( '/' ),
			'name'      => get_bloginfo( 'name' ),
			'publisher' => array( '@id' => home_url( self::ORG_ID ) ),
		);
	}

	// ── Product ───────────────────────────────────────────────────────────────

	private function product( \WC_Product $product ): array {
		$permalink = get_permalink( $product->get_id() );
		$price     = $product->get_price();
		$currency  = get_woocommerce_currency();

		$data = array(
			'@type'       => 'Product',
			'@id'         => $permalink . '#product',
			'name'        => $product->get_name(),
			'description' => wp_strip_all_tags( $product->get_short_description() ?: $product->get_description() ),
			'sku'         => $product->get_sku(),
			'brand'       => array( '@type' => 'Brand', 'name' => get_bloginfo( 'name' ) ),
			'offers'      => array(
				'@type'              => 'Offer',
				'url'                => $permalink,
				'price'              => $price,
				'priceCurrency'      => $currency,
				'availability'       => $product->is_in_stock() ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock',
				'priceSpecification' => array(
					'@type'         => 'UnitPriceSpecification',
					'price'         => $price,
					'priceCurrency' => $currency,
				),
				'seller'             => array( '@id' => home_url( self::ORG_ID ) ),
			),
		);

		$image_id = $product->get_image_id();
		if ( $image_id ) {
			$src = wp_get_attachment_image_url( $image_id, 'full' );
			if ( $src ) {
				$data['image'] = $src;
			}
		}

		// peptidestore_schema_product filter: used by COA class (adds additionalProperty)
		// and open for future enrichment (purity data, citations, etc.).
		return apply_filters( 'peptidestore_schema_product', $data, $product );
	}

	// ── Article ───────────────────────────────────────────────────────────────

	private function article(): array {
		$post_id = get_the_ID();
		$data    = array(
			'@type'            => 'BlogPosting',
			'@id'              => get_permalink( $post_id ) . '#article',
			'headline'         => get_the_title( $post_id ),
			'datePublished'    => get_the_date( 'c', $post_id ),
			'dateModified'     => get_the_modified_date( 'c', $post_id ),
			'author'           => array( '@id' => home_url( self::ORG_ID ) ),
			'publisher'        => array( '@id' => home_url( self::ORG_ID ) ),
			'mainEntityOfPage' => get_permalink( $post_id ),
		);
		if ( has_post_thumbnail( $post_id ) ) {
			$data['image'] = get_the_post_thumbnail_url( $post_id, 'full' );
		}
		return apply_filters( 'peptidestore_schema_article', $data, $post_id );
	}

	// ── BreadcrumbList builders ───────────────────────────────────────────────

	private function breadcrumbs_for_product( \WC_Product $product ): array {
		$permalink = get_permalink( $product->get_id() );
		$items     = array(
			array( '@type' => 'ListItem', 'position' => 1, 'name' => 'Home', 'item' => home_url( '/' ) ),
		);
		$pos = 2;

		$terms = get_the_terms( $product->get_id(), 'product_cat' );
		if ( $terms && ! is_wp_error( $terms ) ) {
			foreach ( $terms as $term ) {
				if ( 'uncategorized' !== $term->slug ) {
					$link = get_term_link( $term );
					if ( ! is_wp_error( $link ) ) {
						$items[] = array( '@type' => 'ListItem', 'position' => $pos++, 'name' => html_entity_decode( $term->name, ENT_QUOTES | ENT_HTML5, 'UTF-8' ), 'item' => $link );
					}
					break;
				}
			}
		}

		$items[] = array( '@type' => 'ListItem', 'position' => $pos, 'name' => $product->get_name(), 'item' => $permalink );

		return array(
			'@type'           => 'BreadcrumbList',
			'@id'             => $permalink . '#breadcrumb',
			'itemListElement' => $items,
		);
	}

	private function breadcrumbs_for_post(): array {
		$post_id   = get_the_ID();
		$permalink = get_permalink( $post_id );
		$items     = array(
			array( '@type' => 'ListItem', 'position' => 1, 'name' => 'Home', 'item' => home_url( '/' ) ),
		);
		$pos = 2;

		$cats = get_the_category( $post_id );
		if ( $cats ) {
			$link = get_category_link( $cats[0]->term_id );
			if ( ! is_wp_error( $link ) ) {
				$items[] = array( '@type' => 'ListItem', 'position' => $pos++, 'name' => html_entity_decode( $cats[0]->name, ENT_QUOTES | ENT_HTML5, 'UTF-8' ), 'item' => $link );
			}
		}

		$items[] = array( '@type' => 'ListItem', 'position' => $pos, 'name' => get_the_title( $post_id ), 'item' => $permalink );

		return array(
			'@type'           => 'BreadcrumbList',
			'@id'             => $permalink . '#breadcrumb',
			'itemListElement' => $items,
		);
	}

	private function breadcrumbs_for_archive(): ?array {
		$term = get_queried_object();
		if ( ! $term instanceof \WP_Term ) {
			return null;
		}
		$link = get_term_link( $term );
		if ( is_wp_error( $link ) ) {
			return null;
		}
		return array(
			'@type'           => 'BreadcrumbList',
			'@id'             => $link . '#breadcrumb',
			'itemListElement' => array(
				array( '@type' => 'ListItem', 'position' => 1, 'name' => 'Home', 'item' => home_url( '/' ) ),
				array( '@type' => 'ListItem', 'position' => 2, 'name' => html_entity_decode( $term->name, ENT_QUOTES | ENT_HTML5, 'UTF-8' ), 'item' => $link ),
			),
		);
	}

	// ── Collection page (product/blog category archives) ─────────────────────

	private function collection_page(): ?array {
		$term = get_queried_object();
		if ( ! $term instanceof \WP_Term ) {
			return null;
		}
		$link = get_term_link( $term );
		if ( is_wp_error( $link ) ) {
			return null;
		}
		return array(
			'@type' => 'CollectionPage',
			'@id'   => $link . '#collection',
			'name'  => html_entity_decode( $term->name, ENT_QUOTES | ENT_HTML5, 'UTF-8' ),
			'url'   => $link,
		);
	}

	// ── FAQ (optional, appended to any page type) ─────────────────────────────

	private function maybe_faq(): ?array {
		$faqs = apply_filters( 'peptidestore_page_faqs', array(), get_the_ID() );
		if ( empty( $faqs ) || ! is_array( $faqs ) ) {
			return null;
		}
		$entities = array();
		foreach ( $faqs as $faq ) {
			if ( empty( $faq['question'] ) || empty( $faq['answer'] ) ) {
				continue;
			}
			$entities[] = array(
				'@type'          => 'Question',
				'name'           => wp_strip_all_tags( $faq['question'] ),
				'acceptedAnswer' => array( '@type' => 'Answer', 'text' => wp_strip_all_tags( $faq['answer'] ) ),
			);
		}
		if ( empty( $entities ) ) {
			return null;
		}
		return array(
			'@type'      => 'FAQPage',
			'mainEntity' => $entities,
		);
	}

	// ── Output ────────────────────────────────────────────────────────────────

	private function print_jsonld( array $data ): void {
		echo "\n<script type=\"application/ld+json\">" . wp_json_encode( $data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . "</script>\n";
	}
}
