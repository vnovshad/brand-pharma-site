<?php
/**
 * FAQ module.
 *
 * Registers [peptidestore_faqs] shortcode → renders an accessible
 * <details>/<summary> accordion.
 *
 * Hooks peptidestore_page_faqs: any page containing the shortcode
 * automatically emits FAQPage JSON-LD schema via class-schema.php.
 *
 * All copy is research-use-only framed — no health or therapeutic claims.
 *
 * @package Peptide_Store
 */
namespace Peptide_Store;

defined( 'ABSPATH' ) || exit;

class FAQ {

	public function __construct() {
		add_shortcode( 'peptidestore_faqs', array( $this, 'render_shortcode' ) );
		add_filter( 'peptidestore_page_faqs', array( $this, 'supply_faqs' ), 10, 2 );
	}

	// ── Schema filter ─────────────────────────────────────────────────────────
	// Returns FAQ data for any page that contains [peptidestore_faqs].

	public function supply_faqs( array $faqs, int $post_id ): array {
		// Learning Hub (page 161): its own educational FAQ set, kept distinct from
		// the operational /faq/ page. The visible Q&A is plain HTML in the page
		// body (no [peptidestore_faqs] shortcode), so supply the pairs here to
		// feed FAQPage JSON-LD. Guarded to page 161 only.
		if ( 161 === (int) $post_id ) {
			return self::get_learning_hub();
		}
		$post = get_post( $post_id );
		if ( $post && has_shortcode( $post->post_content, 'peptidestore_faqs' ) ) {
			return self::get_all();
		}
		return $faqs;
	}

	// ── FAQ data ──────────────────────────────────────────────────────────────
	// Single source of truth for both the shortcode and the schema filter.
	// Keep answers research-framed. No human-outcome or therapeutic claims.

	public static function get_all(): array {
		return array(

			array(
				'question' => 'What does "for research use only" mean?',
				'answer'   => 'All products available on this site are intended exclusively for in vitro laboratory and research purposes by qualified professionals. They are not approved, licensed, or intended for human or animal consumption, diagnosis, or therapeutic use. Purchasers are solely responsible for ensuring their activities comply with all applicable federal, provincial, and local laws and regulations in their jurisdiction.',
			),

			array(
				'question' => 'Where does Brand Pharma ship?',
				'answer'   => 'We ship research-grade compounds to addresses across Canada. All orders are packaged discreetly in plain, unmarked outer packaging with no product-identifying information on the exterior. Delivery timelines vary by destination and carrier; tracking information is provided once an order has been dispatched.',
			),

			array(
				'question' => 'What payment methods are accepted?',
				'answer'   => 'Our primary payment method is Interac e-Transfer, a secure bank-to-bank transfer available through most Canadian online banking platforms. After placing an order you will receive payment instructions by email. Send your e-Transfer to the address provided, using your order number as the message or memo field. Orders are processed and shipped once payment is confirmed, typically within one business day of receipt. We are actively expanding our payment options to include additional methods.',
			),

			array(
				'question' => 'Do you accept cryptocurrency payments?',
				'answer'   => 'Yes. We accept cryptocurrency payments through OxaPay, including Bitcoin (BTC), Ethereum (ETH), Tether (USDT), and USD Coin (USDC). Cryptocurrency can be selected as your payment method at checkout, and orders paid with cryptocurrency receive an automatic 10% discount applied to the order total. Interac e-Transfer remains available for customers who prefer a bank-to-bank transfer.',
			),

			array(
				'question' => 'What is a Certificate of Analysis (COA) and why does it matter?',
				'answer'   => 'A Certificate of Analysis (COA) is a document issued by an independent, accredited third-party analytical laboratory confirming the identity, purity, and measured quantity of a research compound. Our COAs are generated using HPLC-MS (high-performance liquid chromatography-mass spectrometry) methodology, which is the industry standard for peptide identity and purity verification. The COA includes the compound name, batch number, tested purity percentage, and the issuing laboratory\'s signature. Researchers should review the COA for every batch to ensure material meets the specifications required for their study.',
			),

			array(
				'question' => 'How can I verify the authenticity of a COA?',
				'answer'   => 'COAs from our testing partners include a unique verification code or QR code linked to the originating laboratory\'s online verification portal. Researchers can enter the verification code on the laboratory\'s website to independently confirm that the document has not been altered and matches the original test record.',
			),

			array(
				'question' => 'How should lyophilized research compounds be stored?',
				'answer'   => 'Lyophilized (freeze-dried) peptide powders are most stable when stored at -20 °C in a sealed, moisture-proof container away from light and humidity. These are standard laboratory storage conditions for lyophilized biological research compounds. Once a lyophilized sample has been reconstituted, researchers should consult peer-reviewed literature specific to the compound for appropriate handling, temperature, and usage-window parameters. Repeated freeze-thaw cycles should be minimised to preserve sample integrity.',
			),

			array(
				'question' => 'What is bacteriostatic water and why is it used for reconstitution?',
				'answer'   => 'Bacteriostatic water is sterile water containing 0.9% benzyl alcohol, which acts as a preservative to inhibit microbial growth. It is the standard reconstitution solvent used in research laboratory settings when preparing lyophilized peptide solutions that may be stored for multiple uses. It is supplied as a laboratory reagent and is available as an add-on in the Supplies section of our catalogue.',
			),

			array(
				'question' => 'What is your return and refund policy?',
				'answer'   => 'Due to the nature of research-grade compounds and product-integrity requirements, we are unable to accept returns once items have left our facility. If you receive a damaged, defective, or incorrect item, please contact us within 48 hours of delivery with your order number and clear photographic documentation. We will assess the situation and work to resolve it promptly, which may include a replacement or store credit at our discretion.',
			),

			array(
				'question' => 'Do you supply research institutions or offer volume pricing?',
				'answer'   => 'We work with qualified research institutions and professional procurement contacts on a case-by-case basis. Please contact us directly with details of your organisation and research requirements to discuss availability and pricing for larger-quantity orders.',
			),

		);
	}

