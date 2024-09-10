<?php

/**
 * Based on the scripts by Ron Darby shared at
 * https://ABA PayWay.io/integration/shopping-carts/paid-memberships-pro/
 *
 * @author     Ron Darby - ABA PayWay
 * @copyright  2009-2014 ABA PayWay (Pty) Ltd
 * @license    http://www.opensource.org/licenses/lgpl-license.php LGPL
 */

// Require the default PMPro Gateway Class.
require_once PMPRO_DIR . '/classes/gateways/class.pmprogateway.php';

// load classes init method
add_action('init', array('PMProGateway_ABA_PayWay', 'init'));
class PMProGateway_ABA_PayWay extends PMProGateway
{
	function __construct($gateway = null)
	{
		return parent::__construct($gateway);
	}

	/**
	 * Run on WP init
	 *
	 * @since 1.8
	 */
	static function init()
	{

		// make sure ABA PayWay is a gateway option
		add_filter('pmpro_gateways', array('PMProGateway_ABA_PayWay', 'pmpro_gateways'));

		// add fields to payment settings
		add_filter('pmpro_payment_options', array('PMProGateway_ABA_PayWay', 'pmpro_payment_options'));

		add_filter('pmpro_payment_option_fields', array('PMProGateway_ABA_PayWay', 'pmpro_payment_option_fields'), 10, 2);

		if (get_option('pmpro_gateway') == 'aba_payway') {
			// add_action('pmpro_checkout_preheader', array('PMProGateway_ABA_PayWay', 'pmpro_checkout_preheader'));
			add_filter('pmpro_include_billing_address_fields', '__return_false');
			add_filter('pmpro_include_payment_information_fields', '__return_false');
			add_filter('pmpro_billing_show_payment_method', '__return_false');
			add_filter('pmpro_checkout_default_submit_button', '__return_false');
			add_action('pmpro_billing_before_submit_button', array('PMProGateway_ABA_PayWay', 'pmpro_billing_before_submit_button'));
			add_filter('pmpro_checkout_after_payment_information_fields', array('PMProGateway_ABA_PayWay', 'pmpro_checkout_after_payment_information_fields'));
		}

		// add_filter('pmpro_required_billing_fields', array('PMProGateway_ABA_PayWay', 'pmpro_required_billing_fields'));
		add_filter('pmpro_required_billing_fields', '__return_false');
		add_filter('pmpro_checkout_before_submit_button', array('PMProGateway_ABA_PayWay', 'pmpro_checkout_before_submit_button'));
		add_filter('pmpro_checkout_before_change_membership_level', array('PMProGateway_ABA_PayWay', 'pmpro_checkout_before_change_membership_level'), 10, 2);

		// itn handler
		add_action('wp_ajax_nopriv_pmpro_aba_payway_itn_handler', array('PMProGateway_ABA_PayWay', 'wp_ajax_pmpro_aba_payway_itn_handler'));
		add_action('wp_ajax_pmpro_aba_payway_itn_handler', array('PMProGateway_ABA_PayWay', 'wp_ajax_pmpro_aba_payway_itn_handler'));

		add_filter('pmpro_gateways_with_pending_status', array('PMProGateway_ABA_PayWay', 'pmpro_gateways_with_pending_status'));
	}

	public function get_aba_credentails()
	{
		// merchant details
		$merchant_pushback_url = get_option('pmpro_pushback_url');
		$success_url = get_option('pmpro_success_url');

		// build ABA PayWay hash
		$environment = get_option('pmpro_gateway_environment');

		if ('sandbox' === $environment || 'beta-sandbox' === $environment) {
			// staging
			$merchant_id = get_option('pmpro_staging_aba_merchant_id');
			$merchant_key = get_option('pmpro_staging_aba_api');
			$merchant_url = get_option('pmpro_staging_aba_url');
		} else {
			$merchant_id  = get_option('pmpro_aba_merchant_id');
			$merchant_key = get_option('pmpro_aba_api');
			$merchant_url = get_option('pmpro_aba_url');
		}

		return array('env' => $environment, 'success_url' => $success_url, 'return_url' => $merchant_pushback_url, 'aba_url' => $merchant_url, 'aba_api_key' => $merchant_key, 'merchant_id' => $merchant_id);
	}

	public function getHash($hash_str, $aba_api_key)
	{
		$hash = base64_encode(hash_hmac('sha512', $hash_str, $aba_api_key, true));
		return $hash;
	}


	/**
	 * Add ABA PayWay to the list of allowed gateways.
	 *
	 * @return array
	 */
	static function pmpro_gateways_with_pending_status($gateways)
	{
		$gateways[] = 'aba_payway';

		return $gateways;
	}

	/**
	 * Make sure this gateway is in the gateways list
	 *
	 * @since 1.8
	 */

	static function pmpro_gateways($gateways)
	{
		if (empty($gateways['aba_payway'])) {
			$gateways['aba_payway'] = __('ABA PayWay', 'pmpro-aba-payway');
		}

		return $gateways;
	}

	/* What features does ABA PayWay support
	 * 
	 * @since TBD
	 * 
	 * @return array
	 */
	public static function supports($feature)
	{
		$supports = array(
			'subscription_sync' => true,
		);

		if (empty($supports[$feature])) {
			return false;
		}

		return $supports[$feature];
	}

