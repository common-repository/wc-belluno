<?php

/**
 * Classe WC Belluno Card .
 *
 * Built the Belluno method.
 */

use phpseclib\Crypt\RSA;

class WC_Belluno_Card extends WC_Payment_Gateway
{
	public $api;
	public $login;
	public $invoice_prefix;
	public $token;
	public $key;
	public $credit_card;
	public $installments_maxium;
	public $installments_minimum_amount;
	public $installments_fee_1;
	public $installments_fee_2;
	public $installments_fee_3;
	public $installments_fee_4;
	public $installments_fee_5;
	public $installments_fee_6;
	public $installments_fee_7;
	public $installments_fee_8;
	public $installments_fee_9;
	public $installments_fee_10;
	public $installments_fee_11;
	public $installments_fee_12;
	public $sandbox;
	public $discount;

	/**
	 * Supported features such as 'default_credit_card_form', 'refunds'.
	 *
	 * @var array
	 */
	public $supports = array('refunds');

	/**
	 * Constructor for the gateway.
	 *
	 * @return void
	 */
	public function __construct()
	{
		$this->id             = 'belluno_card';
		//$this->icon           = apply_filters( 'woocommerce_belluno_icon', plugins_url( 'assets/images/logoBelluno.png', plugin_dir_path( __FILE__ ) ) );
		$this->has_fields     = false;
		$this->method_title   = __('Cartão Belluno', 'wc-belluno');
		$this->method_description = __('Utilize o gateway de pagamento da Belluno e simplifique as finanças do seu E-commerce.', 'wc-belluno');
		$this->order_button_text  = __('Finalizar Compra', 'wc-belluno');
		// Load the settings.
		$this->init_form_fields();
		$this->init_settings();

		// Display options.
		$this->title       = $this->get_option('title');
		$this->description = $this->get_option('description');

		$this->api = 'tc';

		// Gateway options.
		$this->login          = $this->get_option('login');
		$this->invoice_prefix = $this->get_option('invoice_prefix', 'WC-');

		$this->token = $this->get_option('token');
		$this->key   = $this->get_option('key');

		// Payment methods.
		$this->credit_card       = $this->get_option('credit_card');

		// Installments options.
		$this->installments_maxium   = $this->get_option('installments_maxium', 12);
		$this->installments_minimum_amount   = $this->get_option('installments_minimum_amount');

		// Installments fee.
		$this->installments_fee_1 = $this->get_option('installments_fee_1', 0);
		$this->installments_fee_2 = $this->get_option('installments_fee_2', 0);
		$this->installments_fee_3 = $this->get_option('installments_fee_3', 0);
		$this->installments_fee_4 = $this->get_option('installments_fee_4', 0);
		$this->installments_fee_5 = $this->get_option('installments_fee_5', 0);
		$this->installments_fee_6 = $this->get_option('installments_fee_6', 0);
		$this->installments_fee_7 = $this->get_option('installments_fee_7', 0);
		$this->installments_fee_8 = $this->get_option('installments_fee_8', 0);
		$this->installments_fee_9 = $this->get_option('installments_fee_9', 0);
		$this->installments_fee_10 = $this->get_option('installments_fee_10', 0);
		$this->installments_fee_11 = $this->get_option('installments_fee_11', 0);
		$this->installments_fee_12 = $this->get_option('installments_fee_12', 0);

		// Debug options.
		$this->sandbox = $this->get_option('sandbox');

		//discount payment 1x
		$this->discount = $this->get_option('discount', 0);


		// Actions.

		add_action('woocommerce_api_wc_belluno_gateway', array($this, 'check_ipn_response'));
		add_action('wp_enqueue_scripts', array($this, 'scripts'));
		add_action('woocommerce_receipt_belluno', array($this, 'receipt_page'));
		add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
		// Display admin notices.
		$this->admin_notices();
	}

	/**
	 * Chama os plugins de script do front-end
	 *
	 * @return void
	 */
	public function scripts()
	{
		if ('tc' == $this->api && is_checkout()) {
			wp_enqueue_style('wc-belluno-checkout', plugins_url('assets/css/belluno.min.css', plugin_dir_path(__FILE__)), array(), '', 'all');
		}
	}

	protected function transparentCheckoutPayment($data)
	{
		$order = wc_get_order($data['order_id']);

		$response = self::creditPayment($data);

		$body    = wp_remote_retrieve_body($response);
		$http_code = wp_remote_retrieve_response_code($response);

		$json = json_decode($body);
		$json->status = $http_code;

		if ($json->status !== 200) return $json;

		self::addMeta($order, $json);
		self::updateOrderStatus($order, $json);

		return $json;
	}

	protected function updateOrderStatus($order, $json)
	{
		$transactionID =  $order->get_meta('_belluno_transaction_id');
		$message = "ID da Transação Belluno: $transactionID";

		switch ($json->transaction->status) {
			case "Paid":
				$order->update_meta_data('_nsu_payment', $json->transaction->nsu_payment);
				$order->update_meta_data('_installments_number', $json->transaction->installments_number);

				$order->update_meta_data('_card', $json->transaction->card);
				$order->update_meta_data('_card_brand', $json->transaction->brand);
				$order->update_meta_data('_card_holder', $json->transaction->cardholder);
				$order->update_meta_data('_card_holder_document', $json->transaction->cardholder_document);
				$order->update_meta_data('_card_holder_cellphone', $json->transaction->cardholder_cellphone);
				$order->update_meta_data('_card_holder_birthday', $json->transaction->cardholder_birthday);

				$order->save();

				$order->update_status('processing', __('Belluno: O Pagamento foi aprovado com sucesso! ' . $message, 'wc-belluno'));
				$order->payment_complete($json->transaction->transaction_id);
				break;

			case "Refused":
				$order->update_status('failed', __('Belluno: O Pagamento foi recusado! ' . $message, 'wc-belluno'));
				//Pagamento recusado...Estoque volta.
				if (function_exists('wc_increase_stock_levels')) {
					wc_increase_stock_levels($order_id);
				}
				break;

			case "Processing":
				$order->update_status('on-hold', __('Belluno: O Pagamento está sendo processado! ' . $message, 'wc-belluno'));
				break;
			case "Manual Analysis":
				$order->update_status('on-hold', __('Belluno: O Pagamento está sendo analisado! ' . $message, 'wc-belluno'));
				break;

			case "Client Manual Analysis":
				$order->update_status('on-hold', __('Belluno: O Pagamento está sendo analisado! ' . $message, 'wc-belluno'));
				break;
			case "Open":
			case "Unpaid":
				$order->add_order_note(__('Belluno: Aguardando o pagamento. ' . $message, 'wc-belluno'));
				break;
			default:
				$order->add_order_note(__('Status Belluno: ' . $json->transaction->status . ". " . $message, 'wc-belluno'));
				break;
		}
	}


