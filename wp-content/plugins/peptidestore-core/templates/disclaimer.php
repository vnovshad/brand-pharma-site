<?php
/**
 * Reusable "research use only" disclaimer. Override by copying to
 * {child-theme}/peptidestore/disclaimer.php (wire the lookup if you want that).
 *
 * @package Peptide_Store
 */
defined( 'ABSPATH' ) || exit;
?>
<p class="peptidestore-disclaimer" role="note">
	<?php echo esc_html( \Peptide_Store\Compliance::disclaimer_text() ); ?>
</p>
