<?php

/*
  Plugin Name: WooCommerce Safe2Pay Payment Gateway
  Plugin URI: http://www.safe2pay.com.au/
  Description: Extends WooCommerce by Adding the Safe2Pay Gateway.
  Version: 1.25
  Author: Iman Biglari, DataQuest PTY Ltd.
  Author URI: http://www.dataquest.com.au/
 */

function safe2pay_filter_input_fix($type, $variable_name, $filter = FILTER_DEFAULT, $options = NULL) {
    $checkTypes = [
        INPUT_GET,
        INPUT_POST,
        INPUT_COOKIE 
    ];

    if ($options === NULL) {
        $options = FILTER_NULL_ON_FAILURE;
    }

    if (in_array($type, $checkTypes) || filter_has_var($type, $variable_name)) {
        return sanitize_text_field(filter_input($type, $variable_name, $filter, $options));
    } else if ($type == INPUT_SERVER && isset($_SERVER[$variable_name])) {
        return sanitize_text_field(filter_var($_SERVER[$variable_name], $filter, $options));
    } else if ($type == INPUT_ENV && isset($_ENV[$variable_name])) {
        return sanitize_text_field(filter_var($_ENV[$variable_name], $filter, $options));
    } else {
        return NULL;
    }
}

function safe2pay_get_remote_ip() {
    $ip_addr = safe2pay_filter_input_fix(INPUT_SERVER, 'REMOTE_ADDR');
    if ($ip_addr == "") {
        $ip_addr = gethostbyname(gethostname());
    }
    return $ip_addr;
}

function safe2pay_arr_push_pos($key, $value, $pos, $arr) {
    $new_arr = array();
    $i = 1;
    foreach ($arr as $arr_key => $arr_value) {
        if ($i == $pos) {
            $new_arr[$key] = $value;
        }
        $new_arr[$arr_key] = $arr_value;
        ++$i;
    }

    return $new_arr;
}

function safe2pay_get_latest_plugin_version($slug) {
    $request = array(
        'body' => array(
            'action' => 'plugin_information',
            'request' => serialize((object) array('slug' => $slug))
        )
    );

    $key = 'safe2pay_' . md5(serialize($request));
    if (($plugin = get_transient($key)) === false) {
        $response = wp_remote_post('http://api.wordpress.org/plugins/info/1.0/', $request);
        if (is_wp_error($response)) {
            return $response;
        }
        $plugin = unserialize(wp_remote_retrieve_body($response));
        if (!is_object($plugin) && !is_array($plugin)) {
            return new WP_Error('plugin_api_error', 'An unexpected error has occurred');
        }
        set_transient($key, $plugin, 60 * 60 * 24);
    }

    return $plugin; 
}

function safe2pay_get_plugin_version() {
    $plugin_data = get_plugin_data(__FILE__);
    $plugin_version = $plugin_data['Version'];
    return $plugin_version;
}

function safe2pay_plugin_update_message($data, $response) {
    if (isset($data['upgrade_notice'])) {
        printf('<div class="update-message">%s</div>', wpautop($data['upgrade_notice']));
    }
}

function safe2pay_check_for_update() {
    $version = safe2pay_get_latest_plugin_version('wooe-safe2pay-payment-gateway');
    if ($version->version != safe2pay_get_plugin_version()) {
        // echo '<div class="error"><div class="update-message"><p>There is a new version of Safe2Pay payment plugin available. We strongly recommend you update to the latest version.</p></div></div>';
    }
}

function safe2pay_init() {
    if (!class_exists('WC_Payment_Gateway')) {
        return;
    }

    include_once( 'woocommerce-safe2pay.php' );

    add_filter('woocommerce_payment_gateways', 'safe2pay_gateway');
    add_action('init', 'safe2pay_add_endpoints');
    add_action('admin_notices', 'safe2pay_check_for_update');
    add_action('in_plugin_update_message-' . plugin_basename(__FILE__), 'safe2pay_plugin_update_message', 10, 2);

    function safe2pay_gateway($methods) {
        $methods[] = 'WC_Gateway_Safe2Pay';
        return $methods;
    }

}

function safe2pay_add_endpoints() {
    add_rewrite_endpoint('change-payment-method', EP_PAGES);
}

function safe2pay_action_links($links) {
    $plugin_links = array(
        '<a href="' . admin_url('admin.php?page=wc-settings&tab=checkout') . '">' . __('Settings', 'safe2pay') . '</a>',
    );

    return array_merge($plugin_links, $links);
}

add_action('plugins_loaded', 'safe2pay_init', 0);

add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'safe2pay_action_links');