	protected function addMeta($order, $data)
	{
		$order->update_meta_data("_belluno_status", $data->transaction->status);
		$order->update_meta_data("_belluno_transaction_id", $data->transaction->transaction_id);
		$order->save();
	}

	/**
	 * Compatibilidade com versões anteriores à versão 2.1.
	 *
	 * @return object Returns the main instance of WooCommerce class.
	 */
	protected function woocommerce_instance()
	{
		if (function_exists('WC')) {
			return WC();
		} else {
			global $woocommerce;
			return $woocommerce;
		}
	}

	/**
	 * Exibe notificações quando há algo errado com a configuração.
	 *
	 * @return void
	 */
	protected function admin_notices()
	{
		if (
			isset($_GET['page']) &&
			isset($_GET['tab']) &&
			isset($_GET['section']) &&
			is_admin() &&
			$_GET['page'] === 'wc-settings' &&
			$_GET['tab'] === 'checkout' &&
			$_GET['section'] === 'belluno_card'
		) {
			static $show_once = true;
			if ($show_once) {
				// Verifica se o token não está vazio
				if (empty($this->token)) {
					add_action('admin_notices', array($this, 'token_missing_message'));
				}
				// Verifica se o token antifraude não está vazio.
				if (empty($this->key)) {
					add_action('admin_notices', array($this, 'key_missing_message'));
				}
				// Verifica se a moeda é valida
				if (!$this->moedas_suportadas()) {
					add_action('admin_notices', array($this, 'currency_not_supported_message'));
				}
				// Verifica se o site possui SSL
				if ((empty($_SERVER['HTTPS']) || $_SERVER['HTTPS'] == 'off')) {
					add_action('admin_notices', array($this, 'noticeHttps'));
				}
				$show_once = false;
			}
		}
	}

	/**
	 * Retorna true caso a moeda estiver entre as suportadas.
	 *
	 * @return bool
	 */
	public function moedas_suportadas()
	{
		return ('BRL' == get_woocommerce_currency());
	}

	/**
	 * Retorna um valor indicando que o Gateway está disponível ou não.
	 * É chamado automaticamente pelo WooCommerce antes de permitir
	 * que os clientes usem o gateway para pagamento.
	 *
	 * @return bool
	 */
	public function is_available()
	{
		if ('html' != $this->api) {
			$api = (!empty($this->token) && !empty($this->key));
		} else {
			$api = (!empty($this->login));
		}

		$available = ('yes' == $this->settings['enabled']) && $api && $this->moedas_suportadas();

		return $available;
	}

	/**
	 * Opções do Painel Admin.
	 */
	public function admin_options()
	{
		wp_enqueue_script('wc-belluno', plugins_url('assets/js/admin.min.js', plugin_dir_path(__FILE__)), array('jquery'), '', true);

		echo '<h3>' . __('Belluno', 'wc-belluno') . '</h3>';
		echo '<p>' . __('Para impulsionar o crescimento do seu negócio.', 'wc-belluno') . '</p>';

		// Generate the HTML For the settings form.
		echo '<table class="form-table">';
		$this->generate_settings_html();
		echo '</table>';
	}

	/**
	 * Check API Response.
	 *
	 * @return void
	 */
	public function check_ipn_response()
	{
		@ob_clean();
		if (isset($_POST['id_transacao'])) {
			header('HTTP/1.0 200 OK');
			do_action('valid_belluno_ipn_request', stripslashes_deep($_POST));
		} else {
			wp_die(__('Erro de solicitação à API Belluno', 'wc-belluno'));
		}
	}