	/**
	 * Get a list of payment options that the this gateway needs/supports.
	 *
	 * @since 1.8
	 */
	static function getGatewayOptions()
	{
		$options = array(
			'pmpro_aba_payway_debug',
			'gateway_environment',
			'aba_merchant_id',
			'aba_url',
			'aba_api',
			'currency',
			'tax_state',
			'tax_rate',
			'pushback_url',
			'success_url',
			'staging_aba_merchant_id',
			'staging_aba_url',
			'staging_aba_api',
			'required_billing',
			'available_payment_option'
		);

		return $options;
	}

	/**
	 * Set payment options for payment settings page.
	 *
	 * @since 1.8
	 */
	static function pmpro_payment_options($options)
	{
		// get stripe options
		$ABA_PayWay_options = self::getGatewayOptions();
		// merge with others.
		$options = array_merge($ABA_PayWay_options, $options);

		return $options;
	}

	/**
	 * Display fields for this gateway's options.
	 *
	 * @since 1.8
	 */
	static function pmpro_payment_option_fields($values, $gateway)
	{      ?>
		<tr class="pmpro_settings_divider aba_payment">
			<td colspan="2">
				<hr />
				<h2 class="title propduction-title"><?php esc_html_e('ABA Production Settings', 'paid-memberships-pro'); ?></h2>
			</td>
		</tr>
		<tr class="aba_payment ">
			<th scope="row" valign="top">
				<label for="aba_merchant_id"><?php esc_html_e('ABA Merchant ID', 'paid-memberships-pro'); ?></label>
			</th>
			<td>
				<input type="text" id="aba_merchant_id" name="aba_merchant_id" value="<?php echo esc_attr($values['aba_merchant_id']) ?>" class="regular-text code" />
			</td>
		</tr>
		<tr class="aba_payment">
			<th scope="row" valign="top">
				<label for="aba_url"><?php esc_html_e('ABA Bank URL', 'paid-memberships-pro'); ?></label>
			</th>
			<td>
				<input type="text" id="aba_url" name="aba_url" value="<?php echo esc_attr($values['aba_url']) ?>" class="regular-text" />
			</td>
		</tr>
		<tr class="aba_payment ">
			<th scope="row" valign="top">
				<label for="aba_api"><?php esc_html_e('ABA API', 'paid-memberships-pro'); ?></label>
			</th>
			<td>
				<input type="text" id="aba_api" name="aba_api" value="<?php echo esc_attr($values['aba_api']) ?>" autocomplete="off" class="regular-text code pmpro-admin-secure-key" />
			</td>
		</tr>
		<!-- // sandbox -->
		<tr class="pmpro_settings_divider sandbox_aba_payment">
			<td colspan="2">
				<hr />
				<h2 class="title propduction-title"><?php esc_html_e('ABA Sandbox Settings', 'paid-memberships-pro'); ?></h2>
			</td>
		</tr>
		<tr class="sandbox_aba_payment">
			<th scope="row" valign="top">
				<label for="staging_aba_merchant_id"><?php esc_html_e('ABA Merchant ID', 'paid-memberships-pro'); ?></label>
			</th>
			<td>
				<input type="text" id="staging_aba_merchant_id" name="staging_aba_merchant_id" value="<?php echo esc_attr($values['staging_aba_merchant_id']) ?>" class="regular-text code" />
			</td>
		</tr>
		<tr class="sandbox_aba_payment">
			<th scope="row" valign="top">
				<label for="staging_aba_url"><?php esc_html_e('ABA Bank URL', 'paid-memberships-pro'); ?></label>
			</th>
			<td>
				<input type="text" id="staging_aba_url" name="staging_aba_url" value="<?php echo esc_attr($values['staging_aba_url']) ?>" class="regular-text" />
			</td>
		</tr>
		<tr class="sandbox_aba_payment">
			<th scope="row" valign="top">
				<label for="staging_aba_api"><?php esc_html_e('ABA API', 'paid-memberships-pro'); ?></label>
			</th>
			<td>
				<input type="text" id="staging_aba_api" name="staging_aba_api" value="<?php echo esc_attr($values['staging_aba_api']) ?>" autocomplete="off" class="regular-text code pmpro-admin-secure-key" />
			</td>
		</tr>
		<tr class="aba_option push_back_url">
			<th scope="row" valign="top">
				<label for="pushback_url"><?php esc_html_e('ABA Bank Pushback URL', 'paid-memberships-pro'); ?></label>
			</th>
			<td>
				<input type="text" id="pushback_url" name="pushback_url" value="<?php echo esc_attr($values['pushback_url']) ?>" class="regular-text" />
			</td>
		</tr>
		<tr class="aba_option success_url">
			<th scope="row" valign="top">
				<label for="success_url"><?php esc_html_e('ABA Bank Pushback URL', 'paid-memberships-pro'); ?></label>
			</th>
			<td>
				<input type="text" id="success_url" name="success_url" value="<?php echo esc_attr($values['success_url']) ?>" class="regular-text" />
			</td>
		</tr>
		<tr class="aba_option required_billing">
			<th scope="row" valign="top">
				<?php esc_html_e('Required Billing', 'paid-memberships-pro'); ?>
			</th>
			<td>
				<input type="radio" id="required_billing_yes" <?php echo esc_attr($values['required_billing'] == 'Yes' ? 'checked' : ''); ?> name="required_billing" value="Yes" class="regular-text" /><label for="required_billing_yes">Yes</label>
				<input type="radio" id="required_billing_no" name="required_billing" <?php echo esc_attr($values['required_billing'] == 'No' ? 'checked' : '') ?> value="No" class="regular-text" /><label for="required_billing_no">No</label>
			</td>
		</tr>
		<?php
		$array_payments = array(
			'cards',
			'abapay',
			'abapay_deeplink',
			'wechat',
			'alipay',
			'bakong',
		);
		?>
		<tr class="aba_option available_payment_option">
			<th scope="row" valign="top">
				<?php esc_html_e('Available Payment Options', 'paid-memberships-pro'); ?>
			</th>
			<td>
				<?php
				$option = $values['available_payment_option'] ? $values['available_payment_option'] : '';
				$available_payment_option = [];
				if ($option) {
					$available_payment_option = explode(",", $option);
				}

				for ($i = 0; $i < count($array_payments); $i++) {
				?>
					<input type="checkbox" id="available_payment_option_<?php echo $array_payments[$i]; ?>" <?php echo in_array($array_payments[$i], $available_payment_option) ? 'checked' : ''; ?> name="available_payment_option[]" value="<?php echo $array_payments[$i]; ?>" class="regular-text" /><label for="available_payment_option_<?php echo $array_payments[$i]; ?>"><?php echo $array_payments[$i]; ?></label>
				<?php
				}
				?>
			</td>
		</tr>


		<script type="text/javascript">
			jQuery(document).ready(function($) {
				if ($("#gateway").val() == 'aba_payway') {
					$(".aba_option").show();
					if ($("#gateway_environment").val() == 'sandbox') {
						$(".sandbox_aba_payment").show()
						$(".aba_payment").hide()
					} else {
						$(".sandbox_aba_payment").hide()
						$(".aba_payment").show()
					}
				} else {
					$(".sandbox_aba_payment").hide()
					$(".aba_payment").hide()
					$(".aba_option").hide();
				}
				$("#gateway").on('change', function() {
					if ($(this).val() != 'aba_payway') {
						$(".aba_payment").hide();
						$(".sandbox_aba_payment").hide();
						$(".aba_option").hide();
					} else {
						$(".aba_option").show();
						if ($("#gateway_environment").val() == 'sandbox') {
							$(".sandbox_aba_payment").show()
							$(".aba_payment").hide()
						} else {
							$(".sandbox_aba_payment").hide()
							$(".aba_payment").show()
						}
					}
				});
				$("#gateway_environment").on('change', function() {
					if ($(this).val() == 'sandbox') {
						$(".sandbox_aba_payment").show()
						$(".aba_payment").hide()
					} else {
						$(".sandbox_aba_payment").hide()
						$(".aba_payment").show()
					}
				})
			});
		</script>
		<?php
	}

