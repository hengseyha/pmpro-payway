<?php

/**
 * @author     HENG SEYHA - PayWay
 * @copyright  ToucanAsia
 * @license    http://www.opensource.org/licenses/lgpl-license.php LGPL
 * 
 * ABA PayWay Document
 * @link	https://www.payway.com.kh/developers/general
 */


// Bail if PMPro or the PayWay add on is not active
if (! defined('PMPRO_DIR') || ! defined('PMPRO_ABA_PAYWAY_DIR')) {
	error_log(__('Paid Memberships Pro and the PMPro PayWay Add On must be activated for the PMPro PayWay ITN handler to function.', 'pmpro-aba-payway'));
	exit;
}

require_once(PMPRO_ABA_PAYWAY_DIR . '/classes/class.pmprogateway_aba_payment.php');

define('PMPROPF_SOFTWARE_NAME', 'Paid Memberships Pro');
define('PMPROPF_SOFTWARE_VER', PMPRO_VERSION);
define('PMPROPF_MODULE_NAME', 'PayWay-PaidMembershipsPro');
define('PMPROPF_MODULE_VER', '1.0');

// Features
// - PHP
$pfFeatures = 'PHP ' . phpversion() . ';';

// Create user agrent
define('PMPROPF_USER_AGENT', PMPROPF_SOFTWARE_NAME . '/' . PMPROPF_SOFTWARE_VER . ' (' . trim($pfFeatures) . ') ' . PMPROPF_MODULE_NAME . '/' . PMPROPF_MODULE_VER);
// General Defines
define('PMPROPF_TIMEOUT', 15);
define('PMPROPF_EPSILON', 0.01);
// Messages
// Error
define('PMPROPF_ERR_AMOUNT_MISMATCH', __('Amount mismatch', 'pmpro-aba-payway'));
define('PMPROPF_ERR_BAD_ACCESS', __('Bad access of page', 'pmpro-aba-payway'));
define('PMPROPF_ERR_BAD_SOURCE_IP', __('Bad source IP address', 'pmpro-aba-payway'));
define('PMPROPF_ERR_CONNECT_FAILED', __('Failed to connect to aba_payment', 'pmpro-aba-payway'));
define('PMPROPF_ERR_INVALID_SIGNATURE', __('Security signature mismatch', 'pmpro-aba-payway'));
define('PMPROPF_ERR_MERCHANT_ID_MISMATCH', __('Merchant ID mismatch', 'pmpro-aba-payway'));
define('PMPROPF_ERR_NO_SESSION', __('No saved session found for ITN transaction', 'pmpro-aba-payway'));
define('PMPROPF_ERR_ORDER_ID_MISSING_URL', __('Order ID not present in URL', 'pmpro-aba-payway'));
define('PMPROPF_ERR_ORDER_ID_MISMATCH', __('Order ID mismatch', 'pmpro-aba-payway'));
define('PMPROPF_ERR_ORDER_INVALID', __('This order ID is invalid', 'pmpro-aba-payway'));
define('PMPROPF_ERR_ORDER_NUMBER_MISMATCH', __('Order Number mismatch', 'pmpro-aba-payway'));
define('PMPROPF_ERR_ORDER_PROCESSED', __('This order has already been processed', 'pmpro-aba-payway'));
define('PMPROPF_ERR_PDT_FAIL', __('PDT query failed', 'pmpro-aba-payway'));
define('PMPROPF_ERR_PDT_TOKEN_MISSING', __('PDT token not present in URL', 'pmpro-aba-payway'));
define('PMPROPF_ERR_SESSIONID_MISMATCH', __('Session ID mismatch', 'pmpro-aba-payway'));
define('PMPROPF_ERR_UNKNOWN', __('Unkown error occurred', 'pmpro-aba-payway'));
// General
define('PMPROPF_MSG_OK', __('Payment was successful', 'pmpro-aba-payway'));
define('PMPROPF_MSG_FAILED', __('Payment has failed', 'pmpro-aba-payway'));
define(
	'PMPROPF_MSG_PENDING',
	__('The payment is pending. Please note, you will receive another Instant', 'pmpro-aba-payway') .
		__(' Transaction Notification when the payment status changes to', 'pmpro-aba-payway') .
		__(' "Completed", or "Failed"', 'pmpro-aba-payway')
);