	/**
	 * Initialise Gateway Settings Form Fields.
	 *
	 * @return void
	 */
	public function init_form_fields()
	{
		$this->form_fields = array(
			'enabled' => array(
				'title' => __('Ativar/Inativar', 'wc-belluno'),
				'type' => 'checkbox',
				'label' => __('Ativar o pagamento via cartão', 'wc-belluno'),
				'default' => 'no'
			),
			'title' => array(
				'title' => __('Título', 'wc-belluno'),
				'type' => 'text',
				'description' => __('Define o título que o usuário vê durante o checkout.', 'wc-belluno'),
				'desc_tip' => true,
				'default' => __('Pague via Cartão', 'wc-belluno'),
				'custom_attributes' => array(
					'required' => 'required'
				),
			),
			'description' => array(
				'title' => __('Descrição', 'wc-belluno'),
				'type' => 'textarea',
				'description' => __('Define a descrição que o usuário vê durante o checkout.', 'wc-belluno'),
				'default' => __('Pagamento via Cartão Belluno', 'wc-belluno'),
				'custom_attributes' => array(
					'required' => 'required'
				),
			),
			'api_section' => array(
				'title' => __('API de Pagamento', 'wc-belluno'),
				'type' => 'title',
				'description' => '',
			),
			'api' => array(
				'title' => __('API de Pagamento Belluno', 'wc-belluno'),
				'type' => 'select',
				'description' => '',
				'default' => 'tc',
				'options' => array(
					'tc' => __('Transparent Checkout', 'wc-belluno')
				)
			),
			'token' => array(
				'title' => __('Token de Acesso', 'wc-belluno'),
				'type' => 'text',
				'description' => __('Por favor, digite o seu Token de acesso;  isso é necessário para receber o pagamento.', 'wc-belluno'),
				'desc_tip' => true,
				'default' => '',
				'custom_attributes' => array(
					'required' => 'required'
				),
			),
			'discount' => array(
				'title' => __('Desconto à vista', 'wc-belluno'),
				'type' => 'number',
				'custom_attributes' => array('step' => 'any', 'min' => '0'),
				'desc_tip' => true,
				'description' => sprintf(__('Caso não queria aplicar desconto apenas deixe zero ou vazio. obs: o desconto é aplicado em porcentagem', 'wc-belluno'), 'https://belluno.digital/contato/'),
			),
			'key' => array(
				'title' => __('Token Konduto Antifraude', 'wc-belluno'),
				'type' => 'text',
				'description' => __('Por favor, digite o seu Token Antifraude;  isso é necessário para a segurança da sua transação.', 'wc-belluno'),
				'desc_tip' => true,
				'default' => '',
				'custom_attributes' => array(
					'required' => 'required'
				),
			),
			'installments_section' => array(
				'title' => __('Configurações de Cartão de Crédito', 'wc-belluno'),
				'type' => 'title',
				'description' => '',
			),
			'installments_maxium' => array(
				'title' => __('Parcela Máxima', 'wc-belluno'),
				'type' => 'select',
				'description' => __('Indique o máximo em que o pedido poderá ser parcelado.', 'wc-belluno'),
				'desc_tip' => true,
				'default' => '12',
				'options' => array(
					2 => '2',
					3 => '3',
					4 => '4',
					5 => '5',
					6 => '6',
					7 => '7',
					8 => '8',
					9 => '9',
					10 => '10',
					11 => '11',
					12 => '12'
				)
			),
			'installments_minimum_amount' => array(
				'title' => __('Valor mínimo por parcela', 'wc-belluno'),
				'type' => 'number',
				'description' => __('Valor mínimo por parcela.', 'wc-belluno'),
				'desc_tip' => false,
			),

			'has_installment_fees' => array(
				'title' => __('Juros por parcela', 'wc-belluno'),
				'type' => 'checkbox',
				'label' => __('Deseja habilitar a configuração de juros por parcela?', 'wc-belluno'),
			),

			'installments_fee_1' => array(
				'title' => __('Júros da parcela 1', 'wc-belluno'),
				'type' => 'number',
				'custom_attributes' => array('step' => 'any', 'min' => '0'),
				'description' => __('Informe o percentual de juros que você quer adicionar na 1 parcela. Para não adicionar juros, deixe esse valor igual a 0 ou vazio.', 'wc-belluno'),
				'desc_tip' => true,
			),
			'installments_fee_2' => array(
				'title' => __('Júros da parcela 2', 'wc-belluno'),
				'type' => 'number',
				'custom_attributes' => array('step' => 'any', 'min' => '0'),
				'description' => __('Informe o percentual de juros que você quer adicionar na 2 parcela. Para não adicionar juros, deixe esse valor igual a 0 ou vazio.', 'wc-belluno'),
				'desc_tip' => true,
			),
			'installments_fee_3' => array(
				'title' => __('Júros da parcela 3', 'wc-belluno'),
				'type' => 'number',
				'custom_attributes' => array('step' => 'any', 'min' => '0'),
				'description' => __('Informe o percentual de juros que você quer adicionar na 3 parcela. Para não adicionar juros, deixe esse valor igual a 0 ou vazio.', 'wc-belluno'),
				'desc_tip' => true,
			),
			'installments_fee_4' => array(
				'title' => __('Júros da parcela 4', 'wc-belluno'),
				'type' => 'number',
				'custom_attributes' => array('step' => 'any', 'min' => '0'),
				'description' => __('Informe o percentual de juros que você quer adicionar na 4 parcela. Para não adicionar juros, deixe esse valor igual a 0 ou vazio.', 'wc-belluno'),
				'desc_tip' => true,
			),
			'installments_fee_5' => array(
				'title' => __('Júros da parcela 5', 'wc-belluno'),
				'type' => 'number',
				'custom_attributes' => array('step' => 'any', 'min' => '0'),
				'description' => __('Informe o percentual de juros que você quer adicionar na 5 parcela. Para não adicionar juros, deixe esse valor igual a 0 ou vazio.', 'wc-belluno'),
				'desc_tip' => true,
			),
			'installments_fee_6' => array(
				'title' => __('Júros da parcela 6', 'wc-belluno'),
				'type' => 'number',
				'custom_attributes' => array('step' => 'any', 'min' => '0'),
				'description' => __('Informe o percentual de juros que você quer adicionar na 6 parcela. Para não adicionar juros, deixe esse valor igual a 0 ou vazio.', 'wc-belluno'),
				'desc_tip' => true,
			),
			'installments_fee_7' => array(
				'title' => __('Júros da parcela 7', 'wc-belluno'),
				'type' => 'number',
				'custom_attributes' => array('step' => 'any', 'min' => '0'),
				'description' => __('Informe o percentual de juros que você quer adicionar na 7 parcela. Para não adicionar juros, deixe esse valor igual a 0 ou vazio.', 'wc-belluno'),
				'desc_tip' => true,
			),
			'installments_fee_8' => array(
				'title' => __('Júros da parcela 8', 'wc-belluno'),
				'type' => 'number',
				'custom_attributes' => array('step' => 'any', 'min' => '0'),
				'description' => __('Informe o percentual de juros que você quer adicionar na 8 parcela. Para não adicionar juros, deixe esse valor igual a 0 ou vazio.', 'wc-belluno'),
				'desc_tip' => true,
			),
			'installments_fee_9' => array(
				'title' => __('Júros da parcela 9', 'wc-belluno'),
				'type' => 'number',
				'custom_attributes' => array('step' => 'any', 'min' => '0'),
				'description' => __('Informe o percentual de juros que você quer adicionar na 9 parcela. Para não adicionar juros, deixe esse valor igual a 0 ou vazio.', 'wc-belluno'),
				'desc_tip' => true,
			),
			'installments_fee_10' => array(
				'title' => __('Júros da parcela 10', 'wc-belluno'),
				'type' => 'number',
				'custom_attributes' => array('step' => 'any', 'min' => '0'),
				'description' => __('Informe o percentual de juros que você quer adicionar na 10 parcela. Para não adicionar juros, deixe esse valor igual a 0 ou vazio.', 'wc-belluno'),
				'desc_tip' => true,
			),
			'installments_fee_11' => array(
				'title' => __('Júros da parcela 11', 'wc-belluno'),
				'type' => 'number',
				'custom_attributes' => array('step' => 'any', 'min' => '0'),
				'description' => __('Informe o percentual de juros que você quer adicionar na 11 parcela. Para não adicionar juros, deixe esse valor igual a 0 ou vazio.', 'wc-belluno'),
				'desc_tip' => true,
			),
			'installments_fee_12' => array(
				'title' => __('Júros da parcela 12', 'wc-belluno'),
				'type' => 'number',
				'custom_attributes' => array('step' => 'any', 'min' => '0'),
				'description' => __('Informe o percentual de juros que você quer adicionar na 12 parcela. Para não adicionar juros, deixe esse valor igual a 0 ou vazio.', 'wc-belluno'),
				'desc_tip' => true,
			),
			'async' => array(
				'title' => __('Rota de modo assíncrono', 'wc-belluno'),
				'type' => 'checkbox',
				'default' => 'no',
				'description' => __('Habilitar a rota de modo assíncrono para o processamento de pagamentos.', 'wc-belluno'),
			),

			'sandbox_section' => array(
				'title' => __('Configurações Sandbox', 'wc-belluno'),
				'type' => 'title',
				'description' => '',
			),
			'sandbox' => array(
				'title' => __('Modo de Testes', 'wc-belluno'),
				'type' => 'checkbox',
				'label' => __('Habilitar o modo de testes da API Belluno', 'wc-belluno'),
				'default' => 'no',
				'description' => sprintf(__('O modo de testes da API Belluno pode ser usado para testar pagamentos. Entre em contato conosco para saber mais clicando <a href="%s">aqui</a>.', 'wc-belluno'), 'https://belluno.digital/contato/'),
			),
		);
	}

