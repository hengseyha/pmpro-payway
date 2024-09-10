<?php
get_header('single');
?>
<section class="section-padding header-padding">
    <div class="content section-adding-x col-12">
        <div class="fusion-row">
            <div class="post-content col-12">
                <div class="w-778 m-auto">
                    <div id="aba_main_modal" class="aba-modal">
                        <div class="aba-modal-content">
                            <!-- Include PHP class -->
                            <?php
                            require_once(PMPRO_ABA_PAYWAY_DIR . '/classes/class.pmprogateway_aba_payment.php');
                            $aba = new PMProGateway_ABA_PayWay();

                            $aba_credentials = $aba->get_aba_credentails();
                            $transactionId = '';
                            $amount = '';
                            $firstName = '';
                            $lastName = '';
                            $phone = '';
                            $email = '';
                            $req_time = '';
                            $merchant_id = '';
                            $payment_option = '';

                            #abapay, cards, abapay_deeplink, bakong

                            $hash = '';
                            $form_id = '';
                            if (isset($_GET['first_name'])) {
                                $firstName = $_GET['first_name'];
                            }
                            if (isset($_GET['last_name'])) {
                                $lastName = $_GET['last_name'];
                            }
                            if (isset($_GET['phone'])) {
                                $phone = $_GET['phone'];
                            }
                            if (isset($_GET['req_time'])) {
                                $req_time = $_GET['req_time'];
                            }
                            if (isset($_GET['email'])) {
                                $email = $_GET['email'];
                            }
                            if (isset($_GET['tran_id'])) {
                                $transactionId = $_GET['tran_id'];
                            }
                            if (isset($_GET['amount'])) {
                                $amount = $_GET['amount'];
                            }
                            if (isset($_GET['payment_option'])) {
                                $payment_option = $_GET['payment_option'];
                            }
                            if (isset($_GET['return_param'])) {
                                $return_param = $_GET['return_param'];
                            }
                            if (isset($_GET['return_url'])) {
                                $return_url = $_GET['return_url'];
                            }
                            if (isset($_GET['continue_success_url'])) {
                                $success_url = $_GET['continue_success_url'];
                            }
                            // $return_url = $aba_credentials['return_url'];
                            // $success_url = $aba_credentials['success_url'];
                            // $data['continue_success_url']    = pmpro_url('confirmation', '?level=' . $order->membership_level->id);
                            // $data['cancel_url']    = pmpro_url('levels');
                            // $data['return_url']    = admin_url('admin-ajax.php') . '?action=pmpro_aba_payway_itn_handler';
                            // $data['return_param'] = "{'total': " . $order->total . "}";
                            if ($aba_credentials) {
                                $merchant_id = $aba_credentials['merchant_id'];
                                // $return_url = $aba_credentials['return_url'];
                                // $success_url = $aba_credentials['success_url'];
                                $prepear_hash = $req_time . $merchant_id . $transactionId . $amount . $firstName . $lastName . $email . $phone . $payment_option . $return_url . $success_url;
                                // var_dump($req_time, $merchant_id, $transactionId, $amount, $firstName, $lastName, $email, $phone, $payment_option, $return_url, $success_url, $aba_credentials);
                                $hash = $aba->getHash($prepear_hash, $aba_credentials['aba_api_key']);
                                if ($hash) {
                            ?>
                                    <div class="my-5">
                                        <h3>You are subscribing to <?php bloginfo(); ?> via ABA PayWay</h3>
                                        <hr>
                                        <div class="row">
                                            <div class="aba-info text-start col-md-6 col-sm-12">
                                                <h4>Confirm Your Subscription</h4>
                                                <div class="first-name">
                                                    First Name: <strong><?php echo $firstName; ?></strong>
                                                </div>
                                                <div class="last-name">
                                                    Last Name: <strong><?php echo $lastName; ?></strong>
                                                </div>
                                                <div class="last-name">
                                                    Email: <strong><?php echo $email; ?></strong>
                                                </div>
                                                <div class="last-name">
                                                    Phone: <strong><?php echo $phone; ?></strong>
                                                </div>
                                                <div class="last-name">
                                                    Amount: <strong><?php echo $amount . '.00'; ?> USD</strong>
                                                </div>
                                            </div>
                                            <div class="col-md-6 col-sm-12 payment-option-info">
                                                <h5>Selected Payment</h5>
                                                <div class="confirm-button mt-3">
                                                    <?php
                                                    if ($payment_option == 'bakong') {
                                                    ?>
                                                        <img class="cardType" src="<?php echo plugin_dir_url(__FILE__) . '../assetes/logos/kh-qrcode-v2.png'; ?>">
                                                    <?php
                                                    } elseif ($payment_option == 'abapay') {
                                                    ?>
                                                        <img class="cardType" src="<?php echo plugin_dir_url(__FILE__) . '../assetes/logos/abapay-v2.png'; ?>">
                                                    <?php
                                                    } else {
                                                    ?>
                                                        <img class="cardType" src="<?php echo plugin_dir_url(__FILE__) . '../assetes/logos/4Cards_2x.png'; ?>">
                                                    <?php
                                                    }
                                                    ?>
                                                </div>
                                                <div class="d-flex">
                                                    <input type="button" value="Subscribe Now" name="subscribe" id="aba_checkout_button" class="btn primary-button primary-bg-orrange">
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <form method="POST" target="aba_webservice" action="<?php echo $aba_credentials['aba_url']; ?>" id="aba_merchant_request">
                                        <input type="hidden" name="hash" value="<?php echo $hash; ?>" id="hash" />
                                        <input type="hidden" name="tran_id" value="<?php echo $transactionId; ?>" id="tran_id" />
                                        <input type="hidden" name="amount" value="<?php echo $amount; ?>" id="amount" />
                                        <input type="hidden" name="firstname" value="<?php echo $firstName; ?>" />
                                        <input type="hidden" name="lastname" value="<?php echo $lastName; ?>" />
                                        <input type="hidden" name="phone" value="<?php echo $phone; ?>" />
                                        <input type="hidden" name="email" value="<?php echo $email; ?>" />
                                        <input type="hidden" name="req_time" value="<?php echo $req_time; ?>" />
                                        <input type="hidden" name="merchant_id" value="<?php echo $merchant_id; ?>" />
                                        <input type="hidden" name="return_url" value="<?php echo $return_url; ?>" />
                                        <input type="hidden" name="continue_success_url" value="<?php echo $success_url; ?>" />
                                        <input type="hidden" name="return_param" value="<?php echo $return_param; ?>" />
                                        <input type="hidden" name="payment_option" value="<?php echo $payment_option; ?>" />
                                    </form>
                            <?php
                                }
                            } else {
                                die;
                            }
                            ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
<style>
    #aba_checkout_button {
        margin-top: 2em;
        color: #fff;
        cursor: pointer;
        background: #0d4e8e;
        padding: 12px 42px;
    }

    @media screen and (max-width:768px) {
        .payment-option-info {
            margin-top: 32px;
        }
    }
</style>
<?php

add_action('wp_footer', function () {
?>
    <script src=""></script>
    <script type="text/javascript">
        jQuery(document).ready(function($) {
            $("#aba_checkout_button").on('click', function() {
                AbaPayway.checkout();
            })
        });
    </script>
<?php
});

get_footer();