// some globals
global $wpdb, $gateway_environment, $logstr;
$logstr = '';   // will put debug info here and write to ipnlog.txt
// Variable Initialization
$pfError = false;
$pfErrMsg = '';
$pfDone = false;
$pfData = array();
$pfHost = (($gateway_environment == 'sandbox') ? 'sandbox' : 'www') . '.PayWay.co.za';
$pfOrderId = '';
$pfParamString = '';
$initial_payment_status = '';

pmpro_PayWay_itnlog(__('PayWay ITN call received', 'pmpro-aba-payway'));

// Notify PayWay that information has been received
if (! $pfError && ! $pfDone) {
	header('HTTP/1.0 200 OK');
	flush();
}

// Get data sent by PayWay
if (! $pfError && ! $pfDone) {
	pmpro_PayWay_itnlog(__('Get posted data', 'pmpro-aba-payway'));
	// Posted variables from ITN

	$aba = new PMProGateway_ABA_PayWay();

	$pfData = pmpro_pfGetData();

	// $data = json_decode(file_get_contents('php://input'), true);
	if ($pfData['tran_id']) {
		// $aba->checkTransaction($data['tran_id'])
		// $check_entry = $aba->aba_custom_check_entry_status($entry_id);
		// $aba->aba_process_subscription_entry_status($entry_id);
		$morder = new MemberOrder($pfData['tran_id']);
		$morder->getMembershipLevel();
		$morder->getUser();
		pmpro_PayWay_itnlog(__('PayWay Data: ', 'pmpro-aba-payway') . print_r($pfData, true));
		if ($pfData === false) {
			$pfError = true;
			$pfErrMsg = PMPROPF_ERR_BAD_ACCESS;
		}
	}
}

// may be use later 
// // Verify data received
// if (! $pfError) {
// 	pmpro_PayWay_itnlog(__('Verify data received', 'pmpro-aba-payway'));
// 	$pfValid = pmpro_pfValidData($pfHost, $pfParamString);
// 	if (! $pfValid) {
// 		$pfError = true;
// 		$pfErrMsg = PMPROPF_ERR_BAD_ACCESS;
// 	}
// }

// Check data against internal order - Temporarily disabling this as it doesn't work with levels with different amounts.
// if (! $pfError && ! $pfDone && ($pfData['status'] == 0 || $pfData['status'] == "0")) {
// 	// Only check initial orders.
// 	if ($aba->checkTransaction($pfData['tran_id']) && $aba->aba_custom_check_entry_status($pfData['tran_id'])) {
// 		if (! pmpro_pfAmountsEqual($pfData['total'], $morder->total)) {
// 			pmpro_PayWay_itnlog(__('Amount Returned: ', 'pmpro-aba-payway') . $pfData['total']);
// 			$pfError = true;
// 			$pfErrMsg = PMPROPF_ERR_AMOUNT_MISMATCH;
// 		}
// 	}
// }

// Check status and update order
if (! $pfError && ! $pfDone) {
	if ($pfData['status'] == 0 || $pfData['status'] == "0") {
		// $txn_id = $pfData['tran_id'];
		// custom_str1 is the date of the initial order in gmt

		// trial, get the order
		$morder = new MemberOrder($pfData['tran_id']);

		$morder->getMembershipLevel();
		$morder->getUser();

		$txn_id = $pfData['tran_id'];
		// update membership
		if (pmpro_itnChangeMembershipLevel($txn_id, $morder)) {
			pmpro_PayWay_itnlog('Checkout processed (' . $morder->code . ') success!');
		} else {
			pmpro_PayWay_itnlog(__("ERROR: Couldn't change level for order (", 'pmpro-aba-payway') . $morder->code . __(').', 'pmpro-aba-payway'));
		}
		pmpro_PayWay_ipnExit();
	}
}

