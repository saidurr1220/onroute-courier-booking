<?php
/**
 * Public Business Credit management
 *
 * @package OnRoute_Courier_Booking
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class OnRoute_Business_Credit_Public {

	/**
	 * Constructor
	 */
	public function __construct() {
		// Shortcodes
		add_shortcode( 'onroute_signin', array( $this, 'render_combined_shortcode' ) );
		add_shortcode( 'onroute_business_dashboard', array( $this, 'render_dashboard_shortcode' ) );
		add_shortcode( 'onroute_business_stats', array( $this, 'render_stats_shortcode' ) );
		add_shortcode( 'onroute_auth_button', array( $this, 'render_auth_button' ) );

		// AJAX handlers
		add_action( 'wp_ajax_nopriv_ocb_business_register', array( $this, 'ajax_register_user' ) );
		add_action( 'wp_ajax_nopriv_ocb_business_login', array( $this, 'ajax_login_user' ) );
		add_action( 'wp_ajax_ocb_apply_credit', array( $this, 'ajax_apply_credit' ) );
		add_action( 'wp_ajax_ocb_business_get_quote', array( $this, 'ajax_business_get_quote' ) );
		add_action( 'wp_ajax_ocb_business_create_booking', array( $this, 'ajax_business_create_booking' ) );
		add_action( 'wp_ajax_ocb_business_lookup_address', array( $this, 'ajax_business_lookup_address' ) );

		// Dynamic Menu Filter
		add_filter( 'wp_nav_menu_items', array( $this, 'handle_menu_visibility' ), 10, 2 );

		// Restrict admin access for business users
		add_action( 'admin_init', array( $this, 'restrict_admin_access' ) );

		// SEO: Hidden from search engines
		add_action( 'wp_head', array( $this, 'add_noindex' ) );

		// Disable caching for dashboard and stats pages
		add_action( 'template_redirect', array( $this, 'disable_page_caching' ) );
	}

	/**
	 * Disable caching for business critical pages
	 */
	public function disable_page_caching() {
		global $post;
		if ( ! is_a( $post, 'WP_Post' ) ) return;

		if ( has_shortcode( $post->post_content, 'onroute_business_dashboard' ) || has_shortcode( $post->post_content, 'onroute_business_stats' ) ) {
			// Disable browser cache
			nocache_headers();
			
			// LiteSpeed Cache
			if ( ! defined( 'LITESPEED_NOCACHE' ) ) {
				define( 'LITESPEED_NOCACHE', true );
			}
			
			// Generic for other plugins
			if ( ! defined( 'DONOTCACHEPAGE' ) ) {
				define( 'DONOTCACHEPAGE', true );
			}
		}
	}

	/**
	 * Restrict wp-admin access for business account users
	 */
	public function restrict_admin_access() {
		if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) return;
		
		if ( is_user_logged_in() && ! current_user_can( 'manage_options' ) ) {
			$account = OnRoute_Business_Credit::get_account_by_user( get_current_user_id() );
			if ( $account ) {
				wp_safe_redirect( home_url() );
				exit;
			}
		}
	}

	/**
	 * Standalone price formatter
	 */
	private function format_price( $amount ) {
		return '£' . number_format( (float) $amount, 2 );
	}

	/**
	 * Add noindex tag to pages containing the dashboard
	 */
	public function add_noindex() {
		global $post;
		if ( is_a( $post, 'WP_Post' ) && ( has_shortcode( $post->post_content, 'onroute_signin' ) || has_shortcode( $post->post_content, 'onroute_business_dashboard' ) ) ) {
			echo '<meta name="robots" content="noindex, nofollow">' . "\n";
		}
	}

	/**
	 * Combined shortcode: [onroute_signin]
	 * Shows login if guest, shows dashboard if logged in.
	 */
	public function render_combined_shortcode() {
		if ( ! is_user_logged_in() ) {
			return $this->render_signin_form();
		}

		return $this->render_dashboard_shortcode();
	}

	/**
	 * Dashboard shortcode: [onroute_business_dashboard]
	 */
	public function render_dashboard_shortcode() {
		if ( ! is_user_logged_in() ) {
			return '<p>' . __( 'Please sign in to view your business dashboard.', 'onroute-courier-booking' ) . '</p>';
		}

		$user_id = get_current_user_id();
		$account = OnRoute_Business_Credit::get_account_by_user( $user_id );

		ob_start();
		if ( ! $account ) {
			$this->render_no_account_view();
		} else {
			$this->render_dashboard_view( $account );
		}
		return ob_get_clean();
	}

	/**
	 * Render Sign In Form
	 */
	private function render_signin_form() {
		ob_start();
		?>
		<div class="onroute-auth-wrapper">
			<div class="onroute-auth-tabs">
				<button class="auth-tab active" data-target="signin-view"><?php _e( 'Sign In', 'onroute-courier-booking' ); ?></button>
				<button class="auth-tab" data-target="signup-view"><?php _e( 'Create Account', 'onroute-courier-booking' ); ?></button>
			</div>

			<div id="signin-view" class="auth-container active">
				<div class="onroute-signin-card">
					<div class="signin-header">
						<div class="brand-accent"></div>
						<h2><?php _e( 'Business Login', 'onroute-courier-booking' ); ?></h2>
						<p><?php _e( 'Access your business credit dashboard', 'onroute-courier-booking' ); ?></p>
					</div>

					<form id="onroute-login-form">
						<div class="form-group">
							<label for="user_login"><?php _e( 'Email or Username', 'onroute-courier-booking' ); ?></label>
							<input type="text" name="username" id="user_login" class="input" value="" placeholder="Enter your email" required />
						</div>
						
						<div class="form-group">
							<div class="label-row">
								<label for="user_pass"><?php _e( 'Password', 'onroute-courier-booking' ); ?></label>
								<a href="<?php echo esc_url( wp_lostpassword_url() ); ?>" class="forgot-link"><?php _e( 'Forgot?', 'onroute-courier-booking' ); ?></a>
							</div>
							<input type="password" name="password" id="user_pass" class="input" value="" placeholder="••••••••" required />
						</div>

						<div class="form-submit">
							<button type="submit" class="button button-primary"><?php _e( 'Sign In to Dashboard', 'onroute-courier-booking' ); ?></button>
						</div>
						<div id="login-message"></div>
					</form>
				</div>
			</div>

			<div id="signup-view" class="auth-container">
```,oldString:
				<div class="onroute-signin-card">
					<div class="signin-header">
						<div class="brand-accent"></div>
						<h2><?php _e( 'Business Sign Up', 'onroute-courier-booking' ); ?></h2>
						<p><?php _e( 'Create an account to apply for business credit', 'onroute-courier-booking' ); ?></p>
					</div>

					<form id="onroute-signup-form">
						<div class="form-group">
							<label><?php _e( 'Full Name', 'onroute-courier-booking' ); ?></label>
							<input type="text" name="full_name" placeholder="E.g. John Doe" required />
						</div>
						<div class="form-group">
							<label><?php _e( 'Email Address', 'onroute-courier-booking' ); ?></label>
							<input type="email" name="user_email" placeholder="E.g. john@company.com" required />
						</div>
						<div class="form-group">
							<label><?php _e( 'Company Name', 'onroute-courier-booking' ); ?></label>
							<input type="text" name="company_name" placeholder="E.g. OnRoute Couriers" required />
						</div>
						<div class="form-group">
							<label><?php _e( 'Password', 'onroute-courier-booking' ); ?></label>
							<input type="password" name="password" placeholder="••••••••" required />
						</div>
						<div class="form-submit">
							<button type="submit" class="button button-primary"><?php _e( 'Create Business Account', 'onroute-courier-booking' ); ?></button>
						</div>
						<div id="signup-message"></div>
					</form>
				</div>
			</div>
		</div>

		<script>
		jQuery(document).ready(function($) {
			$('.auth-tab').on('click', function() {
				var target = $(this).data('target');
				$('.auth-tab').removeClass('active');
				$(this).addClass('active');
				$('.auth-container').removeClass('active');
				$('#' + target).addClass('active');
			});

			$('#onroute-login-form').on('submit', function(e) {
				e.preventDefault();
				var $btn = $(this).find('button');
				var data = $(this).serialize() + '&action=ocb_business_login&nonce=<?php echo wp_create_nonce("ocb_auth_nonce"); ?>';
				
				$btn.prop('disabled', true).text('Signing In...');
				
				$.post('<?php echo admin_url("admin-ajax.php"); ?>', data, function(res) {
					if(res.success) {
						$('#login-message').html('<p style="color:green">' + res.data.message + '</p>');
						setTimeout(function() { 
							window.location.href = res.data.redirect || window.location.href; 
						}, 1000);
					} else {
						$('#login-message').html('<p style="color:red">' + res.data.message + '</p>');
						$btn.prop('disabled', false).text('Sign In to Dashboard');
					}
				});
			});

			$('#onroute-signup-form').on('submit', function(e) {
				e.preventDefault();
				var $btn = $(this).find('button');
				var data = $(this).serialize() + '&action=ocb_business_register&nonce=<?php echo wp_create_nonce("ocb_auth_nonce"); ?>';
				
				$btn.prop('disabled', true).text('Creating Account...');
				
				$.post('<?php echo admin_url("admin-ajax.php"); ?>', data, function(res) {
					if(res.success) {
						$('#signup-message').html('<p style="color:green">' + res.data.message + '</p>');
						setTimeout(function() { window.location.reload(); }, 1500);
					} else {
						$('#signup-message').html('<p style="color:red">' + res.data.message + '</p>');
						$btn.prop('disabled', false).text('Create Business Account');
					}
				});
			});
		});
		</script>

		<style>
			.onroute-auth-wrapper { max-width: 480px; margin: 40px auto; font-family: 'Inter', sans-serif; padding: 0 15px; }
			.onroute-auth-tabs { display: flex; gap: 10px; margin-bottom: 25px; background: #f0f0f0; padding: 5px; border-radius: 12px; }
			.auth-tab { flex: 1; border: none; padding: 12px; border-radius: 10px; cursor: pointer; font-weight: 600; color: #666; transition: 0.3s; background: transparent; font-size: 14px; }
			.auth-tab.active { background: #fff; color: #111; box-shadow: 0 4px 10px rgba(0,0,0,0.05); }
			
			.auth-container { display: none; }
			.auth-container.active { display: block; animation: fadeIn 0.4s ease; }
			
			@keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }

			.onroute-signin-card { background: #fff; border-radius: 20px; box-shadow: 0 15px 35px rgba(0,0,0,0.07); overflow: hidden; border: 1px solid #eee; }
			.brand-accent { height: 6px; background: linear-gradient(90deg, #e31837, #b1122a); }
			.signin-header { padding: 40px 30px 20px; text-align: center; }
			.signin-header h2 { font-size: 24px; margin: 0; font-weight: 800; color: #111; }
			.signin-header p { margin: 10px 0 0; color: #777; font-size: 14px; }

			#onroute-login-form, #onroute-signup-form { padding: 0 30px 40px; }
			.form-group { margin-bottom: 20px; }
			.form-group label { display: block; margin-bottom: 8px; font-size: 13px; font-weight: 600; color: #333; text-align: left; }
			.form-group input { width: 100% !important; border: 1px solid #e0e0e0 !important; padding: 12px 16px !important; border-radius: 10px !important; background: #fafafa !important; transition: 0.3s !important; box-sizing: border-box !important; }
			.form-group input:focus { border-color: #e31837 !important; background: #fff !important; outline: none !important; box-shadow: 0 0 0 4px rgba(227, 24, 55, 0.08) !important; }
			
			.label-row { display: flex; justify-content: space-between; align-items: center; }
			.forgot-link { font-size: 12px; color: #e31837; text-decoration: none; font-weight: 500; }

			.button-primary { width: 100% !important; background: #e31837 !important; color: #fff !important; border: none !important; padding: 14px !important; border-radius: 10px !important; font-size: 15px !important; font-weight: 700 !important; cursor: pointer !important; transition: 0.3s !important; margin-top: 10px !important; }
			.button-primary:hover { background: #c1122d !important; transform: translateY(-2px) !important; box-shadow: 0 8px 20px rgba(227, 24, 55, 0.25) !important; }
			
			#signup-message { margin-top: 15px; text-align: center; font-size: 13px; }

			@media (max-width: 480px) {
				.signin-header h2 { font-size: 20px; }
				.signin-header { padding: 30px 20px 20px; }
				#onroute-login-form, #onroute-signup-form { padding: 0 20px 30px; }
			}
		</style>
		<?php
		return ob_get_clean();
	}

	/**
	 * Render "No Account" view
	 */
	private function render_no_account_view() {
		$user = wp_get_current_user();
		?>
		<div class="ocb-promo-container">
			<div class="ocb-promo-header">
				<h1>Business <span class="highlight">Credit</span> Account</h1>
				<p>Flexible billing for businesses that ship regularly with same-day courier services.</p>
			</div>

			<div class="ocb-promo-layout">
				<div class="ocb-promo-main-card">
					<div class="ocb-promo-content">
						<p>Designed for companies that need frequent, time-critical deliveries. Our Business Credit Account simplifies booking, billing, and account management — so you can focus on your business, not admin.</p>
						
						<form id="ocb-application-form">
							<div class="form-group" style="margin-bottom:15px; text-align:left;">
								<label style="font-weight:600; font-size:13px; margin-bottom:5px; display:block;"><?php _e( 'Company Name', 'onroute-courier-booking' ); ?></label>
								<input type="text" name="company_name" placeholder="E.g. OnRoute Couriers" required style="width:100% !important; padding:12px !important; border:1px solid #ddd !important; border-radius:8px !important; box-sizing: border-box !important;" />
							</div>
							<div class="form-group" style="margin-bottom:15px; text-align:left;">
								<label style="font-weight:600; font-size:13px; margin-bottom:5px; display:block;"><?php _e( 'Phone Number', 'onroute-courier-booking' ); ?></label>
								<input type="tel" name="company_phone" placeholder="E.g. 020 1234 5678" required style="width:100% !important; padding:12px !important; border:1px solid #ddd !important; border-radius:8px !important; box-sizing: border-box !important;" />
							</div>
							<div class="form-group" style="margin-bottom:15px; text-align:left;">
								<label style="font-weight:600; font-size:13px; margin-bottom:5px; display:block;"><?php _e( 'Business Address', 'onroute-courier-booking' ); ?></label>
								<textarea name="company_address" placeholder="Full trading address" required style="width:100% !important; padding:12px !important; border:1px solid #ddd !important; border-radius:8px !important; box-sizing: border-box !important; height: 80px;"></textarea>
							</div>
							<button type="submit" class="apply-btn"><?php _e( 'Submit Application', 'onroute-courier-booking' ); ?></button>
						</form>
						
						<div class="promo-footer">
							No setup fees . Quick approval . Start booking today
						</div>

						<div id="apply-message" style="margin-top: 15px; font-size: 14px;"></div>
					</div>
				</div>

				<div class="ocb-feature-list">
					<div class="ocb-feature-card">
						<div class="feature-icon"><i class="fas fa-credit-card"></i></div>
						<div class="feature-text">
							<h4>No Upfront <span class="highlight">Payments</span></h4>
							<p>Book deliveries now and pay later with simple 30-day invoicing.</p>
						</div>
					</div>
					<div class="ocb-feature-card">
						<div class="feature-icon"><i class="fas fa-users"></i></div>
						<div class="feature-text">
							<h4>Multiusers <span class="highlight">Booking</span></h4>
							<p>Allow team members to book deliveries under one business account.</p>
						</div>
					</div>
					<div class="ocb-feature-card">
						<div class="feature-icon"><i class="fas fa-percent"></i></div>
						<div class="feature-text">
							<h4>Accounts <span class="highlight">Discount</span></h4>
							<p>Eligible bookings receive automatic savings with no manual codes.</p>
						</div>
					</div>
					<div class="ocb-feature-card">
						<div class="feature-icon"><i class="fas fa-headset"></i></div>
						<div class="feature-text">
							<h4>Priority <span class="highlight">Support</span></h4>
							<p>Faster responses and dedicated assistance for account customers.</p>
						</div>
					</div>
				</div>
			</div>
			
			<div style="text-align: center; margin-top: 30px;">
				<a href="<?php echo wp_logout_url( get_permalink() ); ?>" class="ocb-logout-btn"><?php _e( 'Logout', 'onroute-courier-booking' ); ?></a>
			</div>
		</div>

		<script>
		jQuery(document).ready(function($) {
			$('#ocb-application-form').on('submit', function(e) {
				e.preventDefault();
				var $btn = $(this).find('.apply-btn');
				var data = $(this).serialize() + '&action=ocb_apply_credit&nonce=<?php echo wp_create_nonce("ocb_auth_nonce"); ?>';
				
				$btn.prop('disabled', true).text('Processing...');
				
				$.post('<?php echo admin_url("admin-ajax.php"); ?>', data, function(res) {
					if(res.success) {
						$('#apply-message').html('<p style="color:green">' + res.data.message + '</p>');
						setTimeout(function() { window.location.reload(); }, 2000);
					} else {
						$('#apply-message').html('<p style="color:red">' + res.data.message + '</p>');
						$btn.prop('disabled', false).text('APPLY FOR A CREDIT ACCOUNT');
					}
				});
			});
		});
		</script>

		<style>
			.ocb-promo-container { max-width: 1200px; margin: 40px auto; padding: 0 20px; font-family: 'Inter', sans-serif; text-align: center; }
			.ocb-promo-header { margin-bottom: 50px; }
			.ocb-promo-header h1 { font-size: clamp(28px, 5vw, 42px); font-weight: 800; color: #111; margin: 0; line-height: 1.2; }
			.ocb-promo-header .highlight { color: #e31837; }
			.ocb-promo-header p { font-size: clamp(14px, 2vw, 18px); color: #666; margin-top: 15px; padding: 0 20px; }

			.ocb-promo-layout { display: grid; grid-template-columns: 1.2fr 1fr; gap: 30px; text-align: left; align-items: stretch; }
			
			.ocb-promo-main-card { background: #fdf2f3; border-radius: 24px; padding: clamp(25px, 5vw, 50px); display: flex; align-items: center; border: 1px solid #f9e2e4; }
			.ocb-promo-content p { font-size: clamp(15px, 2vw, 18px); color: #444; line-height: 1.6; margin-bottom: 30px; }
			
			.apply-btn { background: #e31837 !important; color: #fff !important; border: none !important; padding: 16px 32px !important; font-size: 15px !important; font-weight: 700 !important; border-radius: 8px !important; cursor: pointer !important; transition: 0.3s !important; display: block !important; width: 100% !important; box-shadow: 0 4px 15px rgba(227, 24, 55, 0.2) !important; }
			.apply-btn:hover { background: #c1122d !important; transform: translateY(-2px) !important; }
			
			.promo-footer { font-size: 13px; color: #777; margin-top: 20px; font-weight: 500; text-align: center; }

			.ocb-feature-list { display: grid; grid-template-columns: 1fr; gap: 15px; }
			.ocb-feature-card { background: #fff; padding: 20px; border-radius: 20px; display: flex; gap: 20px; align-items: flex-start; border: 1px solid #eee; transition: 0.3s; box-shadow: 0 5px 15px rgba(0,0,0,0.02); }
			.ocb-feature-card:hover { transform: translateX(8px); border-color: #f9e2e4; }
			
			.feature-icon { width: 40px; height: 40px; background: #fff1f2; color: #e31837; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 18px; flex-shrink: 0; }
			.feature-text h4 { margin: 0 0 5px; font-size: 16px; font-weight: 700; color: #111; }
			.feature-text h4 .highlight { color: #e31837; }
			.feature-text p { margin: 0; color: #666; font-size: 13px; line-height: 1.4; }

			.ocb-logout-btn { color: #888; text-decoration: none; font-size: 14px; font-weight: 600; border-bottom: 2px solid transparent; transition: 0.3s; display: inline-block; padding: 5px 0; }
			.ocb-logout-btn:hover { color: #e31837; border-color: #e31837; }

			@media (max-width: 991px) {
				.ocb-promo-layout { grid-template-columns: 1fr; gap: 20px; }
				.ocb-promo-main-card { order: 1; }
				.ocb-feature-list { order: 2; display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); }
			}
			@media (max-width: 600px) {
				.ocb-feature-list { grid-template-columns: 1fr; }
				.feature-icon { width: 35px; height: 35px; font-size: 16px; }
				.feature-text h4 { font-size: 15px; }
			}
		</style>
		<?php
	}

	/**
	 * Render Dashboard View
	 */
	private function render_dashboard_view( $account ) {
		$user = wp_get_current_user();

		if ( $account->account_status === 'pending' ) {
			$this->render_pending_view( $account );
			return;
		}
		?>
		<div class="ocb-dashboard-wrapper">
			<div class="ocb-dashboard-sidebar">
				<div class="sidebar-user">
					<div class="user-avatar">
						<?php echo get_avatar( $user->ID, 60 ); ?>
					</div>
					<div class="user-info">
						<h4><?php echo esc_html( $user->display_name ); ?></h4>
						<p><?php echo esc_html( $account->company_name ); ?></p>
					</div>
				</div>
				<nav class="sidebar-nav">
					<div class="nav-group-label" style="padding: 10px 15px 5px; font-size: 10px; font-weight: 700; color: #999; text-transform: uppercase; letter-spacing: 0.5px;"><?php _e( 'Main', 'onroute-courier-booking' ); ?></div>
					<button class="nav-item active" data-target="dash-overview"><i class="fas fa-th-large"></i> <span><?php _e( 'Overview', 'onroute-courier-booking' ); ?></span></button>
					<button class="nav-item" data-target="dash-new-booking"><i class="fas fa-plus-circle"></i> <span><?php _e( 'New Booking', 'onroute-courier-booking' ); ?></span></button>
					<button class="nav-item" data-target="dash-bookings"><i class="fas fa-box"></i> <span><?php _e( 'My Bookings', 'onroute-courier-booking' ); ?></span></button>
					
					<div class="nav-group-label" style="padding: 15px 15px 5px; font-size: 10px; font-weight: 700; color: #999; text-transform: uppercase; letter-spacing: 0.5px;"><?php _e( 'Management', 'onroute-courier-booking' ); ?></div>
					<button class="nav-item" data-target="dash-invoices"><i class="fas fa-file-invoice-dollar"></i> <span><?php _e( 'Invoices', 'onroute-courier-booking' ); ?></span></button>
					<button class="nav-item" data-target="dash-locations"><i class="fas fa-map-marker-alt"></i> <span><?php _e( 'Saved Locations', 'onroute-courier-booking' ); ?></span></button>
					
					<div class="nav-group-label" style="padding: 15px 15px 5px; font-size: 10px; font-weight: 700; color: #999; text-transform: uppercase; letter-spacing: 0.5px;"><?php _e( 'Account', 'onroute-courier-booking' ); ?></div>
					<button class="nav-item" data-target="dash-support"><i class="fas fa-life-ring"></i> <span><?php _e( 'Support', 'onroute-courier-booking' ); ?></span></button>
					<button class="nav-item" data-target="dash-settings"><i class="fas fa-cog"></i> <span><?php _e( 'Settings', 'onroute-courier-booking' ); ?></span></button>
					
					<div style="margin-top: auto; padding-top: 15px;">
						<a href="<?php echo wp_logout_url( get_permalink() ); ?>" class="nav-item logout"><i class="fas fa-sign-out-alt"></i> <span><?php _e( 'Sign Out', 'onroute-courier-booking' ); ?></span></a>
					</div>
				</nav>
			</div>

			<div class="ocb-dashboard-main">
				<!-- Tab: Overview -->
				<div id="dash-overview" class="dash-tab-content active">
					<div class="dash-welcome">
						<h2><?php _e( 'Account Overview', 'onroute-courier-booking' ); ?></h2>
						<span class="status-badge status-<?php echo esc_attr( $account->account_status ); ?>">
							<?php echo ucfirst( esc_html( $account->account_status ) ); ?>
						</span>
					</div>

					<div class="stats-grid">
						<div class="stat-card">
							<span class="label"><?php _e( 'Credit Limit', 'onroute-courier-booking' ); ?></span>
							<span class="value"><?php echo $this->format_price( $account->credit_limit ); ?></span>
						</div>
						<div class="stat-card">
							<span class="label"><?php _e( 'Spent/Used', 'onroute-courier-booking' ); ?></span>
							<span class="value"><?php echo $this->format_price( $account->current_balance ); ?></span>
						</div>
						<div class="stat-card highlight">
							<span class="label"><?php _e( 'Remaining Credit', 'onroute-courier-booking' ); ?></span>
							<span class="value"><?php echo $this->format_price( max( 0, $account->credit_limit - $account->current_balance ) ); ?></span>
						</div>
					</div>

					<div class="recent-activity">
						<h3><?php _e( 'Recent Bookings', 'onroute-courier-booking' ); ?></h3>
						<?php $this->render_user_bookings( $user->user_email ); ?>
					</div>
				</div>

				<!-- Tab: New Booking -->
				<div id="dash-new-booking" class="dash-tab-content">
					<?php $this->render_business_booking_form( $account ); ?>
				</div>

				<!-- Tab: Bookings -->
				<div id="dash-bookings" class="dash-tab-content">
					<h2><?php _e( 'All Bookings', 'onroute-courier-booking' ); ?></h2>
					<div class="bookings-full-list">
						<?php $this->render_user_bookings( $user->user_email, 50 ); ?>
					</div>
				</div>

				<!-- Tab: Settings -->
				<div id="dash-settings" class="dash-tab-content">
					<h2><?php _e( 'Account Settings', 'onroute-courier-booking' ); ?></h2>
					
					<div class="ocb-settings-container" style="background: #fff; border-radius: 8px; border: 1px solid #eee; overflow: hidden;">
						<div class="settings-nav" style="background: #f8f9fa; border-bottom: 1px solid #eee; padding: 0 20px; display: flex; gap: 20px;">
							<button type="button" class="settings-nav-tab active" data-tab="set-profile" style="background:none; border:none; padding: 15px 5px; cursor:pointer; font-weight:600; color:#555; border-bottom: 3px solid transparent; margin-bottom:-1px;">Profile</button>
							<button type="button" class="settings-nav-tab" data-tab="set-security" style="background:none; border:none; padding: 15px 5px; cursor:pointer; font-weight:600; color:#555; border-bottom: 3px solid transparent; margin-bottom:-1px;">Security</button>
							<button type="button" class="settings-nav-tab" data-tab="set-billing" style="background:none; border:none; padding: 15px 5px; cursor:pointer; font-weight:600; color:#555; border-bottom: 3px solid transparent; margin-bottom:-1px;">Billing</button>
						</div>

						<div class="settings-content" style="padding: 25px;">
							
							<!-- Profile Tab -->
							<div id="set-profile" class="settings-tab-pane active">
								<h3 style="margin-top: 0; color: #111; font-size: 16px; font-weight: 700; margin-bottom: 20px;">Profile Information</h3>
								<form id="ocb-account-form" class="ocb-ext-form">
									<div class="setting-row">
										<label><?php _e( 'Company Name', 'onroute-courier-booking' ); ?></label>
										<input type="text" name="company_name" value="<?php echo esc_attr( $account->company_name ); ?>" class="ocb-input">
									</div>
									<div class="setting-row">
										<label><?php _e( 'Contact Name', 'onroute-courier-booking' ); ?></label>
										<input type="text" name="display_name" value="<?php echo esc_attr( $user->display_name ); ?>" class="ocb-input">
									</div>
									<div class="setting-row">
										<label><?php _e( 'Phone', 'onroute-courier-booking' ); ?></label>
										<input type="text" name="phone" value="<?php echo esc_attr( get_user_meta( $user->ID, 'billing_phone', true ) ); ?>" class="ocb-input">
									</div>
									<div class="setting-row">
										<label><?php _e( 'Account Status', 'onroute-courier-booking' ); ?></label>
										<p style="margin:0;"><span class="status-badge status-<?php echo esc_attr( $account->account_status ); ?>"><?php echo esc_html( $account->account_status ); ?></span></p>
									</div>
									<button type="submit" class="button combined-btn" style="margin-top:10px;"><?php _e( 'Save Profile', 'onroute-courier-booking' ); ?></button>
									<span id="ocb-account-msg" style="margin-left:10px;"></span>
								</form>
							</div>

							<!-- Security Tab -->
							<div id="set-security" class="settings-tab-pane" style="display: none;">
								<h3 style="margin-top: 0; color: #111; font-size: 16px; font-weight: 700; margin-bottom: 20px;">Change Password</h3>
								<form id="ocb-password-form" class="ocb-ext-form">
									<div class="setting-row">
										<label><?php _e( 'Current Password', 'onroute-courier-booking' ); ?></label>
										<input type="password" name="current_password" class="ocb-input" autocomplete="current-password">
									</div>
									<div class="setting-row">
										<label><?php _e( 'New Password', 'onroute-courier-booking' ); ?></label>
										<input type="password" name="new_password" class="ocb-input" autocomplete="new-password">
									</div>
									<div class="setting-row">
										<label><?php _e( 'Confirm New Password', 'onroute-courier-booking' ); ?></label>
										<input type="password" name="confirm_password" class="ocb-input" autocomplete="new-password">
									</div>
									<button type="submit" class="button combined-btn"><?php _e( 'Update Password', 'onroute-courier-booking' ); ?></button>
									<span id="ocb-password-msg" style="margin-left:10px;"></span>
								</form>
							</div>

							<!-- Billing Tab -->
							<div id="set-billing" class="settings-tab-pane" style="display: none;">
								<h3 style="margin-top: 0; color: #111; font-size: 16px; font-weight: 700; margin-bottom: 20px;">Billing Details</h3>
								<div class="setting-row">
									<label><?php _e( 'Billing Address', 'onroute-courier-booking' ); ?></label>
									<textarea name="billing_address" form="ocb-account-form" class="ocb-input" rows="3"><?php echo esc_textarea( get_user_meta( $user->ID, 'billing_address_1', true ) ); ?></textarea>
									<p class="help-text" style="font-size:11px; color:#888;">Update via Profile tab</p>
								</div>
								<div class="setting-row">
									<label><?php _e( 'Billing Email', 'onroute-courier-booking' ); ?></label>
									<p style="margin:0; padding:8px 0; color:#666;"><?php echo esc_html( $user->user_email ); ?> <small>(cannot be changed)</small></p>
								</div>
								<hr style="margin: 20px 0;">
								<div class="setting-row">
									<p class="help-text"><?php _e( 'To request a credit limit increase or update service settings, contact our accounts team.', 'onroute-courier-booking' ); ?></p>
									<a href="mailto:accounts@onroutecouriers.com" class="button combined-btn" style="display:inline-block; font-size:13px;"><?php _e( 'Contact Billing Support', 'onroute-courier-booking' ); ?></a>
								</div>
							</div>

						</div>
					</div>
				</div>

				<!-- Tab: Job Detail (shown when clicking View on a booking) -->
				<div id="dash-job-detail" class="dash-tab-content">
					<div id="ocb-job-detail-content">
						<p style="text-align:center; padding:40px; color:#999;"><?php _e( 'Select a booking to view details.', 'onroute-courier-booking' ); ?></p>
					</div>
				</div>

				<!-- Tab: Invoices -->
				<div id="dash-invoices" class="dash-tab-content">
					<h2><?php _e( 'Invoices', 'onroute-courier-booking' ); ?></h2>
					<?php
					$invoices = OnRoute_Dashboard_Extensions::get_user_invoices( $user->ID );
					if ( ! empty( $invoices ) ) :
					?>
					<div class="ocb-table-wrap">
						<table class="ocb-data-table">
							<thead>
								<tr>
									<th><?php _e( 'Invoice #', 'onroute-courier-booking' ); ?></th>
									<th><?php _e( 'Booking', 'onroute-courier-booking' ); ?></th>
									<th><?php _e( 'Amount', 'onroute-courier-booking' ); ?></th>
									<th><?php _e( 'VAT', 'onroute-courier-booking' ); ?></th>
									<th><?php _e( 'Total', 'onroute-courier-booking' ); ?></th>
									<th><?php _e( 'Status', 'onroute-courier-booking' ); ?></th>
									<th><?php _e( 'Due', 'onroute-courier-booking' ); ?></th>
									<th><?php _e( 'Actions', 'onroute-courier-booking' ); ?></th>
								</tr>
							</thead>
							<tbody>
								<?php foreach ( $invoices as $inv ) : ?>
								<tr>
									<td><strong><?php echo esc_html( $inv->invoice_number ); ?></strong></td>
									<td><?php echo esc_html( $inv->booking_reference ?: 'N/A' ); ?></td>
									<td><?php echo $this->format_price( $inv->amount ); ?></td>
									<td><?php echo $this->format_price( $inv->vat_amount ); ?></td>
									<td><strong><?php echo $this->format_price( $inv->total_amount ); ?></strong></td>
									<td>
										<span class="ocb-badge ocb-badge-<?php echo esc_attr( $inv->status ); ?>">
											<?php echo esc_html( ucfirst( $inv->status ) ); ?>
										</span>
									</td>
									<td><?php echo $inv->due_date ? esc_html( date( 'j M Y', strtotime( $inv->due_date ) ) ) : '-'; ?></td>
									<td>
										<button class="ocb-btn-sm ocb-print-invoice" data-invoice="<?php echo esc_attr( $inv->invoice_number ); ?>" data-booking="<?php echo esc_attr( $inv->booking_reference ); ?>" data-company="<?php echo esc_attr( $inv->company_name ); ?>" data-amount="<?php echo esc_attr( $inv->amount ); ?>" data-vat="<?php echo esc_attr( $inv->vat_amount ); ?>" data-total="<?php echo esc_attr( $inv->total_amount ); ?>" data-status="<?php echo esc_attr( $inv->status ); ?>" data-due="<?php echo esc_attr( $inv->due_date ); ?>" data-date="<?php echo esc_attr( $inv->created_at ); ?>">
											<i class="fas fa-print"></i> <?php _e( 'Print', 'onroute-courier-booking' ); ?>
										</button>
									</td>
								</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
					</div>
					<?php else : ?>
						<div class="ocb-empty-state">
							<i class="fas fa-file-invoice" style="font-size:48px; color:#ddd; margin-bottom:15px;"></i>
							<p><?php _e( 'No invoices yet. Invoices are auto-generated when bookings are placed.', 'onroute-courier-booking' ); ?></p>
						</div>
					<?php endif; ?>
				</div>

				<!-- Tab: Saved Locations -->
				<div id="dash-locations" class="dash-tab-content">
					<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
						<h2 style="margin:0;"><?php _e( 'Saved Locations', 'onroute-courier-booking' ); ?></h2>
						<button class="button combined-btn" id="ocb-add-location-btn"><i class="fas fa-plus"></i> <?php _e( 'Add Location', 'onroute-courier-booking' ); ?></button>
					</div>

					<!-- Add/Edit Location Form (hidden by default) -->
					<div id="ocb-location-form-wrap" style="display:none; margin-bottom:25px;">
						<div class="settings-card">
							<h3 id="ocb-location-form-title" style="margin-top:0;"><?php _e( 'Add New Location', 'onroute-courier-booking' ); ?></h3>
							<form id="ocb-location-form" class="ocb-ext-form">
								<input type="hidden" name="location_id" value="">
								<div class="ocb-form-grid">
									<div class="setting-row">
										<label><?php _e( 'Label *', 'onroute-courier-booking' ); ?></label>
										<input type="text" name="label" placeholder="e.g. Head Office, Warehouse" class="ocb-input" required>
									</div>
									<div class="setting-row">
										<label><?php _e( 'Type', 'onroute-courier-booking' ); ?></label>
										<select name="type" class="ocb-input">
											<option value="both">Pickup & Delivery</option>
											<option value="pickup">Pickup Only</option>
											<option value="delivery">Delivery Only</option>
										</select>
									</div>
								</div>
								<div class="setting-row">
									<label><?php _e( 'Address *', 'onroute-courier-booking' ); ?></label>
									<textarea name="address" class="ocb-input" rows="2" required></textarea>
								</div>
								<div class="ocb-form-grid">
									<div class="setting-row">
										<label><?php _e( 'Postcode *', 'onroute-courier-booking' ); ?></label>
										<input type="text" name="postcode" class="ocb-input" required>
									</div>
									<div class="setting-row">
										<label><?php _e( 'Contact Name', 'onroute-courier-booking' ); ?></label>
										<input type="text" name="contact_name" class="ocb-input">
									</div>
									<div class="setting-row">
										<label><?php _e( 'Contact Phone', 'onroute-courier-booking' ); ?></label>
										<input type="text" name="contact_phone" class="ocb-input">
									</div>
									<div class="setting-row">
										<label><?php _e( 'Contact Email', 'onroute-courier-booking' ); ?></label>
										<input type="email" name="contact_email" class="ocb-input">
									</div>
								</div>
								<div style="margin-top:15px;">
									<button type="submit" class="button combined-btn"><?php _e( 'Save Location', 'onroute-courier-booking' ); ?></button>
									<button type="button" class="button" id="ocb-cancel-location" style="margin-left:10px;"><?php _e( 'Cancel', 'onroute-courier-booking' ); ?></button>
									<span id="ocb-location-msg" style="margin-left:10px;"></span>
								</div>
							</form>
						</div>
					</div>

					<!-- Locations List -->
					<div id="ocb-locations-list">
						<?php
						$locations = OnRoute_Dashboard_Extensions::get_user_locations( $user->ID );
						if ( ! empty( $locations ) ) :
						?>
						<div class="ocb-locations-grid">
							<?php foreach ( $locations as $loc ) : ?>
							<div class="ocb-location-card" data-id="<?php echo $loc->id; ?>">
								<div class="loc-header">
									<h4><?php echo esc_html( $loc->label ); ?></h4>
									<span class="ocb-badge ocb-badge-<?php echo esc_attr( $loc->type ); ?>"><?php echo esc_html( ucfirst( $loc->type ) ); ?></span>
								</div>
								<p class="loc-address"><?php echo esc_html( $loc->address ); ?></p>
								<p class="loc-postcode"><strong><?php echo esc_html( $loc->postcode ); ?></strong></p>
								<?php if ( $loc->contact_name ) : ?>
									<p class="loc-contact"><i class="fas fa-user"></i> <?php echo esc_html( $loc->contact_name ); ?><?php echo $loc->contact_phone ? ' — ' . esc_html( $loc->contact_phone ) : ''; ?></p>
								<?php endif; ?>
								<div class="loc-actions">
									<button class="ocb-btn-sm ocb-edit-location" data-loc='<?php echo esc_attr( json_encode( $loc ) ); ?>'><i class="fas fa-edit"></i> Edit</button>
									<button class="ocb-btn-sm ocb-btn-danger ocb-delete-location" data-id="<?php echo $loc->id; ?>"><i class="fas fa-trash"></i> Delete</button>
								</div>
							</div>
							<?php endforeach; ?>
						</div>
						<?php else : ?>
						<div class="ocb-empty-state">
							<i class="fas fa-map-marker-alt" style="font-size:48px; color:#ddd; margin-bottom:15px;"></i>
							<p><?php _e( 'No saved locations yet. Save frequently used addresses for faster bookings.', 'onroute-courier-booking' ); ?></p>
						</div>
						<?php endif; ?>
					</div>
				</div>

				<!-- Tab: Support -->
				<div id="dash-support" class="dash-tab-content">
					<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
						<h2 style="margin:0;"><?php _e( 'Support', 'onroute-courier-booking' ); ?></h2>
						<button class="button combined-btn" id="ocb-new-ticket-btn"><i class="fas fa-plus"></i> <?php _e( 'New Ticket', 'onroute-courier-booking' ); ?></button>
					</div>

					<!-- New Ticket Form -->
					<div id="ocb-ticket-form-wrap" style="display:none; margin-bottom:25px;">
						<div class="settings-card">
							<h3 style="margin-top:0;"><?php _e( 'Create Support Ticket', 'onroute-courier-booking' ); ?></h3>
							<form id="ocb-ticket-form" class="ocb-ext-form" enctype="multipart/form-data">
								<div class="setting-row">
									<label><?php _e( 'Subject *', 'onroute-courier-booking' ); ?></label>
									<input type="text" name="subject" class="ocb-input" required>
								</div>
								<div class="setting-row">
									<label><?php _e( 'Related Booking Reference', 'onroute-courier-booking' ); ?></label>
									<input type="text" name="booking_reference" class="ocb-input" placeholder="e.g. ONR-XXXX-XXXX">
								</div>
								<div class="setting-row">
									<label><?php _e( 'Message *', 'onroute-courier-booking' ); ?></label>
									<textarea name="message" class="ocb-input" rows="5" required></textarea>
								</div>
								<div class="setting-row">
									<label><?php _e( 'Attachment (optional)', 'onroute-courier-booking' ); ?></label>
									<input type="file" name="attachment" class="ocb-input" accept="image/*,.pdf,.doc,.docx">
								</div>
								<div style="margin-top:15px;">
									<button type="submit" class="button combined-btn"><?php _e( 'Submit Ticket', 'onroute-courier-booking' ); ?></button>
									<button type="button" class="button" id="ocb-cancel-ticket" style="margin-left:10px;"><?php _e( 'Cancel', 'onroute-courier-booking' ); ?></button>
									<span id="ocb-ticket-msg" style="margin-left:10px;"></span>
								</div>
							</form>
						</div>
					</div>

					<!-- Tickets List -->
					<div id="ocb-tickets-list">
						<?php
						$tickets = OnRoute_Dashboard_Extensions::get_user_tickets( $user->ID );
						if ( ! empty( $tickets ) ) :
						?>
						<div class="ocb-tickets-list">
							<?php foreach ( $tickets as $t ) : ?>
							<div class="ocb-ticket-card ocb-ticket-<?php echo esc_attr( $t->status ); ?>">
								<div class="ticket-header">
									<h4>#<?php echo $t->id; ?> — <?php echo esc_html( $t->subject ); ?></h4>
									<span class="ocb-badge ocb-badge-<?php echo esc_attr( $t->status ); ?>">
										<?php echo esc_html( ucfirst( str_replace( '_', ' ', $t->status ) ) ); ?>
									</span>
								</div>
								<?php if ( $t->booking_reference ) : ?>
									<p class="ticket-booking"><i class="fas fa-box"></i> <?php echo esc_html( $t->booking_reference ); ?></p>
								<?php endif; ?>
								<p class="ticket-message"><?php echo esc_html( wp_trim_words( $t->message, 30 ) ); ?></p>
								<p class="ticket-date"><i class="fas fa-clock"></i> <?php echo esc_html( date( 'j M Y H:i', strtotime( $t->created_at ) ) ); ?></p>
								<?php if ( $t->admin_reply ) : ?>
									<div class="ticket-reply">
										<p class="reply-label"><i class="fas fa-reply"></i> <?php _e( 'Admin Reply', 'onroute-courier-booking' ); ?> <small>(<?php echo esc_html( date( 'j M Y', strtotime( $t->replied_at ) ) ); ?>)</small></p>
										<p><?php echo esc_html( $t->admin_reply ); ?></p>
									</div>
								<?php endif; ?>
							</div>
							<?php endforeach; ?>
						</div>
						<?php else : ?>
						<div class="ocb-empty-state">
							<i class="fas fa-life-ring" style="font-size:48px; color:#ddd; margin-bottom:15px;"></i>
							<p><?php _e( 'No support tickets. Need help? Create a ticket and we\'ll respond within 24 hours.', 'onroute-courier-booking' ); ?></p>
						</div>
						<?php endif; ?>
					</div>
				</div>
			</div>
		</div>

		<script>
		jQuery(document).ready(function($) {
			var extNonce = '<?php echo wp_create_nonce( "ocb_dashboard_ext" ); ?>';

			// Tab switching
			$('.sidebar-nav .nav-item').on('click', function(e) {
				if($(this).hasClass('logout')) return;
				e.preventDefault();
				var target = $(this).data('target');
				$('.nav-item').removeClass('active');
				$(this).addClass('active');
				$('.dash-tab-content').removeClass('active');
				$('#' + target).addClass('active');
			});

			// === VIEW JOB DETAIL ===
			$(document).on('click', '.ocb-view-job', function(e) {
				e.preventDefault();
				var bookingId = $(this).data('id');
				$('#ocb-job-detail-content').html('<p style="text-align:center; padding:40px;"><i class="fas fa-spinner fa-spin"></i> Loading...</p>');
				$('.nav-item').removeClass('active');
				$('.dash-tab-content').removeClass('active');
				$('#dash-job-detail').addClass('active');

				$.post('<?php echo admin_url("admin-ajax.php"); ?>', {
					action: 'ocb_get_job_detail',
					nonce: extNonce,
					booking_id: bookingId
				}, function(res) {
					if (res.success && res.data.html) {
						$('#ocb-job-detail-content').html(res.data.html);
					} else {
						$('#ocb-job-detail-content').html('<p style="color:red; text-align:center; padding:40px;">Could not load booking details.</p>');
					}
				});
			});

			// === ACCOUNT UPDATE ===
			$('#ocb-account-form').on('submit', function(e) {
				e.preventDefault();
				var $form = $(this);
				var data = $form.serialize() + '&action=ocb_update_account&nonce=' + extNonce;
				$.post('<?php echo admin_url("admin-ajax.php"); ?>', data, function(res) {
					$('#ocb-account-msg').html(res.success ? '<span style="color:#28a745;">✓ ' + res.data.message + '</span>' : '<span style="color:#dc3545;">✗ ' + res.data.message + '</span>');
				});
			});

			// === PASSWORD CHANGE ===
			$('#ocb-password-form').on('submit', function(e) {
				e.preventDefault();
				var data = $(this).serialize() + '&action=ocb_change_password&nonce=' + extNonce;
				$.post('<?php echo admin_url("admin-ajax.php"); ?>', data, function(res) {
					$('#ocb-password-msg').html(res.success ? '<span style="color:#28a745;">✓ ' + res.data.message + '</span>' : '<span style="color:#dc3545;">✗ ' + res.data.message + '</span>');
					if (res.success) { $('input[type=password]').val(''); }
				});
			});

			// === SAVED LOCATIONS ===
			$('#ocb-add-location-btn').on('click', function() {
				$('#ocb-location-form')[0].reset();
				$('#ocb-location-form input[name=location_id]').val('');
				$('#ocb-location-form-title').text('Add New Location');
				$('#ocb-location-form-wrap').slideDown(300);
			});
			$('#ocb-cancel-location').on('click', function() {
				$('#ocb-location-form-wrap').slideUp(300);
			});

			$(document).on('click', '.ocb-edit-location', function() {
				var loc = $(this).data('loc');
				$('#ocb-location-form input[name=location_id]').val(loc.id);
				$('#ocb-location-form input[name=label]').val(loc.label);
				$('#ocb-location-form select[name=type]').val(loc.type);
				$('#ocb-location-form textarea[name=address]').val(loc.address);
				$('#ocb-location-form input[name=postcode]').val(loc.postcode);
				$('#ocb-location-form input[name=contact_name]').val(loc.contact_name || '');
				$('#ocb-location-form input[name=contact_phone]').val(loc.contact_phone || '');
				$('#ocb-location-form input[name=contact_email]').val(loc.contact_email || '');
				$('#ocb-location-form-title').text('Edit Location');
				$('#ocb-location-form-wrap').slideDown(300);
				$('html, body').animate({ scrollTop: $('#ocb-location-form-wrap').offset().top - 100 }, 300);
			});

			$('#ocb-location-form').on('submit', function(e) {
				e.preventDefault();
				var data = $(this).serialize() + '&action=ocb_save_location&nonce=' + extNonce;
				$.post('<?php echo admin_url("admin-ajax.php"); ?>', data, function(res) {
					$('#ocb-location-msg').html(res.success ? '<span style="color:#28a745;">✓ ' + res.data.message + '</span>' : '<span style="color:#dc3545;">✗ ' + res.data.message + '</span>');
					if (res.success) { setTimeout(function() { location.reload(); }, 1000); }
				});
			});

			$(document).on('click', '.ocb-delete-location', function() {
				if (!confirm('Delete this location?')) return;
				var $card = $(this).closest('.ocb-location-card');
				$.post('<?php echo admin_url("admin-ajax.php"); ?>', {
					action: 'ocb_delete_location',
					nonce: extNonce,
					location_id: $(this).data('id')
				}, function(res) {
					if (res.success) { $card.fadeOut(300, function() { $(this).remove(); }); }
				});
			});

			// === SUPPORT TICKETS ===
			$('#ocb-new-ticket-btn').on('click', function() {
				$('#ocb-ticket-form')[0].reset();
				$('#ocb-ticket-form-wrap').slideDown(300);
			});
			$('#ocb-cancel-ticket').on('click', function() {
				$('#ocb-ticket-form-wrap').slideUp(300);
			});

			$('#ocb-ticket-form').on('submit', function(e) {
				e.preventDefault();
				var formData = new FormData(this);
				formData.append('action', 'ocb_create_ticket');
				formData.append('nonce', extNonce);

				$.ajax({
					url: '<?php echo admin_url("admin-ajax.php"); ?>',
					type: 'POST',
					data: formData,
					processData: false,
					contentType: false,
					success: function(res) {
						$('#ocb-ticket-msg').html(res.success ? '<span style="color:#28a745;">✓ ' + res.data.message + '</span>' : '<span style="color:#dc3545;">✗ ' + res.data.message + '</span>');
						if (res.success) { setTimeout(function() { location.reload(); }, 1500); }
					}
				});
			});

			// === SETTINGS TAB SWITCHING ===
			$('.settings-nav-tab').on('click', function() {
				var target = $(this).data('tab');
				$('.settings-nav-tab').removeClass('active');
				$(this).addClass('active');
				$('.settings-tab-pane').hide();
				$('#' + target).fadeIn(200);
			});

			// === INVOICE PRINT ===
			$(document).on('click', '.ocb-print-invoice', function() {
				var d = $(this).data();
				var w = window.open('', '_blank', 'width=800,height=900');
				w.document.write('<html><head><title>Invoice ' + d.invoice + '</title>');
				w.document.write('<style>body{font-family:Arial,sans-serif;padding:40px;color:#333}h1{color:#e31837;margin-bottom:5px}table{width:100%;border-collapse:collapse;margin-top:20px}th,td{padding:10px;border-bottom:1px solid #eee;text-align:left}th{background:#f8f9fa;font-weight:700}.totals td{border-top:2px solid #333;font-weight:700}.badge{display:inline-block;padding:3px 10px;border-radius:4px;font-size:12px;font-weight:600}.paid{background:#d4edda;color:#155724}.unpaid{background:#fff3cd;color:#856404}</style>');
				w.document.write('</head><body>');
				w.document.write('<h1>ONROUTE COURIERS</h1>');
				w.document.write('<p style="color:#666;margin:0 0 30px">Invoice</p>');
				w.document.write('<table><tr><td><strong>Invoice:</strong> ' + d.invoice + '</td><td><strong>Date:</strong> ' + (d.date ? new Date(d.date).toLocaleDateString() : '-') + '</td></tr>');
				w.document.write('<tr><td><strong>Booking:</strong> ' + (d.booking || 'N/A') + '</td><td><strong>Due:</strong> ' + (d.due ? new Date(d.due).toLocaleDateString() : '-') + '</td></tr>');
				w.document.write('<tr><td><strong>Company:</strong> ' + (d.company || '-') + '</td><td><strong>Status:</strong> <span class="badge ' + d.status + '">' + d.status.toUpperCase() + '</span></td></tr></table>');
				w.document.write('<table style="margin-top:30px"><tr><th>Description</th><th style="text-align:right">Amount</th></tr>');
				w.document.write('<tr><td>Courier delivery service (Ref: ' + (d.booking || '') + ')</td><td style="text-align:right">£' + parseFloat(d.amount).toFixed(2) + '</td></tr>');
				w.document.write('<tr><td>VAT</td><td style="text-align:right">£' + parseFloat(d.vat).toFixed(2) + '</td></tr>');
				w.document.write('<tr class="totals"><td>Total</td><td style="text-align:right">£' + parseFloat(d.total).toFixed(2) + '</td></tr></table>');
				w.document.write('<p style="margin-top:40px;color:#999;font-size:12px">OnRoute Couriers | ops@onroutecouriers.com | Payment terms: 30 days</p>');
				w.document.write('</body></html>');
				w.document.close();
				w.print();
			});

			// === POD DOWNLOAD ===
			$(document).on('click', '.ocb-download-pod', function() {
				var bookingId = $(this).data('id');
				$.post('<?php echo admin_url("admin-ajax.php"); ?>', {
					action: 'ocb_download_pod',
					nonce: extNonce,
					booking_id: bookingId
				}, function(res) {
					if (res.success) {
						var w = window.open('', '_blank', 'width=800,height=900');
						w.document.write(res.data.html);
						w.document.close();
					} else {
						alert(res.data.message || 'Failed to download POD');
					}
				});
			});
		});
		</script>

		<style>
			/* Force FontAwesome for all OCB icons */
			.ocb-dashboard-wrapper i, .ocb-promo-container i { font-family: "Font Awesome 6 Free" !important; font-weight: 900 !important; }

			.ocb-dashboard-wrapper { display: flex; background: #fff; border-radius: 20px; overflow: hidden; box-shadow: 0 10px 40px rgba(0,0,0,0.05); border: 1px solid #eee; min-height: 600px; font-family: 'Inter', sans-serif; }
			
			.ocb-dashboard-sidebar { width: 260px; background: #f8f9fa; border-right: 1px solid #eee; padding: 40px 15px; display: flex; flex-direction: column; flex-shrink: 0; }
			.sidebar-user { text-align: center; margin-bottom: 30px; padding-bottom: 25px; border-bottom: 1px solid #eee; }
			.user-avatar img { border-radius: 50%; border: 3px solid #fff; box-shadow: 0 5px 15px rgba(0,0,0,0.1); margin-bottom: 12px; width: 60px; }
			.sidebar-user h4 { margin: 0; font-size: 16px; font-weight: 700; color: #111; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
			.sidebar-user p { margin: 5px 0 0; color: #777; font-size: 13px; }
			
			.nav-group-label { font-size: 11px; text-transform: uppercase; color: #999; font-weight: 700; margin: 15px 0 5px 15px; letter-spacing: 0.5px; }

			.sidebar-nav { display: flex; flex-direction: column; gap: 6px; }
			.nav-item { border: none; background: transparent; padding: 12px 16px; border-radius: 10px; text-align: left; font-size: 14px; font-weight: 600; color: #555; cursor: pointer; transition: 0.3s; display: flex; align-items: center; gap: 10px; text-decoration: none; width: 100%; box-sizing: border-box; }
			.nav-item i { font-size: 16px; width: 20px; text-align: center; }
			.nav-item:hover { background: #fee2e2; color: #e31837; }
			.nav-item.active { background: #e31837; color: #fff; box-shadow: 0 4px 12px rgba(227, 24, 55, 0.2); }
			.nav-item.logout { margin-top: 10px; color: #888; border-top: 1px solid #eee; padding-top: 20px; border-radius: 0; }
			.nav-item.logout:hover { color: #e31837; background: transparent; }

			.ocb-dashboard-main { flex: 1; padding: clamp(20px, 4vw, 40px); min-width: 0; }
			.dash-tab-content { display: none; animation: fadeIn 0.4s ease; }
			.dash-tab-content.active { display: block; }

			/* Settings Tabs CSS */
			.settings-nav { display: flex; border-bottom: 2px solid #eee; margin-bottom: 25px; gap: 20px; overflow-x: auto; white-space: nowrap; -webkit-overflow-scrolling: touch; padding-bottom: 5px; }
			.settings-nav-tab { background: none; border: none; padding: 10px 5px; font-weight: 600; color: #777; cursor: pointer; border-bottom: 2px solid transparent; margin-bottom: -2px; transition: 0.3s; font-size: 14px; flex-shrink: 0; }
			.settings-nav-tab:hover { color: #e31837; }
			.settings-nav-tab.active { color: #e31837; border-bottom-color: #e31837; }
			.settings-tab-pane { display: none; animation: fadeIn 0.3s ease; }
			.settings-tab-pane.active { display: block; }

			
			.dash-welcome { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; flex-wrap: wrap; gap: 15px; }
			.dash-welcome h2 { margin: 0; font-size: clamp(20px, 3vw, 24px); font-weight: 800; color: #111; }
			
			.stats-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; margin-bottom: 40px; }
			.stat-card { background: #fff; padding: 25px; border-radius: 16px; border: 1px solid #eee; box-shadow: 0 5px 15px rgba(0,0,0,0.02); }
			.stat-card.highlight { background: #111; border-color: #111; }
			.stat-card.highlight .label { color: #999; }
			.stat-card.highlight .value { color: #fff; }
			.stat-card .label { display: block; font-size: 11px; font-weight: 700; text-transform: uppercase; color: #777; letter-spacing: 0.5px; margin-bottom: 10px; }
			.stat-card .value { font-size: clamp(20px, 2.5vw, 28px); font-weight: 800; color: #111; overflow: hidden; text-overflow: ellipsis; }

			.recent-activity h3 { font-size: 18px; margin-bottom: 20px; font-weight: 700; }
			
			.status-badge { padding: 5px 12px; border-radius: 30px; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; white-space: nowrap; }
			.status-approved { background: #e7f4e4; color: #1e4620; }
			.status-pending { background: #fff8e5; color: #856404; }
			.status-suspended { background: #f8d7da; color: #721c24; }
			
			.settings-card { max-width: 100%; background: #fafafa; padding: 25px; border-radius: 16px; border: 1px solid #eee; }
			.setting-row { margin-bottom: 15px; }
			.setting-row label { display: block; font-size: 12px; color: #888; margin-bottom: 5px; }
			.setting-row p { margin: 0; font-size: 15px; color: #111; font-weight: 600; }
			.help-text { font-size: 12px; color: #777; font-style: italic; margin-bottom: 12px !important; }

			@media (max-width: 991px) {
				.ocb-dashboard-wrapper { flex-direction: column; min-height: auto; }
				.ocb-dashboard-sidebar { width: 100%; border-right: none; border-bottom: 1px solid #eee; padding: 25px 20px; }
				.nav-group-label { display: none; }
				.sidebar-user { display: flex; align-items: center; text-align: left; margin-bottom: 20px; padding-bottom: 20px; gap: 15px; }
				.user-avatar img { margin-bottom: 0; width: 50px; }
				.sidebar-nav { flex-direction: row; overflow-x: auto; padding-bottom: 5px; -webkit-overflow-scrolling: touch; }
				.nav-item { flex-shrink: 0; width: auto; padding: 10px 15px; font-size: 13px; }
				.nav-item.logout { margin-top: 0; border-top: none; padding-top: 10px; margin-left: auto; }
				.stats-grid { grid-template-columns: repeat(3, 1fr); gap: 10px; margin-bottom: 25px; }
				.stat-card { padding: 12px 8px; border-radius: 10px; text-align: center; }
				.stat-card .label { font-size: 8px; margin-bottom: 5px; letter-spacing: 0.5px; }
				.stat-card .value { font-size: 14px; }
			}
			@media (max-width: 600px) {
				.dash-welcome { flex-direction: column; align-items: flex-start; }
				.sidebar-user { flex-direction: column; text-align: center; }
				.sidebar-nav { justify-content: space-between; gap: 5px; }
				.nav-item span { display: none; }
				.nav-item { padding: 10px; font-size: 12px; }
				.stats-grid { gap: 6px; }
				.stat-card { padding: 10px 5px; }
				.stat-card .value { font-size: 12px; }
			}
		</style>
		<?php
	}

	/**
	 * Render user bookings
	 */
	private function render_user_bookings( $email, $limit = 10 ) {
		global $wpdb;
		$bookings_table = OnRoute_Courier_Booking_Database::get_bookings_table();
		$email = trim(strtolower($email));
		$user_id = get_current_user_id();

		// Check if user_id column exists for robust matching
		$has_user_id = $wpdb->get_results( "SHOW COLUMNS FROM $bookings_table LIKE 'user_id'" );
		
		if ( ! empty( $has_user_id ) && $user_id > 0 ) {
			// Query by both Email OR User ID for maximum reliability
			$bookings = $wpdb->get_results( $wpdb->prepare( 
				"SELECT * FROM $bookings_table 
				 WHERE (LOWER(TRIM(customer_email)) = %s OR user_id = %d) 
				 ORDER BY created_at DESC LIMIT %d", 
				$email, $user_id, $limit 
			) );
		} else {
			$bookings = $wpdb->get_results( $wpdb->prepare( 
				"SELECT * FROM $bookings_table 
				 WHERE LOWER(TRIM(customer_email)) = %s 
				 ORDER BY created_at DESC LIMIT %d", 
				$email, $limit 
			) );
		}

		if ( empty( $bookings ) ) {
			echo '<p class="no-bookings">' . __( 'No bookings found.', 'onroute-courier-booking' ) . '</p>';
			return;
		}
		?>
		<div class="bookings-table-wrap" style="overflow-x:auto;">
			<table class="dashboard-table">
				<thead>
					<tr>
						<th><?php _e( 'Reference', 'onroute-courier-booking' ); ?></th>
						<th><?php _e( 'Route (Pickup → Delivery)', 'onroute-courier-booking' ); ?></th>
						<th><?php _e( 'Vehicle / Service', 'onroute-courier-booking' ); ?></th>
						<th><?php _e( 'Status', 'onroute-courier-booking' ); ?></th>
						<th><?php _e( 'Total', 'onroute-courier-booking' ); ?></th>
						<th><?php _e( 'Action', 'onroute-courier-booking' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $bookings as $booking ) : 
						// Display vehicle and service names more nicely
						$v_name = ucfirst( str_replace( array( 'mwb', 'lwb', '_' ), array( 'Medium Van', 'Large Van', ' ' ), $booking->vehicle_id ) );
						$s_name = ucfirst( str_replace( array( 'direct', 'timed' ), array( 'Dedicated', 'Priority' ), $booking->service_id ) );
						
						// Determine if addresses or only postcodes should be shown based on length
						$route_display = '<strong>' . esc_html( $booking->pickup_postcode ) . '</strong> → <strong>' . esc_html( $booking->delivery_postcode ) . '</strong>';
					?>
						<tr>
							<td style="font-weight:700;">
								#<?php echo esc_html( $booking->booking_reference ); ?>
								<div style="font-size: 10px; color: #999; font-weight: 400;"><?php echo date_i18n( get_option( 'date_format' ), strtotime( $booking->collection_date ) ); ?></div>
							</td>
							<td>
								<div class="route-info">
									<?php echo $route_display; ?>
									<?php if ( ! empty( $booking->pickup_address ) ) : ?>
										<div class="address-hint" style="font-size:11px; color:#777; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; max-width:250px;">
											<?php 
											$addr_lines = explode("\n", $booking->pickup_address);
											echo esc_html( $addr_lines[0] ); 
											?>
											→ 
											<?php 
											$del_lines = explode("\n", $booking->delivery_address);
											echo esc_html( $del_lines[0] ); 
											?>
										</div>
									<?php endif; ?>
								</div>
							</td>
							<td>
								<div style="font-weight:600; font-size:13px;"><?php echo esc_html( $v_name ); ?></div>
								<div style="font-size:11px; color:#e31837; font-weight:700; text-transform:uppercase; letter-spacing:0.5px;"><?php echo esc_html( $s_name ); ?></div>
							</td>
							<td><span class="status-pill status-<?php echo esc_attr( strtolower( $booking->status ) ); ?>"><?php 
								$status_labels = array(
									'booked' => 'Booked',
									'picked_up' => 'Picked up',
									'in_transit' => 'In Transit',
									'delivered' => 'Delivered',
									'completed' => 'Completed',
									'cancelled' => 'Cancelled',
								);
								echo esc_html( $status_labels[ $booking->status ] ?? ucfirst( str_replace( '_', ' ', $booking->status ) ) ); 
							?></span></td>
							<td><strong style="color:#111;"><?php echo $this->format_price( $booking->total_price ); ?></strong></td>
							<td>
								<button class="ocb-view-job" data-id="<?php echo esc_attr( $booking->id ); ?>" title="<?php _e( 'View Details', 'onroute-courier-booking' ); ?>" style="background:#e31837; color:#fff; border:none; padding:8px 12px; border-radius:6px; cursor:pointer; font-size:13px; margin-right:5px;"><i class="fas fa-eye"></i></button>
								<button class="print-booking-btn" 
									data-booking-id="<?php echo esc_attr( $booking->id ); ?>" 
									data-reference="<?php echo esc_attr( $booking->booking_reference ); ?>"
									data-collection-date="<?php echo esc_attr( $booking->collection_date ); ?>"
									data-collection-time="<?php echo esc_attr( $booking->collection_time ); ?>"
									data-pickup-address="<?php echo esc_attr( $booking->pickup_address ); ?>"
									data-pickup-postcode="<?php echo esc_attr( $booking->pickup_postcode ); ?>"
									data-delivery-date="<?php echo esc_attr( $booking->delivery_date ); ?>"
									data-delivery-time="<?php echo esc_attr( $booking->delivery_time ); ?>"
									data-delivery-address="<?php echo esc_attr( $booking->delivery_address ); ?>"
									data-delivery-postcode="<?php echo esc_attr( $booking->delivery_postcode ); ?>"
									data-vehicle="<?php echo esc_attr( $v_name ); ?>"
									data-service="<?php echo esc_attr( $s_name ); ?>"
									data-total="<?php echo esc_attr( $this->format_price( $booking->total_price ) ); ?>"
									data-status="<?php echo esc_attr( $booking->status ); ?>"
									data-notes="<?php echo esc_attr( $booking->notes ?? '' ); ?>"
									title="<?php _e( 'Print Booking', 'onroute-courier-booking' ); ?>">
									<i class="fas fa-print"></i>
								</button>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<style>
			.dashboard-table { width: 100%; border-collapse: collapse; text-align: left; min-width: 700px; }
			.dashboard-table th { padding: 15px; color: #888; font-size: 11px; font-weight: 700; text-transform: uppercase; border-bottom: 2px solid #f0f0f0; letter-spacing: 0.5px; }
			.dashboard-table td { padding: 16px 15px; border-bottom: 1px solid #f8f8f8; font-size: 14px; vertical-align: middle; }
			.dashboard-table tr:hover td { background: #fafafa; }
			.status-pill { padding: 4px 10px; border-radius: 4px; font-size: 10px; font-weight: 800; text-transform: uppercase; background: #eee; color: #777; white-space: nowrap; }
			.status-pill.status-completed, .status-pill.status-paid { background: #d4edda; color: #155724; }
			.status-pill.status-booked { background: #fff3cd; color: #856404; }
			.status-pill.status-confirmed { background: #cce5ff; color: #004085; }
			.status-pill.status-picked_up { background: #d1ecf1; color: #0c5460; }
			.status-pill.status-in_transit, .status-pill.status-in.transit { background: #d1ecf1; color: #0c5460; }
			.status-pill.status-delivered { background: #d4edda; color: #155724; }
			.status-pill.status-cancelled, .status-pill.status-failed { background: #f8d7da; color: #721c24; }
			.status-pill.status-unpaid { background: #e2e3e5; color: #383d41; }
			.no-bookings { color: #aaa; text-align: center; padding: 60px 40px; background: #fdfdfd; border: 2px dashed #eee; border-radius: 12px; margin: 20px 0; }
			.route-info strong { color: #333; }
			.print-booking-btn { background: #17a2b8; color: #fff; border: none; padding: 8px 12px; border-radius: 6px; cursor: pointer; transition: 0.3s; font-size: 14px; }
			.print-booking-btn:hover { background: #138496; transform: translateY(-2px); }
			.print-booking-btn i { pointer-events: none; }
			
			@media (max-width: 991px) {
				.recent-activity { overflow-x: auto; -webkit-overflow-scrolling: touch; }
				.dashboard-table { min-width: 600px; font-size: 13px; }
				.dashboard-table th { padding: 10px 8px; font-size: 9px; }
				.dashboard-table td { padding: 12px 8px; }
				.status-pill { font-size: 9px; padding: 3px 8px; }
				.print-booking-btn { padding: 6px 10px; font-size: 12px; }
			}
			
			@media (max-width: 768px) {
				.dashboard-table { min-width: 500px; font-size: 12px; }
				.dashboard-table th { padding: 8px 6px; font-size: 8px; }
				.dashboard-table td { padding: 10px 6px; }
				.route-info { font-size: 11px; }
				.no-bookings { padding: 40px 20px; font-size: 13px; }
			}
			
			@media (max-width: 600px) {
				.recent-activity h3 { font-size: 16px; margin-bottom: 15px; }
				.dashboard-table { min-width: 450px; }
				.print-booking-btn { padding: 5px 8px; }
				.print-booking-btn i { font-size: 12px; }
			}
			
			@media print {
				body * { visibility: hidden; }
				#booking-print-area, #booking-print-area * { visibility: visible; }
				#booking-print-area { position: absolute; left: 0; top: 0; width: 100%; }
			}

			/* === Dashboard Extension Styles === */
			.ocb-ext-form .ocb-input { width: 100%; padding: 10px 14px; border: 1px solid #ddd; border-radius: 8px; font-size: 14px; font-family: inherit; box-sizing: border-box; transition: border-color 0.3s; }
			.ocb-ext-form .ocb-input:focus { border-color: #e31837; outline: none; box-shadow: 0 0 0 3px rgba(227,24,55,0.08); }
			.ocb-ext-form select.ocb-input { appearance: auto; }
			.ocb-form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; }
			@media (max-width: 600px) { .ocb-form-grid { grid-template-columns: 1fr; } }

			/* Data tables */
			.ocb-table-wrap { overflow-x: auto; -webkit-overflow-scrolling: touch; }
			.ocb-data-table { width: 100%; border-collapse: collapse; min-width: 600px; }
			.ocb-data-table th { padding: 12px; color: #888; font-size: 11px; font-weight: 700; text-transform: uppercase; border-bottom: 2px solid #f0f0f0; text-align: left; }
			.ocb-data-table td { padding: 12px; border-bottom: 1px solid #f8f8f8; font-size: 13px; vertical-align: middle; }
			.ocb-data-table tr:hover td { background: #fafafa; }

			/* Badges */
			.ocb-badge { display: inline-block; padding: 3px 10px; border-radius: 4px; font-size: 11px; font-weight: 700; text-transform: uppercase; }
			.ocb-badge-paid { background: #d4edda; color: #155724; }
			.ocb-badge-unpaid { background: #fff3cd; color: #856404; }
			.ocb-badge-overdue { background: #f8d7da; color: #721c24; }
			.ocb-badge-open { background: #fff3cd; color: #856404; }
			.ocb-badge-in_progress { background: #cce5ff; color: #004085; }
			.ocb-badge-closed { background: #d4edda; color: #155724; }
			.ocb-badge-both { background: #e2e3e5; color: #383d41; }
			.ocb-badge-pickup { background: #cce5ff; color: #004085; }
			.ocb-badge-delivery { background: #d1ecf1; color: #0c5460; }

			/* Buttons */
			.ocb-btn-sm { background: #f0f0f0; color: #333; border: none; padding: 6px 12px; border-radius: 6px; cursor: pointer; font-size: 12px; font-weight: 600; transition: 0.2s; }
			.ocb-btn-sm:hover { background: #e0e0e0; }
			.ocb-btn-danger { color: #dc3545 !important; }
			.ocb-btn-danger:hover { background: #f8d7da !important; }

			/* Saved Locations */
			.ocb-locations-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 15px; }
			.ocb-location-card { background: #fafafa; border: 1px solid #eee; border-radius: 12px; padding: 20px; transition: box-shadow 0.3s; }
			.ocb-location-card:hover { box-shadow: 0 5px 20px rgba(0,0,0,0.05); }
			.loc-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px; }
			.loc-header h4 { margin: 0; font-size: 15px; font-weight: 700; color: #111; }
			.loc-address { font-size: 13px; color: #555; margin: 5px 0; }
			.loc-postcode { font-size: 14px; margin: 5px 0; }
			.loc-contact { font-size: 12px; color: #777; margin: 5px 0; }
			.loc-actions { display: flex; gap: 8px; margin-top: 12px; padding-top: 12px; border-top: 1px solid #eee; }

			/* Support Tickets */
			.ocb-tickets-list { display: flex; flex-direction: column; gap: 15px; }
			.ocb-ticket-card { background: #fafafa; border: 1px solid #eee; border-radius: 12px; padding: 20px; transition: box-shadow 0.3s; }
			.ocb-ticket-card:hover { box-shadow: 0 5px 20px rgba(0,0,0,0.05); }
			.ocb-ticket-open { border-left: 4px solid #ffc107; }
			.ocb-ticket-in_progress { border-left: 4px solid #007bff; }
			.ocb-ticket-closed { border-left: 4px solid #28a745; }
			.ticket-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px; }
			.ticket-header h4 { margin: 0; font-size: 15px; font-weight: 700; color: #111; }
			.ticket-booking { font-size: 12px; color: #666; margin: 5px 0; }
			.ticket-message { font-size: 13px; color: #555; margin: 8px 0; line-height: 1.5; }
			.ticket-date { font-size: 12px; color: #999; margin: 5px 0; }
			.ticket-reply { margin-top: 15px; padding: 15px; background: #e7f4e4; border-radius: 8px; }
			.reply-label { font-size: 12px; font-weight: 700; color: #1e4620; margin-bottom: 5px; }

			/* Empty States */
			.ocb-empty-state { text-align: center; padding: 60px 40px; background: #fdfdfd; border: 2px dashed #eee; border-radius: 12px; margin: 20px 0; }
			.ocb-empty-state p { color: #999; font-size: 14px; margin: 0; }

			/* Job Detail */
			.ocb-job-info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
			.ocb-job-card { background: #fafafa; border: 1px solid #eee; border-radius: 12px; padding: 20px; }
			.ocb-job-card h4 { margin: 0 0 15px; font-size: 15px; font-weight: 700; color: #111; }
			.ocb-pod-section { margin-top: 15px; padding-top: 15px; border-top: 1px solid #eee; }
			.ocb-pod-confirmed { color: #28a745; font-weight: 700; }
			.ocb-pod-pending { color: #dc3545; }
			.ocb-pod-sig img { max-width: 200px; border: 1px solid #ddd; border-radius: 6px; padding: 5px; background: #fff; margin-top: 8px; }
			@media (max-width: 768px) { .ocb-job-info-grid { grid-template-columns: 1fr; } }

			.button.combined-btn { background: #e31837; color: #fff; border: none; padding: 10px 24px; border-radius: 8px; font-weight: 700; cursor: pointer; transition: 0.3s; font-size: 14px; }
			.button.combined-btn:hover { background: #c4142e; color: #fff; }
		</style>
		
		<div id="booking-print-area" style="display:none;"></div>
		
		<script>
		jQuery(document).ready(function($) {
			$('.print-booking-btn').on('click', function() {
				var $btn = $(this);
				var reference = $btn.data('reference');
				var collectionDate = $btn.data('collection-date');
				var collectionTime = $btn.data('collection-time');
				var pickupAddress = $btn.data('pickup-address');
				var pickupPostcode = $btn.data('pickup-postcode');
				var deliveryDate = $btn.data('delivery-date');
				var deliveryTime = $btn.data('delivery-time');
				var deliveryAddress = $btn.data('delivery-address');
				var deliveryPostcode = $btn.data('delivery-postcode');
				var vehicle = $btn.data('vehicle');
				var service = $btn.data('service');
				var total = $btn.data('total');
				var status = $btn.data('status');
				var notesStr = $btn.data('notes') || '{}';
				
				// Parse notes to get collection/delivery contact info
				var notes = {};
				try {
					notes = typeof notesStr === 'string' ? JSON.parse(notesStr) : notesStr;
				} catch (e) {
					notes = {};
				}
				
				var collectionName = notes.collection_contact_name || '';
				var collectionPhone = notes.collection_contact_phone || '';
				var collectionEmail = notes.collection_contact_email || '';
				var deliveryName = notes.delivery_contact_name || '';
				var deliveryPhone = notes.delivery_contact_phone || '';
				var deliveryEmail = notes.delivery_contact_email || '';
				var company = notes.booked_by_company || '';
				
				// Create print window
				var printWindow = window.open('', '_blank', 'width=900,height=1200');
				var printContent = '<!DOCTYPE html>' +
					'<html><head>' +
					'<title>Booking Confirmation - ' + reference + '</title>' +
					'<style>' +
					'* { margin: 0; padding: 0; }' +
					'body { font-family: "Segoe UI", Arial, sans-serif; background: #fff; padding: 30px; }' +
					'.print-container { max-width: 850px; margin: 0 auto; }' +
					'.header { text-align: center; margin-bottom: 25px; padding-bottom: 20px; border-bottom: 3px solid #e31837; }' +
					'.header h1 { color: #e31837; margin: 0 0 5px 0; font-size: 36px; font-weight: bold; }' +
					'.header p { margin: 5px 0; color: #666; font-size: 14px; }' +
					'.reference-box { background: #f8f8f8; border: 2px solid #e31837; border-radius: 8px; padding: 15px; margin-bottom: 25px; }' +
					'.reference-box h2 { color: #e31837; font-size: 18px; margin: 0; }' +
					'.reference-box p { margin: 5px 0; font-size: 16px; font-weight: bold; }' +
					'.section { margin-bottom: 30px; }' +
					'.section-title { background: #e31837; color: #fff; padding: 12px 15px; margin-bottom: 15px; border-radius: 4px; font-size: 14px; font-weight: bold; text-transform: uppercase; letter-spacing: 1px; }' +
					'.section-content { background: #fafafa; border: 1px solid #e0e0e0; border-radius: 4px; padding: 15px; }' +
					'.detail-row { display: flex; margin-bottom: 12px; }' +
					'.detail-label { width: 150px; font-weight: bold; color: #333; font-size: 13px; }' +
					'.detail-value { flex: 1; color: #555; font-size: 13px; }' +
					'.address-block { background: #fff; border: 1px solid #ddd; border-radius: 4px; padding: 12px; margin-bottom: 10px; }' +
					'.address-block strong { color: #333; display: block; margin-bottom: 5px; }' +
					'.address-line { color: #555; font-size: 13px; margin: 2px 0; }' +
					'.summary-table { width: 100%; background: #fff; border-collapse: collapse; margin-top: 10px; }' +
					'.summary-table td { padding: 12px; border-bottom: 1px solid #eee; font-size: 13px; }' +
					'.summary-table td:last-child { text-align: right; }' +
					'.summary-table .label { font-weight: bold; width: 50%; }' +
					'.summary-table .total-row td { border-bottom: 2px solid #e31837; border-top: 2px solid #e31837; font-weight: bold; font-size: 16px; }' +
					'.summary-table .total-row .value { color: #e31837; }' +
					'.status-badge { display: inline-block; background: #e7f4e4; color: #1e4620; padding: 6px 12px; border-radius: 4px; font-size: 12px; font-weight: bold; text-transform: uppercase; }' +
					'.status-badge.completed { background: #e7f4e4; color: #1e4620; }' +
					'.footer { text-align: center; margin-top: 40px; padding-top: 20px; border-top: 1px solid #ddd; color: #999; font-size: 12px; }' +
					'@media print { body { padding: 20px; } .print-container { box-shadow: none; } }' +
					'</style>' +
					'</head><body>' +
					'<div class="print-container">' +
					'<div class="header">' +
					'<h1>OnRoute Couriers</h1>' +
					'<p>Professional Courier Services</p>' +
					'</div>' +
					'<div class="reference-box">' +
					'<h2>Booking Reference</h2>' +
					'<p>' + reference + '</p>' +
					'</div>' +
					'<div class="section">' +
					'<div class="section-title"><i style="margin-right: 8px;">📦</i> Collection Details</div>' +
					'<div class="section-content">' +
					(collectionName ? '<div class="detail-row"><div class="detail-label">Contact:</div><div class="detail-value">' + escapeHtml(collectionName) + '</div></div>' : '') +
					(collectionPhone ? '<div class="detail-row"><div class="detail-label">Phone:</div><div class="detail-value">' + escapeHtml(collectionPhone) + '</div></div>' : '') +
					(collectionEmail ? '<div class="detail-row"><div class="detail-label">Email:</div><div class="detail-value">' + escapeHtml(collectionEmail) + '</div></div>' : '') +
					'<div class="detail-row"><div class="detail-label">When:</div><div class="detail-value">' + formatDate(collectionDate) + ' at ' + formatTime(collectionTime) + '</div></div>' +
					'<div class="address-block"><strong>Pickup Address:</strong>' +
					'<div class="address-line">' + escapeHtml(pickupAddress).replace(/\\n/g, '<br>') + '</div>' +
					'<div class="address-line"><strong>Postcode:</strong> ' + escapeHtml(pickupPostcode) + '</div>' +
					'</div>' +
					'</div>' +
					'</div>' +
					'<div class="section">' +
					'<div class="section-title"><i style="margin-right: 8px;">🚚</i> Delivery Details</div>' +
					'<div class="section-content">' +
					(deliveryName ? '<div class="detail-row"><div class="detail-label">Contact:</div><div class="detail-value">' + escapeHtml(deliveryName) + '</div></div>' : '') +
					(deliveryPhone ? '<div class="detail-row"><div class="detail-label">Phone:</div><div class="detail-value">' + escapeHtml(deliveryPhone) + '</div></div>' : '') +
					(deliveryEmail ? '<div class="detail-row"><div class="detail-label">Email:</div><div class="detail-value">' + escapeHtml(deliveryEmail) + '</div></div>' : '') +
					'<div class="detail-row"><div class="detail-label">When:</div><div class="detail-value">' + 
					(deliveryDate ? formatDate(deliveryDate) : 'Today (Same Day)') + 
					(deliveryTime ? ' at ' + formatTime(deliveryTime) : (service.toLowerCase().indexOf('same day') !== -1 ? ' (Auto-scheduled)' : '')) + 
					'</div></div>' +
					'<div class="address-block"><strong>Delivery Address:</strong>' +
					'<div class="address-line">' + escapeHtml(deliveryAddress).replace(/\\n/g, '<br>') + '</div>' +
					'<div class="address-line"><strong>Postcode:</strong> ' + escapeHtml(deliveryPostcode) + '</div>' +
					'</div>' +
					'</div>' +
					'</div>' +
					'<div class="section">' +
					'<div class="section-title">Service & Pricing</div>' +
					'<div class="section-content">' +
					'<table class="summary-table">' +
					'<tr><td class="label">Vehicle/Service:</td><td>' + escapeHtml(vehicle) + ' - ' + escapeHtml(service) + '</td></tr>' +
					'<tr><td class="label">Status:</td><td><span class="status-badge ' + escapeHtml(status.toLowerCase()) + '">' + escapeHtml(status) + '</span></td></tr>' +
					'<tr class="total-row"><td class="label">Total Amount:</td><td class="value">' + escapeHtml(total) + '</td></tr>' +
					'</table>' +
					'</div>' +
					'</div>' +
					(company ? '<div class="section"><div class="section-title">Booking Information</div><div class="section-content"><div class="detail-row"><div class="detail-label">Booked By:</div><div class="detail-value">' + escapeHtml(company) + '</div></div></div></div>' : '') +
					'<div class="footer">' +
					'<p>Thank you for choosing OnRoute Couriers</p>' +
					'<p>For any queries, please contact us at: support@onroutecouriers.com | Tel: 0845 838 8000</p>' +
					'<p style="margin-top: 10px; color: #ccc;">Booking Confirmation Generated: ' + new Date().toLocaleString() + '</p>' +
					'</div>' +
					'</div>' +
					'</body></html>';
				
				printWindow.document.write(printContent);
				printWindow.document.close();
				printWindow.focus();
				setTimeout(function() {
					printWindow.print();
					printWindow.close();
				}, 500);
				
				function escapeHtml(text) {
					if (!text) return '';
					var div = document.createElement('div');
					div.textContent = text;
					return div.innerHTML;
				}
				
				function formatDate(dateStr) {
					if (!dateStr) return '';
					var date = new Date(dateStr);
					return date.toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' });
				}
				
				function formatTime(timeStr) {
					if (!timeStr) return '';
					return timeStr;
				}
			});
		});
		</script>
		<?php
	}

	/**
	 * Render Pending Application View
	 */
	private function render_pending_view( $account ) {
		$user = wp_get_current_user();
		?>
		<div class="ocb-pending-container">
			<div class="ocb-pending-card">
				<div class="pending-icon"><i class="fas fa-clock"></i></div>
				<h2><?php _e( 'Application Under Review', 'onroute-courier-booking' ); ?></h2>
				<p class="pending-message"><?php _e( 'Thanks for applying for a Business Credit Account. Your application is currently being reviewed by our accounts team.', 'onroute-courier-booking' ); ?></p>
				
				<div class="pending-details">
					<div class="detail-row">
						<span><?php _e( 'Company Name:', 'onroute-courier-booking' ); ?></span>
						<strong><?php echo esc_html( $account->company_name ); ?></strong>
					</div>
					<div class="detail-row">
						<span><?php _e( 'Status:', 'onroute-courier-booking' ); ?></span>
						<span class="status-badge status-pending"><?php _e( 'Pending Approval', 'onroute-courier-booking' ); ?></span>
					</div>
					<div class="detail-row">
						<span><?php _e( 'Estimated Time:', 'onroute-courier-booking' ); ?></span>
						<strong><?php _e( '24 - 48 Hours', 'onroute-courier-booking' ); ?></strong>
					</div>
				</div>

				<div class="pending-note">
					<p><?php _e( 'We will notify you via email at', 'onroute-courier-booking' ); ?> <strong><?php echo esc_html( $user->user_email ); ?></strong> <?php _e( 'once your account has been approved.', 'onroute-courier-booking' ); ?></p>
				</div>

				<a href="<?php echo wp_logout_url( get_permalink() ); ?>" class="ocb-logout-btn"><?php _e( 'Logout', 'onroute-courier-booking' ); ?></a>
			</div>
		</div>

		<style>
			.ocb-pending-container { max-width: 600px; margin: 60px auto; padding: 0 20px; font-family: 'Inter', sans-serif; text-align: center; }
			.ocb-pending-card { background: #fff; border-radius: 20px; padding: 50px 40px; box-shadow: 0 20px 60px rgba(0,0,0,0.08); border: 1px solid #eee; }
			.pending-icon { width: 80px; height: 80px; background: #fff8e1; color: #f59e0b; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 32px; margin: 0 auto 25px; }
			.ocb-pending-card h2 { font-size: 28px; font-weight: 800; color: #111; margin: 0 0 15px; }
			.pending-message { color: #666; font-size: 16px; line-height: 1.6; margin-bottom: 30px; }
			
			.pending-details { background: #fafafa; border-radius: 12px; padding: 20px; margin-bottom: 30px; text-align: left; }
			.detail-row { display: flex; justify-content: space-between; padding: 10px 0; border-bottom: 1px solid #eee; font-size: 14px; }
			.detail-row:last-child { border-bottom: none; }
			.detail-row span { color: #777; font-weight: 500; }
			.detail-row strong { color: #111; font-weight: 600; }
			
			.status-badge.status-pending { background: #fff8e1; color: #b45309; padding: 4px 10px; border-radius: 6px; font-size: 12px; font-weight: 700; text-transform: uppercase; }

			.pending-note { font-size: 14px; color: #555; background: #e318370d; padding: 15px; border-radius: 8px; margin-bottom: 30px; }
			.pending-note strong { color: #e31837; }
			
			.ocb-logout-btn { color: #999; text-decoration: none; font-size: 14px; font-weight: 600; transition: 0.3s; }
			.ocb-logout-btn:hover { color: #e31837; }
		</style>
		<?php
	}

	/**
	 * AJAX: Register business user
	 */
	public function ajax_register_user() {
		check_ajax_referer( 'ocb_auth_nonce', 'nonce' );

		$full_name = sanitize_text_field( $_POST['full_name'] );
		$email = sanitize_email( $_POST['user_email'] );
		$password = $_POST['password'];
		$company = sanitize_text_field( $_POST['company_name'] );

		if ( email_exists( $email ) ) {
			wp_send_json_error( array( 'message' => 'Email already registered.' ) );
		}

		$username = strstr( $email, '@', true ) . rand( 100, 999 );
		$user_id = wp_create_user( $username, $password, $email );

		if ( is_wp_error( $user_id ) ) {
			wp_send_json_error( array( 'message' => $user_id->get_error_message() ) );
		}

		// Update user name
		wp_update_user( array( 'ID' => $user_id, 'display_name' => $full_name ) );

		// Ensure tables exist before saving (fail-safe)
		if ( class_exists( 'OnRoute_Courier_Booking_Database' ) ) {
			OnRoute_Courier_Booking_Database::create_tables();
		}

		// Create pending business account
		$saved = OnRoute_Business_Credit::save_account( array(
			'user_id' => $user_id,
			'company_name' => $company,
			'account_status' => 'pending'
		) );

		if ( false === $saved ) {
			global $wpdb;
			wp_send_json_error( array( 'message' => 'Database error: ' . $wpdb->last_error ) );
		}

		// Send Notification to Admin
		if ( class_exists( 'OnRoute_Courier_Booking_Emails' ) ) {
			OnRoute_Courier_Booking_Emails::send_new_account_notification( $user_id, $company );
		}

		// Auto login
		$creds = array(
			'user_login'    => $email,
			'user_password' => $password,
			'remember'      => true
		);
		wp_signon( $creds, false );

		wp_send_json_success( array( 'message' => 'Account created! Redirecting...' ) );
	}

	/**
	 * AJAX: Login business user
	 */
	public function ajax_login_user() {
		check_ajax_referer( 'ocb_auth_nonce', 'nonce' );

		$username = sanitize_user( $_POST['username'] );
		$password = $_POST['password'];

		$creds = array(
			'user_login'    => $username,
			'user_password' => $password,
			'remember'      => true
		);

		$user = wp_signon( $creds, false );

		if ( is_wp_error( $user ) ) {
			wp_send_json_error( array( 'message' => 'Invalid email or password.' ) );
		}

		// Calculate redirect URL: 
		// If a page with 'dashboard' in slug exists, use it, otherwise use current
		$redirect_url = home_url( '/dashboard/' ); 
		
		wp_send_json_success( array( 
			'message' => 'Login successful! Redirecting...',
			'redirect' => $redirect_url
		) );
	}

	/**
	 * AJAX: Apply for credit
	 */
	public function ajax_apply_credit() {
		check_ajax_referer( 'ocb_auth_nonce', 'nonce' );

		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => 'Please log in first.' ) );
		}

		$user_id = get_current_user_id();
		$company = sanitize_text_field( $_POST['company_name'] );
		$phone   = isset( $_POST['company_phone'] ) ? sanitize_text_field( $_POST['company_phone'] ) : '';
		$address = isset( $_POST['company_address'] ) ? sanitize_textarea_field( $_POST['company_address'] ) : '';

		// Update user meta with contact details
		if ( ! empty( $phone ) ) {
			update_user_meta( $user_id, 'billing_phone', $phone );
		}
		if ( ! empty( $address ) ) {
			update_user_meta( $user_id, 'billing_address_1', $address );
		}

		// Check for existing account to avoid duplicates or overwrite
		$existing_account = OnRoute_Business_Credit::get_account_by_user( $user_id );
		
		$account_data = array(
			'user_id' => $user_id,
			'company_name' => $company,
			'account_status' => 'pending'
		);

		if ( $existing_account ) {
			$account_data['id'] = $existing_account->id;
		}

		// Ensure tables exist before saving (fail-safe)
		if ( class_exists( 'OnRoute_Courier_Booking_Database' ) ) {
			OnRoute_Courier_Booking_Database::create_tables();
		}

		$saved = OnRoute_Business_Credit::save_account( $account_data );

		if ( false === $saved ) {
			global $wpdb;
			wp_send_json_error( array( 'message' => 'Application failed. DB Error: ' . $wpdb->last_error ) );
		}

		// Send Notification to Admin
		if ( class_exists( 'OnRoute_Courier_Booking_Emails' ) ) {
			OnRoute_Courier_Booking_Emails::send_credit_application_notification( $user_id, $company, $phone, $address );
		}

		wp_send_json_success( array( 'message' => 'Application submitted! We will review it shortly.' ) );
	}

	/**
	 * Render Stats Shortcode: [onroute_business_stats]
	 * Useful for placing on Home Page
	 */
	public function render_stats_shortcode() {
		if ( ! is_user_logged_in() ) return '';

		$user_id = get_current_user_id();
		$account = OnRoute_Business_Credit::get_account_by_user( $user_id );
		if ( ! $account ) return '';

		// If balance reset recently, force a check or output diagnostic
		if ( isset( $_GET['debug_balance'] ) && current_user_can( 'manage_options' ) ) {
			echo "<!-- DEBUG: Account ID " . $account->id . " Balance " . $account->current_balance . " -->";
		}

		ob_start();
		?>
		<div class="ocb-home-stats">
			<div class="stats-grid">
				<div class="stat-card">
					<span class="label"><?php _e( 'Credit Limit', 'onroute-courier-booking' ); ?></span>
					<span class="value"><?php echo $this->format_price( $account->credit_limit ); ?></span>
				</div>
				<div class="stat-card">
					<span class="label"><?php _e( 'Spent Credit', 'onroute-courier-booking' ); ?></span>
					<span class="value"><?php echo $this->format_price( $account->current_balance ); ?></span>
				</div>
				<div class="stat-card highlight">
					<span class="label"><?php _e( 'Available Credit', 'onroute-courier-booking' ); ?></span>
					<span class="value"><?php echo $this->format_price( max( 0, $account->credit_limit - $account->current_balance ) ); ?></span>
				</div>
			</div>
		</div>
		<style>
			.ocb-home-stats .stats-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; margin: 20px 0; font-family: 'Inter', sans-serif; }
			.ocb-home-stats .stat-card { background: #fff; padding: 25px; border-radius: 16px; border: 1px solid #eee; box-shadow: 0 5px 15px rgba(0,0,0,0.04); }
			.ocb-home-stats .stat-card.highlight { background: #e31837; border-color: #e31837; }
			.ocb-home-stats .stat-card.highlight .label { color: rgba(255,255,255,0.7); }
			.ocb-home-stats .stat-card.highlight .value { color: #fff; }
			.ocb-home-stats .stat-card .label { display: block; font-size: 11px; font-weight: 700; text-transform: uppercase; color: #777; letter-spacing: 1px; margin-bottom: 10px; }
			.ocb-home-stats .stat-card .value { font-size: 24px; font-weight: 800; color: #111; }
			@media (max-width: 768px) {
				.ocb-home-stats .stats-grid { grid-template-columns: repeat(3, 1fr); gap: 10px; margin: 10px 0; }
				.ocb-home-stats .stat-card { padding: 12px 8px; border-radius: 10px; text-align: center; }
				.ocb-home-stats .stat-card .label { font-size: 8px; margin-bottom: 5px; letter-spacing: 0.5px; }
				.ocb-home-stats .stat-card .value { font-size: 14px; }
			}
			@media (max-width: 480px) {
				.ocb-home-stats .stats-grid { gap: 6px; }
				.ocb-home-stats .stat-card { padding: 10px 5px; }
				.ocb-home-stats .stat-card .value { font-size: 12px; }
			}
		</style>
		<?php
		return ob_get_clean();
	}

	/**
	 * Handle Menu Visibility:
	 * 1. Hides "Dashboard" if not logged in.
	 * 2. Hides "Sign In" if already logged in.
	 */
	public function handle_menu_visibility( $items, $args ) {
		if ( is_admin() ) return $items;

		$is_logged_in = is_user_logged_in();
		
		if ( $is_logged_in ) {
			// লগইন থাকলে 'Sign in' এবং 'Login' মেনু থেকে ডিলিট করো
			$items = preg_replace('/<li[^>]*>.*?Sign in.*?<\/li>/i', '', $items);
			$items = preg_replace('/<li[^>]*>.*?Login.*?<\/li>/i', '', $items);
		} else {
			// লগআউট থাকলে 'Dashboard' মেনু থেকে ডিলিট করো
			$items = preg_replace('/<li[^>]*>.*?Dashboard.*?<\/li>/i', '', $items);
		}

		return $items;
	}

	/**
	 * Render Auth Button: [onroute_auth_button]
	 */
	public function render_auth_button() {
		if ( is_user_logged_in() ) {
			return '<a href="' . home_url('/dashboard/') . '" class="ocb-auth-btn dashboard-btn">' . __( 'My Dashboard', 'onroute-courier-booking' ) . '</a>';
		} else {
			return '<a href="' . home_url('/sign-in/') . '" class="ocb-auth-btn signin-btn">' . __( 'Sign In', 'onroute-courier-booking' ) . '</a>';
		}
	}

	/**
	 * Render Business Booking Form
	 */
	private function render_business_booking_form( $account ) {
		$available_credit = max( 0, $account->credit_limit - $account->current_balance );
		$pricing = new OnRoute_Courier_Booking_Pricing();
		$vehicles = $pricing->get_vehicles();
		$services = $pricing->get_services();
		$user = wp_get_current_user();
		?>
		<h2><?php _e( 'New Booking', 'onroute-courier-booking' ); ?></h2>
		
		<!-- Credit Balance Display -->
		<div class="credit-balance-banner">
			<div class="balance-item">
				<span class="balance-label"><?php _e( 'Available Credit', 'onroute-courier-booking' ); ?></span>
				<span class="balance-value" id="available-credit-display"><?php echo $this->format_price( $available_credit ); ?></span>
			</div>
			<div class="balance-item">
				<span class="balance-label"><?php _e( 'Credit Limit', 'onroute-courier-booking' ); ?></span>
				<span class="balance-value"><?php echo $this->format_price( $account->credit_limit ); ?></span>
			</div>
			<div class="balance-item">
				<span class="balance-label"><?php _e( 'Used', 'onroute-courier-booking' ); ?></span>
				<span class="balance-value"><?php echo $this->format_price( $account->current_balance ); ?></span>
			</div>
		</div>

		<div class="business-booking-wizard">
			<!-- Step 1: Route Selection -->
			<div class="booking-step step-1 active">
				<h3><?php _e( 'Step 1: Route Information', 'onroute-courier-booking' ); ?></h3>
				<div class="business-form-grid">
					<div class="form-group">
						<label><?php _e( 'Pickup Postcode', 'onroute-courier-booking' ); ?> *</label>
						<input type="text" id="biz-pickup-postcode" placeholder="e.g., SW1A 1AA" required />
					</div>
					<div class="form-group">
						<label><?php _e( 'Delivery Postcode', 'onroute-courier-booking' ); ?> *</label>
						<input type="text" id="biz-delivery-postcode" placeholder="e.g., M1 1AE" required />
					</div>
				</div>
				<button class="business-btn business-btn-primary" id="biz-get-quote"><?php _e( 'Get Quote', 'onroute-courier-booking' ); ?></button>
				<div id="biz-quote-message"></div>
			</div>

			<!-- Step 2: Service & Vehicle Selection -->
			<div class="booking-step step-2">
				<h3><?php _e( 'Step 2: Select Service & Vehicle', 'onroute-courier-booking' ); ?></h3>
				
				<div class="route-summary" id="biz-route-summary"></div>

				<div class="service-selection">
					<h4><?php _e( 'Service Type', 'onroute-courier-booking' ); ?></h4>
					<div class="service-options" id="biz-service-options">
						<?php foreach ( $services as $service ) : if ( ! $service['active'] ) continue; ?>
							<label class="service-radio-card">
								<input type="radio" name="biz-service" value="<?php echo esc_attr( $service['id'] ); ?>" />
								<div class="service-card-content">
									<h5><?php echo esc_html( ucfirst( str_replace( '_', ' ', $service['id'] ) ) ); ?></h5>
									<p><?php echo esc_html( $service['name'] ?? '' ); ?></p>
								</div>
							</label>
						<?php endforeach; ?>
					</div>
				</div>

				<div class="vehicle-selection">
					<h4><?php _e( 'Vehicle Type', 'onroute-courier-booking' ); ?></h4>
					<div class="vehicle-options" id="biz-vehicle-options">
						<?php foreach ( $vehicles as $vehicle ) : if ( ! $vehicle['active'] ) continue; ?>
							<label class="vehicle-radio-card">
								<input type="radio" name="biz-vehicle" value="<?php echo esc_attr( $vehicle['id'] ); ?>" 
									data-base-rate="<?php echo esc_attr( $vehicle['rate_per_mile'] ); ?>" 
									data-admin-fee="0" 
									data-min-charge="<?php echo esc_attr( $vehicle['min_charge'] ); ?>" />
								<div class="vehicle-card-content">
									<h5><?php echo esc_html( $vehicle['name'] ); ?></h5>
									<p><?php echo esc_html( $vehicle['description'] ); ?></p>
									<small><?php echo esc_html( $vehicle['dimensions'] ); ?></small>
								</div>
							</label>
						<?php endforeach; ?>
					</div>
				</div>

				<div class="price-preview" id="biz-price-preview">
					<div class="price-row">
						<span><?php _e( 'Distance Cost', 'onroute-courier-booking' ); ?>:</span>
						<strong id="biz-distance-cost">£0.00</strong>
					</div>
					<div class="price-row" id="biz-night-row" style="display:none;">
						<span><?php _e( 'Night Surcharge', 'onroute-courier-booking' ); ?>:</span>
						<strong id="biz-night-surcharge">+£0.00</strong>
					</div>
					<div class="price-row">
						<span><?php _e( 'Service Multiplier', 'onroute-courier-booking' ); ?>:</span>
						<strong id="biz-service-mult">1.0x</strong>
					</div>
					<div class="price-row highlight">
						<span><?php _e( 'Total Price', 'onroute-courier-booking' ); ?>:</span>
						<strong id="biz-total-price">£0.00</strong>
					</div>
					<div class="discount-note">
						<i class="fas fa-info-circle"></i> <?php _e( 'Business Credit accounts: No admin fees!', 'onroute-courier-booking' ); ?>
					</div>
				</div>

				<div class="button-group">
					<button class="business-btn business-btn-secondary" id="biz-back-step1"><?php _e( 'Back', 'onroute-courier-booking' ); ?></button>
					<button class="business-btn business-btn-primary" id="biz-continue-step3" disabled><?php _e( 'Continue', 'onroute-courier-booking' ); ?></button>
				</div>
			</div>

			<!-- Step 3: Booking Details -->
			<div class="booking-step step-3">
				<h3><?php _e( 'Step 3: Booking Details', 'onroute-courier-booking' ); ?></h3>
				
				<!-- Collection Information -->
				<h4 style="margin-top: 0;"><i class="fas fa-box-open"></i> <?php _e( 'Collection Information', 'onroute-courier-booking' ); ?></h4>
				<div class="business-form-grid">
					<div class="form-group">
						<label><?php _e( 'Collection Address', 'onroute-courier-booking' ); ?> *</label>
						<div class="address-lookup-wrapper">
							<button type="button" class="address-lookup-btn" data-target="biz-pickup-address" data-postcode="biz-pickup-postcode">
								<i class="fas fa-search"></i> <?php _e( 'Find Address', 'onroute-courier-booking' ); ?>
							</button>
							<div class="address-dropdown" id="biz-pickup-address-dropdown"></div>
						</div>
						<textarea id="biz-pickup-address" placeholder="Full street address" required></textarea>
					</div>
					<div class="form-group">
						<label><?php _e( 'Collection Contact Name', 'onroute-courier-booking' ); ?> *</label>
						<input type="text" id="biz-collection-name" placeholder="e.g., John Smith" required />
					</div>
				</div>

				<div class="business-form-grid">
					<div class="form-group">
						<label><?php _e( 'Collection Contact Phone', 'onroute-courier-booking' ); ?> *</label>
						<input type="tel" id="biz-collection-phone" placeholder="e.g., 07123456789" required />
					</div>
					<div class="form-group">
						<label><?php _e( 'Collection Contact Email', 'onroute-courier-booking' ); ?></label>
						<input type="email" id="biz-collection-email" placeholder="e.g., sender@company.com" />
					</div>
				</div>

				<div class="business-form-grid">
					<div class="form-group">
						<label><?php _e( 'Collection Date', 'onroute-courier-booking' ); ?> *</label>
						<input type="date" id="biz-collection-date" required />
					</div>
					<div class="form-group">
						<label><?php _e( 'Collection Time', 'onroute-courier-booking' ); ?> *</label>
						<input type="time" id="biz-collection-time" required />
					</div>
				</div>

				<!-- Delivery Information -->
				<h4><i class="fas fa-shipping-fast"></i> <?php _e( 'Delivery Information', 'onroute-courier-booking' ); ?></h4>
				<div class="business-form-grid">
					<div class="form-group">
						<label><?php _e( 'Delivery Address', 'onroute-courier-booking' ); ?> *</label>
						<div class="address-lookup-wrapper">
							<button type="button" class="address-lookup-btn" data-target="biz-delivery-address" data-postcode="biz-delivery-postcode">
								<i class="fas fa-search"></i> <?php _e( 'Find Address', 'onroute-courier-booking' ); ?>
							</button>
							<div class="address-dropdown" id="biz-delivery-address-dropdown"></div>
						</div>
						<textarea id="biz-delivery-address" placeholder="Full street address" required></textarea>
					</div>
					<div class="form-group">
						<label><?php _e( 'Delivery Contact Name', 'onroute-courier-booking' ); ?> *</label>
						<input type="text" id="biz-delivery-name" placeholder="e.g., Jane Doe" required />
					</div>
				</div>

				<div class="business-form-grid">
					<div class="form-group">
						<label><?php _e( 'Delivery Contact Phone', 'onroute-courier-booking' ); ?> *</label>
						<input type="tel" id="biz-delivery-phone" placeholder="e.g., 07987654321" required />
					</div>
					<div class="form-group">
						<label><?php _e( 'Delivery Contact Email', 'onroute-courier-booking' ); ?></label>
						<input type="email" id="biz-delivery-email" placeholder="e.g., receiver@company.com" />
					</div>
				</div>

				<div class="business-form-grid">
					<div class="form-group">
						<label><?php _e( 'Delivery Date', 'onroute-courier-booking' ); ?> <span id="biz-delivery-date-required">*</span></label>
						<input type="date" id="biz-delivery-date" />
						<small id="biz-delivery-date-hint" style="color: #999; font-size: 12px;"></small>
					</div>
					<div class="form-group">
						<label><?php _e( 'Delivery Time', 'onroute-courier-booking' ); ?> <span id="biz-delivery-time-label"><?php _e( '(Optional)', 'onroute-courier-booking' ); ?></span></label>
						<input type="time" id="biz-delivery-time" />
					</div>
				</div>

				<!-- Delivery Window Display (for Same Day / Direct) -->
				<div id="biz-delivery-window-display" style="display:none; padding: 15px; background: #f8fafc; border-left: 4px solid #3182ce; border-radius: 8px; margin-bottom: 15px;">
					<div style="font-weight: 700; color: #2d3748; margin-bottom: 5px; display: flex; align-items: center;">
						<i class="fas fa-clock" style="margin-right: 10px; color: #3182ce;"></i> 
						<?php _e( 'Estimated Delivery Window', 'onroute-courier-booking' ); ?>:
					</div>
					<div id="biz-delivery-window-text" style="font-size: 1.1em; color: #2c5282; font-weight: 600;"></div>
				</div>

				<div class="button-group">
					<button class="business-btn business-btn-secondary" id="biz-back-step2"><?php _e( 'Back', 'onroute-courier-booking' ); ?></button>
					<button class="business-btn business-btn-primary" id="biz-continue-step4"><?php _e( 'Review Booking', 'onroute-courier-booking' ); ?></button>
				</div>
			</div>

			<!-- Step 4: Review & Confirm -->
			<div class="booking-step step-4">
				<h3><?php _e( 'Step 4: Review & Confirm', 'onroute-courier-booking' ); ?></h3>
				
				<div class="booking-review" id="biz-booking-review"></div>

				<div class="payment-section">
					<h4><?php _e( 'Payment Method', 'onroute-courier-booking' ); ?></h4>
					<div class="payment-option selected">
						<i class="fas fa-credit-card"></i>
						<div>
							<strong><?php _e( 'Pay with Business Credit', 'onroute-courier-booking' ); ?></strong>
							<p><?php _e( 'Amount will be deducted from your available credit balance', 'onroute-courier-booking' ); ?></p>
						</div>
					</div>
					<div class="credit-warning" id="biz-credit-warning" style="display:none;">
						<i class="fas fa-exclamation-triangle"></i>
						<?php _e( 'Insufficient credit balance. Please contact support to increase your credit limit.', 'onroute-courier-booking' ); ?>
					</div>
				</div>

				<div class="button-group">
					<button class="business-btn business-btn-secondary" id="biz-back-step3"><?php _e( 'Back', 'onroute-courier-booking' ); ?></button>
					<button class="business-btn business-btn-success" id="biz-confirm-booking"><?php _e( 'Confirm Booking', 'onroute-courier-booking' ); ?></button>
				</div>
				<div id="biz-booking-message"></div>
			</div>
		</div>

		<style>
			.credit-balance-banner { display: grid; grid-template-columns: repeat(3, 1fr); gap: 15px; margin-bottom: 30px; padding: 20px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border-radius: 12px; color: #fff; }
			.balance-item { text-align: center; }
			.balance-label { display: block; font-size: 11px; text-transform: uppercase; opacity: 0.8; margin-bottom: 8px; letter-spacing: 0.5px; }
			.balance-value { display: block; font-size: 24px; font-weight: 800; }
			
			.business-booking-wizard { background: #fafafa; padding: 30px; border-radius: 12px; }
			.booking-step { display: none; }
			.booking-step.active { display: block; animation: fadeIn 0.3s ease; }
			.booking-step h3 { font-size: 20px; margin-bottom: 20px; color: #111; font-weight: 700; }
			.booking-step h4 { font-size: 16px; margin: 20px 0 15px; color: #333; font-weight: 600; }
			
			.business-form-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 20px; margin-bottom: 20px; }
			.form-group label { display: block; font-size: 13px; font-weight: 600; color: #333; margin-bottom: 8px; }
			.form-group input, .form-group textarea { width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 8px; font-size: 14px; transition: 0.3s; box-sizing: border-box; }
			.form-group input:focus, .form-group textarea:focus { border-color: #e31837; outline: none; box-shadow: 0 0 0 3px rgba(227, 24, 55, 0.1); }
			.form-group textarea { min-height: 80px; resize: vertical; }
			
			.address-lookup-wrapper { position: relative; margin-bottom: 10px; }
			.address-lookup-btn { padding: 10px 16px; background: #6c757d; color: #fff; border: none; border-radius: 6px; font-size: 13px; font-weight: 600; cursor: pointer; transition: 0.3s; display: inline-flex; align-items: center; gap: 8px; }
			.address-lookup-btn:hover { background: #5a6268; transform: translateY(-1px); }
			.address-lookup-btn i { font-size: 12px; }
			.address-dropdown { position: absolute; top: 45px; left: 0; right: 0; background: #fff; border: 1px solid #ddd; border-radius: 8px; max-height: 400px; overflow-y: auto; z-index: 1000; display: none; box-shadow: 0 8px 24px rgba(0,0,0,0.15); }
			.address-dropdown.active { display: block; }
			.address-count-info { padding: 8px 16px; background: #f0f7ff; border-bottom: 1px solid #ddd; font-size: 12px; color: #0066cc; font-weight: 600; }
			.address-item { padding: 12px 16px; cursor: pointer; border-bottom: 1px solid #f0f0f0; transition: 0.2s; font-size: 14px; }
			.address-item:last-child { border-bottom: none; }
			.address-item:hover { background: #f8f9fa; color: #e31837; }
			.address-loading { padding: 12px 16px; text-align: center; color: #666; font-size: 13px; }
			.address-error { padding: 12px 16px; color: #721c24; background: #f8d7da; border-radius: 6px; margin: 8px; font-size: 13px; }
			
			.business-btn { padding: 12px 24px; border: none; border-radius: 8px; font-size: 14px; font-weight: 700; cursor: pointer; transition: 0.3s; }
			.business-btn-primary { background: #e31837; color: #fff; }
			.business-btn-primary:hover:not(:disabled) { background: #c1122d; transform: translateY(-2px); box-shadow: 0 4px 12px rgba(227, 24, 55, 0.3); }
			.business-btn-primary:disabled { background: #ccc; cursor: not-allowed; }
			.business-btn-secondary { background: #6c757d; color: #fff; }
			.business-btn-secondary:hover { background: #5a6268; }
			.business-btn-success { background: #28a745; color: #fff; }
			.business-btn-success:hover { background: #218838; }
			.button-group { display: flex; gap: 10px; margin-top: 20px; }
			
			.route-summary { background: #fff; padding: 15px; border-radius: 8px; margin-bottom: 20px; border-left: 4px solid #e31837; }
			.service-options, .vehicle-options { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; }
			.service-radio-card, .vehicle-radio-card { position: relative; cursor: pointer; }
			.service-radio-card input, .vehicle-radio-card input { position: absolute; opacity: 0; }
			.service-card-content, .vehicle-card-content { background: #fff; padding: 15px; border-radius: 8px; border: 2px solid #ddd; transition: 0.3s; }
			.service-radio-card input:checked + .service-card-content,
			.vehicle-radio-card input:checked + .vehicle-card-content { border-color: #e31837; background: #fff5f5; }
			.service-card-content h5, .vehicle-card-content h5 { margin: 0 0 5px; font-size: 15px; color: #333; }
			.service-card-content p, .vehicle-card-content p { margin: 0; font-size: 12px; color: #666; }
			.vehicle-card-content small { display: block; margin-top: 5px; color: #999; font-size: 11px; }
			
			.price-preview { background: #fff; padding: 20px; border-radius: 8px; margin-top: 20px; }
			.price-row { display: flex; justify-content: space-between; padding: 10px 0; border-bottom: 1px solid #eee; }
			.price-row.highlight { border-bottom: none; font-size: 18px; color: #e31837; margin-top: 10px; padding-top: 15px; border-top: 2px solid #e31837; }
			.discount-note { margin-top: 15px; padding: 10px; background: #e7f4e4; color: #1e4620; border-radius: 6px; font-size: 13px; }
			.discount-note i { margin-right: 5px; }
			
			.booking-review { background: #fff; padding: 20px; border-radius: 8px; margin-bottom: 20px; }
			.review-section { margin-bottom: 15px; padding-bottom: 15px; border-bottom: 1px solid #eee; }
			.review-section:last-child { border-bottom: none; }
			.review-section h5 { margin: 0 0 10px; font-size: 14px; color: #666; text-transform: uppercase; letter-spacing: 0.5px; }
			.review-section p { margin: 5px 0; color: #333; }
			
			.payment-section { background: #fff; padding: 20px; border-radius: 8px; margin-bottom: 20px; }
			.payment-option { display: flex; align-items: center; gap: 15px; padding: 15px; border: 2px solid #ddd; border-radius: 8px; margin-top: 10px; }
			.payment-option.selected { border-color: #28a745; background: #e7f4e4; }
			.payment-option i { font-size: 24px; color: #28a745; }
			.credit-warning { margin-top: 15px; padding: 12px; background: #f8d7da; color: #721c24; border-radius: 6px; font-size: 13px; }
			.credit-warning i { margin-right: 8px; }
			
			#biz-quote-message, #biz-booking-message { margin-top: 15px; padding: 12px; border-radius: 6px; font-size: 14px; }
			.success-message { background: #e7f4e4; color: #1e4620; }
			.error-message { background: #f8d7da; color: #721c24; }
			
			@media (max-width: 768px) {
				.credit-balance-banner { grid-template-columns: 1fr; gap: 10px; padding: 15px; }
				.balance-value { font-size: 20px; }
				.business-form-grid { grid-template-columns: 1fr; gap: 15px; }
				.service-options, .vehicle-options { grid-template-columns: 1fr; }
				.button-group { flex-direction: column; }
				.address-dropdown { max-height: 250px; }
				.address-lookup-btn { width: 100%; justify-content: center; }
				.step-indicator { gap: 10px; }
				.step { font-size: 11px; padding: 8px 12px; }
				.step-number { width: 22px; height: 22px; font-size: 11px; }
			}
			
			@media (max-width: 600px) {
				.biz-form-wrapper { padding: 20px 15px; }
				.biz-form-wrapper h2 { font-size: 20px; }
				.credit-balance-banner .balance-title { font-size: 10px; }
				.balance-value { font-size: 18px; }
				.form-row label { font-size: 13px; }
				.form-row input, .form-row select { font-size: 13px; padding: 10px; }
				.combined-btn, .red-btn { font-size: 13px; padding: 10px 18px; }
				.radio-option { padding: 12px; }
				.radio-option-label { font-size: 13px; }
				.price-badge { font-size: 12px; padding: 4px 8px; }
				.step-indicator { flex-wrap: nowrap; overflow-x: auto; -webkit-overflow-scrolling: touch; padding-bottom: 5px; }
				.step { flex-shrink: 0; min-width: max-content; padding: 6px 10px; }
				.step span { display: none; }
				.step-number { margin-right: 0; }
			}
			
			@media (max-width: 480px) {
				.biz-form-wrapper { padding: 15px 10px; border-radius: 12px; }
				.credit-balance-banner { padding: 12px; gap: 8px; border-radius: 8px; }
				.balance-value { font-size: 16px; }
				.business-form-grid { gap: 12px; }
				.form-row label { font-size: 12px; }
				.combined-btn, .red-btn { font-size: 12px; padding: 8px 15px; }
				.address-lookup-btn { font-size: 12px; padding: 8px; }
				.address-lookup-btn i { font-size: 12px; }
				.radio-option { padding: 10px; }
				.radio-option-label { font-size: 12px; }
				.summary-row { font-size: 13px; }
				.summary-row.total { font-size: 16px; }
			}
		</style>

		<script>
		jQuery(document).ready(function($) {
			var bookingData = {
				distance: 0,
				pickupPostcode: '',
				deliveryPostcode: '',
				serviceId: '',
				vehicleId: '',
				basePrice: 0
			};
			var availableCredit = <?php echo $available_credit; ?>;

			// Address lookup functionality
			$('.address-lookup-btn').on('click', function() {
				var $btn = $(this);
				var targetId = $btn.data('target');
				var postcodeId = $btn.data('postcode');
				var postcode = $('#' + postcodeId).val().trim();
				var dropdownId = targetId + '-dropdown';
				
				if (!postcode) {
					alert('<?php _e( 'Please enter a postcode first', 'onroute-courier-booking' ); ?>');
					$('#' + postcodeId).focus();
					return;
				}

				var $dropdown = $('#' + dropdownId);
				$dropdown.html('<div class="address-loading"><i class="fas fa-spinner fa-spin"></i> <?php _e( 'Loading addresses...', 'onroute-courier-booking' ); ?></div>').addClass('active');
				
				$.post('<?php echo admin_url("admin-ajax.php"); ?>', {
					action: 'ocb_business_lookup_address',
					nonce: '<?php echo wp_create_nonce("ocb_business_booking"); ?>',
					postcode: postcode
				}, function(res) {
					if (res.success && res.data.addresses && res.data.addresses.length > 0) {
						var html = '<div class="address-count-info"><i class="fas fa-check-circle"></i> ' + res.data.addresses.length + ' <?php _e( 'addresses found', 'onroute-courier-booking' ); ?></div>';
						res.data.addresses.forEach(function(addr) {
							html += '<div class="address-item" data-address="' + addr.formatted.replace(/"/g, '&quot;') + '">' + addr.formatted + '</div>';
						});
						$dropdown.html(html);
						
						// Handle address selection
						$dropdown.find('.address-item').on('click', function() {
							var selectedAddress = $(this).data('address');
							$('#' + targetId).val(selectedAddress);
							$dropdown.removeClass('active').html('');
						});
					} else {
						$dropdown.html('<div class="address-error">' + (res.data && res.data.message || '<?php _e( 'No addresses found for this postcode', 'onroute-courier-booking' ); ?>') + '</div>');
						setTimeout(function() {
							$dropdown.removeClass('active').html('');
						}, 3000);
					}
				}).fail(function() {
					$dropdown.html('<div class="address-error"><?php _e( 'Failed to load addresses. Please try again.', 'onroute-courier-booking' ); ?></div>');
					setTimeout(function() {
						$dropdown.removeClass('active').html('');
					}, 3000);
				});
			});

			// Close dropdown when clicking outside
			$(document).on('click', function(e) {
				if (!$(e.target).closest('.address-lookup-wrapper').length) {
					$('.address-dropdown').removeClass('active').html('');
				}
			});

			// Step navigation
			$('#biz-get-quote').on('click', function() {
				var pickup = $('#biz-pickup-postcode').val().trim();
				var delivery = $('#biz-delivery-postcode').val().trim();
				
				if (!pickup || !delivery) {
					$('#biz-quote-message').html('<div class="error-message">Please enter both postcodes</div>');
					return;
				}

				$(this).prop('disabled', true).text('Calculating...');
				
				$.post('<?php echo admin_url("admin-ajax.php"); ?>', {
					action: 'ocb_business_get_quote',
					nonce: '<?php echo wp_create_nonce("ocb_business_booking"); ?>',
					pickup_postcode: pickup,
					delivery_postcode: delivery
				}, function(res) {
					if (res.success) {
						bookingData.distance = res.data.distance;
						bookingData.pickupPostcode = pickup;
						bookingData.deliveryPostcode = delivery;
						
						$('#biz-route-summary').html(
							'<strong>Route:</strong> ' + pickup + ' → ' + delivery + '<br>' +
							'<strong>Distance:</strong> ' + res.data.distance.toFixed(2) + ' miles'
						);
						
						$('.step-1').removeClass('active');
						$('.step-2').addClass('active');
					} else {
						$('#biz-quote-message').html('<div class="error-message">' + res.data.message + '</div>');
					}
					$('#biz-get-quote').prop('disabled', false).text('Get Quote');
				});
			});

			// Service and vehicle selection
			$('input[name="biz-service"], input[name="biz-vehicle"]').on('change', function() {
				var serviceChecked = $('input[name="biz-service"]:checked').length > 0;
				var vehicleChecked = $('input[name="biz-vehicle"]:checked').length > 0;
				
				if (serviceChecked && vehicleChecked) {
					updatePrice();
					updateDeliveryWindow();
					$('#biz-continue-step3').prop('disabled', false);
				}
			});

			// Time change listeners for night surcharge
			$('#biz-collection-time, #biz-delivery-time').on('change', function() {
				updatePrice();
			});

			function isNightTime(timeString) {
				if (!timeString) return false;
				var hour = parseInt(timeString.split(':')[0]);
				var nightStart = 22; // 22:00
				var nightEnd = 6;    // 06:00
				return (hour >= nightStart || hour < nightEnd);
			}

			function updateDeliveryWindow() {
				var serviceId = $('input[name="biz-service"]:checked').val();
				
				if (serviceId === 'same_day' || serviceId === 'direct' || serviceId === 'dedicated') {
					var now = new Date();
					var startTime, endTime;
					
					if (serviceId === 'same_day') {
						// Auto-fill delivery date with today for same_day service
						var todayString = now.toISOString().split('T')[0];
						$('#biz-delivery-date').val(todayString);
						$('#biz-delivery-date').prop('required', false);
						$('#biz-delivery-date-required').text('');
						$('#biz-delivery-date-hint').text('Auto-set to today');
						
						var currentHour = now.getHours();
						var startMs = now.getTime() + (3 * 60 * 60 * 1000); // +3 hours
						var endMs;
						
						if (currentHour < 13) {
							endMs = new Date(now.toDateString() + ' 17:00:00').getTime();
						} else {
							endMs = new Date(now.toDateString() + ' 20:00:00').getTime();
						}
						
						if (startMs > endMs) startMs = endMs;
						
						startTime = new Date(startMs).toLocaleTimeString('en-GB', {hour: '2-digit', minute: '2-digit'});
						endTime = new Date(endMs).toLocaleTimeString('en-GB', {hour: '2-digit', minute: '2-digit'});
					} else {
						// Direct/Dedicated - allow user to select date
						$('#biz-delivery-date').prop('required', true);
						$('#biz-delivery-date-required').text('*');
						$('#biz-delivery-date-hint').text('');
						
						// Direct/Dedicated with 1.5-3 hour window
						var startMs = now.getTime() + (1.5 * 60 * 60 * 1000);
						var endMs = now.getTime() + (3 * 60 * 60 * 1000);
						
						startTime = new Date(startMs).toLocaleTimeString('en-GB', {hour: '2-digit', minute: '2-digit'});
						endTime = new Date(endMs).toLocaleTimeString('en-GB', {hour: '2-digit', minute: '2-digit'});
					}
					
					$('#biz-delivery-window-text').text(startTime + ' - ' + endTime);
					$('#biz-delivery-window-display').show();
					$('#biz-delivery-time').prop('disabled', true);
					$('#biz-delivery-time-label').text('(Auto-calculated)');
				} else {
					// Other services - require delivery date, optional delivery time
					$('#biz-delivery-date').prop('required', true);
					$('#biz-delivery-date-required').text('*');
					$('#biz-delivery-date-hint').text('');
					$('#biz-delivery-window-display').hide();
					$('#biz-delivery-time').prop('disabled', false);
					$('#biz-delivery-time-label').text('(Optional)');
				}
			}

			function updatePrice() {
				var serviceId = $('input[name="biz-service"]:checked').val();
				var vehicleInput = $('input[name="biz-vehicle"]:checked');
				var ratePerMile = parseFloat(vehicleInput.data('base-rate'));
				var minCharge = parseFloat(vehicleInput.data('min-charge'));
				
				var serviceMultipliers = { 'same_day': 1.0, 'timed': 1.5, 'dedicated': 2.0, 'direct': 2.0 };
				var serviceMult = serviceMultipliers[serviceId] || 1.0;
				
				// Check for night surcharge
				var collectionTime = $('#biz-collection-time').val();
				var deliveryTime = $('#biz-delivery-time').val();
				var nightApplied = false;
				var nightMultiplier = 2.0;
				
				if (isNightTime(collectionTime) || isNightTime(deliveryTime)) {
					nightApplied = true;
				}
				
				var effectiveRate = nightApplied ? (ratePerMile * nightMultiplier) : ratePerMile;
				var distanceCost = bookingData.distance * effectiveRate;
				var chargeableCost = Math.max(distanceCost, minCharge);
				var totalPrice = chargeableCost * serviceMult;
				
				bookingData.serviceId = serviceId;
				bookingData.vehicleId = vehicleInput.val();
				bookingData.basePrice = totalPrice;
				
				$('#biz-distance-cost').text('£' + chargeableCost.toFixed(2));
				$('#biz-service-mult').text(serviceMult.toFixed(1) + 'x');
				$('#biz-total-price').text('£' + totalPrice.toFixed(2));
				
				if (nightApplied) {
					var nightSurcharge = (chargeableCost - (bookingData.distance * ratePerMile));
					$('#biz-night-surcharge').text('+£' + nightSurcharge.toFixed(2));
					$('#biz-night-row').show();
				} else {
					$('#biz-night-row').hide();
				}
			}

			// Step navigation buttons
			$('#biz-back-step1').on('click', function() {
				$('.step-2').removeClass('active');
				$('.step-1').addClass('active');
			});

			$('#biz-continue-step3').on('click', function() {
				$('.step-2').removeClass('active');
				$('.step-3').addClass('active');
			});

			$('#biz-back-step2').on('click', function() {
				$('.step-3').removeClass('active');
				$('.step-2').addClass('active');
			});

			$('#biz-continue-step4').on('click', function() {
				// Validate delivery date if required
				var deliveryDateRequired = $('#biz-delivery-date').prop('required');
				var deliveryDate = $('#biz-delivery-date').val();
				
				if (deliveryDateRequired && !deliveryDate) {
					alert('Please select a delivery date');
					$('#biz-delivery-date').focus();
					return false;
				}
				
				// Build review
				var reviewHTML = '<div class="review-section">' +
					'<h5><i class="fas fa-box-open"></i> Collection</h5>' +
					'<p><strong>Address:</strong> ' + $('#biz-pickup-address').val() + '</p>' +
					'<p><strong>Postcode:</strong> ' + bookingData.pickupPostcode + '</p>' +
					'<p><strong>Contact:</strong> ' + $('#biz-collection-name').val() + '</p>' +
					'<p><strong>Phone:</strong> ' + $('#biz-collection-phone').val() + '</p>' +
					($('#biz-collection-email').val() ? '<p><strong>Email:</strong> ' + $('#biz-collection-email').val() + '</p>' : '') +
					'<p><strong>Date/Time:</strong> ' + $('#biz-collection-date').val() + ' at ' + $('#biz-collection-time').val() + '</p>' +
					'</div><div class="review-section">' +
					'<h5><i class="fas fa-shipping-fast"></i> Delivery</h5>' +
					'<p><strong>Address:</strong> ' + $('#biz-delivery-address').val() + '</p>' +
					'<p><strong>Postcode:</strong> ' + bookingData.deliveryPostcode + '</p>' +
					'<p><strong>Contact:</strong> ' + $('#biz-delivery-name').val() + '</p>' +
					'<p><strong>Phone:</strong> ' + $('#biz-delivery-phone').val() + '</p>' +
					($('#biz-delivery-email').val() ? '<p><strong>Email:</strong> ' + $('#biz-delivery-email').val() + '</p>' : '') +
					'<p><strong>Date/Time:</strong> ' + $('#biz-delivery-date').val() + ($('#biz-delivery-time').val() ? ' at ' + $('#biz-delivery-time').val() : '') + '</p>' +
					'</div><div class="review-section">' +
					'<h5>Service Details</h5>' +
					'<p><strong>Vehicle:</strong> ' + $('input[name="biz-vehicle"]:checked').closest('.vehicle-radio-card').find('h5').text() + '</p>' +
					'<p><strong>Service:</strong> ' + $('input[name="biz-service"]:checked').closest('.service-radio-card').find('h5').text() + '</p>' +
					'<p><strong>Distance:</strong> ' + bookingData.distance.toFixed(2) + ' miles</p>' +
					'<p><strong>Total Price:</strong> <span style="color:#e31837; font-size:20px; font-weight:800;">£' + bookingData.basePrice.toFixed(2) + '</span></p>' +
					'</div>';
				
				$('#biz-booking-review').html(reviewHTML);
				
				// Check credit sufficiency
				if (bookingData.basePrice > availableCredit) {
					$('#biz-credit-warning').show();
					$('#biz-confirm-booking').prop('disabled', true);
				} else {
					$('#biz-credit-warning').hide();
					$('#biz-confirm-booking').prop('disabled', false);
				}
				
				$('.step-3').removeClass('active');
				$('.step-4').addClass('active');
			});

			$('#biz-back-step3').on('click', function() {
				$('.step-4').removeClass('active');
				$('.step-3').addClass('active');
			});

			// Final booking confirmation
			$('#biz-confirm-booking').on('click', function() {
				$(this).prop('disabled', true).text('Processing...');
				
				var bookingPayload = {
					action: 'ocb_business_create_booking',
					nonce: '<?php echo wp_create_nonce("ocb_business_booking"); ?>',
					pickup_postcode: bookingData.pickupPostcode,
					delivery_postcode: bookingData.deliveryPostcode,
					pickup_address: $('#biz-pickup-address').val(),
					delivery_address: $('#biz-delivery-address').val(),
					collection_date: $('#biz-collection-date').val(),
					collection_time: $('#biz-collection-time').val(),
					delivery_date: $('#biz-delivery-date').val(),
					delivery_time: $('#biz-delivery-time').val(),
					collection_name: $('#biz-collection-name').val(),
					collection_phone: $('#biz-collection-phone').val(),
					collection_email: $('#biz-collection-email').val(),
					delivery_name: $('#biz-delivery-name').val(),
					delivery_phone: $('#biz-delivery-phone').val(),
					delivery_email: $('#biz-delivery-email').val(),
					vehicle_id: bookingData.vehicleId,
					service_id: bookingData.serviceId,
					distance: bookingData.distance,
					total_price: bookingData.basePrice
				};
				
				$.post('<?php echo admin_url("admin-ajax.php"); ?>', bookingPayload, function(res) {
					if (res.success) {
						$('#biz-booking-message').html('<div class="success-message"><strong>Success!</strong> ' + res.data.message + '</div>');
						availableCredit = res.data.new_balance;
						$('#available-credit-display').text('£' + availableCredit.toFixed(2));
						
						setTimeout(function() {
							$('.dash-tab-content').removeClass('active');
							$('#dash-bookings').addClass('active');
							$('.nav-item').removeClass('active');
							$('.nav-item[data-target="dash-bookings"]').addClass('active');
							location.reload();
						}, 2000);
					} else {
						$('#biz-booking-message').html('<div class="error-message">' + res.data.message + '</div>');
						$('#biz-confirm-booking').prop('disabled', false).text('Confirm Booking');
					}
				});
			});
		});
		</script>
		<?php
	}

	/**
	 * AJAX: Get quote for business booking
	 */
	public function ajax_business_get_quote() {
		check_ajax_referer( 'ocb_business_booking', 'nonce' );

		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => 'Please log in.' ) );
		}

		$pickup = sanitize_text_field( $_POST['pickup_postcode'] );
		$delivery = sanitize_text_field( $_POST['delivery_postcode'] );

		// Calculate distance using Distance Matrix API (returns float or WP_Error)
		$distance = OnRoute_Courier_Booking_Distance_Matrix::get_distance( $pickup, $delivery );
		
		if ( is_wp_error( $distance ) ) {
			wp_send_json_error( array( 'message' => $distance->get_error_message() ) );
		}

		if ( $distance > 0 ) {
			wp_send_json_success( array( 'distance' => $distance ) );
		}

		wp_send_json_error( array( 'message' => 'Could not calculate distance. Please check postcodes.' ) );
	}

	/**
	 * AJAX: Create business booking with credit payment
	 */
	public function ajax_business_create_booking() {
		check_ajax_referer( 'ocb_business_booking', 'nonce' );

		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => 'Please log in.' ) );
		}

		$user_id = get_current_user_id();
		$account = OnRoute_Business_Credit::get_account_by_user( $user_id );

		if ( ! $account || $account->account_status !== 'approved' ) {
			wp_send_json_error( array( 'message' => 'Your business credit account is not active.' ) );
		}

		// Get booking data
		$pickup_postcode = sanitize_text_field( $_POST['pickup_postcode'] );
		$delivery_postcode = sanitize_text_field( $_POST['delivery_postcode'] );
		$pickup_address = sanitize_textarea_field( $_POST['pickup_address'] );
		$delivery_address = sanitize_textarea_field( $_POST['delivery_address'] );
		$collection_date = sanitize_text_field( $_POST['collection_date'] );
		$collection_time = sanitize_text_field( $_POST['collection_time'] );
		$delivery_date = sanitize_text_field( $_POST['delivery_date'] );
		$delivery_time = sanitize_text_field( $_POST['delivery_time'] );
		
		// Collection contact info
		$collection_name = sanitize_text_field( $_POST['collection_name'] ?? '' );
		$collection_phone = sanitize_text_field( $_POST['collection_phone'] ?? '' );
		$collection_email = sanitize_email( $_POST['collection_email'] ?? '' );
		
		// Delivery contact info
		$delivery_name = sanitize_text_field( $_POST['delivery_name'] ?? '' );
		$delivery_phone = sanitize_text_field( $_POST['delivery_phone'] ?? '' );
		$delivery_email = sanitize_email( $_POST['delivery_email'] ?? '' );
		
		$vehicle_id = sanitize_text_field( $_POST['vehicle_id'] );
		$service_id = sanitize_text_field( $_POST['service_id'] );
		$distance = floatval( $_POST['distance'] );

		// Recalculate price server-side with business credit discount and night surcharge
		$pricing = new OnRoute_Courier_Booking_Pricing();
		$total_price = $pricing->calculate_price( 
			$distance, 
			$vehicle_id, 
			$collection_time, 
			$service_id, 
			$delivery_time, 
			false, 
			true // Business credit: No admin fees
		);

		// Check available credit
		$available_credit = $account->credit_limit - $account->current_balance;
		if ( $total_price > $available_credit ) {
			wp_send_json_error( array( 'message' => 'Insufficient credit balance.' ) );
		}

		// Use collection contact as primary (fallback to delivery contact)
		$customer_name = ! empty( $collection_name ) ? $collection_name : $delivery_name;
		$customer_phone = ! empty( $collection_phone ) ? $collection_phone : $delivery_phone;
		$customer_email = ! empty( $collection_email ) ? $collection_email : $delivery_email;

		// Create booking
		$booking = new OnRoute_Courier_Booking_Booking();
		$booking_id = $booking->create(
			$customer_email,
			$customer_phone,
			$pickup_address,
			$pickup_postcode,
			$delivery_address,
			$delivery_postcode,
			$collection_date,
			$collection_time,
			$delivery_date,
			$delivery_time,
			$vehicle_id,
			$service_id,
			$total_price,
			0, // VAT amount
			0, // Discount amount
			$total_price,
			'' // Promo code
		);

		if ( ! $booking_id ) {
			wp_send_json_error( array( 'message' => 'Failed to create booking.' ) );
		}

		// Build notes with collection/delivery contact info
		$notes = array(
			'collection_contact_name' => $collection_name,
			'collection_contact_phone' => $collection_phone,
			'collection_contact_email' => $collection_email,
			'delivery_contact_name' => $delivery_name,
			'delivery_contact_phone' => $delivery_phone,
			'delivery_contact_email' => $delivery_email,
			'booked_by_company' => $account->company_name
		);

		// Update booking with user_id, notes, and mark as paid
		global $wpdb;
		$wpdb->update(
			OnRoute_Courier_Booking_Database::get_bookings_table(),
			array(
				'user_id' => $user_id,
				'customer_name' => $customer_name,
				'payment_status' => 'paid',
				'payment_method' => 'business_credit',
				'status' => 'confirmed',
				'notes' => wp_json_encode( $notes )
			),
			array( 'id' => $booking_id )
		);

		// Deduct from credit balance
		$new_balance = $account->current_balance + $total_price;
		OnRoute_Business_Credit::save_account( array(
			'id' => $account->id,
			'current_balance' => $new_balance
		) );

		// Send Confirmation Emails
		if ( class_exists( 'OnRoute_Courier_Booking_Emails' ) ) {
			OnRoute_Courier_Booking_Emails::send_booking_confirmation( $booking_id );
		}

		wp_send_json_success( array(
			'message' => 'Booking created successfully! Reference: #' . $booking->get( $booking_id )->booking_reference,
			'new_balance' => $account->credit_limit - $new_balance
		) );
	}

	/**
	 * AJAX: Lookup address by postcode
	 */
	public function ajax_business_lookup_address() {
		check_ajax_referer( 'ocb_business_booking', 'nonce' );

		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => 'Please log in.' ) );
		}

		$postcode = sanitize_text_field( $_POST['postcode'] );

		if ( empty( $postcode ) ) {
			wp_send_json_error( array( 'message' => 'Postcode is required.' ) );
		}

		$all_addresses = array();

		// Try GetAddress.io first - it returns all addresses with 'all' => 'true'
		$getaddress_key = get_option( 'ocb_getaddress_io_api_key', '' );
		if ( ! empty( $getaddress_key ) && class_exists( 'OCB_GetAddress_API' ) ) {
			$result = OCB_GetAddress_API::search_by_postcode( $postcode );
			if ( $result['success'] && ! empty( $result['data'] ) ) {
				wp_send_json_success( array( 
					'addresses' => $result['data'],
					'total' => count( $result['data'] )
				) );
			}
		}

		// Try Ideal Postcodes as fallback - fetch multiple pages if available
		$ideal_key = get_option( 'ocb_ideal_postcodes_api_key', '' );
		if ( ! empty( $ideal_key ) && class_exists( 'OCB_Ideal_Postcodes_API' ) ) {
			// Fetch first page
			$result = OCB_Ideal_Postcodes_API::search_by_postcode( $postcode, 0 );
			if ( $result['success'] && ! empty( $result['data'] ) ) {
				$all_addresses = $result['data'];
				
				// If we got exactly 100 results, there might be more pages
				if ( count( $result['data'] ) === 100 ) {
					// Fetch page 1 and 2 as well
					for ( $page = 1; $page <= 2; $page++ ) {
						$next_result = OCB_Ideal_Postcodes_API::search_by_postcode( $postcode, $page );
						if ( $next_result['success'] && ! empty( $next_result['data'] ) ) {
							$all_addresses = array_merge( $all_addresses, $next_result['data'] );
							if ( count( $next_result['data'] ) < 100 ) {
								break; // No more pages
							}
						}
					}
				}
				
				wp_send_json_success( array( 
					'addresses' => $all_addresses,
					'total' => count( $all_addresses )
				) );
			}
		}

		// If no API configured or both failed
		wp_send_json_error( array( 
			'message' => 'Address lookup service not configured or postcode not found.' 
		) );
	}
}