	public function payment_scripts()
	{
		if (!is_admin()) return;

		wp_register_script('wc-belluno', plugins_url('assets/js/admin.js', __FILE__), array('jquery'));
		wp_enqueue_script('wc-belluno');
	}

	public function validate_fields()
	{
		// Validate credit card number
		if (empty(sanitize_text_field($_POST['belluno_credit_card_number']))) {
			wc_add_notice('O número do cartão de crédito é obrigatório. Tente novamente.', 'error');
			return false;
		}
		if (!$this->checkCreditCard(sanitize_text_field($_POST['belluno_credit_card_number']))) {
			wc_add_notice('O número do cartão de crédito informado não é válido. Verifique-o e tente novamente.', 'error');
			return false;
		}

		// Validate credit card brand
		if (empty(sanitize_text_field($_POST['belluno_credit_card_brand']))) {
			wc_add_notice('Não conseguimos identificar a bandeira do cartão de crédito. Verifique se você informou o número correto do cartão.', 'error');
			return false;
		}

		// Validate credit card expiration date
		if (empty(sanitize_text_field($_POST['belluno_credit_card_expiration']))) {
			wc_add_notice('O vencimento do cartão de crédito é obrigatório. Tente novamente.', 'error');
			return false;
		}

		if (!$this->checkExpirationDate(sanitize_text_field($_POST['belluno_credit_card_expiration']))) {
			wc_add_notice('O vencimento do cartão de crédito informado não é válido. Verifique-o e tente novamente.', 'error');
			return false;
		}

		// Validate credit card security code
		if (empty(sanitize_text_field($_POST['belluno_credit_card_security_code']))) {
			wc_add_notice('O código de verificação (CVV) do cartão de crédito é obrigatório. Tente novamente.', 'error');
			return false;
		}
		if (!$this->checkCVV(sanitize_text_field($_POST['belluno_credit_card_security_code']))) {
			wc_add_notice('O código de verificação (CVV) do cartão de crédito informado não é válido. Verifique-o e tente novamente.', 'error');
			return false;
		}

		// Validate credit card name
		if (empty(sanitize_text_field($_POST['belluno_credit_card_name']))) {
			wc_add_notice('O nome do titular do cartão de crédito é obrigatório. Tente novamente.', 'error');
			return false;
		}
		if (!$this->checkName(sanitize_text_field($_POST['belluno_credit_card_name']))) {
			wc_add_notice('O nome do titular do cartão não é válido. Verifique-o e tente novamente.', 'error');
			return false;
		}

		// Validate credit card birth date
		// if (empty(sanitize_text_field($_POST['belluno_credit_card_birthdate']))) {
		// 	wc_add_notice('A data de nascimento do titular do cartão de crédito é obrigatório. Tente novamente.', 'error');
		// 	return false;
		// }
		if (
			!empty(sanitize_text_field($_POST['belluno_credit_card_birthdate'])) &&
			!$this->checkBirthdayDate(sanitize_text_field($_POST['belluno_credit_card_birthdate']))
		) {
			wc_add_notice('A data de nascimento do titular do cartão não é válida. Verifique-a e tente novamente.', 'error');
			return false;
		}

		// Validate credit card phone
		if (empty(sanitize_text_field($_POST['belluno_credit_card_phone']))) {
			wc_add_notice('O telefone do titular do cartão de crédito é obrigatório. Tente novamente.', 'error');
			return false;
		}
		if (!$this->checkPhone(sanitize_text_field($_POST['belluno_credit_card_phone']))) {
			wc_add_notice('O telefone do titular do cartão não é válido. Verifique-o e tente novamente.', 'error');
			return false;
		}

		// Validate credit card document
		$document = sanitize_text_field($_POST['belluno_credit_card_document']);
		if (empty($document)) {
			wc_add_notice('O CPF/CNPJ do titular do cartão de crédito é obrigatório. Tente novamente.', 'error');
			return false;
		} else if (strlen($document) === 14) {
			if (!$this->checkCPF($document)) {
				wc_add_notice(__('O CPF do titular do cartão de crédito não é valido. Verifique-o e tente novamente.'), 'error');
				return false;
			}
		} else if (strlen($document) === 18) {
			if (!$this->checkCNPJ($document)) {
				wc_add_notice(__('O CNPJ do titular do cartão de crédito não é valido. Verifique-o e tente novamente.'), 'error');
				return false;
			}
		} else {
			wc_add_notice(__('O CPF/CNPJ do titular do cartão de crédito não é valido. Verifique-o e tente novamente.'), 'error');
			return false;
		}

		// Validate installments
		if (empty(sanitize_text_field($_POST['belluno_credit_card_installments']))) {
			wc_add_notice('Selecione o número de parcelas e tente novamente.', 'error');
			return false;
		}

		$args = array(
			'body' => json_encode(array(
				"document" => !empty($_POST['billing_cpf']) ? sanitize_text_field($_POST['billing_cpf']) : $document,
				"postal_code" => str_replace('-', '', sanitize_text_field($_POST['billing_postcode'])),
			)),
			'headers' => array(
				'Content-Language'  => 'pt_BR',
				'Accept'  => 'application/json',
				'Content-Type'  => 'application/json',
				'Authorization' => 'Bearer ' . self::getWPOptions('token'),
			),
			'timeout' => 15,
			'httpversion' => '1.0',
			'sslverify' => true,
		);

		$response = wp_safe_remote_post("https://api.belluno.digital/v2/transaction/validate/customer", $args);

		$body = json_decode(wp_remote_retrieve_body($response));

		if ($body->valid) {
			wc_add_notice('A transação não pode ser concluída no momento! Tente novamente mais tarde.', 'error');
			wp_redirect($_SERVER['REQUEST_URI']);
			return false;
		}


		return true;
	}

