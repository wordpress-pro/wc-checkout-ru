<?php
/*
Plugin Name: J@W WooCommerce CheckOut.ru
Plugin URI: https://bitbucket.org/jaw_projects/jaw-wc-checkout-ru
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

      const VERSION = '0.0.1';
      const METHOD = 'JAW_WC_Checkout_Ru';
      const TEXT_DOMAIN = 'jaw-wc-checkout-ru';

      function __construct() {
        $this->id = 'checkout_ru';

        $text_domain = load_plugin_textdomain($this::TEXT_DOMAIN, false, plugin_basename(__DIR__).'/languages');

        $this->method_title = __('Checkout.ru Shipping', $this::TEXT_DOMAIN);

        $this->init();
      }

      /**
       * Init settings
       */
      function init() {

        setlocale(LC_ALL, get_locale());

        $this->init_form_fields();
        $this->init_settings();

        // Define user set variables
        $this->title        = $this->get_option( 'title' );
        $this->type         = $this->get_option( 'type' );
        $this->fee          = $this->get_option( 'fee' );
        $this->type         = $this->get_option( 'type' );
        $this->codes        = $this->get_option( 'codes' );
        $this->availability = $this->get_option( 'availability' );
        $this->countries    = $this->get_option( 'countries' );

        add_action( 'woocommerce_update_options_shipping_' . $this->id, array( $this, 'process_admin_options' ) );
      }

      /**
       * The Shipping fields
       */
      function init_form_fields() {
        $this->form_fields = array(
          'enabled' => array(
            'title' => __('Enable', 'woocommerce'),
            'type' => 'checkbox',
            'label' => __('Enable Checkout.ru', $this::TEXT_DOMAIN),
            'default' => 'no',
          ),
          //@todo add other settings
        );
      }

      /**
       * Calculate shipping function.
       */
      function calculate_shipping() {
        //@todo calc shipping cost
        $shipping_total = 0;

        $rate = array(
          'id'    => $this->id,
          'label' => $this->title,
          'cost'  => $shipping_total
        );

        $this->add_rate($rate);

      }

      /**
       * admin_options function. Simplest.
       *
       * @access public
       * @return void
       */
      function admin_options() {
        ?>
        <h3><?php echo $this->method_title; ?></h3>
        <p><?php _e( 'Checkout.ru shipping for delivering orders by CheckOut service.', $this::TEXT_DOMAIN ); ?></p>
        <table class="form-table">
          <?php $this->generate_settings_html(); ?>
        </table> <?php
      }

    }
  }
}
add_action('woocommerce_shipping_init', 'jaw_wc_checkout_ru_init', 0);

/**
 * woocommerce_shipping_methods hook function
 * @param $methods
 * @return array
 */
function jaw_wc_checkout_ru_add_method($methods) {
  $methods[] = JAW_WC_Checkout_Ru::METHOD;
  return $methods;
}
add_filter('woocommerce_shipping_methods', 'jaw_wc_checkout_ru_add_method');

/**
 * woocommerce_cart_totals_before_order_total hook function
 */
function jaw_wc_checkout_ru_costs() {

  $wc = WC();

  if($wc->session->choosen_shipping_methods[0] == JAW_WC_Checkout_Ru::METHOD) {
    //@todo check this and set
//    $wc->shipping->shipping_total = $_SESSION['price'];
//    $wc->cart->total = $wc->cart->subtotal + $_SESSION['price'];
//    $wc->session->shipping_total = '10';
//    $wc->session->total = $wc->session->subtotal + $_SESSION['price'];
//    $wc->cart->add_fee(__('Shipping Cost', 'woocommerce'), $_SESSION['price']);
//    $wc->session->set('shipping_total"', $_SESSION['price']);
  }
}
add_filter('woocommerce_cart_totals_before_order_total', 'jaw_wc_checkout_ru_costs');