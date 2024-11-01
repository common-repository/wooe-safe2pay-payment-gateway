<?php
/* Safe2Pay Payment Gateway Class */

class WC_Gateway_Safe2Pay extends WC_Payment_Gateway {

    function __construct() {

        $this->id = "safe2pay";
        $this->method_title = __("Safe2Pay", 'safe2pay');
        $this->method_description = __("Safe2Pay Payment Gateway Plug-in for WooCommerce", 'safe2pay');
        $this->title = __("Safe2Pay", 'safe2pay');
        $this->has_fields = true;
        $this->supports = array(
            'default_credit_card_form',
            'products',
            'subscriptions',
            'subscription_cancellation'
        );

        $this->init_form_fields();
        $this->init_settings();

        foreach ($this->settings as $setting_key => $value) {
            $this->$setting_key = $value;
        }

        if ($this->showicon === "yes") {
            // $this->icon = "https://safe2pay.com.au/wp-content/uploads/2017/04/Safe2pay_Logo-.jpg";
        }
        add_filter('woocommerce_account_menu_items', array($this, 'safe2pay_account_menu_items'), 40);
        add_action('woocommerce_account_change-payment-method_endpoint', array($this, 'change_payment_method_endpoint_content'));

        add_action('woocommerce_subscription_cancelled_' . $this->id, array($this, 'cancelled_subscription'));
        add_action('admin_notices', array($this, 'do_ssl_check'));

        if (is_admin()) {
            add_action('woocommerce_update_options_payment_gateways_' . $this->id, array(
                $this,
                'process_admin_options'
            ));
        }
    }

    public function init_form_fields() {
        $this->form_fields = array(
            'enabled' => array(
                'title' => __('Enable / Disable', 'safe2pay'),
                'label' => __('Enable this payment gateway', 'safe2pay'),
                'type' => 'checkbox',
                'default' => 'no'
            ),
            'title' => array(
                'title' => __('Title', 'safe2pay'),
                'type' => 'text',
                'desc_tip' => __('Payment title the customer will see during the checkout process.', 'safe2pay'),
                'default' => __('Credit card', 'safe2pay')
            ),
            'description' => array(
                'title' => __('Description', 'safe2pay'),
                'type' => 'textarea',
                'desc_tip' => __('Payment description the customer will see during the checkout process.', 'safe2pay'),
                'default' => __('Pay securely using your credit card.', 'safe2pay'),
                'css' => 'max-width:350px;'
            ),
            'username' => array(
                'title' => __('Safe2Pay User ID', 'safe2pay'),
                'type' => 'text',
                'desc_tip' => __('This is the API Login provided by Safe2Pay when you signed up for an account.', 'safe2pay')
            ),
            'token' => array(
                'title' => __('Safe2Pay Token', 'safe2pay'),
                'type' => 'password',
                'desc_tip' => __('This is the Token provided by Safe2Pay when you signed up for an account.', 'safe2pay')
            ),
            'dishonour_fee' => array(
                'title' => __('Retry Fee for Faied Recurring Payments', 'safe2pay'),
                'type' => 'text',
                'desc_tip' => __('This is the amount a customer will be charged if their recurring payment fails', 'safe2pay')
            ),
            'retry_interval' => array(
                'title' => __('Days between retries', 'safe2pay'),
                'type' => 'text',
                'desc_tip' => __('Days between retries if a scheduled payment fails', 'safe2pay')
            ),
            'showicon' => array(
                'title' => __('Show Icon', 'safe2pay'),
                'label' => __("Show Safe2Pay's icon on checkout page", "safe2pay"),
                'type' => 'checkbox',
                'description' => __("Show Safe2Pay's icon on checkout page.", 'safe2pay'),
                'default' => 'no'
            ),
            'testmode' => array(
                'title' => __('Safe2Pay Test Mode', 'safe2pay'),
                'label' => __('Enable Test Mode', 'safe2pay'),
                'type' => 'checkbox',
                'description' => __('Place the payment gateway in test mode.', 'safe2pay'),
                'default' => 'no'
            ),
            'logging' => array(
                'title' => __('Logging', 'safe2pay'),
                'label' => __('Enable Logging', 'safe2pay'),
                'type' => 'checkbox',
                'description' => __('Send transaction details to Safe2Pay for debugging purposes. Keep this option off unless instructed otherwise by Safe2Pay', 'safe2pay'),
                'default' => 'no'
            ),
        );
    }

