<?php
/*
Plugin Name: Paid Memberships Pro - aba_payment Gateway
Plugin URI: https://toucanasia.com/
Description: Adds ABA PayWay payment as a gateway option for Paid Memberships Pro who located in Cambodia.
Version: 1.5.2
Author: HENG SEYHA
Author URI: https://toucanasia.com/
Text Domain: pmpro-aba_payment
*/

define('PMPRO_ABA_PAYWAY_DIR', plugin_dir_path(__FILE__));

// add a wp query variable to redirect to
add_action('query_vars', 'set_custome_aba_payway_query_var');
add_action('query_vars', 'set_custome_aba_payway_pushback_query_var');
function set_custome_aba_payway_query_var($vars)
{
	array_push($vars, 'aba_payway_page');
	// ref url redirected to in add rewrite rule
	return $vars;
}
function set_custome_aba_payway_pushback_query_var($vars)
{
	array_push($vars, 'aba_payway_pushback');
	// ref url redirected to in add rewrite rule
	return $vars;
}

// Create a redirect
add_action('init', 'custom_add_aba_payway_rewrite_rule');
add_action('init', 'custom_add_aba_payway_pushback_rewrite_rule');
function custom_add_aba_payway_rewrite_rule()
{
	add_rewrite_rule('^aba-payway/payment$', 'index.php?aba_payway_page=1', 'top');
	//flush the rewrite rules, should be in a plugin activation hook, i.e only run once...
	flush_rewrite_rules();
}
function custom_add_aba_payway_pushback_rewrite_rule()
{
	add_rewrite_rule('^aba-payway/pushback$', 'index.php?aba_payway_pushback=1', 'top');
	flush_rewrite_rules();
}

add_action('wp_enqueue_scripts', 'aba_payway_script');
function aba_payway_script()
{
	wp_enqueue_script('aba_payway_js', 'https://checkout.payway.com.kh/plugins/checkout2-0.js', false);
	wp_enqueue_script('pmpro-aba-paway', plugins_url('/assetes/js/pmpro-aba-payway.js', __FILE__));
}
// return the file we want...
add_filter('template_include', 'aba_payway_plugin_include_template');
function aba_payway_plugin_include_template($template)
{
	if (!empty(get_query_var('aba_payway_page'))) {
		$template = plugin_dir_path(__FILE__) . 'services/aba_payway_page.php';
	}
	return $template;
}

// load payment gateway class after all plugins are loaded to make sure PMPro stuff is available
function pmpro_aba_payment_plugins_loaded()
{

	load_plugin_textdomain('pmpro-aba-payway', false, basename(__DIR__) . '/languages');

	// make sure PMPro is loaded
	if (! defined('PMPRO_DIR')) {
		return;
	}

	require_once(PMPRO_ABA_PAYWAY_DIR . '/classes/class.pmprogateway_aba_payment.php');
}
add_action('plugins_loaded', 'pmpro_aba_payment_plugins_loaded');

// Register activation hook.
register_activation_hook(__FILE__, 'pmpro_aba_payment_admin_notice_activation_hook');
/**
 * Runs only when the plugin is activated.
 *
 * @since 0.1.0
 */
function pmpro_aba_payment_admin_notice_activation_hook()
{
	// Create transient data.
	set_transient('pmpro-aba-payway-admin-notice', true, 5);
}

/**
 * Admin Notice on Activation.
 *
 * @since 0.1
 */
function pmpro_aba_payment_admin_notice()
{
	// Check transient, if available display notice.
	if (get_transient('pmpro-aba-payway-admin-notice')) { ?>
		<div class="updated notice is-dismissible">
			<p><?php printf(__('Thank you for activating. <a href="%s">Visit the payment settings page</a> to configure the aba_payment Gateway.', 'pmpro-aba_payment'), esc_url(get_admin_url(null, 'admin.php?page=pmpro-paymentsettings'))); ?></p>
		</div>
	<?php
		// Delete transient, only display this notice once.
		delete_transient('pmpro-aba-payway-admin-notice');
	}
}
add_action('admin_notices', 'pmpro_aba_payment_admin_notice');

/** 
 * Show an admin warning notice if there is a level setup that is incorrect.
 * @since 0.9
 */
function pmpro_aba_payment_check_level_compat()
{

	// Only show the notice on either the levels page or payment settings page.
	if (! isset($_REQUEST['page']) || $_REQUEST['page'] != 'pmpro-membershiplevels') {
		return;
	}

	$level = isset($_REQUEST['edit']) ? intval($_REQUEST['edit']) : '';

	// Don't check if level is not set.
	if (empty($level)) {
		return;
	}

	$compatible = pmpro_aba_payment_check_billing_compat($level);

	if (! $compatible) {
	?>
		<div class="notice notice-error fade">
			<p>
				<?php esc_html_e("aba_payment currently doesn't support custom trials. Please can you update your membership levels that may have these set.", 'pmpro-aba_payment'); ?>
			</p>
		</div>
	<?php
	}
}
add_action('admin_notices', 'pmpro_aba_payment_check_level_compat');

/**
 * Fix PMPro aba_payment showing SSL error in admin menus
 * when set up correctly.
 *
 * @since 0.9
 */
function pmpro_aba_payment_pmpro_is_ready($pmpro_is_ready)
{
	global $pmpro_gateway_ready, $pmpro_pages_ready;

	if (empty($pmpro_gateway_ready) && 'aba_payway' === get_option('pmpro_gateway')) {
		if (get_option('pmpro_aba_payment_merchant_id') && get_option('pmpro_aba_payment_merchant_key') && get_option('pmpro_aba_payment_passphrase')) {
			$pmpro_gateway_ready = true;
		}
	}

	return ($pmpro_gateway_ready && $pmpro_pages_ready);
}
add_filter('pmpro_is_ready', 'pmpro_aba_payment_pmpro_is_ready');

