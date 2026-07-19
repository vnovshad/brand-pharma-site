<?php
/**
 * Account promo: drive first-order-discount signups.
 *
 * Two logged-out-only promotions for the automatic first-order discount:
 *
 *   1. A centered popup modal that appears once per visitor (after ~5s or once
 *      they scroll past half the page, whichever comes first). Dismissal is
 *      remembered with a cookie so it does not reappear.
 *
 *   2. A subtle inline note next to the checkout "Create an account?" control.
 *      The live checkout is the WooCommerce block checkout, where classic PHP
 *      template hooks do not render, so the note is placed two ways: the classic
 *      registration hook (for a shortcode checkout) and a small footer script
 *      that inserts it beside the block checkout account control. Both run only
 *      from this plugin; no WooCommerce templates are edited.
 *
 * All text is research-store neutral and contains no em-dashes.
 *
 * @package Peptide_Store
 */
namespace Peptide_Store;

defined( 'ABSPATH' ) || exit;

class Account_Promo {

	/** Cookie that remembers a dismissed popup. */
	const COOKIE = 'brand_promo_dismissed';

	/** Discount headline percentage. */
	const PCT = 10;

	public function __construct() {
		add_action( 'wp_footer', array( $this, 'render_popup' ), 50 );
		add_action( 'wp_footer', array( $this, 'render_checkout_nudge' ), 60 );
		// Classic checkout fallback (does not fire on the block checkout).
		add_action( 'woocommerce_before_checkout_registration_form', array( $this, 'classic_nudge' ) );
	}