    function safe2pay_account_menu_items($menu_links) {
        return safe2pay_arr_push_pos('change-payment-method', __('Change Payment Method', 'safe2pay'), 6, $menu_links);
    }

    function get_authorization() {
        $username = $this->username;
        $token = $this->token;
        return 'Basic ' . base64_encode($username . ':' . $token);
    }

    function change_payment_method_endpoint_content() {
        $current_user = wp_get_current_user();
        if ($current_user == 0) {
            echo "User needs to create an account";
            return;
        }

        if ($this->testmode == "yes") {
            $url = 'https://gateway.pmnts-sandbox.io/v1.0/customers/customer-' . $current_user->ID;
        } else {
            $url = 'https://gateway.pmnts.io/v1.0/customers/customer-' . $current_user->ID;
        }

        $args = array(
            'headers' => array(
                'Authorization' => $this->get_authorization()
            )
        );
        $response = wp_remote_get($url, $args);
        $this->mail_debug_log($url, "", $response);
        $httpcode = wp_remote_retrieve_response_code($response);
        if ($httpcode == 200) {
            $body = wp_remote_retrieve_body($response);
            $body = json_decode($body);
            $customer_id = $body->response->id;
            $customer_name = $body->response->first_name . " " . $body->response->last_name;
            $current_card = $body->response->card_number;
            if (safe2pay_filter_input_fix(INPUT_SERVER, 'REQUEST_METHOD') === 'POST') {
                if (safe2pay_filter_input_fix(INPUT_POST, 'preauth-consent') === 'Yes') {
                    $cardNumber = str_replace(array(' ', '-'), '', safe2pay_filter_input_fix(INPUT_POST, 'safe2pay-card-number'));
                    $cardExpiry = str_replace(array('/', ' '), '', safe2pay_filter_input_fix(INPUT_POST, 'safe2pay-card-expiry'));
                    $cardExpiry = substr($cardExpiry, 0, 2) . "/20" . substr($cardExpiry, -2);
                    $cardCvv = safe2pay_filter_input_fix(INPUT_POST, 'safe2pay-card-cvc');
                    $ip_addr = safe2pay_get_remote_ip();
                    global $WOOCS;
                    if ($WOOCS) {
                        $currencyCode = $WOOCS->current_currency;
                    } else {
                        $currencyCode = "AUD";
                    }
                    $purchase_data = array(
                        'card_holder' => $customer_name,
                        'card_number' => $cardNumber,
                        'card_expiry' => $cardExpiry,
                        'cvv' => $cardCvv,
                        'amount' => 100,
                        'reference' => "cc-check-" . time(),
                        'customer_ip' => $ip_addr,
                        'currency' => $currencyCode,
                        'capture' => 'false',
                        'final' => 'false',
                    );
                    if ($this->testmode == "yes") {
                        $purchase_data['test'] = 'true';
                        $url = 'https://gateway.sandbox.fatzebra.com.au/v1.0/purchases';
                    } else {
                        $url = 'https://gateway.fatzebra.com.au/v1.0/purchases';
                    }

                    $args = array(
                        'body' => json_encode($purchase_data),
                        'timeout' => '45',
                        'redirection' => '5',
                        'httpversion' => '1.0',
                        'blocking' => true,
                        'headers' => array(
                            'Authorization' => $this->get_authorization()
                        ),
                        'cookies' => array()
                    );
                    $response = wp_remote_post($url, $args);
                    $this->mail_debug_log($url, $purchase_data, $response);
                    $body = json_decode(wp_remote_retrieve_body($response));
                    if (($body->successful) && ($body->response->successful)) {
                        if ($this->testmode == "yes") {
                            $url = 'https://gateway.pmnts-sandbox.io/v1.0/customers/' . $customer_id;
                        } else {
                            $url = 'https://gateway.pmnts.io/v1.0/customers/' . $customer_id;
                        }
                        $update_data = array(
                            card => array(
                                'card_holder' => $customer_name,
                                'card_number' => $cardNumber,
                                'expiry_date' => $cardExpiry,
                                'cvv' => $cardCvv
                            )
                        );
                        $args = array(
                            'method' => 'PUT',
                            'body' => json_encode($update_data),
                            'timeout' => '45',
                            'redirection' => '5',
                            'httpversion' => '1.0',
                            'blocking' => true,
                            'headers' => array(
                                'Authorization' => $this->get_authorization()
                            ),
                            'cookies' => array()
                        );
                        $response = wp_remote_request($url, $args);
                        $this->mail_debug_log($url, $update_data, $response);
                        $httpcode = wp_remote_retrieve_response_code($response);
                        if ($httpcode == 200) {
                            ?><div style="color: green;">Card details successfully updated</div>
                            <?php
                        } else {
                            ?><div style="color: red;">Could not update card details. Response is <?= $httpcode ?></div>
                            <?php
                        }
                    } else {
                        ?> <div style="color: red;">Validating your card failed. Please try again.</div><?php
                    }
                }
            } else {
                ?>
                <div class="safe2pay-current-card-no">Current card on file: <span style="font-family: monospace;"><?= $current_card ?></span></div><?php
            }
        } else {
            ?><div style="color: red;">Could not find customer. Response is <?= $httpcode ?></div>
                <?php
            }
            ?>

        <form name="checkout" method="post" class="checkout woocommerce-checkout" enctype="multipart/form-data">
            <?php
            $this->payment_fields();
            ?>
            <div>
                <div><input type="checkbox" name="preauth-consent" id="preauth-consent" value="Yes" required/><label for="preauth-consent">&nbsp;I understand a pre-authorization in the amount of $1 is required to validate my card</label></div>
                <div><button type="submit" class="button alt" name="safe2pay_update_card_details" id="update_cc" value="Update" data-value="Update">Update</button></div>
            </div>
        </form> <?php
    }

