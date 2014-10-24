<?php
/**
 * Plugin Name: JAW WooCommerce CheckOut.ru Delivery
 * Plugin URI: https://bitbucket.org/jaw_projects/jaw-wc-checkout-ru
 * Description: Checkout.ru shipping plugin for WooCommerce
 * Version: 0.1.5
 * Author: pshentsoff
 * Author URI: http://pshentsoff.ru/
 * Requires at least: 3.8
 * Tested up to: 4.0
 *
 * Text Domain: jaw-wc-checkout-ru
 * Domain Path: /languages/
 *
 * License: GPL version 3 or later - http://www.gnu.org/licenses/gpl-3.0.html
 *
 * @package WooCommerce
 * @category Add-on
 * @author pshentsoff
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

define('_JAW_WC_CHECKOUT_RU_TICKET_URL', 'http://platform.checkout.ru/service/login/ticket/');
define('_JAW_WC_CHECKOUT_RU_COP_SCRIPT_URL', 'http://platform.checkout.ru/cop/popup.js');
define('_JAW_WC_CHECKOUT_RU_TEXT_DOMAIN', 'jaw-wc-checkout-ru');
define('_JAW_WC_CHECKOUT_RU_METHOD_ID', 'checkout_ru');
define('_JAW_WC_CHECKOUT_RU_PLUGIN_DIR', __DIR__);

include('includes/class-jaw-wc-checkout-ru-checkout.php');

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
function jaw_wc_checkout_ru_fields($checkout_fields = array()) {

  //@fixme change this workaround to native solution
  $settings = get_option( 'woocommerce_checkout_ru_settings', null );
  $api_key = isset($settings['api_key']) ? $settings['api_key'] : null;
  $use_cop = isset($settings['use_cop']) ? $settings['use_cop'] : false;
  $send_weight = isset($settings['send_weight']) ? $settings['send_weight'] : false;

  if($use_cop) {

    $wc = WC();

    $address_parts = jaw_wc_checkout_ru_parse_full_address($_POST['address']);

    $checkout_fields['checkout_ru'] = array(
      'ticket' => array(
        'type' => 'hidden',
        'default' => jaw_wc_checkout_ru_get_session_ticket($api_key),
      ),
      'callbackURL' => array(
        'type' => 'hidden',
        'default' => esc_url( $wc->cart->get_cart_url()),
      ),
      'place' => (isset($checkout_fields['shipping']['shipping_city']) ? $checkout_fields['shipping']['shipping_city'] : array()),
      'street' => (isset($checkout_fields['shipping']['billing_address_1']) ? $checkout_fields['shipping']['billing_address_1'] : array()),
      'house' => array(
        'type' => 'hidden',
        'default' => (isset($address_parts['house']) ? $address_parts['house'] : ''),
      ),
      'housing' => array(
        'type' => 'hidden',
        'default' => (isset($address_parts['housing']) ? $address_parts['housing'] : ''),
      ),
      'building' => array(
        'type' => 'hidden',
        'default' => (isset($address_parts['building']) ? $address_parts['building'] : ''),
      ),
      'appartment' => array(
        'type' => 'hidden',
        'default' => (isset($address_parts['apartment']) ? $address_parts['apartment'] : ''),
      ),
      'postindex' => array(
        'type' => 'hidden',
        'default' => (isset($_POST['deliveryPostindex']) ? $_POST['deliveryPostindex'] : ''),
      ),
      'fullname' => array(
        'type' => 'hidden',
        'default' => (isset($_POST['clientFIO']) ? $_POST['clientFIO'] : ''),
      ),
      'email' => array(
        'type' => 'hidden',
        'default' => (isset($_POST['clientEmail']) ? $_POST['clientEmail'] : ''),
      ),
      'phone' => array(
        'type' => 'hidden',
        'default' => (isset($_POST['clientPhone']) ? $_POST['clientPhone'] : ''),
      ),
    );
    $checkout_fields['checkout_ru']['place']['type'] = 'hidden';
    $checkout_fields['checkout_ru']['place']['default'] = isset($_POST['deliveryPlace']) ? $_POST['deliveryPlace'] : $checkout_fields['checkout_ru']['place']['default'];
    $checkout_fields['checkout_ru']['street']['type'] = 'hidden';
    $checkout_fields['checkout_ru']['street']['default'] = isset($address_parts['street']) ? $address_parts['street'] : $checkout_fields['checkout_ru']['street']['default'];

    $i = 0;
    foreach ($wc->cart->cart_contents as $cart_item) {
      $checkout_fields['checkout_ru']["names[$i]"] = array(
        'type' => 'hidden',
        'default' => $cart_item['data']->post->post_title,
      );
      $checkout_fields['checkout_ru']["codes[$i]"] = array(
        'type' => 'hidden',
        'default' => $cart_item['product_id'],
      );
      $checkout_fields['checkout_ru']["varcodes[$i]"] = array(
        'type' => 'hidden',
        'default' => $cart_item['variation_id'],
      );
      $checkout_fields['checkout_ru']["quantities[$i]"] = array(
        'type' => 'hidden',
        'default' => $cart_item['quantity'],
      );
      $checkout_fields['checkout_ru']["costs[$i]"] = array(
        'type' => 'hidden',
        'default' => $cart_item['line_subtotal'],
      );
      $checkout_fields['checkout_ru']["paycosts[$i]"] = array(
        'type' => 'hidden',
        'default' => $cart_item['line_total'],
      );
      if($send_weight) {
        $checkout_fields['checkout_ru']["weights[$i]"] = array(
          'type' => 'hidden',
          'default' => 0, //@todo get weight data?
        );
      }
    }

  }

  return $checkout_fields;

}
add_filter('woocommerce_checkout_fields', 'jaw_wc_checkout_ru_fields');

/**
 * 'woocommerce_locate_template' hook functions to change standard woocommerce/cart/shipping-calculator
 * @param $template
 * @param $template_name
 * @param $template_path
 * @return string
 */