// if ($pfData['payment_status'] == 'CANCELLED') {
// 	if (function_exists('pmpro_handle_subscription_cancellation_at_gateway')) {
// 		// Using PMPro v3.0+, so we have a helper function to handle subscription cancellations.
// 		pmpro_PayWay_itnlog(pmpro_handle_subscription_cancellation_at_gateway($pfData['m_payment_id'], 'aba_payment', $gateway_environment));
// 		pmpro_PayWay_ipnExit();
// 	}
// 	// PMPro version < 3.0. Use the legacy method of handling subscription cancellations.
// 	// find last order
// 	$last_subscr_order = new MemberOrder();
// 	if ($last_subscr_order->getLastMemberOrderBySubscriptionTransactionID($pfData['m_payment_id']) == false) {
// 		pmpro_PayWay_itnlog(__("ERROR: Couldn't find this order to cancel (subscription_transaction_id=", 'pmpro-aba-payway') . $pfData['m_payment_id'] . __(').', 'pmpro-aba-payway'));
// 		pmpro_PayWay_ipnExit();
// 	} else {
// 		// found order, let's cancel the membership
// 		$user = get_userdata($last_subscr_order->user_id);
// 		if (empty($user) || empty($user->ID)) {
// 			pmpro_PayWay_itnlog(__('ERROR: Could not cancel membership. No user attached to order #', 'pmpro-aba-payway') . $last_subscr_order->id . __(' with subscription transaction id = ', 'pmpro-aba-payway') . $last_subscr_order->subscription_transaction_id . __('.', 'pmpro-aba-payway'));
// 		} else {

// 			if ($last_subscr_order->status == 'cancelled') {
// 				pmpro_PayWay_itnlog(__("We've already processed this cancellation. Probably originated from WP/PMPro. (Order #", 'pmpro-aba-payway') . $last_subscr_order->id . __(', Subscription Transaction ID #', 'pmpro-aba-payway') . $pfData['m_payment_id'] . __(')', 'pmpro-aba-payway'));
// 			} elseif (! pmpro_hasMembershipLevel($last_subscr_order->membership_id, $user->ID)) {
// 				pmpro_PayWay_itnlog(__('This user has a different level than the one associated with this order. Their membership was probably changed by an admin or through an upgrade/downgrade. (Order #', 'pmpro-aba-payway') . $last_subscr_order->id . __(', Subscription Transaction ID #', 'pmpro-aba-payway') . $pfData['m_payment_id'] . __(')', 'pmpro-aba-payway'));
// 			} else {
// 				// if the initial payment failed, cancel with status error instead of cancelled
// 				if ($initial_payment_status === 'Failed') {
// 					pmpro_cancelMembershipLevel($last_subsc_order->membership_id, $last_subscr_order->user_id, 'error');
// 				} else {
// 					// pmpro_changeMembershipLevel( 0, $last_subscr_order->user_id, 'cancelled' );
// 					$last_subscr_order->updateStatus('cancelled');
// 					global $wpdb;
// 					$query = $wpdb->prepare(
// 						"UPDATE $wpdb->pmpro_memberships_orders 
// 						SET status = 'cancelled' 
// 						WHERE subscription_transaction_id = %d",
// 						$pfData['m_payment_id']
// 					);
// 					$wpdb->query($query);
// 					$sqlQuery = $wpdb->prepare(
// 						"UPDATE $wpdb->pmpro_memberships_users 
// 						SET status = 'cancelled' 
// 						WHERE user_id = %d
// 						AND membership_id = %d
// 						AND status = 'active'",
// 						$last_subscr_order->user_id,
// 						$last_subscr_order->membership_id
// 					);
// 					$wpdb->query($sqlQuery);
// 				}
// 				pmpro_PayWay_itnlog(__('Cancelled membership for user with id = ', 'pmpro-aba-payway') . $last_subscr_order->user_id . __('. Subscription transaction id = ', 'pmpro-aba-payway') . $pfData['m_payment_id'] . __('.', 'pmpro-aba-payway'));
// 				// send an email to the member
// 				$myemail = new PMProEmail();
// 				$myemail->sendCancelEmail($user);
// 				// send an email to the admin
// 				$myemail = new PMProEmail();
// 				$myemail->sendCancelAdminEmail($user, $last_subscr_order->membership_id);
// 			}
// 		}
// 		pmpro_PayWay_ipnExit();
// 	}
// }