	/**
	 * Check if a credit card is valid.
	 *
	 * @param  string $value
	 * @return bool
	 */
	public function checkCreditCard(string $value): bool
	{
		if (strlen($value) === 0) return false;
		if (preg_match("/[^0-9-\s]+/", $value)) return false; // accept only digits, dashes or spaces

		$nCheck = 0;
		$nDigit = 0;
		$bEven = false;

		$value = str_replace(' ', '', $value);
		for ($n = strlen($value) - 1; $n >= 0; $n--) {
			$cDigit = $value[$n];
			$nDigit = (int) $cDigit;
			if ($bEven) {
				if (($nDigit *= 2) > 9) $nDigit -= 9;
			}
			$nCheck += $nDigit;
			$bEven = !$bEven;
		}
		return $nCheck % 10 == 0;
	}

	/**
	 * Check if credit card expiration date is valid.
	 *
	 * @param  string $date
	 * @return bool
	 */
	public function checkExpirationDate(string $date): bool
	{
		$date_array = explode("/", $date);
		if ($date_array == null) return false;

		$month = $date_array[0];
		$year = $date_array[1];
		if (strlen($year) === 2) $year = "20" . $year;

		if ($month < 1 || $month > 12) return false;
		if ($year < date("Y") || $year > 2050) return false;

		return true;
	}

	/**
	 * Check if credit card security code is valid.
	 *
	 * @param  string $cvv
	 * @return bool
	 */
	public function checkCVV(string $cvv): bool
	{
		if (strlen($cvv) == 0) return false;
		if (strlen($cvv) != 3 && strlen($cvv) != 4) return false;
		return true;
	}

	/**
	 * Check if credit card name is valid.
	 *
	 * @param  string $name
	 * @return bool
	 */
	public function checkName(string $name): bool
	{
		if (strlen($name) == 0) return false;
		if (strlen($name) > 50) return false;
		return true;
	}

	/**
	 * Check if credit card birthday date is valid.
	 *
	 * @param  string $date
	 * @return bool
	 */
	public function checkBirthdayDate(string $date): bool
	{
		$date_array = explode("/", $date);
		if ($date_array == null) return false;

		$day = $date_array[0];
		$month = $date_array[1];
		$year = $date_array[2];

		if ($year < 1930 || $year >= date("Y")) return false;

		if ($month < 1 || $month > 12) return false;
		else if ($day < 1 || $day > 31) return false;
		else if (
			($month == 4 || $month == 6 || $month == 9 || $month == 11) &&
			$day == 31
		)
			return false;
		else if ($month == 2) {
			$isleap = $year % 4 == 0 && ($year % 100 != 0 || $year % 400 == 0);
			if ($day > 29 || ($day == 29 && !$isleap)) return false;
		}
		return true;
	}

	/**
	 *  Check if credit card phone is valid.
	 *
	 * @param  string $phone
	 * @return bool
	 */
	public function checkPhone(string $phone): bool
	{
		return strlen($phone) >= 14;
	}


	/**
	 * Check if CPF is valid.
	 *
	 * @param  string $cpf
	 * @return bool
	 */
	public function checkCPF(string $cpf): bool
	{
		$cpf = preg_replace('/[^0-9]/is', '', $cpf);
		if (strlen($cpf) != 11) {
			return false;
		}
		if (preg_match('/(\d)\1{10}/', $cpf)) {
			return false;
		}
		for ($t = 9; $t < 11; $t++) {
			for ($d = 0, $c = 0; $c < $t; $c++) {
				$d += $cpf[$c] * (($t + 1) - $c);
			}
			$d = ((10 * $d) % 11) % 10;
			if ($cpf[$c] != $d) {
				return false;
			}
		}
		return true;
	}