	/**
	 * Remove required billing fields
	 *
	 * @since 1.8
	 */
	static function pmpro_required_billing_fields($fields)
	{

		unset($fields['bfirstname']);
		unset($fields['blastname']);
		unset($fields['baddress1']);
		unset($fields['bcity']);
		unset($fields['bstate']);
		unset($fields['bzipcode']);
		unset($fields['bphone']);
		unset($fields['bemail']);
		unset($fields['bcountry']);
		// unset($fields['CardType']);
		unset($fields['AccountNumber']);
		unset($fields['ExpirationMonth']);
		unset($fields['ExpirationYear']);
		unset($fields['CVV']);

		return $fields;
	}

	/**
	 * Show a notice on the Update Billing screen.
	 * 
	 * @since 1.0.0
	 */
	static function pmpro_billing_before_submit_button()
	{

		if (apply_filters('pmpro_aba_payway_hide_update_billing_button', true)) {
		?>
			<script>
				jQuery(document).ready(function() {
					jQuery('.pmpro_form_submit').hide();
				});
			</script>
		<?php
		}
		echo sprintf(__("If you need to update your billing details, please login to your %s account to update these credentials. Selecting the update button below will automatically redirect you to ABA PayWay.", 'pmpro-aba-payway'), "<a href='https://ABA PayWay.io' target='_blank'>ABA PayWay</a>");
	}