pmpro_PayWay_itnlog(__('Check status and update order', 'pmpro-aba-payway'));
$transaction_id = $pfData['tran_id'];
$morder = new MemberOrder($pfData['tran_id']);
$morder->getMembershipLevel();
$morder->getUser();
pmpro_PayWay_itnlog(__('check token', 'pmpro-aba-payway'));
// if ( ! empty( $pfData['token'] ) )
// {
switch ($pfData['status']) {
	case '0' || 0:
		$morder = new MemberOrder($pfData['tran_id']);
		$morder->getMembershipLevel();
		$morder->getUser();
		// update membership
		if (pmpro_itnChangeMembershipLevel($transaction_id, $morder)) {
			pmpro_PayWay_itnlog('Checkout processed (' . $morder->code . ') success!');
		} else {
			pmpro_PayWay_itnlog(__("ERROR: Couldn't change level for order (", 'pmpro-aba-payway') . $morder->code . ').');
		}
		break;
	case '1' || 1:
		pmpro_PayWay_itnlog(__('ERROR: ITN from PayWay for order (', 'pmpro-aba-payway') . $morder->code . __(') Failed.', 'pmpro-aba-payway'));
		break;
	default:
		pmpro_PayWay_itnlog(__('ERROR: Unknown error for order (', 'pmpro-aba-payway') . $morder->code . ').');
		break;
}
// }
// If an error occurred
if ($pfError) {

	pmpro_PayWay_itnlog(__('Error occurred: ', 'pmpro-aba-payway') . $pfErrMsg);
}

pmpro_PayWay_ipnExit();

/*
	Add message to ipnlog string
*/
function pmpro_PayWay_itnlog($s)
{
	global $logstr;
	$logstr .= "\t" . $s . "\n";
}

/*
	Output ipnlog and exit;
*/
function pmpro_PayWay_ipnExit()
{
	global $logstr;
	// for log
	if ($logstr) {
		$logstr = __('Logged On: ', 'pmpro-aba-payway') . date('m/d/Y H:i:s') . "\n" . $logstr . "\n-------------\n";
		echo esc_html($logstr);

		//Log to file or email, 
		if (get_option('pmpro_aba_payway_debug') || (defined('PMPROPF_DEBUG'))) {
			// Let's create the file and add a random suffix to it, to tighten up security.
			$file_suffix = substr(md5(get_option('pmpro_aba_payway_merchant_id', true)), 0, 10);
			$filename = 'PayWay_itn_' . $file_suffix . '.txt';
			$logfile = apply_filters('pmpro_aba_payway_itn_logfile', PMPRO_ABA_PAYWAY_DIR . '/logs/' . $filename);

			// Make the /logs directory if it doesn't exist
			if (! file_exists(PMPRO_ABA_PAYWAY_DIR . '/logs')) {
				mkdir(PMPRO_ABA_PAYWAY_DIR . '/logs', 0700);
			}

			// If the log file doesn't exist let's create it.
			if (! file_exists($logfile)) {
				// create a blank text file
				file_put_contents($logfile, '');
			}

			$loghandle = fopen($logfile, "a+");
			fwrite($loghandle, $logstr);
			fclose($loghandle);
		} elseif (defined('PMPROPF_DEBUG')) {
			// Send via email.
			$log_email =  get_option('admin_email');
			wp_mail($log_email, get_option('blogname') . ' PayWay Webhook Log', nl2br(esc_html($logstr)));
		}
	}
	exit;
}

