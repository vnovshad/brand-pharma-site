<?php
/**
 * Peptide Dosage Calculator — shortcode + Gutenberg block.
 * Updated to match the Polar Peptides reference layout:
 *   - Two-row input layout (no section headers)
 *   - Units in label names, no badge suffixes, no mcg/mg dropdown
 *   - "Your Dosage" result heading with numbered instructions
 *   - Syringe visual inside the result card
 *
 * @package Peptide_Store
 */
namespace Peptide_Store;

defined( 'ABSPATH' ) || exit;

class Calculator {

	public function __construct() {
		add_shortcode( 'peptidestore_calculator', array( $this, 'render' ) );
		add_action( 'init',               array( $this, 'register_block' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_script' ) );
	}

	public function enqueue_script(): void {
		wp_enqueue_script(
			'peptidestore-calculator',
			PEPTIDE_STORE_URL . 'assets/js/calculator.js',
			array(),
			PEPTIDE_STORE_VERSION,
			true
		);
	}

	public function register_block(): void {
		wp_register_script( 'peptidestore-calculator-editor', false,
			array( 'wp-blocks', 'wp-element' ), PEPTIDE_STORE_VERSION );
		wp_add_inline_script( 'peptidestore-calculator-editor',
			"( function( blocks, el ) {
				blocks.registerBlockType( 'peptidestore/calculator', {
					title: 'Peptide Dosage Calculator', icon: 'calculator', category: 'widgets',
					edit: function() { return el.createElement( 'div', { className: 'psc-calc-editor-placeholder' },
						el.createElement( 'strong', null, 'Peptide Dosage Calculator' ), ' — renders on the frontend.' ); },
					save: function() { return null; }
				} );
			} )( window.wp.blocks, window.wp.element );" );
		register_block_type( 'peptidestore/calculator', array(
			'editor_script'   => 'peptidestore-calculator-editor',
			'render_callback' => array( $this, 'render' ),
		) );
	}

	public function render( $atts = array() ): string {
		static $n = 0;
		$uid = 'psc-calc-' . ( ++$n );
		ob_start();
		?>
		<div class="psc-calc" data-psc-calc="<?php echo esc_attr( $uid ); ?>">

			<div class="psc-calc__fields">

				<!-- Row 1: two columns -->
				<div class="psc-calc__row psc-calc__row--2">

					<div class="psc-calc__field">
						<label class="psc-calc__label" for="<?php echo esc_attr( $uid ); ?>-sml">
							<?php esc_html_e( 'Syringe Volume (ml)', 'peptidestore' ); ?>
						</label>
						<input type="number" id="<?php echo esc_attr( $uid ); ?>-sml"
							class="psc-calc__input" data-field="syringe_ml"
							min="0.01" step="any" placeholder="e.g. 1" value="1"
							autocomplete="off" />
						<span class="psc-calc__hint"><?php esc_html_e( 'Example: 0.3, 0.5, 1, 2 ml', 'peptidestore' ); ?></span>
					</div>

					<div class="psc-calc__field">
						<label class="psc-calc__label" for="<?php echo esc_attr( $uid ); ?>-tunits">
							<?php esc_html_e( 'Total Units on Syringe', 'peptidestore' ); ?>
						</label>
						<input type="number" id="<?php echo esc_attr( $uid ); ?>-tunits"
							class="psc-calc__input" data-field="total_units"
							min="1" step="1" placeholder="e.g. 100" value="100"
							autocomplete="off" />
						<span class="psc-calc__hint"><?php esc_html_e( 'Example: 30, 50, 100, 200 units', 'peptidestore' ); ?></span>
					</div>

				</div><!-- /.row--2 -->

				<!-- Row 2: three columns -->
				<div class="psc-calc__row psc-calc__row--3">

					<div class="psc-calc__field">
						<label class="psc-calc__label" for="<?php echo esc_attr( $uid ); ?>-mg">
							<?php esc_html_e( 'Peptide Vial Quantity (mg)', 'peptidestore' ); ?>
						</label>
						<input type="number" id="<?php echo esc_attr( $uid ); ?>-mg"
							class="psc-calc__input" data-field="vial_mg"
							min="0.001" step="any" placeholder="e.g. 10"
							autocomplete="off" />
						<span class="psc-calc__hint"><?php esc_html_e( 'Example: 1, 2, 5, 10, 15, 20 mg', 'peptidestore' ); ?></span>
					</div>

					<div class="psc-calc__field">
						<label class="psc-calc__label" for="<?php echo esc_attr( $uid ); ?>-wml">
							<?php esc_html_e( 'Bacteriostatic Water Added (ml)', 'peptidestore' ); ?>
						</label>
						<input type="number" id="<?php echo esc_attr( $uid ); ?>-wml"
							class="psc-calc__input" data-field="water_ml"
							min="0.001" step="any" placeholder="e.g. 2"
							autocomplete="off" />
						<span class="psc-calc__hint"><?php esc_html_e( 'Total volume you added to the vial', 'peptidestore' ); ?></span>
					</div>

					<div class="psc-calc__field">
						<label class="psc-calc__label" for="<?php echo esc_attr( $uid ); ?>-dose">
							<?php esc_html_e( 'Desired Peptide Dose (mg)', 'peptidestore' ); ?>
						</label>
						<input type="number" id="<?php echo esc_attr( $uid ); ?>-dose"
							class="psc-calc__input" data-field="desired_mg"
							min="0.0001" step="any" placeholder="e.g. 0.25"
							autocomplete="off" />
						<span class="psc-calc__hint"><?php esc_html_e( 'Enter any dose in milligrams', 'peptidestore' ); ?></span>
					</div>

				</div><!-- /.row--3 -->

			</div><!-- /.psc-calc__fields -->

			<!-- Result card — syringe always visible; text/instructions shown once inputs are valid -->
			<div class="psc-calc__result" data-output="result" aria-live="polite">

				<h3 class="psc-calc__your-dosage"><?php esc_html_e( 'Your Dosage', 'peptidestore' ); ?></h3>

				<p class="psc-calc__draw-line" data-output="text">
					<?php esc_html_e( 'Enter values above to calculate.', 'peptidestore' ); ?>
				</p>

				<div class="psc-calc__syringe-wrap" data-output="syringe" aria-hidden="true"></div>

				<div class="psc-calc__instructions" data-output="instructions"></div>

			</div><!-- /.psc-calc__result -->

			<p class="psc-calc__disclaimer">
				<?php esc_html_e( 'For research and calculation purposes only. Not for use in human or animal treatment. Verify all calculations independently.', 'peptidestore' ); ?>
			</p>

		</div><!-- /.psc-calc -->

		<section class="psc-recon-guide">
			<h3 class="psc-recon-guide__title"><?php esc_html_e( 'How to Reconstitute Your Peptide', 'peptidestore' ); ?></h3>
			<p class="psc-recon-guide__intro"><?php esc_html_e( 'Reconstitution is the process of dissolving the lyophilized (freeze-dried) research compound in bacteriostatic water to prepare a solution. Follow these steps for laboratory use:', 'peptidestore' ); ?></p>
			<ol class="psc-recon-guide__steps">
				<li><?php esc_html_e( 'Allow the peptide vial and the bacteriostatic water to come to room temperature.', 'peptidestore' ); ?></li>
				<li><?php esc_html_e( 'Wipe the rubber stopper of each vial with a fresh alcohol swab.', 'peptidestore' ); ?></li>
				<li><?php esc_html_e( 'Using a sterile syringe, draw up your chosen volume of bacteriostatic water. Use the calculator above to determine the exact volume.', 'peptidestore' ); ?></li>
				<li><?php esc_html_e( 'Insert the needle into the peptide vial and slowly release the water down the inside wall of the vial. Never spray it directly onto the powder.', 'peptidestore' ); ?></li>
				<li><?php esc_html_e( 'Gently swirl or roll the vial until the powder is fully dissolved and the solution is clear. Do not shake the vial.', 'peptidestore' ); ?></li>
				<li><?php esc_html_e( 'Store the reconstituted vial refrigerated at 2-8 °C and minimise repeated freeze-thaw cycles to preserve sample integrity.', 'peptidestore' ); ?></li>
			</ol>
			<p class="psc-recon-guide__note"><?php esc_html_e( 'For research and laboratory use only. Not for human or animal consumption.', 'peptidestore' ); ?></p>
		</section>
		<?php
		return ob_get_clean();
	}
}
