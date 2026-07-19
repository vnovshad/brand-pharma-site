<?php
/**
 * Front page — V4 "Banded Azure" homepage, rendered over live WooCommerce data.
 * Brand name comes from get_bloginfo(); product data from the catalogue. No
 * hardcoded brand string, no store logic (that stays in peptidestore-core).
 *
 * @package NewBrand_Azure
 */
defined( 'ABSPATH' ) || exit;

get_header();

$shop_url = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : home_url( '/shop/' );
$labs_url = nbazure_page_url( array( 'testing', 'lab-reports', 'quality' ) );
?>

<main class="nb-home">

	<!-- HERO (azure band) -->
	<header class="nb-hero">
		<div class="nb-wrap nb-hero__grid">
			<div class="nb-hero__copy">
				<div class="nb-eyebrow">Research peptides · Made in Canada</div>
				<h1 class="nb-hero__title">Pharmaceutical-grade purity, <span class="nb-ac">proven every batch.</span></h1>
				<p class="nb-hero__lead">Research compounds with third-party HPLC-MS verification published for every lot. Synthesized, tested, and shipped from our Canadian facility.</p>
				<div class="nb-hero__cta">
					<a class="nb-btn nb-btn--primary" href="<?php echo esc_url( $shop_url ); ?>">Browse catalogue</a>
					<a class="nb-btn nb-btn--ghost" href="<?php echo esc_url( $labs_url ); ?>">View lab reports</a>
				</div>
			</div>
			<div class="nb-hero__stage">
				<div class="nb-vialcard">
					<div class="nb-vial" aria-hidden="true">
						<div class="nb-vial__cap"></div>
						<div class="nb-vial__neck"></div>
						<div class="nb-vial__glass"><div class="nb-vial__fill"></div></div>
					</div>
					<div class="nb-coa">
						<div><b>HPLC-MS</b>Method</div>
						<div><b>99%+</b>Typical purity</div>
						<div><b>Per lot</b>COA published</div>
						<div><b>Canada</b>Synthesis</div>
					</div>
				</div>
			</div>
		</div>
	</header>

	<!-- TRUST band -->
	<section class="nb-trust">
		<div class="nb-wrap nb-trust__grid">
			<div><b>HPLC-MS tested</b><small>Every batch, third-party verified</small></div>
			<div><b>Made in Canada</b><small>Synthesized and shipped domestically</small></div>
			<div><b>Discreet shipping</b><small>Tracked, 24 h dispatch</small></div>
		</div>
	</section>

	<!-- CATALOGUE (live products as V4 text cards) -->
	<section class="nb-cat">
		<div class="nb-wrap">
			<?php
			$q = new WP_Query( array(
				'post_type'      => 'product',
				'post_status'    => 'publish',
				'posts_per_page' => 9,
				'orderby'        => 'menu_order title',
				'order'          => 'ASC',
				'no_found_rows'  => true,
			) );
			$total = wp_count_posts( 'product' )->publish;
			?>
			<div class="nb-sechead">
				<h2>Catalogue</h2>
				<span><?php echo esc_html( $total ); ?> compounds · all in stock</span>
			</div>
			<div class="nb-grid">
				<?php
				while ( $q->have_posts() ) :
					$q->the_post();
					$product = wc_get_product( get_the_ID() );
					if ( ! $product ) {
						continue;
					}
					$cats  = wc_get_product_terms( get_the_ID(), 'product_cat', array( 'fields' => 'names' ) );
					$cat   = $cats ? preg_replace( '/\s+Research.*/i', '', $cats[0] ) : 'Compound';
					$sizes = wc_get_product_terms( get_the_ID(), 'pa_size', array( 'fields' => 'names' ) );
					$size  = $sizes ? $sizes[0] : '';
					?>
					<article class="nb-card">
						<a class="nb-card__link" href="<?php the_permalink(); ?>">
							<div class="nb-card__media"><?php echo $product->get_image( 'woocommerce_thumbnail' ); // native WC image: placeholder now, real brand image on drop-in ?></div>
							<div class="nb-card__cat"><?php echo esc_html( $cat ); ?></div>
							<h3 class="nb-card__name"><?php the_title(); ?></h3>
							<div class="nb-card__purity">HPLC-MS verified<?php echo $size ? ' · ' . esc_html( $size ) : ''; ?></div>
						</a>
						<div class="nb-card__row">
							<div class="nb-card__price"><?php echo wp_kses_post( $product->get_price_html() ); ?></div>
							<a class="nb-add" href="<?php echo esc_url( '?add-to-cart=' . get_the_ID() ); ?>" data-quantity="1" rel="nofollow">Add</a>
						</div>
					</article>
				<?php endwhile; wp_reset_postdata(); ?>
			</div>
			<div class="nb-cat__more">
				<a class="nb-btn nb-btn--primary" href="<?php echo esc_url( $shop_url ); ?>">Browse all compounds</a>
			</div>
		</div>
	</section>

</main>

<?php
get_footer();
