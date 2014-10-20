<?php
/*
Plugin Name: J@W WooCommerce CheckOut.ru
Plugin URI: http://
Description: Checkout.ru shipping plugin for WooCommerce
Author: pshentsoff
Author URI: http://pshentsoff.ru/
Version: 0.0.1
Text Domain: jaw-wc-checkout-ru
License: GPL version 3 or later - http://www.gnu.org/licenses/gpl-3.0.html
*/
/**
 * @file        jaw-wc-checkout-ru.php
 * @description Checkout.ru shipping plugin for WooCommerce
 *
 * PHP Version  5.4.4
 *
 * @package     Wordpress.local
 * @category
 * @plugin URI
 * @copyright   2014, Vadim Pshentsov. All Rights Reserved.
 * @license     http://www.gnu.org/licenses/gpl-3.0.html GNU/GPLv3
 * @author      Vadim Pshentsov <pshentsoff@gmail.com> 
 * @link        http://pshentsoff.ru Author's homepage
 * @link        http://blog.pshentsoff.ru Author's blog
 *
 * @created     19.10.14
 */

// Exit if accessed directly
defined('ABSPATH') or exit;

function jaw_wc_checkout_ru_init() {

  if(!class_exists('WC_Shipping_Method')) return;

  if (!class_exists('JAW_WC_Checkout_Ru')) {

    class JAW_WC_Checkout_Ru extends WC_Shipping_Method {
      /**
       * Plugin version
       * @var string
       */
      const VERSION = '0.0.1';

      function __construct() {
        $this->id = 'checkout_ru';
        load_plugin_textdomain($this->id, false, plugin_basename(__FILE__).'/languages');

        $this->method_title = __('Checkout.ru Shipping', $this->id);

        $this->shipping_init();
      }

      /**
       * Init settings
       */
      function shipping_init() {
        setlocale(LC_ALL, get_locale());
      }

      /**
       * The Shipping fields
       */
      function init_form_settings() {
        $this->form_fields = array(
          'enabled' => array(
            'title' => __('Enable', 'woocommerce'),
            'type' => 'checkbox',
            'label' => __('Enable Checkout.ru', $this->id),
            'default' => 'no',
          ),
        );
      }

      function admin_options() {
        global $woocommerce, $wpdb;
        //@todo Change to native WP options statements
        $field = $this->plugin_id.$this->id.'_';
        $shipping_details = $wpdb->get_results("SELECT `option_value` FROM `wp_options` WHERE `option_name`='".$field."settings'");
        $default_values = unserialize($shipping_details[0]->option_value);
      }

      function process_admin_options() {
        global$wpdb;

        //@todo save admin options (update_option)

      }
    }
  }
}

add_action('woocommerce_shipping_init', 'jaw_wc_checkout_ru_init', 0);

function jaw_wc_checkout_ru_add_method($methods) {
  $methods[] = 'JAW_WC_Checkout_Ru';
  return $methods;
}

add_filter('woocommerce_shipping_methods', 'jaw_wc_checkout_ru_add_method');