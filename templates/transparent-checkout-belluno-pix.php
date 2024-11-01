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

<fieldset id="belluno_pix_form">
	<?php if ($discount != 0) { ?>
		<p><b>Pague no pix e receba <?php echo number_format($discount, 2, ',', '.'); ?>% de desconto</b></p>
	<?php } ?>
	<p>Ao finalizar a compra, será gerado o QR Code do PIX que pode ser pago em qualquer Internet Banking.</p>
	<p><b>Nota:</b> O pedido será confirmado apenas após a confirmação do pagamento.</p>
	<?php if ($discount != 0) { ?>
		<p><b>Valor no PIX: R$ <?php echo number_format($woocommerce->cart->total, 2, ',', '.'); ?></b>		
		<p><b>Economize: R$ <?php echo number_format($woocommerce->cart->get_cart_discount_total() + WC()->session->get('pix_discount'), 2, ',', '.'); ?></b></p>
	<?php } ?>
</fieldset>