function jaw_wc_checkout_ru_locate_template($template, $template_name, $template_path) {
  if($template_name == 'cart/shipping-calculator.php') {
    $template = __DIR__.'/templates/cart/shipping-calculator.php';
  }
  return $template;
}
add_filter('woocommerce_locate_template', 'jaw_wc_checkout_ru_locate_template', 0, 3);

/**
 * Get CheckOut service session ticket from service or cookie
 * @param string CheckOut.ru service API key. Try to return cookie ticket value if set - then this parameter can bypass
 * @return string session ticket or false on error or API key not set
 */
function jaw_wc_checkout_ru_get_session_ticket($api_key = null) {


  if(!isset($_COOKIE['jaw_wc_checkout_ru_ticket'])) {

    // CheckOut service API key must be set (at settings on admin options page)
    if(!isset($api_key) || empty($api_key)) return false;

    $tuCurl = curl_init();
    curl_setopt($tuCurl, CURLOPT_URL, _JAW_WC_CHECKOUT_RU_TICKET_URL . $api_key);
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

/**
 * 'woocommerce_cart_collaterals' hook function to check if shipping data is returned from cop
 */
function jaw_wc_checkout_ru_cart_collaterals() {

  wp_enqueue_script('jaw-wc-checkout-ru-cop', _JAW_WC_CHECKOUT_RU_COP_SCRIPT_URL, array(), '1.0', true);

  if($_POST['orderId']) {
    // Save CO3 data to session
    $cop_fields = array(
      'orderId' => $_POST['orderId'],
      'deliveryId' => (isset($_POST['deliveryId']) ? $_POST['deliveryId'] : 0),
      'deliveryType' => (isset($_POST['deliveryType']) ? $_POST['deliveryType'] : ''),
      'deliveryCost' => (isset($_POST['deliveryCost']) ? $_POST['deliveryCost'] : 0),
      'deliveryOrderCost' => (isset($_POST['deliveryOrderCost']) ? $_POST['deliveryOrderCost'] : 0),
      'deliveryMinTerm' => (isset($_POST['deliveryMinTerm']) ? $_POST['deliveryMinTerm'] : 0),
      'deliveryMaxTerm' => (isset($_POST['deliveryMaxTerm']) ? $_POST['deliveryMaxTerm'] : 0),
      'deliveryPlace' => (isset($_POST['deliveryPlace']) ? $_POST['deliveryPlace'] : ''),
      'deliveryPlaceId' => (isset($_POST['deliveryPlaceId']) ? $_POST['deliveryPlaceId'] : ''),
      'deliveryPostindex' => (isset($_POST['deliveryPostindex']) ? $_POST['deliveryPostindex'] : ''),
      'deliveryStreetId' => (isset($_POST['deliveryStreetId']) ? $_POST['deliveryStreetId'] : ''),
      'deliveryWeight' => (isset($_POST['deliveryWeight']) ? $_POST['deliveryWeight'] : 0),
      'address' => (isset($_POST['address']) ? $_POST['address'] : ''),
      'clientFIO' => (isset($_POST['clientFIO']) ? $_POST['clientFIO'] : ''),
      'clientPhone' => (isset($_POST['clientPhone']) ? $_POST['clientPhone'] : ''),
      'clientEmail' => (isset($_POST['clientEmail']) ? $_POST['clientEmail'] : ''),
      'comment' => (isset($_POST['comment']) ? $_POST['comment'] : ''),
      'status' => (isset($_POST['status']) ? $_POST['status'] : ''),
    );
    WC()->session->set('checkout_ru_cop_fields', $cop_fields);
  } else {
    // Get CO3 fields from cart
    WC()->cart->cop_fields = jaw_wc_checkout_ru_fields();
  }

  jaw_wc_checkout_ru_costs();
}
add_action('woocommerce_cart_collaterals', 'jaw_wc_checkout_ru_cart_collaterals');

/**
 * 'woocommerce_cart_shipping_method_full_label' hook function
 * @param $label
 * @param $method
 * @return string
 */
function jaw_wc_checkout_ru_cart_shipping_method_full_label($label, $method) {

  if(isset($_POST['deliveryCost']) && $method->id == _JAW_WC_CHECKOUT_RU_METHOD_ID) {
    $method->cost = $_POST['deliveryCost'];
    $label = $method->label.': '.wc_price($method->cost);
  }

  return $label;
}
add_filter( 'woocommerce_cart_shipping_method_full_label',  'jaw_wc_checkout_ru_cart_shipping_method_full_label', 0, 2);

/**
 * 'woocommerce_cart_total' hook function. Correct cart total after co3 popup callback
 * @param $cart_total
 * @return string
 */
function jaw_wc_checkout_ru_cart_total($cart_total) {
  if(isset($_POST['deliveryCost'])) {
    if(isset($_POST['deliveryOrderCost'])) {
      $cart_total = wc_price($_POST['deliveryOrderCost'] + $_POST['deliveryCost']);
    } else {
      $cart_total = wc_price($cart_total + $_POST['deliveryCost']);
    }
  }
  return $cart_total;
}
add_filter('woocommerce_cart_total', 'jaw_wc_checkout_ru_cart_total', 0, 1);

/**
 * Function to build full address string from parts
 * @param $street
 * @param $house
 * @param $housing
 * @param $building
 * @param $apartment
 * @return string
 */
function jaw_wc_checkout_ru_build_full_address($street, $house, $housing, $building, $apartment) {

  $address = '';

  if(!empty($street) && !empty($house)) {
    $address = $street . __(', h.', _JAW_WC_CHECKOUT_RU_TEXT_DOMAIN) . $house;

    if(!empty($housing)) $address .= __(' housing ') . $housing; // корп.
    if (!empty($building)) $address .= __(' building ') . $building; // стр.
    if (!empty($apartment)) $address .= __(' ap.') . $apartment; //" кв."
  }

  return $address;
}

/**
 * Parse adsress string to associative array
 * @param $address
 * @return array
 */
function jaw_wc_checkout_ru_parse_full_address($address) {

  $encoding = mb_regex_encoding();
  mb_regex_encoding('UTF-8');

  if (!empty($address)) {

    $addressParts = explode(', ', $address);

    $data = array(
      'street' => $addressParts[0],
      'house' => '',
      'housing' => '',
      'building' => '',
      'apartment' => '',
    );

    $matches = array();

    if (mb_eregi("д\.([а-яА-ЯёЁ0-9\-]+)(\s|$)", $addressParts[1], $matches)) {
      $data['house'] = $matches[1];
    } else {
      $data['house'] = $addressParts[1];
    }
    if (mb_eregi("корп\.([а-яА-ЯёЁ0-9\-]+)(\s|$)", $addressParts[1], $matches)) $data['housing'] = $matches[1];
    if (mb_eregi("стр\.([а-яА-ЯёЁ0-9\-]+)(\s|$)", $addressParts[1], $matches)) $data['building'] = $matches[1];
    if (mb_eregi("кв\.([а-яА-ЯёЁ0-9\-]+)(\s|$)", $addressParts[1], $matches)) $data['apartment'] = $matches[1];

  } else {
    $data = array(); // empty array
  }

  mb_regex_encoding($encoding);
  return $data;

}