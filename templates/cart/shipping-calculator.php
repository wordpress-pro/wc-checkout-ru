<?php
/**
 * @file        shipping-calculator.php
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

?>
<form id="jaw-wc-checkout-ru-cart-form">
<?php
$wc = WC();
if(isset($wc->cart->cop_fields['checkout_ru']) && !empty($wc->cart->cop_fields['checkout_ru'])) {
  foreach ( $wc->cart->cop_fields['checkout_ru'] as $key => $field ) {

    jaw_wc_checkout_ru_form_field( $key, $field);

  }
}
?>
  <input type="submit" value="<?php _e('Calculate shipping', _JAW_WC_CHECKOUT_RU_TEXT_DOMAIN); ?>">
</form>
<script>
  var copFormId = 'jaw-wc-checkout-ru-cart-form';
</script>
