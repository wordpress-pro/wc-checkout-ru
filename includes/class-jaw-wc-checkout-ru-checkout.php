<?php
/**
 * @file        class-jaw-wc-checkout-ru-checkout.php
 * @description
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
 * @created     23.10.14
 */

// Exit if accessed directly
defined('ABSPATH') or exit;

function jaw_wc_checkout_ru_checkout_init() {

  if(!class_exists('WC_Shipping_Method')) return;

  class JAW_WC_Checkout_Ru_Checkout extends WC_Shipping_Method {

    const VERSION = '0.1.4';
    const METHOD = 'JAW_WC_Checkout_Ru_Checkout';
    const TEXT_DOMAIN = _JAW_WC_CHECKOUT_RU_TEXT_DOMAIN;
    /**
     * URL to get session ticket
     */
    const TICKET_URL = _JAW_WC_CHECKOUT_RU_TICKET_URL;
    /**
     * URL of CO3 popup script
     */
    const COP_SCRIPT_URL = _JAW_WC_CHECKOUT_RU_COP_SCRIPT_URL;

    /**
     * @var string CheckOut service API key
     */
    public $api_key = '';
    /**
     * @var JAW_WC_Checkout_Ru_Checkout The single instance of the class
     */
    protected static $_instance = null;
    /**
     * @var boolean Use CheckOut popup for checkout
     */
    public $use_cop;
    /**
     * @var boolean Is cart must send product weight to service
     */
    public $send_weight;

    /**
     * Main JAW_WC_Checkout_Ru_Checkout Instance
     *
     * Ensures only one instance of JAW_WC_Checkout_Ru_Checkout is loaded or can be loaded.
     *
     * @static
     * @return JAW_WC_Checkout_Ru_Checkout Main instance
     */
    public static function instance() {
      if ( is_null( self::$_instance ) )
        self::$_instance = new self();
      return self::$_instance;
    }

    function __construct() {

      $this->init();

      add_action( 'woocommerce_update_options_shipping_' . $this->id, array( $this, 'process_admin_options' ) );

      if($this->use_cop) {
        wp_enqueue_script('jaw-wc-checkout-ru--cop', $this::COP_SCRIPT_URL, array(), '1.0', true);
      } else {
        //@todo without CO3?
//      wp_enqueue_script('jaw-wc-checkout-ru-js-checkout', plugins_url('assets/js/checkout.js', __FILE__), array('jquery', 'wc-checkout', 'woocommerce'));
//      wp_enqueue_script('jaw-wc-checkout-ru-js-checkout-billing', plugins_url('assets/js/checkout-billing.js', __FILE__), array('jaw-wc-checkout-ru-js-checkout', 'wc-checkout', 'woocommerce'));
//      if(!WC()->cart->ship_to_billing_address_only()) {
//        wp_enqueue_script('jaw-wc-checkout-ru-js-checkout-shipping', plugins_url('assets/js/checkout-shipping.js', __FILE__), array('jaw-wc-checkout-ru-js-checkout'));
//      }
      }
    }

    /**
     * Init settings
     */
    function init() {

      $this->id = 'checkout_ru';

      setlocale(LC_ALL, get_locale());
      load_plugin_textdomain($this::TEXT_DOMAIN, false, plugin_basename(__DIR__).'/languages');

      $this->method_title = __('Checkout.ru Shipping', $this::TEXT_DOMAIN);

      $this->init_form_fields();

      // Define user set variables
      $this->init_settings();
      $this->title = $this->get_option( 'title' );
      $this->api_key = $this->get_option('api_key');
      $this->use_cop = ($this->get_option('use_cop', 'yes') == 'yes');
      $this->send_weight = ($this->get_option('send_weight', 'yes') == 'yes');

      $this->get_session_ticket();

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
        'title' => array(
          'title'       => __( 'Title', 'woocommerce' ),
          'type'        => 'text',
          'description' => __( 'This controls the title which the user sees during checkout.', 'woocommerce', $this::TEXT_DOMAIN),
          'default'     => __( 'CheckOut Delivery', $this::TEXT_DOMAIN ),
          'desc_tip'    => true,
        ),
        'api_key' => array(
          'title' => __('API key', $this::TEXT_DOMAIN),
          'type' => 'text',
          'label' => __('CheckOut service API key', $this::TEXT_DOMAIN),
          'default' => __('ENTER API KEY HERE', $this::TEXT_DOMAIN),
          'description' => __('Get your API key to CheckOut service functions at service clients private area.', $this::TEXT_DOMAIN),
          'desc_tip'    => true,
        ),
        'use_cop' => array(
          'title' => __('Use CheckOut.ru popup', $this::TEXT_DOMAIN),
          'type' => 'checkbox',
          'description' => __('Use CheckOut.ru popup form for checkout instead of native WooCommerce.', $this::TEXT_DOMAIN),
          'desc_tip'    => true,
          'default' => 'on',
        ),
        'send_weight' => array(
          'title' => __('Send weight', $this::TEXT_DOMAIN),
          'type' => 'checkbox',
          'description' => __('Is cart must send product weight to service CheckOut.ru.', $this::TEXT_DOMAIN),
          'desc_tip'    => true,
          'default' => 'off',
        ),
      );
    }

    /**
     * Calculate shipping function.
     */
    function calculate_shipping() {

      $rate = array(
        'id' => $this->id,
        'label' => $this->title,
        'cost' => 0,
        );

      if($_POST['deliveryCost']) {
        $rate['cost'] = $_POST['deliveryCost'];
      }

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

    /**
     * is_available function.
     * @param array $package
     * @return bool
     */
    function is_available( $package ) {

      if ($this->enabled == 'no') return false;

      return apply_filters( 'woocommerce_shipping_' . $this->id . '_is_available', true, $package );

    }

    /**
     * Get CheckOut service session ticket from service or cookie
     * @return string session ticket or false on error or API key not set
     */
    function get_session_ticket() {
      return jaw_wc_checkout_ru_get_session_ticket($this->api_key);
    }

  }
}
add_action('woocommerce_shipping_init', 'jaw_wc_checkout_ru_checkout_init', 0);

/**
 * woocommerce_shipping_methods hook function
 * @param $methods
 * @return array
 */
function jaw_wc_checkout_ru_add_method($methods) {
  $methods[] = JAW_WC_Checkout_Ru_Checkout::METHOD;
  return $methods;
}
add_filter('woocommerce_shipping_methods', 'jaw_wc_checkout_ru_add_method');

/**
 * woocommerce_cart_totals_before_order_total hook function
 */
function jaw_wc_checkout_ru_costs() {

  $wc = WC();

  if($wc->session->choosen_shipping_methods[0] == JAW_WC_Checkout_Ru_Checkout::METHOD) {
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

/**
 * wc_get_template hook function
 */
function jaw_wc_checkout_ru_get_template($located, $template_name, $args) {

  if(!class_exists('JAW_WC_Checkout_Ru_Checkout')) return $located;

  $checkout_ru = JAW_WC_Checkout_Ru_Checkout::instance();

  if(!$checkout_ru->use_cop) {
    if($template_name == 'checkout/form-billing.php'
      || $template_name == 'checkout/form-shipping.php'
    ) {
      $located = __DIR__.'/templates/'.$template_name;
    }
  } else {
    if($template_name == 'checkout/form-checkout.php'
      || $template_name == 'checkout/form-billing.php'
      || $template_name == 'checkout/form-shipping.php'
    ) {
      $located = __DIR__.'/templates/cop/'.$template_name;
    }
  }

  return $located;
}
add_filter('wc_get_template', 'jaw_wc_checkout_ru_get_template', 0, 3);