	static function pmpro_checkout_after_payment_information_fields()
	{
		global $gateway, $pmpro_review, $skip_account_fields, $pmpro_paypal_token, $wpdb, $current_user, $pmpro_msg, $pmpro_msgt, $pmpro_requirebilling, $pmpro_level, $tospage, $pmpro_show_discount_code, $pmpro_error_fields, $pmpro_default_country;
		global $discount_code, $username, $password, $password2, $bfirstname, $blastname, $baddress1, $baddress2, $bcity, $bstate, $bzipcode, $bcountry, $bphone, $bemail, $bconfirmemail, $CardType, $AccountNumber, $ExpirationMonth, $ExpirationYear;
		if ($gateway == 'aba_payway' && !pmpro_isLevelFree($pmpro_level)) {
			$availablePayment = get_option('pmpro_available_payment_option');
			$environment = get_option("pmpro_gateway_environment");
			$check_gateway_label = 'Select Payment Options';
			$array_payments = array(
				'cards' => get_template_directory_uri() . '/assetes/images/payments/card.svg',
				'abapay' => get_template_directory_uri() . '/assetes/images/payments/aba-pay.svg',
				'abapay_deeplink' => '',
				'wechat' => '',
				'alipay' => '',
				'bakong' => get_template_directory_uri() . '/assetes/images/payments/KHQR.svg',
			);
		?>
			<fieldset id="pmpro_payment_information_fields" class="<?php echo esc_attr(pmpro_get_element_class('pmpro_form_fieldset', 'pmpro_payment_information_fields')); ?>">

				<div class="<?php echo esc_attr(pmpro_get_element_class('pmpro_card')); ?>">
					<div class="<?php echo esc_attr(pmpro_get_element_class('pmpro_card_content')); ?>">
						<?php
						$all_level = [];
						if (function_exists('pmpro_getAllLevels')) {
							$all_level = pmpro_getAllLevels();
							echo '<div class="account-level ">';
							echo '<div class="mt-1 pmpro_checkout">';
							echo '<h3><span class="pmpro_checkout-choise">Your Selection</span></h3>';
							echo '<div class="list-levels">';
							$i = 0;
							$selected  = '';
							foreach ($all_level as $key => $level) {
								if (!$level->cycle_number && !$level->cycle_period) {
									$period = 'Life Time';
								} else {
									$cycle_period = $level->cycle_number > 1 ?  $level->cycle_period . 's' : $level->cycle_period;
									$period = $level->cycle_number  . ' ' . $cycle_period;
								}

								if ($i == 0) {
									$selected = $level->id;
								}
						?>
								<div>

									<input id="check-<?php echo $level->id; ?>" <?php echo $i == 0 ? 'checked' : '' ?> type="checkbox" name="selecteLevel" value="<?php echo $level->id; ?>">

									<label for="check-<?php echo $level->id; ?>"><?php echo $level->initial_payment . ' USD For ' . $period; ?></label>
								</div>
							<?php
								$i++;
							}
							echo '</div>';
							echo '<div id="discountsection">';
							?>
							<?php if ($pmpro_show_discount_code) { ?>
								<div class="<?php echo esc_attr(pmpro_get_element_class('pmpro_cols-2')); ?>">
									<div class="mt-5 <?php echo esc_attr(pmpro_get_element_class('pmpro_form_field pmpro_form_field-text pmpro_payment-discount-code', 'pmpro_payment-discount-code')); ?>">
										<label for="pmpro_discount_code_aba_payment" class="<?php echo esc_attr(pmpro_get_element_class('pmpro_form_label')); ?>">
											<?php esc_html_e('Do you have discount code?', 'paid-memberships-pro'); ?>
										</label>
										<div class="<?php echo esc_attr(pmpro_get_element_class('pmpro_form_fields-inline')); ?>">
											<input class="<?php echo esc_attr(pmpro_get_element_class('pmpro_form_input pmpro_form_input-text pmpro_alter_price', 'discount_code')); ?>" id="pmpro_discount_code_aba_payment" name="pmpro_discount_code_aba_payment" type="text" size="10" value="<?php echo esc_attr($discount_code); ?>" />
											<input aria-label="<?php esc_html_e('Apply discount code', 'paid-memberships-pro'); ?>" type="button" id="discount_code_button" name="discount_code_button" value="<?php esc_attr_e('Apply', 'paid-memberships-pro'); ?>" class="<?php echo esc_attr(pmpro_get_element_class('pmpro_btn pmpro_btn-submit-discount-code', 'other_discount_code_button')); ?>" />
										</div> <!-- end pmpro_form_fields-inline -->
										<div id="discount_code_message" class="<?php echo esc_attr(pmpro_get_element_class('pmpro_message', 'discount_code_message')); ?>" style="display: none;"></div>
									</div>
								</div> <!-- end pmpro_cols-2 -->
							<?php } ?>

						<?php
							echo '</div>';
							echo '</div>';
							echo '</div>';
						}
						?>
						<legend class="mt-5  <?php echo esc_attr(pmpro_get_element_class('pmpro_form_legend')); ?>">
							<h3 class=<?php echo esc_attr(pmpro_get_element_class('pmpro_form_heading pmpro_font-large')); ?>"><?php echo esc_html(sprintf(__('%s', 'paid-memberships-pro'), $check_gateway_label)); ?></h3>
						</legend>
						<div class="<?php echo esc_attr(pmpro_get_element_class('pmpro_form_fields')); ?>">
							<?php
							$available_payment_option = [];
							if ($availablePayment) {
								$available_payment_option = explode(",", $availablePayment);
							}
							?>
							<ul class="payment-otpion-lists">
								<?php
								for ($i = 0; $i < count($available_payment_option); $i++) {
								?>
									<li class="<?php echo $available_payment_option[$i] == 'cards' ? 'active' : '' ?>" data-option='<?php echo $available_payment_option[$i]; ?>'><?php if ($array_payments[$available_payment_option[$i]]) { ?> <img class="payment-option-icon" src="<?php echo $array_payments[$available_payment_option[$i]]; ?>" /><?php } else {
																																																																																						echo $available_payment_option[$i];
																																																																																					} ?></li>
								<?php
								}
								?>
							</ul>
						</div> <!-- end pmpro_check_instructions -->
					</div> <!-- end pmpro_form_fields -->
				</div> <!-- end pmpro_card -->
			</fieldset> <!-- end pmpro_payment_information_fields -->
		<?php
		}
	}