/*
	Change the membership level. We also update the membership order to include filtered valus.
*/
function pmpro_itnChangeMembershipLevel($txn_id, &$morder)
{
	global $wpdb;
	// filter for level
	$morder->membership_level = apply_filters('pmpro_PayWay_itnhandler_level', $morder->membership_level, $morder->user_id);
	// fix expiration date
	if (! empty($morder->membership_level->expiration_number)) {
		$enddate = "'" . date('Y-m-d', strtotime('+ ' . $morder->membership_level->expiration_number . ' ' . $morder->membership_level->expiration_period)) . "'";
	} else {
		$enddate = 'NULL';
	}
	// get discount code     (NOTE: but discount_code isn't set here. How to handle discount codes for PayPal Standard?)
	$morder->getDiscountCode();
	if (! empty($morder->discount_code)) {
		// update membership level
		$morder->getMembershipLevel(true);
		$discount_code_id = $morder->discount_code->id;
	} else {
		$discount_code_id = '';
	}
	// set the start date to current_time('timestamp') but allow filters
	$startdate = apply_filters('pmpro_checkout_start_date', "'" . current_time('mysql') . "'", $morder->user_id, $morder->membership_level);
	// custom level to change user to
	$custom_level = array(
		'user_id' => $morder->user_id,
		'membership_id' => $morder->membership_level->id,
		'code_id' => $discount_code_id,
		'initial_payment' => $morder->membership_level->initial_payment,
		'billing_amount' => $morder->membership_level->billing_amount,
		'cycle_number' => $morder->membership_level->cycle_number,
		'cycle_period' => $morder->membership_level->cycle_period,
		'billing_limit' => $morder->membership_level->billing_limit,
		'trial_amount' => $morder->membership_level->trial_amount,
		'trial_limit' => $morder->membership_level->trial_limit,
		'startdate' => $startdate,
		'enddate' => $enddate,
	);
	global $pmpro_error;
	if (! empty($pmpro_error)) {
		echo $pmpro_error;
		pmpro_PayWay_itnlog($pmpro_error);
	}
	// change level and continue "checkout"
	if (pmpro_changeMembershipLevel($custom_level, $morder->user_id) !== false) {
		// update order status and transaction ids
		$morder->status = 'success';
		$morder->payment_transaction_id = $txn_id;
		$morder->subscription_transaction_id = sanitize_text_field($_POST['tran_id']);
		$morder->saveOrder();
		// add discount code use
		if (! empty($discount_code) && ! empty($use_discount_code)) {
			$wpdb->query(
				$wpdb->prepare(
					"INSERT INTO $wpdb->pmpro_discount_codes_uses 
					(code_id, user_id, order_id, timestamp) 
					VALUES( %d, %d, %d, %s )",
					$discount_code_id,
					$morder->user_id,
					$morder->id,
					current_time('mysql')
				)
			);
		}
		// save first and last name fields
		if (! empty($_POST['first_name'])) {
			$old_firstname = get_user_meta($morder->user_id, 'first_name', true);
			if (! empty($old_firstname)) {
				update_user_meta($morder->user_id, 'first_name', sanitize_text_field($_POST['first_name']));
			}
		}
		if (! empty($_POST['last_name'])) {
			$old_lastname = get_user_meta($morder->user_id, 'last_name', true);
			if (! empty($old_lastname)) {
				update_user_meta($morder->user_id, 'last_name', sanitize_text_field($_POST['last_name']));
			}
		}
		// hook
		do_action('pmpro_after_checkout', $morder->user_id, $morder);
		// setup some values for the emails
		if (! empty($morder)) {
			$invoice = new MemberOrder($morder->id);
		} else {
			$invoice = null;
		}
		$user = get_userdata($morder->user_id);
		$user->membership_level = $morder->membership_level;        // make sure they have the right level info
		// send email to member
		$pmproemail = new PMProEmail();
		$pmproemail->sendCheckoutEmail($user, $invoice);
		// send email to admin
		$pmproemail = new PMProEmail();
		$pmproemail->sendCheckoutAdminEmail($user, $invoice);
		return true;
	} else {
		return false;
	}
}

