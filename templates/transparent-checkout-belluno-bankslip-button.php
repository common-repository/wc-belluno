<?php

/**
 * Template do Checkout Transparante Belluno
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
	exit;
}
$order = wc_get_order($order_id);

$bankslip_url =  $order->get_meta('_belluno_bankslip_url');

?>

<section class="belluno_bankslip_section">
	<a style="margin-bottom: 1.5rem;" class="button" href="<?php echo $bankslip_url; ?>" target="_blank">Imprimir boleto</a>
</section>