<?php

/**
 * Template do Checkout Transparante Belluno
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
	exit;
}

$order = wc_get_order($order_id);

$base64_text =  $order->get_meta('_belluno_base64_text');
$base64_image =  $order->get_meta('_belluno_base64_image');
$hash = $order->get_meta('_belluno_transaction_hash');
$due_pix = date('d/m/Y à\s H:i', strtotime($order->get_meta('_belluno_due_pix')));

?>


<script type="text/javascript">
	jQuery(document).ready(function() {
		window.Pusher = Pusher;
		window.Echo = new Echo({
			broadcaster: 'pusher',
			key: 'ws_belluno',
			wsHost: 'websocket.belluno.digital',
			wsPort: 6001,
			wssPort: 443,
			forceTLS: true,
			encrypted: true,
			disableStats: true,
			enabledTransports: ['ws', 'wss'],
		});
		window.Echo.channel("transaction.<?php echo $hash; ?>")
			.listen('.TransactionPaid', (e) => {
				location.reload()
			});

		jQuery('#copy_pix').on('click', function(){
			var pix = document.getElementById('pix_code');
			pix.select();
			document.execCommand('copy');

			jQuery('#copy_pix').text('Código PIX copiado');
		});
	});
</script>

<section class="belluno_pix_section">
	<h2>Pagar com PIX</h2>
	<h4>Escaneie o QR Code em seu Internet Banking para fazer o pagamento.</h4>
	<div id="qr_code_image">
		<img src="data:image/png;base64, <?php echo $base64_image; ?>" alt="QR Code">
	</div>
	<p>Código válido até <b><?php echo $due_pix; ?>.</b></p>
	<p>Se preferir, você pode pagar copiando o código abaixo:</p>
	<input style="width: 100%; margin-bottom: 1.5rem;" type="text" id="pix_code" name="pix_code" placeholder="Código PIX" aria-label="Código PIX" value="<?php echo $base64_text; ?>" readonly="">
	<a id="copy_pix" class="button">Copiar código PIX</a>
</section>