function pmpro_ipnSaveOrder($txn_id, $last_order)
{
	global $wpdb;
	// check that txn_id has not been previously processed
	$old_txn = $wpdb->get_var(
		$wpdb->prepare(
			"SELECT payment_transaction_id 
			FROM $wpdb->pmpro_membership_orders 
			WHERE payment_transaction_id = %d 
			LIMIT 1",
			$txn_id
		)
	);
	if (empty($old_txn)) {
		// hook for successful subscription payments
		// do_action("pmpro_subscription_payment_completed");
		// save order
		$morder = new MemberOrder();
		$morder->user_id = $last_order->user_id;
		$morder->membership_id = $last_order->membership_id;
		$morder->payment_transaction_id = $txn_id;
		$morder->subscription_transaction_id = $last_order->subscription_transaction_id;
		$morder->gateway = $last_order->gateway;
		$morder->gateway_environment = $last_order->gateway_environment;
		$morder->paypal_token = $last_order->paypal_token;
		// Payment Status
		$morder->status = 'success'; // We have confirmed that and thats the reason we are here.
		// Payment Type.
		$morder->payment_type = $last_order->payment_type;
		// set amount based on which PayPal type
		if ($last_order->gateway == 'aba_payment') {
			$morder->InitialPayment = sanitize_text_field($_POST['amount_gross']);    // not the initial payment, but the class is expecting that
			$morder->PaymentAmount = sanitize_text_field($_POST['amount_gross']);
		}
		$morder->FirstName = sanitize_text_field($_POST['name_first']);
		$morder->LastName = sanitize_text_field($_POST['name_last']);
		$morder->Email = sanitize_email($_POST['email_address']);
		// get address info if appropriate
		if ($last_order->gateway == 'aba_payment') {
			$morder->Address1 = get_user_meta($last_order->user_id, 'pmpro_baddress1', true);
			$morder->City = get_user_meta($last_order->user_id, 'pmpro_bcity', true);
			$morder->State = get_user_meta($last_order->user_id, 'pmpro_bstate', true);
			$morder->CountryCode = 'ZA';
			$morder->Zip = get_user_meta($last_order->user_id, 'pmpro_bzip', true);
			$morder->PhoneNumber = get_user_meta($last_order->user_id, 'pmpro_bphone', true);

			if (! isset($morder->billing)) {
				$morder->billing = new stdClass();
			}

			$morder->billing->name = sanitize_text_field($_POST['name_first']) . ' ' . sanitize_text_field($_POST['name_last']);
			$morder->billing->street = get_user_meta($last_order->user_id, 'pmpro_baddress1', true);
			$morder->billing->city = get_user_meta($last_order->user_id, 'pmpro_bcity', true);
			$morder->billing->state = get_user_meta($last_order->user_id, 'pmpro_bstate', true);
			$morder->billing->zip = get_user_meta($last_order->user_id, 'pmpro_bzip', true);
			$morder->billing->country = get_user_meta($last_order->user_id, 'pmpro_bcountry', true);
			$morder->billing->phone = get_user_meta($last_order->user_id, 'pmpro_bphone', true);
			// get CC info that is on file
			$morder->cardtype = get_user_meta($last_order->user_id, 'pmpro_CardType', true);
			$morder->accountnumber = hideCardNumber(get_user_meta($last_order->user_id, 'pmpro_AccountNumber', true), false);
			$morder->expirationmonth = get_user_meta($last_order->user_id, 'pmpro_ExpirationMonth', true);
			$morder->expirationyear = get_user_meta($last_order->user_id, 'pmpro_ExpirationYear', true);
			$morder->ExpirationDate = $morder->expirationmonth . $morder->expirationyear;
			$morder->ExpirationDate_YdashM = $morder->expirationyear . '-' . $morder->expirationmonth;
		}
		// figure out timestamp or default to none (today)
		// if(!empty($_POST['payment_date']))
		// $morder->timestamp = strtotime($_POST['payment_date']);
		// save
		$morder->saveOrder();
		$morder->getMemberOrderByID($morder->id);
		// email the user their invoice
		$pmproemail = new PMProEmail();
		$pmproemail->sendInvoiceEmail(get_userdata($last_order->user_id), $morder);
		do_action('pmpro_subscription_payment_completed', $morder);

		pmpro_PayWay_itnlog(__('New order (', 'pmpro-aba-payway') . $morder->code . __(') created.', 'pmpro-aba-payway'));
		return true;
	} else {
		pmpro_PayWay_itnlog(__('Duplicate Transaction ID: ', 'pmpro-aba-payway') . $txn_id);
		return true;
	}
}

/**
 * pfGetData
 * documentation reference - https://developers.PayWay.co.za/documentation/#notify-page-itn
 * @uses pmpro_getParam - https://github.com/strangerstudios/paid-memberships-pro/blob/dev/includes/functions.php#L2260
 *
 * @author Jonathan Smit (PayWay.co.za)
 * @author Stranger Studios 2019 (paidmembershipspro.com)
 */
function pmpro_pfGetData()
{

	$pfData = array();
	$data = json_decode(file_get_contents('php://input'), true);
	// Ensure that all posted data is used at the ITN stage
	$postedData = $data;
	// Sanitize all posted data
	foreach ($postedData as $key => $value) {
		if ($key != 'email_address') {
			$pfData[$key] = $value;
		} else {
			$pfData[$key] = pmpro_getParam($key, 'POST', '', 'sanitize_email');
		}
	}

	// Return "false" if no data was received
	if (sizeof($pfData) == 0) {
		return (false);
	} else {
		return ($pfData);
	}
}