    function mail_debug_log($url, $sent, $received) {
        if ($this->logging == "yes") {
            $t = microtime(true);
            $micro = sprintf("%06d", ($t - floor($t)) * 1000000);
            if (isset($sent)) {
                if (isset($sent["card_number"])) {
                    $sent["card_number"] = "xxxx";
                } else if (isset($sent["card"]["card_number"])) {
                    $sent["card"]["card_number"] = "xxxx";
                }
                $sent_data = json_encode($sent, JSON_PRETTY_PRINT);
            } else {
                $sent_data = print_r($sent, 1);
            }
            $callstack = json_encode(debug_backtrace(), JSON_PRETTY_PRINT);
            $s = (new DateTime(date('Y-m-d H:i:s.' . $micro, $t)))->format("Y-m-d H:i:s.u") .
                    "\nFile: " .
                    __FILE__ .
                    "\nURL: $url\nSent:\n$sent_data\nReceived:\n" . print_r($received, 1) .
                    "\nCall Stack:\n" .
                    $callstack;
            wp_mail('log@gateway.safe2pay.com.au', 'WooCommerce Lecagy Plugin - DEBUG LOG', $s);
        }
    }

    private function myDate($format) {
        $tz = 'Australia/Sydney';
        $dt = new DateTime("now", new DateTimeZone($tz)); //first argument "must" be a string
        return $dt->format($format);
    }