/**
 * Check if there are billing compatibility issues for levels and aba_payment.
 * @since 0.9
 */
function pmpro_aba_payment_check_billing_compat($level = NULL)
{

	if (!function_exists('pmpro_init')) {
		return;
	}

	$gateway = get_option("pmpro_gateway");

	if ($gateway == "aba_payment") {

		global $wpdb;

		//check ALL the levels
		if (empty($level)) {
			$sqlQuery = "SELECT * FROM $wpdb->pmpro_membership_levels ORDER BY id ASC";
			$levels = $wpdb->get_results($sqlQuery, OBJECT);

			if (!empty($levels)) {
				foreach ($levels as $level) {
					if (!pmpro_aba_payment_check_billing_compat($level->id)) {
						return false;
					}
				}
			}
		} else {

			if (is_numeric($level) && $level > 0) {

				$level = $wpdb->get_row($wpdb->prepare("SELECT * FROM $wpdb->pmpro_membership_levels WHERE id = %d LIMIT 1", $level));

				if (pmpro_isLevelTrial($level)) {
					return false;
				}
			}
		}
	}

	return true;
}

/**
 * Show a warning if custom trial is selected during level setup.
 * @since 0.9
 */
function pmpro_aba_payment_custom_trial_js_check()
{
	$gateway = get_option('pmpro_gateway');

	if ($gateway !== 'aba_payway') {
		return;
	}

	$custom_trial_warning = __(sprintf('aba_payment does not support custom trials. Please use the %s instead.', "<a href='https://www.paidmembershipspro.com/add-ons/subscription-delays' target='_blank'>Subscription Delay Add On</a>"), 'pmpro-aba_payment'); ?>
	<script>
		jQuery(document).ready(function() {
			var message = "<?php echo $custom_trial_warning; ?>";
			jQuery('<tr id="aba_payment-trial-warning" style="display:none"><th></th><td><em><strong>' + message + '</strong></em></td></tr>').insertAfter('.trial_info');

			// Show for existing levels.
			if (jQuery('#custom-trial').is(':checked')) {
				jQuery('#aba_payment-trial-warning').show();

			}

			// Toggle if checked or not
			pmpro_aba_payment_trial_checked();

			function pmpro_aba_payment_trial_checked() {

				jQuery('#custom_trial').change(function() {
					if (jQuery(this).prop('checked')) {
						jQuery('#aba_payment-trial-warning').show();
					} else {
						jQuery('#aba_payment-trial-warning').hide();
					}
				});
			}
		});
	</script>
	<?php
}
add_action('pmpro_membership_level_after_other_settings', 'pmpro_aba_payment_custom_trial_js_check');

/**
 * Function to add links to the plugin action links
 *
 * @param array $links Array of links to be shown in plugin action links.
 */
function pmpro_aba_payment_plugin_action_links($links)
{
	$new_links = array();

	if (current_user_can('manage_options')) {
		$new_links[] = '<a href="' . get_admin_url(null, 'admin.php?page=pmpro-paymentsettings') . '">' . __('Configure aba_payment', 'pmpro-aba_payment') . '</a>';
	}

	return array_merge($new_links, $links);
}
add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'pmpro_aba_payment_plugin_action_links');

/**
 * Function to add links to the plugin row meta
 *
 * @param array  $links Array of links to be shown in plugin meta.
 * @param string $file Filename of the plugin meta is being shown for.
 */
function pmpro_aba_payment_plugin_row_meta($links, $file)
{
	if (strpos($file, 'pmpro-payway.php') !== false) {
		$new_links = array(
			'<a href="' . esc_url('https://www.paidmembershipspro.com/add-ons/aba_payment-payment-gateway/') . '" title="' . esc_attr(__('View Documentation', 'pmpro-aba_payment')) . '">' . __('Docs', 'pmpro-aba_payment') . '</a>',
			'<a href="' . esc_url('https://www.paidmembershipspro.com/support/') . '" title="' . esc_attr(__('Visit Customer Support Forum', 'pmpro-aba_payment')) . '">' . __('Support', 'pmpro-aba_payment') . '</a>',
		);
		$links = array_merge($links, $new_links);
	}
	return $links;
}
add_filter('plugin_row_meta', 'pmpro_aba_payment_plugin_row_meta', 10, 2);

function pmpro_aba_payment_discount_code_result($discount_code, $discount_code_id, $level_id, $code_level)
{

	global $wpdb;

	//okay, send back new price info
	$sqlQuery = "SELECT l.id, cl.*, l.name, l.description, l.allow_signups FROM $wpdb->pmpro_discount_codes_levels cl LEFT JOIN $wpdb->pmpro_membership_levels l ON cl.level_id = l.id LEFT JOIN $wpdb->pmpro_discount_codes dc ON dc.id = cl.code_id WHERE dc.code = '" . $discount_code . "' AND cl.level_id = '" . $level_id . "' LIMIT 1";

	$code_level = $wpdb->get_row($sqlQuery);

	//if the discount code doesn't adjust the level, let's just get the straight level
	if (empty($code_level)) {
		$code_level = $wpdb->get_row("SELECT * FROM $wpdb->pmpro_membership_levels WHERE id = '" . $level_id . "' LIMIT 1");
	}

	if (pmpro_isLevelFree($code_level)) { //A valid discount code was returned
	?>
		jQuery('#pmpro_aba_payment_before_checkout').hide();
<?php
	}
}
add_action('pmpro_applydiscountcode_return_js', 'pmpro_aba_payment_discount_code_result', 10, 4);