/**
 * pfValidSignature
 *
 * @author Jonathan Smit (PayWay.co.za)
 */
function pmpro_pfValidSignature($pfData = null, &$pfParamString = null, $passPhrase = null)
{
	// Dump the submitted variables and calculate security signature
	foreach ($pfData as $key => $val) {
		if ($key != 'signature') {
			$pfParamString .= $key . '=' . urlencode($val) . '&';
		} else {
			break;
		}
	}
	// Remove the last '&' from the parameter string
	$pfParamString = substr($pfParamString, 0, -1);

	if (is_null($passPhrase)) {
		$tempParamString = $pfParamString;
	} else {
		$tempParamString = $pfParamString . '&passphrase=' . urlencode(trim($passPhrase));
	}

	$signature = md5($tempParamString);
	$result = ($pfData['signature'] == $signature);
	pmpro_PayWay_itnlog(__('Signature Sent: ', 'pmpro-aba-payway') . $signature);
	pmpro_PayWay_itnlog(__('Signature = ', 'pmpro-aba-payway') . ($result ? __('valid', 'pmpro-aba-payway') : __('invalid', 'pmpro-aba-payway')));
	return ($result);
}

/**
 * pfValidData
 *
 * @author Jonathan Smit (PayWay.co.za)
 * @param $pfHost String Hostname to use
 * @param $pfParamString String Parameter string to send
 * @param $proxy String Address of proxy to use or NULL if no proxy
 */


function pmpro_pfValidData($pfHost = 'www.PayWay.co.za', $pfParamString = '', $pfProxy = null)
{
	pmpro_PayWay_itnlog(__('Host = ', 'pmpro-aba-payway') . $pfHost);
	pmpro_PayWay_itnlog(__('Params = ', 'pmpro-aba-payway') . $pfParamString);
	// Variable initialization
	$url = 'https://' . $pfHost . '/eng/query/validate';

	$response = wp_remote_post(
		$url,
		array(
			'method' => 'POST',
			'sslverify' => false,
			'body' => $pfParamString,
			'timeout' => PMPROPF_TIMEOUT
		)
	);


	if (is_wp_error($response)) {
		$error_message = $response->get_error_message();
		pmpro_PayWay_itnlog('Error validating data: ' . $error_message);
		die('Error validating data: ' . $error_message);
	}

	$body = wp_remote_retrieve_body($response);

	pmpro_PayWay_itnlog($body);

	if ($body === 'VALID') {
		return (true);
	} else {
		return (false);
	}
}

/**
 * pfValidIP
 *
 * @author Jonathan Smit (PayWay.co.za)
 * @param $sourceIP String Source IP address
 */
function pmpro_pfValidIP($sourceIP)
{
	// Variable initialization
	$validHosts = array(
		'www.PayWay.co.za',
		'sandbox.PayWay.co.za',
		'w1w.PayWay.co.za',
		'w2w.PayWay.co.za',
	);
	$validIps = array();
	foreach ($validHosts as $pfHostname) {
		$ips = gethostbynamel($pfHostname);
		if ($ips !== false) {
			$validIps = array_merge($validIps, $ips);
		}
	}
	// Remove duplicates
	$validIps = array_unique($validIps);
	pmpro_PayWay_itnlog("Valid IPs:\n" . print_r($validIps, true));
	if (in_array($sourceIP, $validIps)) {
		return (true);
	} else {
		return (false);
	}
}

/**
 * pfAmountsEqual
 *
 * Checks to see whether the given amounts are equal using a proper floating
 * point comparison with an Epsilon which ensures that insignificant decimal
 * places are ignored in the comparison.
 *
 * eg. 100.00 is equal to 100.0001
 *
 * @author Jonathan Smit (PayWay.co.za)
 * @param $amount1 Float 1st amount for comparison
 * @param $amount2 Float 2nd amount for comparison
 */
function pmpro_pfAmountsEqual($amount1, $amount2)
{
	if (abs(floatval($amount1) - floatval($amount2)) > PMPROPF_EPSILON) {
		return (false);
	} else {
		return (true);
	}
}