	/**
	 * Check if CNPJ is valid.
	 *
	 * @param  string $cnpj
	 * @return bool
	 */
	public function checkCNPJ(string $cnpj): bool
	{
		$cnpj = preg_replace('/[^0-9]/', '', (string) $cnpj);

		// Valida tamanho
		if (strlen($cnpj) != 14)
			return false;

		// Verifica se todos os digitos são iguais
		if (preg_match('/(\d)\1{13}/', $cnpj))
			return false;

		// Valida primeiro dígito verificador
		for ($i = 0, $j = 5, $soma = 0; $i < 12; $i++) {
			$soma += $cnpj[$i] * $j;
			$j = ($j == 2) ? 9 : $j - 1;
		}

		$resto = $soma % 11;

		if ($cnpj[12] != ($resto < 2 ? 0 : 11 - $resto))
			return false;

		// Valida segundo dígito verificador
		for ($i = 0, $j = 6, $soma = 0; $i < 13; $i++) {
			$soma += $cnpj[$i] * $j;
			$j = ($j == 2) ? 9 : $j - 1;
		}

		$resto = $soma % 11;

		return $cnpj[13] == ($resto < 2 ? 0 : 11 - $resto);
	}

	/**
	 * Process the payment and return the result.
	 *
	 * @param int    $order_id Order ID.
	 * @return array Redirect.
	 */
	public function process_payment($order_id)
	{
		$order = wc_get_order($order_id);

		/* Sanitizing $_POST */
		$data['belluno_credit_card_expiration'] = sanitize_text_field($_POST['belluno_credit_card_expiration']);
		$data['belluno_credit_card_number'] = sanitize_text_field($_POST['belluno_credit_card_number']);
		$data['belluno_credit_card_security_code'] = sanitize_text_field($_POST['belluno_credit_card_security_code']);
		$data['belluno_credit_card_name'] = sanitize_text_field($_POST['belluno_credit_card_name']);
		$data['belluno_credit_card_document'] = sanitize_text_field($_POST['belluno_credit_card_document']);
		$data['belluno_credit_card_phone'] = sanitize_text_field($_POST['belluno_credit_card_phone']);
		$data['belluno_credit_card_birthdate'] = sanitize_text_field($_POST['belluno_credit_card_birthdate'] ?? '');
		$data['belluno_credit_card_brand'] = sanitize_text_field($_POST['belluno_credit_card_brand']);
		$data['belluno_credit_card_installments'] = sanitize_text_field($_POST['belluno_credit_card_installments']);
		$data['belluno_visitor_id'] = sanitize_text_field($_POST['belluno_visitor_id'] !== "" ? $_POST['belluno_visitor_id'] : (string)$order_id);

		if (isset($_POST['ship_to_different_address'])) $data['ship_to_different_address'] = sanitize_text_field($_POST['ship_to_different_address']);

		$discount_name = __('Desconto à vista');
		$fees = $order->get_fees();

		foreach ($fees as $key => $fee) {
			if ($fee->get_name() === $discount_name && $data['belluno_credit_card_installments'] != 1) {
				unset($fees[$key]);
				$order->remove_fee($key);
				$order->calculate_totals();
				$order->save();
			}
		}

		$data['order_id'] = $order_id;
		$data['belluno_credit_card_expiration'] = $this->formatDate($data['belluno_credit_card_expiration']);

		$response = $this->transparentCheckoutPayment($data);

		if ($response->status !== 200) {
			if ($response->message) {
				$this->belluno_add_notice('Ocorreu um erro ao processar seu pagamento: ' . $response->message . '. Verifique os dados do cartão e tente novamente!');
				$order->update_status('failed', 'Ocorreu um erro ao processar o pedido.');
			} else if ($response->errors) {
				$details = "";
				foreach ($response->errors as $error) {
					foreach ($error as $message) {
						$this->belluno_add_notice($message);
						$details .= $message . "<br>";
					}
				}
				$order->update_status('failed', $details);
			}
			return;
		} else {
			$order = wc_get_order($order_id);
			if ($order->has_status('failed')) {
				if ($response->transaction->status === "Refused") {
					$this->increment_failed_attempt_counter($response->transaction->reason);
				}

				$this->belluno_add_notice('Ocorreu um erro ao processar seu pagamento: ' . $response->transaction->reason);
				return;
			} else {
				WC()->cart->empty_cart();
				return array(
					'result'   => 'success',
					'redirect' => $this->get_return_url($order)
				);
			}
		}
	}


	/**
	 * Force the backend make a download of new version of page
	 * Thats include the new version of js
	 *
	 * @param  string     $message the message of error.
	 * @return void.
	 */
	private function increment_failed_attempt_counter($message)
	{
		$transient_name = 'failed_payment_attempts';
		$transient_expiration = 3600;

		$counter = get_transient($transient_name);
		if ($counter === false) {
			$counter = 0;
		}

		$counter++;

		set_transient($transient_name, $counter, $transient_expiration);

		if ($counter >= 3) {
			// Reseta o contador
			set_transient($transient_name, 0, $transient_expiration);
			$this->belluno_add_notice('Ocorreu um erro ao processar seu pagamento: ' . $message);
			wp_redirect($_SERVER['REQUEST_URI']);
			exit;
		}
		return;
	}

	/**
	 * Process refund.
	 *
	 * If the gateway declares 'refunds' support, this will allow it to refund.
	 * a passed in amount.
	 *
	 * @param  int        $order_id Order ID.
	 * @param  float|null $amount Refund amount.
	 * @param  string     $reason Refund reason.
	 * @return boolean True or false based on success, or a WP_Error object.
	 */
	public function process_refund($order_id, $amount = null, $reason = '')
	{
		return new WP_Error('broke', __("Realize o reembolso através da plataforma Belluno.", "my_textdomain"));;
	}

	protected function getWPOptions($option)
	{
		return get_option('woocommerce_belluno_card_settings')[$option];
	}

