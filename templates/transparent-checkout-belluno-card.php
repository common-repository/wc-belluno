<?php

/**
 * Template do Checkout Transparante Belluno
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
	exit;
}

global $woocommerce;
$cart = $woocommerce->cart;

$installments_maxium = $this->installments_maxium;
$installments_minimum_amount = $this->installments_minimum_amount ?? "";
$order_total = $this->get_order_total() - WC()->session->get('installment_fee');;
$discount = $this->discount ?? 0;

$discount_name = __('Desconto à vista');

$discount_amount = 0;
foreach ($cart->get_fees() as $key => $fee) {
	if ($fee->name === $discount_name) {
		$discount_amount = $fee->amount * -1;
	}
}

$order_total += $discount_amount;
?>

<fieldset id="belluno_credit_card_form">
	<div class="form-row form-row-wide">
		<label for="belluno_credit_card_number">
			<?php _e('Número do Cartão', 'wc-belluno'); ?>
			&nbsp;
			<abbr class="required" title="<?php _e('obrigatório', 'wc-belluno'); ?>">*</abbr>
		</label>
		<input type="text" name="belluno_credit_card_number" id="belluno_credit_card_number" placeholder="<?php _e('.... .... .... ....', 'wc-belluno'); ?>" />
	</div>
	<div class="form-row form-row-first">
		<label for="belluno_credit_card_expiration">
			<?php _e('Vencimento', 'wc-belluno'); ?>&nbsp;
			<abbr class="required" title="<?php _e('obrigatório', 'wc-belluno'); ?>">*</abbr>
		</label>
		<input type="text" name="belluno_credit_card_expiration" id="belluno_credit_card_expiration" placeholder="00/00" />
	</div>
	<div class="form-row form-row-last">
		<label for="belluno_credit_card_security_code">
			<?php _e('CVV', 'wc-belluno'); ?>
			&nbsp;
			<abbr class="required" title="<?php _e('obrigatório', 'wc-belluno'); ?>">*</abbr>
		</label>
		<input type="text" name="belluno_credit_card_security_code" id="belluno_credit_card_security_code" placeholder="000" />
	</div>

	<div class="form-row form-row-wide">
		<label for="belluno_credit_card_name">
			<?php _e('Titular do cartão', 'wc-belluno'); ?>
			&nbsp;
			<abbr class="required" title="<?php _e('obrigatório', 'wc-belluno'); ?>">*</abbr>
		</label>
		<input type="text" name="belluno_credit_card_name" id="belluno_credit_card_name" placeholder="<?php _e('Nome impresso no cartão', 'wc-belluno'); ?>" />
	</div>

	<div class="form-row form-row-first">
		<label for="belluno_credit_card_birthdate">
			<?php _e('Data de nascimento', 'wc-belluno'); ?>
			&nbsp;
			<abbr class="required" title="<?php _e('obrigatório', 'wc-belluno'); ?>">*</abbr>
		</label>
		<input type="text" name="belluno_credit_card_birthdate" id="belluno_credit_card_birthdate" placeholder="00/00/0000" />
	</div>
	<div class="form-row form-row-last">
		<label for="belluno_credit_card_phone">
			<?php _e('Telefone do titular', 'wc-belluno'); ?>
			&nbsp;
			<abbr class="required" title="<?php _e('obrigatório', 'wc-belluno'); ?>">*</abbr>
		</label>
		<input type="text" name="belluno_credit_card_phone" id="belluno_credit_card_phone" placeholder="(00) 00000-0000" />
	</div>

	<div class="form-row form-row-wide">
		<label for="belluno_credit_card_document">
			<?php _e('CPF/CNPJ do titular', 'wc-belluno'); ?>
			&nbsp;
			<abbr class="required" title="<?php _e('obrigatório', 'wc-belluno'); ?>">*</abbr>
		</label>
		<input type="text" name="belluno_credit_card_document" id="belluno_credit_card_document" placeholder="Digite aqui" />
	</div>

	<div class="form-row form-row-wide">
		<label for="belluno_credit_card_installments">
			<?php _e('Parcelas', 'wc-belluno'); ?>
			&nbsp;
			<abbr class="required" title="<?php _e('obrigatório', 'wc-belluno'); ?>">*</abbr>
		</label>
		<select style="width:100%" name="belluno_credit_card_installments" id="belluno_credit_card_installments">
			<option disabled value="" selected>Selecione a parcela</option>
			<?php for ($i = 1; $i <= $installments_maxium; $i++) {
				$installment_value = $order_total / $i;
				$installment_text = $i . 'x de R$ ' . number_format($installment_value, 2, ',', '.');
				$installment = WC()->session->get('installment_number');
				if ($i == 1 && $discount != 0) {
					$discounted_value = $installment_value * (1 - ($discount / 100));
					$installment_text = $i . 'x de R$ ' . number_format($discounted_value, 2, ',', '.') . ' (' . $discount . '% de desconto à vista)';
				} elseif ($this->get_option('has_installment_fees') === 'yes' && $this->get_option('installments_fee_' . $i) > 0) {
					$installment_value_with_fee = $installment_value * (1 + ($this->get_option('installments_fee_' . $i) / 100));
					$installment_text = $i . 'x de R$ ' . number_format($installment_value_with_fee, 2, ',', '.') . ' - com juros';
				}

				if ($installments_minimum_amount <= round($installment_value, 2) || $i === 1 || $installments_minimum_amount === "") {
					echo '<option ' . ($installment == $i ? 'selected' : '') . ' value="' . $i . '">' . $installment_text . '</option>';
				}
			} ?>


		</select>
	</div>

	<input type="hidden" name="belluno_visitor_id" id="belluno_visitor_id" />
	<input type="hidden" name="belluno_credit_card_brand" id="belluno_credit_card_brand" />
</fieldset>

<script>
	jQuery(document).ready(function($) {
		$('#belluno_credit_card_installments').change(function() {
			var installment_number = $(this).val();
			$.ajax({
				type: 'POST',
				url: '<?php echo admin_url('admin-ajax.php'); ?>',
				data: {
					action: 'set_custom_discount',
					installment_number: installment_number
				},
				success: function(result) {
					console.log(result);
					$(document.body).trigger('update_checkout');
				},
				error: function(result) {
					console.log(result);
				}
			});
		});
	});
</script>