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
      const TICKET_URL = 'http://platform.checkout.ru/service/login/ticket/';

      /**
       * @var string CheckOut service API key
       */
      public $api_key = '';
      /**
       * @var JAW_WC_Checkout_Ru The single instance of the class
       */
      protected static $_instance = null;
      /**
       * @var boolean Udse CheckOut popup for checkout
       */
      public $use_cop;

      /**
       * Main JAW_WC_Checkout_Ru Instance
       *
       * Ensures only one instance of JAW_WC_Checkout_Ru is loaded or can be loaded.
       *
       * @static
       * @return JAW_WC_Checkout_Ru Main instance
       */
      public static function instance() {
        if ( is_null( self::$_instance ) )
          self::$_instance = new self();
        return self::$_instance;
      }

      function __construct() {
        $this->id = 'checkout_ru';

        load_plugin_textdomain($this::TEXT_DOMAIN, false, plugin_basename(__DIR__).'/languages');

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
        $this->title = $this->get_option( 'title' );
        $this->api_key = $this->get_option('api_key');
        $this->use_cop = ($this->get_option('use_cop', 'yes') == 'yes');

//        $this->type         = $this->get_option( 'type' );
//        $this->fee          = $this->get_option( 'fee' );
//        $this->type         = $this->get_option( 'type' );
//        $this->codes        = $this->get_option( 'codes' );
//        $this->availability = $this->get_option( 'availability' );
//        $this->countries    = $this->get_option( 'countries' );

        $this->get_session_ticket();

        add_action( 'woocommerce_update_options_shipping_' . $this->id, array( $this, 'process_admin_options' ) );
//        add_filter('woocommerce_checkout_fields', array($this, 'checkout_ru_fields'));
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
            'description' => __( 'This controls the title which the user sees during checkout.', 'woocommerce' ),
            'default'     => __( 'CheckOut Delivery', $this::TEXT_DOMAIN ),
            'desc_tip'    => true,
          ),
          'api_key' => array(
            'title' => __('API key', $this::TEXT_DOMAIN),
            'type' => 'text',
            'label' => __('CheckOut service API key', $this::TEXT_DOMAIN),
            'default' => __('ENTER API KEY HERE', $this::TEXT_DOMAIN),
            'description' => __('Get your API key to CheckOut service functions at service clients private area.'),
            'desc_tip'    => true,
          ),
          'use_cop' => array(
            'title' => __('Use CheckOut.ru popup', $this::TEXT_DOMAIN),
            'type' => 'checkbox',
            'description' => __('Use CheckOut.ru popup form for checkout instead of native WooCommerce.'),
            'desc_tip'    => true,
            'default' => 'on',
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

        // CheckOut service API key must be set (at settings on admin options page)
        if(!isset($this->api_key) || empty($this->api_key)) return false;

        if(!isset($_COOKIE['jaw_wc_checkout_ru_ticket'])) {

          $tuCurl = curl_init();
          curl_setopt($tuCurl, CURLOPT_URL, $this::TICKET_URL . $this->api_key);
          curl_setopt($tuCurl, CURLOPT_VERBOSE, 0);
          curl_setopt($tuCurl, CURLOPT_HEADER, 0);
          curl_setopt($tuCurl, CURLOPT_RETURNTRANSFER, 1);
          $tuData = curl_exec($tuCurl);

          if(curl_errno($tuCurl)){
            echo 'Curl error: ' . curl_error($tuCurl);
            return false;
          }

          curl_close($tuCurl);
          $response = json_decode($tuData,true);

          if(!headers_sent()) {
            wc_setcookie('jaw_wc_checkout_ru_ticket', $response['ticket'], time() + HOUR_IN_SECONDS);
          }

          return $response['ticket'];
        } else {
          return $_COOKIE['jaw_wc_checkout_ru_ticket'];
        }

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

/**
 * wc_get_template hook function
 */
function jaw_wc_checkout_ru_get_template($located, $template_name, $args) {

  $checkout_ru = JAW_WC_Checkout_Ru::instance();

  if(!$checkout_ru->use_cop) {
    if($template_name == 'checkout/form-billing.php' || $template_name == 'checkout/form-shipping.php') {
      $located = __DIR__.'/templates/'.$template_name;
    }
  } else {
    if($template_name == 'checkout/form-checkout.php' || $template_name == 'checkout/form-shipping.php') {
      $located = __DIR__.'/templates/cop/'.$template_name;
    }
  }

  return $located;
}
add_filter('wc_get_template', 'jaw_wc_checkout_ru_get_template', 0, 3);

/**
 * wp_enqueue_scripts hook function
 */
function jaw_wc_checkout_ru_enqueue_script() {

  $checkout_ru = JAW_WC_Checkout_ru::instance();

  if($checkout_ru->use_cop) {
    wp_enqueue_script('jaw-wc-checkout-ru--cop', 'http://platform.checkout.ru/cop/popup.js?ver=1.0');
  } else {
    //@todo without CO3?
//  wp_enqueue_script('jaw-wc-checkout-ru-js-checkout', plugins_url('assets/js/checkout.js', __FILE__), array('jquery', 'wc-checkout', 'woocommerce'));
//  wp_enqueue_script('jaw-wc-checkout-ru-js-checkout-billing', plugins_url('assets/js/checkout-billing.js', __FILE__), array('jaw-wc-checkout-ru-js-checkout', 'wc-checkout', 'woocommerce'));
//  if(!WC()->cart->ship_to_billing_address_only()) {
//    wp_enqueue_script('jaw-wc-checkout-ru-js-checkout-shipping', plugins_url('assets/js/checkout-shipping.js', __FILE__), array('jaw-wc-checkout-ru-js-checkout'));
//  }
  }

}
add_filter('wp_enqueue_scripts', 'jaw_wc_checkout_ru_enqueue_script');

/**
 * Outputs a checkout/address form field.
 *
 * @access public
 * @subpackage	Forms
 * @param mixed $key
 * @param mixed $args
 * @param string $value (default: null)
 * @return void
 * @todo This function needs to be broken up in smaller pieces
 */
function jaw_wc_checkout_ru_form_field( $key, $args, $value = null ) {
  $defaults = array(
    'type'              => 'text',
    'label'             => '',
    'description'       => '',
    'placeholder'       => '',
    'maxlength'         => false,
    'required'          => false,
    'id'                => $key,
    'class'             => array(),
    'label_class'       => array(),
    'input_class'       => array(),
    'return'            => false,
    'options'           => array(),
    'custom_attributes' => array(),
    'validate'          => array(),
    'default'           => '',
  );

  $args = wp_parse_args( $args, $defaults  );

  if ( ( ! empty( $args['clear'] ) ) ) $after = '<div class="clear"></div>'; else $after = '';

  if ( $args['required'] ) {
    $args['class'][] = 'validate-required';
    $required = ' <abbr class="required" title="' . esc_attr__( 'required', 'woocommerce'  ) . '">*</abbr>';
  } else {
    $required = '';
  }

  $args['maxlength'] = ( $args['maxlength'] ) ? 'maxlength="' . absint( $args['maxlength'] ) . '"' : '';

  if ( is_string( $args['label_class'] ) )
    $args['label_class'] = array( $args['label_class'] );

  if ( is_null( $value ) )
    $value = $args['default'];

  // Custom attribute handling
  $custom_attributes = array();

  if ( ! empty( $args['custom_attributes'] ) && is_array( $args['custom_attributes'] ) )
    foreach ( $args['custom_attributes'] as $attribute => $attribute_value )
      $custom_attributes[] = esc_attr( $attribute ) . '="' . esc_attr( $attribute_value ) . '"';

  if ( ! empty( $args['validate'] ) )
    foreach( $args['validate'] as $validate )
      $args['class'][] = 'validate-' . $validate;

  switch ( $args['type'] ) {
    case "country" :

      $countries = $key == 'shipping_country' ? WC()->countries->get_shipping_countries() : WC()->countries->get_allowed_countries();

      if ( sizeof( $countries ) == 1 ) {

        $field = '<p class="form-row ' . esc_attr( implode( ' ', $args['class'] ) ) .'" id="' . esc_attr( $args['id'] ) . '_field">';

        if ( $args['label'] )
          $field .= '<label class="' . esc_attr( implode( ' ', $args['label_class'] ) ) .'">' . $args['label']  . '</label>';

        $field .= '<strong>' . current( array_values( $countries ) ) . '</strong>';

        $field .= '<input type="hidden" name="' . esc_attr( $key ) . '" id="' . esc_attr( $args['id'] ) . '" value="' . current( array_keys($countries ) ) . '" ' . implode( ' ', $custom_attributes ) . ' class="country_to_state" />';

        if ( $args['description'] )
          $field .= '<span class="description">' . esc_attr( $args['description'] ) . '</span>';

        $field .= '</p>' . $after;

      } else {

        $field = '<p class="form-row ' . esc_attr( implode( ' ', $args['class'] ) ) .'" id="' . esc_attr( $args['id'] ) . '_field">'
          . '<label for="' . esc_attr( $args['id'] ) . '" class="' . esc_attr( implode( ' ', $args['label_class'] ) ) .'">' . $args['label'] . $required  . '</label>'
          . '<select name="' . esc_attr( $key ) . '" id="' . esc_attr( $args['id'] ) . '" class="country_to_state country_select" ' . implode( ' ', $custom_attributes ) . '>'
          . '<option value="">'.__( 'Select a country&hellip;', 'woocommerce' ) .'</option>';

        foreach ( $countries as $ckey => $cvalue )
          $field .= '<option value="' . esc_attr( $ckey ) . '" '.selected( $value, $ckey, false ) .'>'.__( $cvalue, 'woocommerce' ) .'</option>';

        $field .= '</select>';

        $field .= '<noscript><input type="submit" name="woocommerce_checkout_update_totals" value="' . __( 'Update country', 'woocommerce' ) . '" /></noscript>';

        if ( $args['description'] )
          $field .= '<span class="description">' . esc_attr( $args['description'] ) . '</span>';

        $field .= '</p>' . $after;

      }

      break;
    case "state" :

      /* Get Country */
      $country_key = $key == 'billing_state'? 'billing_country' : 'shipping_country';
      $current_cc  = WC()->checkout->get_value( $country_key );
      $states      = WC()->countries->get_states( $current_cc );

      if ( is_array( $states ) && empty( $states ) ) {

        $field  = '<p class="form-row ' . esc_attr( implode( ' ', $args['class'] ) ) .'" id="' . esc_attr( $args['id'] ) . '_field" style="display: none">';

        if ( $args['label'] )
          $field .= '<label for="' . esc_attr( $args['id'] ) . '" class="' . esc_attr( implode( ' ', $args['label_class'] ) ) .'">' . $args['label'] . $required . '</label>';
        $field .= '<input type="hidden" class="hidden" name="' . esc_attr( $key )  . '" id="' . esc_attr( $args['id'] ) . '" value="" ' . implode( ' ', $custom_attributes ) . ' placeholder="' . esc_attr( $args['placeholder'] ) . '" />';

        if ( $args['description'] )
          $field .= '<span class="description">' . esc_attr( $args['description'] ) . '</span>';

        $field .= '</p>' . $after;

      } elseif ( is_array( $states ) ) {

        $field  = '<p class="form-row ' . esc_attr( implode( ' ', $args['class'] ) ) .'" id="' . esc_attr( $args['id'] ) . '_field">';

        if ( $args['label'] )
          $field .= '<label for="' . esc_attr( $args['id'] ) . '" class="' . esc_attr( implode( ' ', $args['label_class'] ) ) .'">' . $args['label']. $required . '</label>';
        $field .= '<select name="' . esc_attr( $key ) . '" id="' . esc_attr( $args['id'] ) . '" class="state_select" ' . implode( ' ', $custom_attributes ) . ' placeholder="' . esc_attr( $args['placeholder'] ) . '">
					<option value="">'.__( 'Select a state&hellip;', 'woocommerce' ) .'</option>';

        foreach ( $states as $ckey => $cvalue )
          $field .= '<option value="' . esc_attr( $ckey ) . '" '.selected( $value, $ckey, false ) .'>'.__( $cvalue, 'woocommerce' ) .'</option>';

        $field .= '</select>';

        if ( $args['description'] )
          $field .= '<span class="description">' . esc_attr( $args['description'] ) . '</span>';

        $field .= '</p>' . $after;

      } else {

        $field  = '<p class="form-row ' . esc_attr( implode( ' ', $args['class'] ) ) .'" id="' . esc_attr( $args['id'] ) . '_field">';

        if ( $args['label'] )
          $field .= '<label for="' . esc_attr( $args['id'] ) . '" class="' . esc_attr( implode( ' ', $args['label_class'] ) ) .'">' . $args['label']. $required . '</label>';
        $field .= '<input type="text" class="input-text ' . esc_attr( implode( ' ', $args['input_class'] ) ) .'" value="' . esc_attr( $value ) . '"  placeholder="' . esc_attr( $args['placeholder'] ) . '" name="' . esc_attr( $key ) . '" id="' . esc_attr( $args['id'] ) . '" ' . implode( ' ', $custom_attributes ) . ' />';

        if ( $args['description'] )
          $field .= '<span class="description">' . esc_attr( $args['description'] ) . '</span>';

        $field .= '</p>' . $after;

      }

      break;
    case "textarea" :

      $field = '<p class="form-row ' . esc_attr( implode( ' ', $args['class'] ) ) .'" id="' . esc_attr( $args['id'] ) . '_field">';

      if ( $args['label'] )
        $field .= '<label for="' . esc_attr( $args['id'] ) . '" class="' . esc_attr( implode( ' ', $args['label_class'] ) ) .'">' . $args['label']. $required  . '</label>';

      $field .= '<textarea name="' . esc_attr( $key ) . '" class="input-text ' . esc_attr( implode( ' ', $args['input_class'] ) ) .'" id="' . esc_attr( $args['id'] ) . '" placeholder="' . esc_attr( $args['placeholder'] ) . '"' . ( empty( $args['custom_attributes']['rows'] ) ? ' rows="2"' : '' ) . ( empty( $args['custom_attributes']['cols'] ) ? ' cols="5"' : '' ) . implode( ' ', $custom_attributes ) . '>'. esc_textarea( $value  ) .'</textarea>';

      if ( $args['description'] )
        $field .= '<span class="description">' . esc_attr( $args['description'] ) . '</span>';

      $field .= '</p>' . $after;

      break;
    case "checkbox" :

      $field = '<p class="form-row ' . esc_attr( implode( ' ', $args['class'] ) ) .'" id="' . esc_attr( $args['id'] ) . '_field">
					<input type="' . esc_attr( $args['type'] ) . '" class="input-checkbox" name="' . esc_attr( $key ) . '" id="' . esc_attr( $args['id'] ) . '" value="1" '.checked( $value, 1, false ) .' />
					<label for="' . esc_attr( $args['id'] ) . '" class="checkbox ' . implode( ' ', $args['label_class'] ) .'" ' . implode( ' ', $custom_attributes ) . '>' . $args['label'] . $required . '</label>';

      if ( $args['description'] )
        $field .= '<span class="description">' . esc_attr( $args['description'] ) . '</span>';

      $field .= '</p>' . $after;

      break;
    case "password" :

      $field = '<p class="form-row ' . esc_attr( implode( ' ', $args['class'] ) ) .'" id="' . esc_attr( $args['id'] ) . '_field">';

      if ( $args['label'] )
        $field .= '<label for="' . esc_attr( $args['id'] ) . '" class="' . esc_attr( implode( ' ', $args['label_class'] ) ) .'">' . $args['label']. $required . '</label>';

      $field .= '<input type="password" class="input-text ' . esc_attr( implode( ' ', $args['input_class'] ) ) .'" name="' . esc_attr( $key ) . '" id="' . esc_attr( $args['id'] ) . '" placeholder="' . esc_attr( $args['placeholder'] ) . '" value="' . esc_attr( $value ) . '" ' . implode( ' ', $custom_attributes ) . ' />';

      if ( $args['description'] )
        $field .= '<span class="description">' . esc_attr( $args['description'] ) . '</span>';

      $field .= '</p>' . $after;

      break;
    case "text" :

      $field = '<p class="form-row ' . esc_attr( implode( ' ', $args['class'] ) ) .'" id="' . esc_attr( $args['id'] ) . '_field">';

      if ( $args['label'] )
        $field .= '<label for="' . esc_attr( $args['id'] ) . '" class="' . esc_attr( implode( ' ', $args['label_class'] ) ) .'">' . $args['label'] . $required . '</label>';

      $field .= '<input type="text" class="input-text ' . esc_attr( implode( ' ', $args['input_class'] ) ) .'" name="' . esc_attr( $key ) . '" id="' . esc_attr( $args['id'] ) . '" placeholder="' . esc_attr( $args['placeholder'] ) . '" '.$args['maxlength'].' value="' . esc_attr( $value ) . '" ' . implode( ' ', $custom_attributes ) . ' />';

      if ( $args['description'] )
        $field .= '<span class="description">' . esc_attr( $args['description'] ) . '</span>';

      $field .= '</p>' . $after;

      break;
    case "select" :

      $options = '';

      if ( ! empty( $args['options'] ) )
        foreach ( $args['options'] as $option_key => $option_text )
          $options .= '<option value="' . esc_attr( $option_key ) . '" '. selected( $value, $option_key, false ) . '>' . esc_attr( $option_text ) .'</option>';

      $field = '<p class="form-row ' . esc_attr( implode( ' ', $args['class'] ) ) .'" id="' . esc_attr( $args['id'] ) . '_field">';

      if ( $args['label'] )
        $field .= '<label for="' . esc_attr( $args['id'] ) . '" class="' . esc_attr( implode( ' ', $args['label_class'] ) ) .'">' . $args['label']. $required . '</label>';

      $field .= '<select name="' . esc_attr( $key ) . '" id="' . esc_attr( $args['id'] ) . '" class="select" ' . implode( ' ', $custom_attributes ) . '>
						' . $options . '
					</select>';

      if ( $args['description'] )
        $field .= '<span class="description">' . esc_attr( $args['description'] ) . '</span>';

      $field .= '</p>' . $after;

      break;
    case "radio" :

      $field = '<p class="form-row ' . esc_attr( implode( ' ', $args['class'] ) ) .'" id="' . esc_attr( $args['id'] ) . '_field">';

      if ( $args['label'] )
        $field .= '<label for="' . esc_attr( current( array_keys( $args['options'] ) ) ) . '" class="' . esc_attr( implode( ' ', $args['label_class'] ) ) .'">' . $args['label']. $required  . '</label>';

      if ( ! empty( $args['options'] ) ) {
        foreach ( $args['options'] as $option_key => $option_text ) {
          $field .= '<input type="radio" class="input-radio" value="' . esc_attr( $option_key ) . '" name="' . esc_attr( $key ) . '" id="' . esc_attr( $args['id'] ) . '_' . esc_attr( $option_key ) . '"' . checked( $value, $option_key, false ) . ' />';
          $field .= '<label for="' . esc_attr( $args['id'] ) . '_' . esc_attr( $option_key ) . '" class="radio ' . implode( ' ', $args['label_class'] ) .'">' . $option_text . '</label>';
        }
      }

      $field .= '</p>' . $after;

      break;
    case 'hidden':
      $field = '<input type="hidden" name="' . esc_attr( $key ) . '" id="' . esc_attr( $args['id'] ) . '" value="' . esc_attr( $value ) . '" ' . implode( ' ', $custom_attributes ) . ' />';
      break;
    default :
      $field = apply_filters( 'woocommerce_form_field_' . $args['type'], '', $key, $args, $value );
      break;
  }

  if ( $args['return'] ) return $field; else echo $field;
}

/**
 * 'woocommerce_checkout_fields' hook function
 * @param $checkout_fields
 * @return mixed
 */
function jaw_wc_checkout_ru_fields($checkout_fields) {

  $checkout_ru = JAW_WC_Checkout_Ru::instance();

  if($checkout_ru->use_cop) {

    $wc = WC();

    $checkout_fields['checkout_ru'] = array(
      'test' => array(
        'type' => 'hidden',
      ),
    );
  }

  return $checkout_fields;

}
add_filter('woocommerce_checkout_fields', 'jaw_wc_checkout_ru_fields');