	protected function generate_cardHash($data)
	{

		if ('yes' == self::getWPOptions('sandbox')) {
			$url = 'https://ws-sandbox.bellunopag.com.br/transaction/card_hash_key/';
		} else {
			$url = 'https://api.belluno.digital/transaction/card_hash_key';
		}

		$responseDecoded =  self::geturl($url, self::getWPOptions('token'));

		if ($responseDecoded->error) return;

		$card_number = preg_replace("/[^0-9]/", "", $data['belluno_credit_card_number']);
		$card_expiration_date = str_replace('/', '', $data['belluno_credit_card_expiration']);
		$card_cvv = $data['belluno_credit_card_security_code'];

		//cartão de testes liberado pela API Belluno
		if ('yes' == self::getWPOptions('sandbox')) {
			$card_number = '4970100000000055';
			$card_expiration_date = '112021';
			$card_cvv = '123';
		}

		$stringHash = "card_number=$card_number&card_expiration_date=$card_expiration_date&card_cvv=$card_cvv";

		$rsa = new RSA();

		$rsa->setEncryptionMode(RSA::ENCRYPTION_PKCS1);

		$rsa->loadKey($responseDecoded->rsa_public_key);

		$crypto = $rsa->encrypt($stringHash);

		$crypto_64 = base64_encode($crypto);

		$card_hash =  "" . $responseDecoded->id . "_" . $crypto_64 . "";

		return $card_hash;
	}
	protected function geturl($url, $token)
	{
		$args = array(
			'headers' => array(
				'Content-Type'  => 'application/json',
				'Authorization' => 'Bearer ' . $token,
			)
		);
		$request = wp_remote_get($url, $args);
		$body    = wp_remote_retrieve_body($request);
		$http_code = wp_remote_retrieve_response_code($request);

		if ($http_code == 200) return json_decode($body);
	}

	protected function creditPayment($data)
	{
		$order = wc_get_order($data['order_id']);

		$cardHash = self::generate_cardHash($data);

		$ship_to = isset($data['ship_to_different_address']) ? true : false;

		$shipping = $this->addShipping($order, $ship_to);

		if ('yes' == self::getWPOptions('sandbox')) {
			$url = 'https://ws-sandbox.bellunopag.com.br/transaction';
		} else {
			$url = 'https://api.belluno.digital/transaction';
		}
		$url = self::getWPOptions('async') == 'yes' ? $url . '/async' : $url;

		if (sizeof($order->get_items()) > 0) {
			foreach ($order->get_items() as $item) {
				if ($item['qty']) {
					$cart[] = array(
						"product_name" => $item['name'],
						"quantity" => $item['qty'],
						"unit_value" => $item['total'] / $item['qty']
					);
				}
			}
		}
		$client_name = $order->get_billing_first_name() . " " . $order->get_billing_last_name();

		$person_type =  $order->get_meta('_billing_persontype');

		$document = (
			$order->get_meta('_billing_cpf') ??
			$order->get_meta('_billing_cpf_1') ??
			$order->get_meta('_billing_client_cpf') ??
			$order->get_meta('_billing_cnpj')
		);

		if ($person_type == "2") {
			$document = $order->get_meta('_billing_cnpj');
			$company_name = $order->get_meta('_billing_company');
			if (($company_name ?? "") !== "" && strlen($company_name) > 5) $client_name = $company_name;
		}

		$order_total = $order->get_total();

		$transaction = array(
			"transaction" => array(
				"value" => round($order_total, 2),
				"capture" => 1,
				"card_hash" => $cardHash,
				"cardholder_name" => $data['belluno_credit_card_name'],
				"cardholder_document" => self::formatCnpjCpf($data['belluno_credit_card_document']),
				"cardholder_cellphone" => $data['belluno_credit_card_phone'],
				// "cardholder_birth" => $data['belluno_credit_card_birthdate'],
				"brand" => intval(self::getFlag($data['belluno_credit_card_brand'])),
				"installment_number" => $data['belluno_credit_card_installments'],
				"visitor_id" => $data['belluno_visitor_id'],
				"payer_ip" => $_SERVER['REMOTE_ADDR'],
				"client_name" => $client_name,
				"client_document" => "'" . $document . "'",
				"client_email" => $order->get_billing_email(),
				"client_cellphone" => self::format_phone($order->get_billing_phone()),
				"details" => $order->get_billing_address_2(),
				"shipping" => array(
					"postalCode" => $shipping['postalCode'],
					"street" => $shipping['street'],
					"number" => $shipping['number'],
					"city" => $shipping['city'],
					"state" => $shipping['state']
				),
				"billing" => array(
					"postalCode" => $order->get_billing_postcode(),
					"street" => $order->get_billing_address_1(),
					"number" => "'" . $order->get_meta('_billing_number') . "'",
					"city" => $order->get_billing_city(),
					"state" => $order->get_billing_state()
				),
				"cart" => $cart,
				"postback" => array(
					"url" => get_site_url() . "/wp-json/belluno/v1/card",
				)
			)
		);

		if ($data['belluno_credit_card_birthdate'] !== '') {
			$transaction['transaction']['cardholder_birth'] = $data['belluno_credit_card_birthdate'];
		}

		$transaction = json_encode($transaction);

		$args = array(
			'body' => $transaction,
			'headers' => array(
				'Content-Type'  => 'application/json',
				'Authorization' => 'Bearer ' . self::getWPOptions('token'),
			),
			'timeout' => 15,
			'httpversion' => '1.0',
			'sslverify' => false,
		);

		$response = wp_safe_remote_post($url, $args);

		return $response;
	}

	protected function addShipping($order, $ship_to = false)
	{
		if ($ship_to) {
			$shipping['postalCode'] = $order->get_shipping_postcode();
			$shipping['street'] = $order->get_shipping_address_1();
			$shipping['number'] = $order->get_meta('_shipping_number');
			$shipping['city'] = $order->get_shipping_city();
			$shipping['state'] =  $order->get_shipping_state();
		} else {
			$shipping['postalCode'] = $order->get_billing_postcode();
			$shipping['street'] = $order->get_billing_address_1();
			$shipping['number'] = $order->get_meta('_billing_number');
			$shipping['city'] = $order->get_billing_city();
			$shipping['state'] =  $order->get_billing_state();
		}
		return $shipping;
	}