    public function process_payment($order_id) {
        global $woocommerce;
        global $WOOCS;

        $order = new WC_Order($order_id);

        $dishonour_fee = round(isset($this->dishonour_fee) ? $this->dishonour_fee * 100 : 0, 2);
        $retry_interval = isset($this->retry_interval) ? $this->retry_interval : 3;
        if ($WOOCS) {
            $currencyCode = $WOOCS->current_currency;
        } else {
            $currencyCode = "AUD";
        }

        $cardNumber = str_replace(array(' ', '-'), '', safe2pay_filter_input_fix(INPUT_POST, 'safe2pay-card-number'));
        $cardExpiry = str_replace(array('/', ' '), '', safe2pay_filter_input_fix(INPUT_POST, 'safe2pay-card-expiry'));
        $cardExpiry = substr($cardExpiry, 0, 2) . "/20" . substr($cardExpiry, -2);
        $cardCvv = safe2pay_filter_input_fix(INPUT_POST, 'safe2pay-card-cvc');
        $ip_addr = safe2pay_get_remote_ip();

        $firstname = $order->get_billing_first_name();
        $lastname = $order->get_billing_last_name();
        $email = $order->get_billing_email();
        $address1 = $order->get_billing_address_1();
        $city = $order->get_billing_city();
        $state = $order->get_billing_state();
        $postcode = $order->get_billing_postcode();
        $country = $order->get_billing_country();

        $invoiceId = str_replace("#", "", $order->get_order_number());

        if (class_exists("WC_Subscriptions") && (WC_Subscriptions_Order::order_contains_subscription($order_id))) {
            $current_user = wp_get_current_user();
            if ($current_user == 0) {
                wc_add_notice("User needs to create an account", 'error');
                return;
            }
            $price_per_period = WC_Subscriptions_Order::get_recurring_total($order) * 100;
            $purchase_data = array(
                'card_holder' => $firstname . " " . $lastname,
                'card_number' => $cardNumber,
                'card_expiry' => $cardExpiry,
                'cvv' => $cardCvv,
                'amount' => $price_per_period,
                'reference' => $invoiceId . "-INITIAL-" . hash("crc32b", time()),
                'customer_ip' => $ip_addr,
                'currency' => $currencyCode
            );
            if ($this->testmode == "yes") {
                $purchase_data['test'] = 'true';
                $url = 'https://gateway.sandbox.fatzebra.com.au/v1.0/purchases';
            } else {
                $url = 'https://gateway.fatzebra.com.au/v1.0/purchases';
            }

            $args = array(
                'body' => json_encode($purchase_data),
                'timeout' => '45',
                'redirection' => '5',
                'httpversion' => '1.0',
                'blocking' => true,
                'headers' => array(
                    'Authorization' => $this->get_authorization()
                ),
                'cookies' => array()
            );
            $response = wp_remote_post($url, $args);
            $this->mail_debug_log($url, $purchase_data, $response);
            $body = json_decode(wp_remote_retrieve_body($response));
            if (($body->successful) && ($body->response->successful)) {
                if ($this->testmode == "yes") {
                    $url = 'https://gateway.sandbox.fatzebra.com.au/v1.0/customers/customer-' . $current_user->ID;
                } else {
                    $url = 'https://gateway.fatzebra.com.au/v1.0/customers/customer-' . $current_user->ID;
                }

                $args = array(
                    'headers' => array(
                        'Authorization' => $this->get_authorization()
                    )
                );
                $response = wp_remote_get($url, $args);
                $this->mail_debug_log($url, "", $response);
                $httpcode = wp_remote_retrieve_response_code($response);
                if ($httpcode == 200) {
                    $body = json_decode(wp_remote_retrieve_body($response));
                    $customer_id = $body->response->id;
                } else if ($httpcode == 404) {
                    $customer_data = array(
                        'first_name' => $firstname,
                        'last_name' => $lastname,
                        'reference' => "customer-" . $current_user->ID,
                        'email' => $email,
                        'ip_address' => $ip_addr,
                        'card_token' => $body->response->card_token,
                        'address' => array(
                            'address' => $address1,
                            'city' => $city,
                            'state' => $state,
                            'postcode' => $postcode,
                            'country' => $country
                        )
                    );
                    if ($this->testmode == "yes") {
                        $customer_data['test'] = 'true';
                        $url = 'https://gateway.sandbox.fatzebra.com.au/v1.0/customers';
                    } else {
                        $url = 'https://gateway.fatzebra.com.au/v1.0/customers';
                    }
                    $args = array(
                        'body' => json_encode($customer_data),
                        'timeout' => '45',
                        'redirection' => '5',
                        'httpversion' => '1.0',
                        'blocking' => true,
                        'headers' => array(
                            'Authorization' => $this->get_authorization()
                        ),
                        'cookies' => array()
                    );
                    $response = wp_remote_post($url, $args);
                    $this->mail_debug_log($url, $customer_data, $response);
                    $httpcode = wp_remote_retrieve_response_code($response);
                    if ($httpcode != 200) {
                        wc_add_notice("Could not create customer on payment gateway", 'error');
                        return;
                    }
                    $body = json_decode(wp_remote_retrieve_body($response));
                    $customer_id = $body->response->id;
                }

                $setup_fee = WC_Subscriptions_Order::get_sign_up_fee($order) * 100;
                switch (strtolower(WC_Subscriptions_Order::get_subscription_period($order))) {
                    case 'day':
                        $billing_period = 'Daily'; // will fail
                        break;
                    case 'week':
                        $billing_period = 'Weekly';
                        $anniversary = $this->myDate("N");
                        if ($anniversary > 5) {
                            $anniversary = 5;
                        }
                        break;
                    case 'year':
                        $billing_period = 'Yearly'; // will fail
                        break;
                    case 'month':
                    default:
                        $billing_period = 'Monthly';
                        $anniversary = $this->myDate("j");
                        break;
                }

                $payment_plan = array(
                    'payment_method' => 'Credit Card',
                    'customer' => $customer_id,
                    'reference' => $invoiceId,
                    'currency' => $currencyCode,
                    'setup_fee' => $setup_fee,
                    'amount' => $price_per_period,
                    'start_date' => $this->myDate("Y-m-d"),
                    'frequency' => $billing_period,
                    'anniversary' => $anniversary,
                    'failed_payment_fee' => $dishonour_fee,
                    'retry_interval' => $retry_interval
                );
                if ($this->testmode == "yes") {
                    $payment_plan['test'] = 'true';
                    $url = 'https://gateway.sandbox.fatzebra.com.au/v1.0/payment_plans';
                } else {
                    $url = 'https://gateway.fatzebra.com.au/v1.0/payment_plans';
                }
                $args = array(
                    'body' => json_encode($payment_plan),
                    'timeout' => '45',
                    'redirection' => '5',
                    'httpversion' => '1.0',
                    'blocking' => true,
                    'headers' => array(
                        'Authorization' => $this->get_authorization()
                    ),
                    'cookies' => array()
                );
                $response = wp_remote_post($url, $args);
                $this->mail_debug_log($url, $payment_plan, $response);
                $httpcode = wp_remote_retrieve_response_code($response);
                if ($httpcode != 201) {
                    wc_add_notice("Could not create payment plan on payment gateway", 'error');
                    return;
                }
                $body = json_decode(wp_remote_retrieve_body($response));
                if ($body->successful && ($body->response->status == "Active")) {
                    $order->add_order_note(__('Safe2Pay payment completed.', 'safe2pay'));
                    $order->payment_complete();
                    $woocommerce->cart->empty_cart();
                    return array(
                        'result' => 'success',
                        'redirect' => $this->get_return_url($order)
                    );
                } else {
                    wc_add_notice($body->response->message, 'error');
                    $order->add_order_note('Error: ' . $body->response->message);
                    return;
                }
            } else {
                wc_add_notice("Could not create customer on payment gateway", 'error');
                return;
            }
        } else {

            $amount = $order->order_total;

            if ($currencyCode != "AUD") {
                $currencies = $WOOCS->get_currencies();
                $amount = $amount / $currencies[$WOOCS->current_currency]['rate'];
                $currencyCode = "AUD";
            }

            $purchase_data = array(
                'card_holder' => $firstname . " " . $lastname,
                'card_number' => $cardNumber,
                'card_expiry' => $cardExpiry,
                'cvv' => $cardCvv,
                'amount' => $amount * 100,
                'reference' => $invoiceId . "-" . hash("crc32b", time()),
                'customer_ip' => $ip_addr,
                'currency' => $currencyCode
            );
            if ($this->testmode == "yes") {
                $purchase_data['test'] = 'true';
                $url = 'https://gateway.sandbox.fatzebra.com.au/v1.0/purchases';
            } else {
                $url = 'https://gateway.fatzebra.com.au/v1.0/purchases';
            }

            $args = array(
                'body' => json_encode($purchase_data),
                'timeout' => '45',
                'redirection' => '5',
                'httpversion' => '1.0',
                'blocking' => true,
                'headers' => array(
                    'Authorization' => $this->get_authorization()
                ),
                'cookies' => array()
            );
            $response = wp_remote_post($url, $args);
            $this->mail_debug_log($url, $purchase_data, $response);
            $body = json_decode(wp_remote_retrieve_body($response));
            if (($body->successful) && ($body->response->successful)) {
                $order->add_order_note(__('Safe2Pay payment completed.', 'safe2pay'));
                $order->payment_complete();
                $woocommerce->cart->empty_cart();
                return array(
                    'result' => 'success',
                    'redirect' => $this->get_return_url($order)
                );
            } else {
                wc_add_notice($body->response->message, 'error');
                $order->add_order_note('Error: ' . $body->response->message);
            }
        }
    }

