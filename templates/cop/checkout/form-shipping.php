<?php
/**
 * Checkout shipping information form
 *
 * @author 		WooThemes
 * @package 	WooCommerce/Templates
 * @version     2.2.0
 */

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly
?>
<div class="woocommerce-shipping-fields">

  <div class="shipping_address">

    <?php do_action( 'woocommerce_before_checkout_shipping_form', $checkout ); ?>

    <?php foreach ( $checkout->checkout_fields['shipping'] as $key => $field ) : ?>

      <?php jaw_wc_checkout_ru_form_field( $key, $field, $checkout->get_value( $key ) ); ?>

    <?php endforeach; ?>

    <?php do_action( 'woocommerce_after_checkout_shipping_form', $checkout ); ?>

  </div>

  <?php do_action( 'woocommerce_before_order_notes', $checkout ); ?>

  <?php if ( apply_filters( 'woocommerce_enable_order_notes_field', get_option( 'woocommerce_enable_order_comments', 'yes' ) === 'yes' ) ) : ?>

    <?php if ( ! WC()->cart->needs_shipping() || WC()->cart->ship_to_billing_address_only() ) : ?>

      <h3><?php _e( 'Additional Information', 'woocommerce' ); ?></h3>

    <?php endif; ?>

    <?php foreach ( $checkout->checkout_fields['order'] as $key => $field ) : ?>

      <?php woocommerce_form_field( $key, $field, $checkout->get_value( $key ) ); ?>

    <?php endforeach; ?>

  <?php endif; ?>

  <?php do_action( 'woocommerce_after_order_notes', $checkout ); ?>

  <?php
  if(isset($checkout->checkout_fields['checkout_ru']) && !empty($checkout->checkout_fields['checkout_ru'])) {
    foreach ( $checkout->checkout_fields['checkout_ru'] as $key => $field ) {

      jaw_wc_checkout_ru_form_field( $key, $field, $checkout->get_value( $key ) );

    }
  }
  ?>
</div>