	public function checkTransaction($tran_id = '')
	{
		if ($tran_id) {
			$aba_credentials = $this->get_aba_credentails();
			$check_production =  'https://checkout.payway.com.kh/api/payment-gateway/v1/payments/check-transaction';
			$check_staging = 'https://checkout-sandbox.payway.com.kh/api/payment-gateway/v1/payments/check-transaction';

			$merchantId = $aba_credentials['aba_merchan_id'];
			if ($aba_credentials['env'] == 'production') {
				$url = $check_production;
			} else {
				$url = $check_staging;
			}

			$reqTime = date("YYYYmdHis");
			$hash = base64_encode(hash_hmac('sha512', $reqTime . $merchantId . $tran_id, $aba_credentials['aba_api_key'], true));
			$postfields = array(
				'tran_id' => $tran_id,
				'hash' => $hash,
				'req_time' => $reqTime,
				'merchant_id' => $merchantId
			);
			$ch = curl_init();
			curl_setopt($ch, CURLOPT_URL, $url);
			curl_setopt($ch, CURLOPT_PROXY, null);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
			curl_setopt($ch, CURLOPT_POST, 1);
			curl_setopt($ch, CURLOPT_POSTFIELDS, $postfields);
			curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0); // On dev server only!
			$result = curl_exec($ch); //Result Json
			$result = json_decode($result, true);
			$paymentStatus = isset($result['payment_status']) ? $result['payment_status'] : '';
			if (strtoupper($paymentStatus) == 'APPROVED') {
				return true;
			} else {
				return false;
			}
		}
	}
	public function aba_custom_check_entry_status($entry_id = '')
	{
		// $entry = GFFormsModel::get_lead($entry_id);
		$status = true;
		// if ($entry['payment_status'] == 'Approved') {
		// 	$status = false;
		// }

		return $status;
	}
	public function aba_process_subscription_entry_status($entry_id = '')
	{
		$this->update_subscription_info($entry_id);
	}

	static function pmpro_checkout_before_processing()
	{
		global $current_user, $gateway;
		//save user fields for PayPal Express
		if (!$current_user->ID) {
			//get values from post
			if (isset($_REQUEST['username']))
				$username = trim(sanitize_text_field($_REQUEST['username']));
			else
				$username = "";
			if (isset($_REQUEST['password'])) {
				// Can't sanitize the password. Be careful.
				$password = $_REQUEST['password']; //phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			} else {
				$password = "";
			}
			if (isset($_REQUEST['bemail']))
				$bemail = sanitize_email($_REQUEST['bemail']);
			else
				$bemail = "";

			//save to session
			$_SESSION['pmpro_signup_username'] = $username;
			$_SESSION['pmpro_signup_password'] = $password;
			$_SESSION['pmpro_signup_email'] = $bemail;
		}

		if (!empty($_REQUEST['tos'])) {
			$tospost = get_post(get_option('pmpro_tospage'));
			$_SESSION['tos'] = array(
				'post_id' => $tospost->ID,
				'post_modified' => $tospost->post_modified,
			);
		}

		//can use this hook to save some other variables to the session
		// @deprecated 2.12.3
		do_action("pmpro_paypalexpress_session_vars");
	}

	static function pmpro_checkout_order($morder)
	{
		// Create a code for the order.
		if (empty($morder->code)) {
			$morder->code = $morder->getRandomCode();
		}

		// Add the PaymentIntent ID to the order.
		if (! empty($_REQUEST['payment_intent_id'])) {
			$morder->payment_intent_id = sanitize_text_field($_REQUEST['payment_intent_id']);
		}
		if (!empty($_REQUEST['aba_paway_option'])) {
			$morder->cardtype = sanitize_text_field($_REQUEST['aba_paway_option']);
		}

		// Add the SetupIntent ID to the order.
		if (! empty($_REQUEST['setup_intent_id'])) {
			$morder->setup_intent_id = sanitize_text_field($_REQUEST['setup_intent_id']);
		}

		// Add the PaymentMethod ID to the order.
		if (! empty($_REQUEST['payment_method_id'])) {
			$morder->payment_method_id = sanitize_text_field($_REQUEST['payment_method_id']);
		}

		//stripe lite code to get name from other sources if available
		global $pmpro_stripe_lite, $current_user;
		if (! empty($pmpro_stripe_lite) && empty($morder->FirstName) && empty($morder->LastName)) {
			if (! empty($current_user->ID)) {
				$morder->FirstName = get_user_meta($current_user->ID, "first_name", true);
				$morder->LastName  = get_user_meta($current_user->ID, "last_name", true);
			} elseif (! empty($_REQUEST['first_name']) && ! empty($_REQUEST['last_name'])) {
				$morder->FirstName = sanitize_text_field($_REQUEST['first_name']);
				$morder->LastName  = sanitize_text_field($_REQUEST['last_name']);
			}
		}

		return $morder;
	}

	/**
	 * Swap in our submit buttons.
	 *
	 * @since 1.8
	 */
	static function pmpro_checkout_default_submit_button($show)
	{
		global $gateway, $pmpro_requirebilling;
		//show our submit buttons
		if ($show) {
		?>
			<?php if ($gateway == "aba_payment") { ?>
				<span id="pmpro_aba_payment_checkout">
					<input type="hidden" name="submit-checkout" value="1" />
					<button type="submit" id="pmpro_btn-submit-aba_payment" class="<?php echo esc_attr(pmpro_get_element_class('pmpro_btn pmpro_btn-submit-checkout pmpro_btn-submit-checkout-aba_payment')); ?>">
						<?php
						printf(
							/* translators: %s is the PayPal logo */
							esc_html__('Subscribe Now', 'paid-memberships-pro'),
							'<span class="pmpro_btn-submit-checkout-aba-image"></span>'
						);
						?>
						<span class="screen-reader-text"><?php esc_html_e('aba_payment', 'paid-memberships-pro'); ?></span>
					</button>
				</span>
			<?php
			}
		} else {
			//don't show the default
			return false;
		}
	}

	/**
	 * Show information before PMPro's checkout button.
	 *
	 * @since 1.8
	 */
	static function pmpro_checkout_before_submit_button()
	{
		global $gateway, $pmpro_requirebilling;

		// Bail if gateway isn't ABA PayWay.
		if ($gateway != 'aba_payway') {
			return;
		}

		// see if Pay By Check Add On is active, if it's selected let's hide the ABA PayWay information.
		if (defined('PMPROPBC_VER')) {
			?>
			<script type="text/javascript">
				jQuery(document).ready(function() {
					jQuery('input:radio[name=gateway]').on('click', function() {
						var val = jQuery(this).val();
						if (val === 'check') {
							jQuery('#pmpro_aba_payway_before_checkout').hide();
						} else {
							jQuery('#pmpro_aba_payay_before_checkout').show();
						}
					});
				});
			</script>
		<?php } ?>

		<div id="pmpro_ABA PayWay_before_checkout" style="text-align:center;">
			<span id="pmpro_aba_payment_checkout" <?php
													if ($gateway != 'aba_payway' || ! $pmpro_requirebilling) {
													?>
				style="display: none;" <?php } ?>>
				<input type="hidden" name="submit-checkout" value="1" />
				<button type="submit" id="pmpro_btn-submit-aba_payment" class="<?php echo esc_attr(pmpro_get_element_class('pmpro_btn pmpro_btn-submit-checkout pmpro_btn-submit-checkout-aba_payment')); ?>">
					<?php
					printf(
						/* translators: %s is the PayPal logo */
						esc_html__('Subscribe Now', 'paid-memberships-pro'),
						'<span class="pmpro_btn-submit-checkout-aba-image"></span>'
					);
					?>
					<span class="screen-reader-text"><?php esc_html_e('aba_payway', 'paid-memberships-pro'); ?></span>
				</button>
			</span>
		</div>

	<?php
	}

	/**
	 * Instead of change membership levels, send users to ABA PayWay to pay.
	 *
	 * @since 1.8
	 */
	static function pmpro_checkout_before_change_membership_level($user_id, $morder)
	{
		global $discount_code_id, $wpdb;
		// if no order, no need to pay
		if (empty($morder)) {
			return;
		}

		// bail if the current gateway is not set to ABA PayWay.
		if ('aba_payway' != $morder->gateway) {
			return;
		}
		$morder->user_id = $user_id;

		$morder->saveOrder();

		// if global is empty by query is available.
		if (empty($discount_code_id) && isset($_REQUEST['discount_code'])) {
			$discount_code_id = $wpdb->get_var("SELECT id FROM $wpdb->pmpro_discount_codes WHERE code = '" . esc_sql(sanitize_text_field($_REQUEST['discount_code'])) . "'");
		}

		// save discount code use
		if (! empty($discount_code_id)) {
			$wpdb->query(
				$wpdb->prepare(
					"INSERT INTO $wpdb->pmpro_discount_codes_uses 
					(code_id, user_id, order_id, timestamp) 
					VALUES( %d , %d, %d, %s )",
					$discount_code_id,
					$user_id,
					$morder->id,
					current_time('mysql')
				)
			);
		}

		do_action('pmpro_before_send_to_aba_payway', $user_id, $morder);

		$morder->Gateway->sendToABAPayWay($morder, $user_id);
	}

	/**
	 * Send traffic to wp-admin/admin-ajax.php?action=pmpro_ABA PayWay_itn_handler to the itn handler
	 * 
	 * @since 1.0.0
	 */
	static function wp_ajax_pmpro_aba_payway_itn_handler()
	{
		require_once PMPRO_ABA_PAYWAY_DIR . 'services/payway_itn_handler.php';
		exit;
	}

	function process(&$order)
	{

		if (empty($order->code)) {
			$order->code = $order->getRandomCode();
		}
		// var_dump('request', $_REQUEST);
		// clean up a couple values
		$order->payment_type = 'ABA PayWay';
		$order->CardType     = '';
		$order->cardtype     = isset($_REQUEST['aba_paway_option']) ? $_REQUEST['aba_paway_option'] : '';

		$order->status = "review";
		$order->saveOrder();

		return true;
	}

	/**
	 * @param $order
	 */
	function sendToABAPayWay($order, $user_id)
	{
		if (empty($order->code)) {
			$order->code = $order->getRandomCode();
		}

		$order->ProfileStartDate = date_i18n('Y-m-d', current_time('timestamp'));

		// taxes on initial payment
		$initial_payment     = $order->InitialPayment;
		$initial_payment_tax = $order->getTaxForPrice($initial_payment);
		$initial_payment     = round((float) $initial_payment + (float) $initial_payment_tax, 2);

		// taxes on the amount
		$amount          = $order->PaymentAmount;
		$amount_tax      = $order->getTaxForPrice($amount);
		$order->subtotal = $amount;
		$amount          = round((float) $amount + (float) $amount_tax, 2);
		$user_first_name = '';
		$user_last_name = '';
		$user_phone_number = '';
		if (metadata_exists('user', $user_id, 'first_name')) {
			$user_first_name = get_user_meta($user_id, 'first_name', true);
		}
		if (metadata_exists('user', $user_id, 'last_name')) {
			$user_last_name = get_user_meta($user_id, 'last_name', true);
		}
		if (metadata_exists('user', $user_id, 'phone_number')) {
			$user_phone_number = get_user_meta($user_id, 'phone_number', true);
		}

		$data['first_name']     = $user_first_name;
		$data['last_name'] 		= $user_last_name;
		$data['phone']      	= $user_phone_number;
		$data['req_time']      	= str_replace("-", "", str_replace(":", "", $order->timestamp));
		$data['email']  		= $order->Email;
		$data['tran_id']        = $order->code;
		$data['amount']         = $order->total;
		$data['payment_option'] = $order->cardtype;
		$data['continue_success_url']    = pmpro_url('confirmation', '?level=' . $order->membership_level->id);
		$data['cancel_url']    = pmpro_url('levels');
		$data['return_url']    = admin_url('admin-ajax.php') . '?action=pmpro_aba_payway_itn_handler';
		$data['return_param'] = "{'total': " . $order->total . "}";

		// filter order before subscription. use with care.
		$order = apply_filters('pmpro_subscribe_order', $order, $this);

		$order->status                      = 'pending';
		$order->payment_transaction_id      = $order->code;
		$order->subscription_transaction_id = $order->code;
		$order->subtotal = $order->InitialPayment;
		$order->tax = $initial_payment_tax;
		$order->total = $initial_payment;

		$pffOutput = '';
		foreach ($data  as $key => $val) {
			$pffOutput .= $key . '=' . urlencode(trim($val)) . '&';
		}

		// Save the order before redirecting to ABA PayWay.
		$order->saveOrder();
		wp_redirect(site_url('aba-payway/payment?' . $pffOutput));
	?>
<?php
		exit;
	}

	function abagetHash($reqTime, $merchantId, $tran_id, $amount, $merchant_key)
	{
		$hash = base64_encode(hash_hmac('sha512', $reqTime . $merchantId . $tran_id . $amount, $merchant_key, true));
		return $hash;
	}

	function subscribe(&$order)
	{
		if (empty($order->code)) {
			$order->code = $order->getRandomCode();
		}

		// filter order before subscription. use with care.
		$order = apply_filters('pmpro_subscribe_order', $order, $this);

		$order->status                      = 'success';
		$order->payment_transaction_id      = $order->code;
		$order->subscription_transaction_id = $order->code;

		// update order
		$order->saveOrder();

		return true;
	}

	// function cancel(&$order, $update_status = true)
	// {

	// 	// Check to see if the order has a token and try to cancel it at the gateway. Only recurring subscriptions should have a token.
	// 	if (! empty($order->subscription_transaction_id)) {

	// 		// Let's double check that the paypal_token isn't really there. (ABA PayWay uses paypal_token to store their token)
	// 		if (empty($order->paypal_token)) {
	// 			$last_subscription_order = $order->get_orders(array('subscription_transaction_id' => $order->subscription_transaction_id, 'limit' => 1));
	// 			$order->paypal_token = $last_subscription_order[0]->paypal_token;
	// 		}

	// 		// cancel order status immediately.
	// 		if ($update_status) {
	// 			$order->updateStatus('cancelled');
	// 		}

	// 		// check if we are getting an ITN notification which means it's already cancelled within ABA PayWay.
	// 		if (! empty($_POST['payment_status']) && $_POST['payment_status'] == 'CANCELLED') {
	// 			return true;
	// 		}

	// 		$token = $order->paypal_token;

	// 		$hashArray  = array();
	// 		$passphrase = get_option('pmpro_ABA PayWay_passphrase');

	// 		$hashArray['version']     = 'v1';
	// 		$hashArray['merchant-id'] = get_option('pmpro_ABA PayWay_merchant_id');
	// 		$hashArray['passphrase']  = $passphrase;
	// 		$hashArray['timestamp']   = date('Y-m-d') . 'T' . date('H:i:s');

	// 		$orderedPrehash = $hashArray;

	// 		ksort($orderedPrehash);

	// 		$signature = md5(http_build_query($orderedPrehash));

	// 		$domain = 'https://api.ABA PayWay.co.za';

	// 		$url = $domain . '/subscriptions/' . $token . '/cancel';

	// 		$environment = get_option('pmpro_gateway_environment');

	// 		if ('sandbox' === $environment || 'beta-sandbox' === $environment) {
	// 			$url = $url . '?testing=true';
	// 		}

	// 		$response = wp_remote_post(
	// 			$url,
	// 			array(
	// 				'method' => 'PUT',
	// 				'timeout' => 60,
	// 				'headers' => array(
	// 					'version'     => 'v1',
	// 					'merchant-id' => $hashArray['merchant-id'],
	// 					'signature'   => $signature,
	// 					'timestamp'   => $hashArray['timestamp'],
	// 					'content-length' => 0
	// 				),
	// 			)
	// 		);

	// 		$response_code    = wp_remote_retrieve_response_code($response);
	// 		$response_message = wp_remote_retrieve_response_message($response);

	// 		if (200 == $response_code) {
	// 			return true;
	// 		} else {
	// 			$order->updateStatus('error');
	// 			$order->errorcode  = $response_code;
	// 			$order->error      = $response_message;
	// 			$order->shorterror = $response_message;

	// 			return false;
	// 		}
	// 	}
	// }

	/**
	 * Function to handle cancellations of Subscriptions.
	 *
	 * @param object $subscription The PMPro Subscription Object
	 * @return string|null Error message returned from gateway.
	 * @since 1.5
	 */
	function update_subscription_info($subscription)
	{

		// We need to get the token from the order with this $subscription_id.
		$subscription_id = $subscription->get_subscription_transaction_id();

		$last_subscription_order = $subscription->get_orders(array('subscription_transaction_id' => $subscription_id, 'limit' => 1));

		$payfast_token = isset($last_subscription_order[0]->paypal_token) ? sanitize_text_field($last_subscription_order[0]->paypal_token) : false;

		if (! $payfast_token) {
			return false;
		}

		// Make an API call to PayFast to get the subscription details.

		$hashArray  = array();
		$passphrase = get_option('pmpro_payfast_passphrase');

		$hashArray['version']     = 'v1';
		$hashArray['merchant-id'] = get_option('pmpro_payfast_merchant_id');
		$hashArray['passphrase']  = $passphrase;
		$hashArray['timestamp']   = date('Y-m-d') . 'T' . date('H:i:s');

		$orderedPrehash = $hashArray;

		ksort($orderedPrehash);

		$signature = md5(http_build_query($orderedPrehash));

		$domain = 'https://api.payfast.co.za';

		$url = $domain . '/subscriptions/' . $payfast_token . '/fetch';

		// Is this a test transaction?
		$environment = get_option('pmpro_gateway_environment');
		if ('sandbox' === $environment || 'beta-sandbox' === $environment) {
			$url = $url . '?testing=true';
		}

		$request = wp_remote_get(
			$url,
			array(
				'timeout' => 60,
				'headers' => array(
					'version'     => 'v1',
					'merchant-id' => $hashArray['merchant-id'],
					'signature'   => $signature,
					'timestamp'   => $hashArray['timestamp'],
					'content-length' => 0
				),
			)
		);

		// Get the data from the response now and update the subscription.
		if (! is_wp_error($request)) {

			$response = json_decode(wp_remote_retrieve_body($request));

			if (200 !== $response->code) {
				return __(sprintf('Payfast error: %s', $response->data->response), 'pmpro-payfast');
			}

			// No data in the response.
			if (empty($response->data->response)) {
				return false;
			}

			$sub_info = $response->data->response;
			$update_array = array();

			// Get the subscription status and update it accordingly.
			if ($sub_info->status !== 1) {
				$update_array['status'] = 'cancelled';
			} else {
				$update_array['status'] = 'active';
			}

			// Convert the frequency of the subscription back to PMPro format.
			switch ($sub_info->frequency) {
				case '1':
					$update_array['cycle_period'] = 'Day';
					break;
				case '2':
					$update_array['cycle_period'] = 'Week';
					break;
				case '3':
					$update_array['cycle_period'] = 'Month';
					break;
				case '6':
					$update_array['cycle_period'] = 'Year';
					break;
				default:
					$update_array['cycle_period'] = 'Month';
			}

			$update_array['next_payment_date'] = sanitize_text_field($sub_info->run_date);
			$update_array['billing_amount'] = (float) $sub_info->amount / 100;

			$subscription->set($update_array);
		} else {
			return esc_html__('There was an error connecting to Payfast. Please check your connectivity or API details and try again later.', 'pmpro-payfast');
		}
	}
} 
//end of class