	/** Account / registration page URL. */
	private function account_url(): string {
		$url = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'myaccount' ) : '';
		return $url ? $url : home_url( '/my-account/' );
	}

	/** Shared nudge text. */
	private function nudge_text(): string {
		return sprintf( 'Create an account and get %d%% off this order automatically.', self::PCT );
	}

	// ── 1. Popup modal ──────────────────────────────────────────────────────────

	public function render_popup(): void {
		if ( is_user_logged_in() ) {
			return;
		}
		$acct = esc_url( $this->account_url() );
		$pct  = (int) self::PCT;
		?>
		<style id="brand-promo-css">
			.brand-promo{position:fixed;inset:0;z-index:100000;display:flex;align-items:center;justify-content:center;padding:1.25rem;
				background:rgba(26,35,50,.55);backdrop-filter:blur(4px);-webkit-backdrop-filter:blur(4px);
				opacity:0;visibility:hidden;transition:opacity .32s ease,visibility .32s ease;}
			.brand-promo.is-open{opacity:1;visibility:visible;}
			.brand-promo__card{position:relative;width:100%;max-width:420px;background:#fff;border-radius:20px;
				padding:2.5rem 2rem 2rem;text-align:center;overflow:hidden;
				box-shadow:0 2px 8px rgba(26,35,50,.10),0 24px 60px -12px rgba(45,79,169,.45);
				transform:translateY(14px) scale(.97);opacity:0;
				transition:transform .42s cubic-bezier(.16,1,.3,1),opacity .42s cubic-bezier(.16,1,.3,1);}
			.brand-promo.is-open .brand-promo__card{transform:translateY(0) scale(1);opacity:1;}
			.brand-promo__card::before{content:"";position:absolute;inset:0 0 auto 0;height:6px;
				background:linear-gradient(90deg,var(--brand-deep,#2D4FA9),var(--brand-primary,#336AAD),var(--brand-sky,#67A8D2));}
			.brand-promo__glow{position:absolute;top:-90px;left:50%;transform:translateX(-50%);width:280px;height:200px;pointer-events:none;
				background:radial-gradient(closest-side,rgba(103,168,210,.30),rgba(103,168,210,0));}
			.brand-promo__badge{position:relative;display:inline-block;margin:0 0 1rem;padding:.32rem .8rem;border-radius:999px;
				font-family:'Space Grotesk',sans-serif;font-weight:600;font-size:.7rem;letter-spacing:.12em;text-transform:uppercase;
				color:var(--brand-primary,#336AAD);background:var(--brand-cool-white,#F4F7FB);border:1px solid var(--brand-border,#D9E4F0);}
			.brand-promo__title{position:relative;margin:0 0 .65rem;font-family:'Space Grotesk',sans-serif;font-weight:700;
				font-size:1.6rem;line-height:1.15;letter-spacing:-.02em;color:var(--brand-ink,#1A2332);}
			.brand-promo__desc{position:relative;margin:0 auto 1.6rem;max-width:330px;font-family:'Poppins',sans-serif;
				font-size:.95rem;line-height:1.65;color:var(--brand-ink-light,#3A4A5C);}
			.brand-promo__cta{position:relative;display:block;width:100%;padding:.95rem 1.25rem;border-radius:12px;
				font-family:'Space Grotesk',sans-serif;font-weight:600;font-size:1rem;text-decoration:none;color:#fff;
				background:linear-gradient(135deg,var(--brand-primary,#336AAD),var(--brand-deep,#2D4FA9));
				box-shadow:0 8px 20px -6px rgba(45,79,169,.55);
				transition:transform .18s cubic-bezier(.16,1,.3,1),box-shadow .18s ease,filter .18s ease;}
			.brand-promo__cta:hover{transform:translateY(-2px);filter:brightness(1.06);box-shadow:0 12px 26px -6px rgba(45,79,169,.6);}
			.brand-promo__cta:active{transform:translateY(0);}
			.brand-promo__cta:focus-visible{outline:3px solid var(--brand-sky,#67A8D2);outline-offset:2px;}
			.brand-promo__nothanks{position:relative;display:inline-block;margin:1rem auto 0;padding:.25rem;border:0;background:none;cursor:pointer;
				font-family:'Poppins',sans-serif;font-size:.85rem;color:var(--brand-ink-light,#3A4A5C);
				text-decoration:underline;text-underline-offset:2px;transition:color .18s ease;}
			.brand-promo__nothanks:hover{color:var(--brand-ink,#1A2332);}
			.brand-promo__nothanks:focus-visible{outline:2px solid var(--brand-sky,#67A8D2);outline-offset:2px;border-radius:4px;}
			.brand-promo__x{position:absolute;top:.9rem;right:.9rem;width:34px;height:34px;display:flex;align-items:center;justify-content:center;
				border:0;border-radius:50%;background:var(--brand-cool-white,#F4F7FB);color:var(--brand-ink-light,#3A4A5C);
				font-size:1.4rem;line-height:1;cursor:pointer;transition:background .18s ease,color .18s ease,transform .18s ease;}
			.brand-promo__x:hover{background:var(--brand-border,#D9E4F0);color:var(--brand-ink,#1A2332);transform:rotate(90deg);}
			.brand-promo__x:focus-visible{outline:2px solid var(--brand-sky,#67A8D2);outline-offset:2px;}
			@media (max-width:480px){
				.brand-promo__card{padding:2.25rem 1.4rem 1.6rem;border-radius:16px;}
				.brand-promo__title{font-size:1.4rem;}
			}
			@media (prefers-reduced-motion:reduce){
				.brand-promo,.brand-promo__card,.brand-promo__cta,.brand-promo__x,.brand-promo__nothanks{transition:none;}
				.brand-promo__card{transform:none;}
			}
		</style>

		<div id="brand-promo" class="brand-promo" role="dialog" aria-modal="true"
			aria-labelledby="brand-promo-title" aria-describedby="brand-promo-desc">
			<div class="brand-promo__card">
				<span class="brand-promo__glow" aria-hidden="true"></span>
				<button type="button" class="brand-promo__x" data-brand-close aria-label="Close">&times;</button>
				<span class="brand-promo__badge">First Order</span>
				<h2 id="brand-promo-title" class="brand-promo__title">Get <?php echo $pct; ?>% Off Your First Order</h2>
				<p id="brand-promo-desc" class="brand-promo__desc">Create a free account and your first order discount applies automatically at checkout. No code needed.</p>
				<a class="brand-promo__cta" href="<?php echo $acct; ?>" data-brand-go>Create My Account</a>
				<button type="button" class="brand-promo__nothanks" data-brand-close>No thanks</button>
			</div>
		</div>

		<script id="brand-promo-js">
		(function(){
			var KEY=<?php echo wp_json_encode( self::COOKIE ); ?>;
			function dismissed(){return document.cookie.indexOf(KEY+'=1')!==-1;}
			function remember(){var d=new Date();d.setTime(d.getTime()+30*864e5);
				document.cookie=KEY+'=1; expires='+d.toUTCString()+'; path=/; SameSite=Lax';}
			if(dismissed())return;
			var overlay=document.getElementById('brand-promo');
			if(!overlay)return;
			var shown=false,lastFocus=null;
			function show(){
				if(shown||dismissed())return;shown=true;
				lastFocus=document.activeElement;
				overlay.classList.add('is-open');
				document.body.style.overflow='hidden';
				document.removeEventListener('scroll',onScroll);
				clearTimeout(timer);
				var cta=overlay.querySelector('.brand-promo__cta');if(cta)cta.focus();
			}
			function close(){
				overlay.classList.remove('is-open');
				document.body.style.overflow='';
				remember();teardown();
				if(lastFocus&&lastFocus.focus)lastFocus.focus();
			}
			function onScroll(){
				var st=window.pageYOffset||document.documentElement.scrollTop;
				var h=document.documentElement.scrollHeight-window.innerHeight;
				if(h>0&&(st/h)>=0.5)show();
			}
			function onKey(e){if(e.key==='Escape'||e.keyCode===27)close();}
			function teardown(){
				document.removeEventListener('scroll',onScroll);
				document.removeEventListener('keydown',onKey);
			}
			var timer=setTimeout(show,5000);
			document.addEventListener('scroll',onScroll,{passive:true});
			document.addEventListener('keydown',onKey);
			overlay.addEventListener('click',function(e){if(e.target===overlay)close();});
			overlay.querySelectorAll('[data-brand-close]').forEach(function(el){
				el.addEventListener('click',function(e){e.preventDefault();close();});
			});
			// Following the CTA counts as handled; remember so it does not reopen.
			var go=overlay.querySelector('[data-brand-go]');
			if(go)go.addEventListener('click',function(){remember();});
		})();
		</script>
		<?php
	}

	// ── 2. Checkout nudge ────────────────────────────────────────────────────────

	/** Classic checkout: render the note directly at the registration hook. */
	public function classic_nudge(): void {
		if ( is_user_logged_in() ) {
			return;
		}
		echo '<p class="brand-acct-nudge">' . esc_html( $this->nudge_text() ) . '</p>';
	}

	/** Footer CSS for the note + a script that injects it into the block checkout. */
	public function render_checkout_nudge(): void {
		if ( is_user_logged_in() || ! function_exists( 'is_checkout' ) || ! is_checkout() ) {
			return;
		}
		?>
		<style id="brand-acct-nudge-css">
			.brand-acct-nudge{margin:.6rem 0 0;padding:.7rem .9rem;border-radius:10px;
				background:var(--brand-cool-white,#F4F7FB);border:1px solid var(--brand-border,#D9E4F0);
				border-left:3px solid var(--brand-primary,#336AAD);
				font-family:'Poppins',sans-serif;font-size:.85rem;line-height:1.5;color:var(--brand-ink-light,#3A4A5C);}
			.brand-acct-nudge strong{color:var(--brand-primary,#336AAD);font-weight:600;}
		</style>
		<script id="brand-acct-nudge-js">
		(function(){
			var TEXT=<?php echo wp_json_encode( $this->nudge_text() ); ?>;
			function make(){var d=document.createElement('p');d.className='brand-acct-nudge';d.textContent=TEXT;return d;}
			function place(){
				if(document.querySelector('.brand-acct-nudge'))return true;
				var anchor=document.querySelector('.wc-block-checkout__create-account');
				if(!anchor){
					var nodes=document.querySelectorAll('.wc-block-components-checkbox__label,label');
					for(var i=0;i<nodes.length;i++){
						if(/create an account/i.test(nodes[i].textContent||'')){
							anchor=nodes[i].closest('.wc-block-components-checkbox')||nodes[i].parentNode;break;
						}
					}
				}
				if(!anchor||!anchor.parentNode)return false;
				anchor.parentNode.insertBefore(make(),anchor.nextSibling);
				return true;
			}
			if(place())return;
			var obs=new MutationObserver(function(){if(place())obs.disconnect();});
			obs.observe(document.body,{childList:true,subtree:true});
			setTimeout(function(){obs.disconnect();},15000);
		})();
		</script>
		<?php
	}
}
