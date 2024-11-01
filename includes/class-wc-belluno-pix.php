<?php

/**
 * Classe WC Belluno PIX .
 *
 * Built the Belluno method.
 */

class WC_Belluno_Pix extends WC_Payment_Gateway
{

	public $api;
	public $login;
	public $invoice_prefix;
	public $token;
	public $key;
	public $pix;
	public $discount;
	public $sandbox;

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
		$this->id             = 'belluno_pix';
		$this->has_fields     = false;
		$this->method_title   = __('PIX Belluno', 'wc-belluno');
		$this->method_description = __('Receba via PIX de forma simplificada no seu e-commerce.', 'wc-belluno');
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
		$this->pix       = $this->get_option('pix');
		$this->discount	 = $this->get_option('discount', '0');

		// Debug options.
		$this->sandbox = $this->get_option('sandbox');

		// Actions.
		add_action('woocommerce_api_wc_belluno_gateway', array($this, 'check_ipn_response'));
		add_action('woocommerce_receipt_belluno', array($this, 'receipt_page'));
		add_action('woocommerce_order_details_before_order_table', array($this, 'showPIX'));
		add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));

		// Display admin notices.
		$this->admin_notices();
	}

	public function showPIX($order_id)
	{
		$order = wc_get_order($order_id);
		if ($order->get_payment_method() === "belluno_pix" && $order->has_status('on-hold')) {
			ob_start();
			$order_id = $order->get_id();
			include plugin_dir_path(dirname(__FILE__)) . 'templates/transparent-checkout-belluno-pix-qr-code.php';
			$html = ob_get_clean();

			$html .= "<script src='https://app.belluno.digital/pagess/plugins/Pusher/pusher-with-encryption.min.js'></script>
			<script src='https://app.belluno.digital/pagess/plugins/Pusher/pusher-with-encryption.worker.min.js'></script>
			<script src='https://app.belluno.digital/pagess/plugins/Echo/echo-without-module.js'></script>";

			echo $html;
		}
	}

	protected function transparentCheckoutPayment($data)
	{
		$order = wc_get_order($data['order_id']);

		$response = self::pixPayment($data);

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
		$transactionID = $order->get_meta('_belluno_transaction_id');
		$message = "";
		if ($transactionID) $message = "ID da Transação Belluno: $transactionID";

		switch ($json->transaction->status) {
			case "Paid":
				$order->update_status('processing', __('Belluno: O Pagamento foi recebido com sucesso! ' . $message, 'wc-belluno'));
				$order->payment_complete($json->transaction->transaction_id);
				break;

			case "Refused":
				$order->update_status('failed', __('Belluno: O Pagamento foi recusado! ' . $message, 'wc-belluno'));
				//Pagamento recusado...Estoque volta.
				if (function_exists('wc_increase_stock_levels')) {
					wc_increase_stock_levels($order_id);
				}
				break;

			case "Manual Analysis":
				$order->update_status('on-hold', __('Belluno: O Pagamento está sendo analisado! ' . $message, 'wc-belluno'));
				break;

			case "Client Manual Analysis":
				$order->update_status('on-hold', __('Belluno: O Pagamento está sendo analisado! ' . $message, 'wc-belluno'));
				break;
			case "Open":
				$order->update_status('on-hold', __('Aguardando Pagamento do PIX. ' . $message, 'wc-belluno'));
				break;
			case "Unpaid":
				$order->add_order_note(__('Belluno: Aguardando o pagamento. ' . $message, 'wc-belluno'));
				break;
			default:
				$order->add_order_note(__('Status Belluno: ' . $json->transaction->status . " " . $message, 'wc-belluno'));
				break;
		}
		return;
	}

	protected function addMeta($order, $data)
	{
		$order->update_meta_data("_belluno_status", $data->transaction->status);
		$order->update_meta_data("_belluno_transaction_id", $data->transaction->transaction_id);
		$order->update_meta_data("_belluno_base64_text", base64_decode($data->transaction->pix->base64_text));
		$order->update_meta_data("_belluno_base64_image", $data->transaction->pix->base64_image);
		$order->update_meta_data("_belluno_transaction_hash", explode("=", $data->transaction->link)[1]);
		$order->update_meta_data("_belluno_due_pix", $data->transaction->pix->expires_at);
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
			$_GET['section'] === 'belluno_pix'
		) {
			static $show_once = true;
			if ($show_once) {
				// Verifica se o token está vazio
				if (empty($this->token)) {
					add_action('admin_notices', array($this, 'token_missing_message'));
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
	 * Payment fields.
	 */
	public function payment_fields()
	{
		$token = self::getWPOptions('token');
		if ($token) {
			ob_start();
			include plugin_dir_path(dirname(__FILE__)) . 'templates/transparent-checkout-belluno-pix.php';
			$html = ob_get_clean();
			echo $html;
		} else {
			// Mensagens caso algum erro ocorra.
			$html = '<p>' . __('Ocorreu um erro ao processar o seu pagamento, tente novamente. Ou entre em contato conosco para obter ajuda.', 'wc-belluno') . '</p>';
			echo $html;
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
			$api = (!empty($this->token));
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
		// wp_enqueue_script('wc-belluno', plugins_url('assets/js/admin.min.js', plugin_dir_path(__FILE__)), array('jquery'), '', true);

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
				'label' => __('Ativar o pagamento via PIX', 'wc-belluno'),
				'default' => 'no'
			),
			'title' => array(
				'title' => __('Título', 'wc-belluno'),
				'type' => 'text',
				'description' => __('Define o título que o usuário vê durante o checkout.', 'wc-belluno'),
				'desc_tip' => true,
				'default' => __('Pague via PIX', 'wc-belluno'),
				'custom_attributes' => array(
					'required' => 'required'
				),
			),
			'description' => array(
				'title' => __('Descrição', 'wc-belluno'),
				'type' => 'textarea',
				'description' => __('Define a descrição que o usuário vê durante o checkout.', 'wc-belluno'),
				'default' => __('Pagamento via PIX', 'wc-belluno'),
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
				'title' => __('Desconto', 'wc-belluno'),
				'type' => 'number',
				'custom_attributes' => array('step' => 'any', 'min' => '0'),
				'desc_tip' => true,
				'description' => sprintf(__('Caso não queria aplicar desconto apenas deixe zero ou vazio. obs: o desconto é aplicado em porcentagem', 'wc-belluno'), 'https://belluno.digital/contato/'),
			),
			'due_minutes' => array(
				'title' => __('Tempo de Expiração PIX', 'wc-belluno'),
				'type' => 'number',
				'description' => __('Tempo em minutos para expiração do pagamento PIX. Deixe vazio para usar o padrão.', 'wc-belluno'),
				'desc_tip' => true,
				'default' => '',
				'custom_attributes' => array(
					'min' => 5,
				),
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
			)
		);
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

		if (isset($_POST['ship_to_different_address'])) $data['ship_to_different_address'] = sanitize_text_field($_POST['ship_to_different_address']);

		$data['order_id'] = $order_id;

		$response = $this->transparentCheckoutPayment($data);

		if ($response->status !== 200) {
			if ($response->message) {
				$this->belluno_add_notice('Ocorreu um erro ao processar seu pagamento: ' . $response->message . '. Verifique os dados do pedido e tente novamente!');
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
			if ($order->has_status('failed')) {
				$this->belluno_add_notice('Ocorreu um erro ao processar seu pedido. Tente novamente!');
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
		return get_option('woocommerce_belluno_pix_settings')[$option];
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

	protected function pixPayment($data)
	{
		$order = wc_get_order($data['order_id']);

		$ship_to = isset($data['ship_to_different_address']) ? true : false;

		$shipping = $this->addShipping($order, $ship_to);

		if ('yes' == self::getWPOptions('sandbox')) {
			$url = 'https://ws-sandbox.bellunopag.com.br/v2/transaction/pix';
		} else {
			$url = 'https://api.belluno.digital/v2/transaction/pix';
		}

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

		$due_minutes_pix = self::getWPOptions('due_minutes') ?? null;

		$transaction = array(
			"transaction" => array(
				"value" => $order->get_total(),
				"client_name" => $client_name,
				"client_document" => $document,
				"client_email" => $order->get_billing_email(),
				"client_cellphone" => self::format_phone($order->get_billing_phone()),
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
					"number" => $order->get_meta('_billing_number'),
					"city" => $order->get_billing_city(),
					"state" => $order->get_billing_state()
				),
				"cart" => $cart,
				"pix" => array(
					"due_minutes" => $due_minutes_pix
				),
				"postback" => array(
					"url" => get_site_url() . "/wp-json/belluno/v2/pix",
				)
			)
		);
		$transaction = json_encode($transaction);
		$args = array(
			'body' => $transaction,
			'headers' => array(
				'Content-Language'  => 'pt_BR',
				'Accept'  => 'application/json',
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

	/**
	 * Gets the admin url.
	 *
	 * @return string
	 */
	protected function admin_url()
	{
		if (version_compare(WOOCOMMERCE_VERSION, '2.1', '>=')) {
			return admin_url('admin.php?page=wc-settings&tab=checkout&section=belluno_pix');
		}

		return admin_url('admin.php?page=woocommerce_settings&tab=payment_gateways&section=belluno_pix');
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
		$message = __('PIX Belluno: Informe o token de acesso.', 'wc-belluno');
		$description = __('É necessário informar o token de acesso para que a forma de pagamento via PIX funcione corretamente.', 'wc-belluno');
		$linkMessage = __('Clique aqui para configurar!', 'wc-belluno');
		$link = $this->admin_url();
		printf('<div class="%1$s"><strong>%2$s</strong><p>%3$s <a href="%4$s">%5$s</a></p></div>', esc_attr($class), esc_html($message), esc_html($description), esc_html($link), esc_html($linkMessage));
	}

	public function noticeHttps()
	{
		//echo '<div class="error belluno belluno-https"><img style="max-width: 25px; min-width: 25px;border-radius: 50px; float:left; margin-top: 5px" src="'.plugins_url( 'assets/images/belluno1.png', plugin_dir_path( __FILE__ )).'"><div class="message" style="margin-left: 30px;margin-top: 4px;position:relative;top:5px">' . sprintf( __( 'Para segurança das transações, é <b>altamente</b> recomendável o uso de certificado SSL para o uso do plugin Belluno.', 'wc-belluno' )) . '</div></p></div>';
		$class = 'notice notice-warning belluno belluno-https is-dismissible';
		$message = __('PIX Belluno: Certificado SSL não encontrado.', 'wc-belluno');
		$description = __('Para segurança das transações, é altamente recomendável o uso de certificado SSL para o uso do plugin Belluno.', 'wc-belluno');
		printf('<div class="%1$s"><strong>%2$s</strong><p>%3$s</p></div>', esc_attr($class), esc_html($message), esc_html($description));
	}

	protected function belluno_add_notice($message, $type = 'error')
	{
		return wc_add_notice($message, $type);
	}
}