	// ── Learning Hub FAQ data (page 161) ────────────────────────────────────────
	// Educational FAQ set, distinct from the operational get_all() set. Plain-text
	// answers that mirror the visible on-page copy (inline links stripped); the
	// FAQPage schema text and the page-body text must stay identical, so keep both
	// in sync when editing.

	public static function get_learning_hub(): array {
		return array(

			array(
				'question' => 'Are research peptides legal in Canada?',
				'answer'   => 'Research peptides are sold within the well-established research-compound category, intended for laboratory and research use. The "research use" label is a standard distribution designation, not a comment on a compound\'s quality or promise. Several compounds that began in this category later became approved products. Our regulations guide explains how the framework works.',
			),

			array(
				'question' => 'What is a Certificate of Analysis?',
				'answer'   => 'A COA is the lab document that verifies a peptide\'s identity, batch, purity, and the testing lab. It is your proof that what is on the label matches what is in the vial.',
			),

			array(
				'question' => 'How do I read a peptide COA?',
				'answer'   => 'Check four things: the compound name and sequence, the lot number, the purity percentage (98% or higher, ideally 99%+), and the name of the testing lab and method (HPLC-MS).',
			),

			array(
				'question' => 'What does HPLC-MS testing tell you?',
				'answer'   => 'It separates a sample and measures each component by mass, confirming both identity and exact purity. It is the standard for verifying peptide quality.',
			),

			array(
				'question' => 'How should I store lyophilized peptides?',
				'answer'   => 'Frozen (around -20 C), sealed, and away from light and humidity for long-term holding; refrigerated is fine short-term. Minimise repeated freeze-thaw cycles.',
			),

			array(
				'question' => 'How do I reconstitute a peptide?',
				'answer'   => 'You add bacteriostatic water to the lyophilized powder to reach a target concentration. The Peptide Reconstitution Calculator does the math for you.',
			),

			array(
				'question' => 'What purity should I look for?',
				'answer'   => '98% or higher, with the best material at 99%+, confirmed by HPLC-MS on a COA tied to the specific batch.',
			),

		);
	}

	// ── Shortcode renderer ────────────────────────────────────────────────────

	public function render_shortcode( $atts = array() ): string {
		$faqs = self::get_all();
		ob_start();
		?>
		<div class="psc-faqs" itemscope itemtype="https://schema.org/FAQPage">

			<?php foreach ( $faqs as $i => $faq ) : ?>
			<details class="psc-faq" itemscope itemprop="mainEntity" itemtype="https://schema.org/Question">

				<summary class="psc-faq__question" itemprop="name">
					<?php echo esc_html( $faq['question'] ); ?>
					<span class="psc-faq__icon" aria-hidden="true"></span>
				</summary>

				<div class="psc-faq__answer"
				     itemscope itemprop="acceptedAnswer" itemtype="https://schema.org/Answer">
					<div itemprop="text">
						<?php echo wp_kses_post( wpautop( $faq['answer'] ) ); ?>
					</div>
				</div>

			</details>
			<?php endforeach; ?>

		</div><!-- /.psc-faqs -->
		<?php
		return ob_get_clean();
	}
}