    public function validate_fields() {
        return true;
    }

    public function cancelled_subscription($subscription) {
        $order = $subscription->get_parent();
        $id = $order->get_order_number();

        if ($this->testmode == "yes") {
            $url = "https://gateway.sandbox.fatzebra.com.au/v1.0/payment_plans/" . $id;
        } else {
            $url = "https://gateway.fatzebra.com.au/v1.0/payment_plans/" . $id;
        }
        $args = array(
            'headers' => array(
                'Authorization' => $this->get_authorization()
            )
        );
        $response = wp_remote_get($url, $args);
        $this->mail_debug_log($url, "", $response);
        $httpcode = wp_remote_retrieve_response_code($response);
        if ($httpcode == 200) {
            $args = array(
                'method' => "DELETE",
                'headers' => array(
                    'Authorization' => $this->get_authorization()
                )
            );
            $response = wp_remote_request($url, $args);
            $this->mail_debug_log($url, "DELETE", $response);
        }
    }

    public function do_ssl_check() {
        if ($this->enabled == "yes") {
            if (get_option('woocommerce_force_ssl_checkout') == "no") {
                echo "<div class=\"error\"><p>" . sprintf(__("<strong>%s</strong> is enabled and WooCommerce is not forcing the SSL certificate on your checkout page. Please ensure that you have a valid SSL certificate and that you are <a href=\"%s\">forcing the checkout pages to be secured.</a>"), $this->method_title, admin_url('admin.php?page=wc-settings&tab=checkout')) . "</p></div>";
            }
        }
    }

    public function get_title() {
        $s = $this->title;

        if (class_exists('WC_apm')) {
            $amex = get_option('wc_apm_american_express');
            $mastercard = get_option('wc_apm_mastercard');
            $visa = get_option('wc_apm_visa');
            $discover = get_option('wc_apm_discover');
            $maestro = get_option('wc_apm_maestro');

            $cards = "";
            if ($visa == "yes") {
                $cards = 'Visa';
            }
            if ($mastercard == "yes") {
                if ($cards != '') {
                    $cards .= "/";
                }
                $cards .= 'MasterCard';
            }
            if ($amex == "yes") {
                if ($cards != '') {
                    $cards .= "/";
                }
                $cards .= 'American Express';
            }
            if ($discover == "yes") {
                if ($cards != '') {
                    $cards .= "/";
                }
                $cards .= 'Discover';
            }
            if ($maestro == "yes") {
                if ($cards != '') {
                    $cards .= "/";
                }
                $cards .= 'Maestro';
            }
            if ($cards != '') {
                $cards = " ($cards)";
            }
            $s .= $cards;
        }
        return apply_filters('woocommerce_gateway_title', $s, $this->id);
    }

}
