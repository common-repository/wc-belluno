<?php
/**
 * Template do Checkout Transparante
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<div class="wbpf-panel">
	<div class="belluno-message">
		<?php //echo apply_filters( 'woocommerce_belluno_transparent_checkout_message', __( 'Selecione uma forma de pagamento para finalizar a compra:', 	'wc-belluno' ) ); ?>
	</div>

	<fieldset action="" method="post" id="woocommerce-belluno-payment-form" class="wbpf">
		<div class="woocommerce-tabs">
			<ul class="tabs">
				<?php if ( 'yes' == $this->credit_card ) : ?>
					<li class="active"><a href="#tab-credit-card">
						<svg width="20" height="16" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M0 2.667C0 1.959.263 1.28.732.78A2.423 2.423 0 012.5 0h15c.663 0 1.299.281 1.768.781.469.5.732 1.178.732 1.886V4H0V2.667zm0 4v6.666c0 .708.263 1.386.732 1.886.47.5 1.105.781 1.768.781h15c.663 0 1.299-.281 1.768-.781A2.76 2.76 0 0020 13.333V6.667H0zm3.75 2.666H5c.332 0 .65.14.884.39.234.25.366.59.366.944V12c0 .354-.132.693-.366.943-.235.25-.552.39-.884.39H3.75c-.332 0-.65-.14-.884-.39A1.38 1.38 0 012.5 12v-1.333c0-.354.132-.693.366-.943.235-.25.552-.39.884-.39z" fill="#000"/></svg>
						<?php _e( 'Cartão de Crédito', 'wc-belluno' ); ?>
					</a></li>
				<?php endif; ?>

				<?php if ( 'yes' == $this->billet_banking ) : ?>
					<li><a href="#tab-billet">
						<svg width="20" height="16" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M0 0h1.429v16H0V0zM7.143 0H8.57v14.546H7.143V0zM2.857 0h2.857v14.546H2.857V0zM10 0h2.857v14.546H10V0zM14.286 0h2.857v14.546h-2.857V0zM18.571 0H20v16h-1.429V0z" fill="#000"/></svg>
						<?php _e( 'Boleto Bancário', 'wc-belluno' ); ?>
					</a></li>
				<?php endif; ?>
			</ul>
			<?php if ( 'yes' == $this->credit_card ) : ?>
				<div id="tab-credit-card" class="panel entry-content" data-payment-method="CartaoCredito">
					<div class="form-row form-row-wide">
						<label for="credit-card-number"><?php _e( 'Número do Cartão', 'wc-belluno' ); ?></label>
						<input class="input-text" type="text" maxlength="20" name="credit_card_number" id="credit-card-number" placeholder="<?php _e( 'Apenas números', 'wc-belluno' ); ?>"/>
						<span class="cardFlag"></span>
					</div>

					<div class="form-row form-row-first">
						<label for="credit-card-expiration"><?php _e( 'Mês/Ano Vencimento', 'wc-belluno' ); ?></label>
						<div class="expiration">
							<input class="input-text" type="text" maxlength="20" name="credit-card-expiration" id="credit-card-expiration" />
						</div>
					</div>
					<div class="form-row form-row-last">
						<label for="credit-card-security-code"><?php _e( 'CVV', 'wc-belluno' ); ?></label>
						<input class="input-text" type="text" name="credit_card_security_code" id="credit-card-security-code" size="5"/>
					</div>

					<div class="form-row form-row-wide">
						<label for="credit-card-name"><?php _e( 'Titular do Cartão', 'wc-belluno' ); ?></label>
						<input class="input-text" type="text" name="credit_card_name" id="credit-card-name" value="" placeholder="<?php _e( 'Nome igual aparece impresso no cartão', 'wc-belluno' ); ?>"/>
					</div>

					<div class="form-row form-row-first">
						<label for="credit-card-birthdate"><?php _e( 'Data de Nascimento', 'wc-belluno' ); ?></label>
						<div class="birthdate">
							<input class="input-text" type="text" maxlength="20" name="credit-card-birthdate" id="credit-card-birthdate" />
						</div>
					</div>
					<div class="form-row form-row-last">
						<label for="credit-card-phone"><?php _e( 'Telefone do Titular', 'wc-belluno' ); ?></label>
						<input class="input-text" type="text" name="credit_card_phone" id="credit-card-phone" value="" />
					</div>

					<div class="form-row form-row-wide">
						<label for="credit-card-cpf"><?php _e( 'CPF/CNPJ do Titular', 'wc-belluno' ); ?></label>
						<input class="input-text" type="text" name="credit_card_cpf" id="credit-card-cpf" value="" />
					</div>

					<div class="form-row form-row-wide">
						<label for="credit-card-installments"><?php _e( 'Parcelas', 'wc-belluno' ); ?></label>
						<select class="input-text" style="width:100%" name="credit_card_installments" id="credit-card-installments">
							<option value="1"><?php echo sprintf( __( '1x R$ %s', 'wc-belluno' ), str_replace( '.', ',', $this->get_order_total() ) ); ?></option>
						</select>
					</div>

				</div>
			<?php endif; ?>

			<?php if ( 'yes' == $this->billet_banking ) : ?>
				<div id="tab-billet" class="panel entry-content" data-payment-method="BoletoBancario">
					<div class="boleto-informacao">
						<p>Ao finalizar a compra, você receberá o seu boleto bancário. É possível imprimir e pagar pelo site do seu banco ou em uma casa lotérica.</p>
						<p><b>Nota:</b> O pedido será confirmado apenas após a confirmação do pagamento.</p>
					</div>
				</div>
			<?php endif; ?>
		</div>
		<input type="hidden" name="urlBase" id="woocommerce-belluno-url-base" value="<?php echo site_url(); ?>" />
		<input type="hidden" name="urlPlugin" id="woocommerce-belluno-url-plugin" value="<?php echo plugins_url('/assets/images', plugin_dir_path( __FILE__ )) ?>" />
		<input type="hidden" name="installments" id="woocommerce-belluno-max-installments" value="<?php echo $this->installments_maxium ?>" />
		<input type="hidden" name="paymentMethod" id="woocommerce-belluno-payment-method" value="CartaoCredito"/>
		<input type="hidden" name="flagCard" id="woocommerce-belluno-flag-card"/>
	</fieldset>
</div>