	protected function getFlag($value)
	{
		$Flags = array(
			1 => "MASTERCARD",
			2 => "VISA",
			3 => "ELO",
			4 => "HIPERCARD",
			5 => "CABAL",
			6 => "HIPER",
			7 => "AMEX"
		);
		return array_search(strtoupper($value), $Flags);
	}
	protected function format_phone($phone)
	{
		if (!(strstr($phone, '('))) {
			$ddd = '(' . substr($phone, 0, 2) . ')';
			$phone = substr($phone, 2, strlen($phone));
			return $ddd . $phone;
		} else {
			return $phone;
		}
	}
	protected function formatCnpjCpf($value)
	{
		$CPF_LENGTH = 11;
		$cnpj_cpf = preg_replace("/\D/", '', $value);

		if (strlen($cnpj_cpf) === $CPF_LENGTH) {
			return preg_replace("/(\d{3})(\d{3})(\d{3})(\d{2})/", "\$1.\$2.\$3-\$4", $cnpj_cpf);
		}

		return preg_replace("/(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})/", "\$1.\$2.\$3/\$4-\$5", $cnpj_cpf);
	}

	protected function formatDate($date)
	{
		$dt = explode("/", $date);
		return (strlen($dt[1]) == 2) ? $dt[0] . "/20" . $dt[1] : $date;
	}

	/**
	 * Gets the admin url.
	 *
	 * @return string
	 */
	protected function admin_url()
	{
		if (version_compare(WOOCOMMERCE_VERSION, '2.1', '>=')) {
			return admin_url('admin.php?page=wc-settings&tab=checkout&section=belluno_card');
		}

		return admin_url('admin.php?page=woocommerce_settings&tab=payment_gateways&section=belluno_card');
	}

	/**
	 * Adds error message when not configured the token.
	 *
	 * @return string Error Mensage.
	 */
	public function token_missing_message()
	{
		//echo '<div class="error belluno belluno-token"><p><strong>' . __( 'Belluno está desabilitado!', 'wc-belluno' ) . '</strong>: ' . sprintf( __( 'É necessário que informe o Token de Acesso. %s', 'wc-belluno' ), '<a href="' . $this->admin_url() . '">' . __( 'Clique aqui para configurar!', 'wc-belluno' ) . '</a>' ) . '</p></div>';
		$class = 'notice notice-warning belluno belluno-token is-dismissible';
		$message = __('Cartão Belluno: Informe o token de acesso.', 'wc-belluno');
		$description = __('É necessário informar o token de acesso para que a forma de pagamento via cartão funcione corretamente.', 'wc-belluno');
		$linkMessage = __('Clique aqui para configurar!', 'wc-belluno');
		$link = $this->admin_url();
		printf('<div class="%1$s"><strong>%2$s</strong><p>%3$s <a href="%4$s">%5$s</a></p></div>', esc_attr($class), esc_html($message), esc_html($description), esc_html($link), esc_html($linkMessage));
	}

	/**
	 * Adds error message when not configured the key.
	 *
	 * @return string Error Mensage.
	 */
	public function key_missing_message()
	{
		//echo '<div class="notice notice-error belluno belluno-key"><p><strong>' . __( 'Belluno está desabilitado!', 'wc-belluno' ) . '</strong>: ' . sprintf( __( 'É necessário informar o Token Antifraude. %s', 'wc-belluno' ), '<a href="' . $this->admin_url() . '">' . __( 'Clique aqui para configurar!', 'wc-belluno' ) . '</a>' ) . '</p></div>';
		$class = 'notice notice-warning belluno belluno-key is-dismissible';
		$message = __('Cartão Belluno: Informe o token anti-fraude.', 'wc-belluno');
		$description = __('É necessário informar o token anti-fraude para que a forma de pagamento via cartão funcione corretamente.', 'wc-belluno');
		$linkMessage = __('Clique aqui para configurar!', 'wc-belluno');
		$link = $this->admin_url();
		printf('<div class="%1$s"><strong>%2$s</strong><p>%3$s <a href="%4$s">%5$s</a></p></div>', esc_attr($class), esc_html($message), esc_html($description), esc_html($link), esc_html($linkMessage));
	}

	public function noticeHttps()
	{
		//echo '<div class="error belluno belluno-https"><img style="max-width: 25px; min-width: 25px;border-radius: 50px; float:left; margin-top: 5px" src="'.plugins_url( 'assets/images/belluno1.png', plugin_dir_path( __FILE__ )).'"><div class="message" style="margin-left: 30px;margin-top: 4px;position:relative;top:5px">' . sprintf( __( 'Para segurança das transações, é <b>altamente</b> recomendável o uso de certificado SSL para o uso do plugin Belluno.', 'wc-belluno' )) . '</div></p></div>';
		$class = 'notice notice-warning belluno belluno-https is-dismissible';
		$message = __('Cartão Belluno: Certificado SSL não encontrado.', 'wc-belluno');
		$description = __('Para segurança das transações, é altamente recomendável o uso de certificado SSL para o uso do plugin Belluno.', 'wc-belluno');
		printf('<div class="%1$s"><strong>%2$s</strong><p>%3$s</p></div>', esc_attr($class), esc_html($message), esc_html($description));
	}


	/**
	 * Payment fields.
	 */
	public function payment_fields()
	{

		$token = self::getWPOptions('token');
		if ($token) {

			if ('yes' == self::getWPOptions('sandbox')) {
				$url = 'https://ws-sandbox.bellunopag.com.br/transaction/card_hash_key/';
			} else {
				$url = 'https://api.belluno.digital/transaction/card_hash_key/';
			}

			ob_start();

			include plugin_dir_path(dirname(__FILE__)) . 'templates/transparent-checkout-belluno-card.php';

			$html = ob_get_clean();

			$html .= "<script src='" . plugins_url('assets/js/jquery.mask.min.js', plugin_dir_path(__FILE__)) . "'></script>
					 <script src='https://app.belluno.digital/pagess/plugins/woocommerce/checkout.min.js?timestamp=" . time() . "'></script>";

			echo $html;
		} else {
			// Mensagens caso algum erro ocorra.
			$html = '<p>' . __('Ocorreu um erro ao processar o seu pagamento, tente novamente. Ou entre em contato conosco para obter ajuda.', 'wc-belluno') . '</p>';
			echo $html;
		}
	}

	protected function belluno_add_notice($message, $type = 'error')
	{
		if (!wc_has_notice($message)) {
			WC()->session->set('wc_notices', array([]));
		}

		return wc_add_notice($message, $type);
	}
}
