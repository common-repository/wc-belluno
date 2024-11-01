<?php

/**
 * Template do Checkout Transparante Belluno
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
	exit;
}
global $woocommerce;
$discount = $this->discount;
$sub_total = (float)$woocommerce->cart->get_subtotal();
?>

<fieldset id="belluno_bank_slip_form">
	<?php if ($discount != 0) { ?>
		<p><b>Pague no boleto e receba <?php echo number_format($discount, 2, ',', '.'); ?>% de desconto</b></p>
	<?php } ?> 
	<p>Ao finalizar a compra, você receberá o seu boleto bancário. É possível imprimir e pagar pelo site do seu banco ou em uma casa lotérica.</p>
	<p><b>Nota:</b> O pedido será confirmado apenas após a confirmação do pagamento.</p>
	<?php if ($discount != 0) { ?>

		<p><b>Valor no Boleto: R$ <?php echo number_format($woocommerce->cart->total, 2, ',', '.'); ?></b>		
		<p><b>Economize: R$ <?php echo number_format($woocommerce->cart->get_cart_discount_total() + WC()->session->get('bankslip_discount'), 2, ',', '.'); ?></b></p>
	<?php } ?>

</fieldset>