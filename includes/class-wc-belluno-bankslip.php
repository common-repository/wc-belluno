<?php

/**
 * Classe WC Belluno Bankslip .
 *
 * Built the Belluno method.
 */

class WC_Belluno_Bankslip extends WC_Payment_Gateway
{
	public $api;
	public $login;
	public $invoice_prefix;
	public $token;
	public $billet_banking;
	public $billet;
	public $billet_type_term;
	public $billet_number_days;
	public $discount;
	public $sandbox;

	/**
	 * Constructor for the gateway.
	 *
	 * @return void
	 */
	public function __construct()
	{
		$this->id             = 'belluno_bankslip';
		$this->has_fields     = false;
		$this->method_title   = __('Boleto Belluno', 'wc-belluno');
		$this->method_description = __('Receba via boleto de forma simplificada no seu e-commerce.', 'wc-belluno');
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

		// Payment methods.
		$this->billet_banking    = $this->get_option('billet_banking');


		// Billet options.
		$this->billet                   = $this->get_option('billet', 'no');
		$this->billet_type_term         = $this->get_option('billet_type_term', 'no');
		$this->billet_number_days       = $this->get_option('billet_number_days', '7');
		$this->discount					= $this->get_option('discount', '0');

		// Debug options.
		$this->sandbox = $this->get_option('sandbox');

		// Actions.

		add_action('woocommerce_api_wc_belluno_gateway', array($this, 'check_ipn_response'));
		add_action('woocommerce_order_details_before_order_table', array($this, 'billetButton'));
		add_action('woocommerce_receipt_belluno', array($this, 'receipt_page'));
		add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
		// Display admin notices.
		$this->admin_notices();
	}

	protected function transparentCheckoutPayment($data)
	{
		$order = wc_get_order($data['order_id']);

		$response = self::billetPayment($data);

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
		if ($transactionID) $message = "ID da Transação Belluno: $transactionID";

		$order->update_status('on-hold', sprintf(__('Aguardando Pagamento do Boleto: %s.', 'wc-belluno'), $message ?? ""));
	}

	protected function addMeta($order, $data)
	{
		$order->update_meta_data("_belluno_status", $data->bankslip->status);
		$order->update_meta_data("_belluno_transaction_id", $data->bankslip->id);
		$order->update_meta_data("_belluno_bankslip_url", $data->bankslip->url);
		$order->update_meta_data("_belluno_bankslip_digitable_line", $data->bankslip->digitable_line);
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
			$_GET['section'] === 'belluno_bankslip'
		) {
			static $show_once = true;
			if ($show_once) {
				// Verifica se o token não está vazio
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
				'label' => __('Ativar o pagamento via Boleto', 'wc-belluno'),
				'default' => 'no'
			),
			'title' => array(
				'title' => __('Título', 'wc-belluno'),
				'type' => 'text',
				'description' => __('Define o título que o usuário vê durante o checkout.', 'wc-belluno'),
				'desc_tip' => true,
				'default' => __('Pague via Boleto', 'wc-belluno'),
				'custom_attributes' => array(
					'required' => 'required'
				),
			),
			'description' => array(
				'title' => __('Descrição', 'wc-belluno'),
				'type' => 'textarea',
				'description' => __('Define a descrição que o usuário vê durante o checkout.', 'wc-belluno'),
				'default' => __('Pagamento via Boleto Belluno', 'wc-belluno'),
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
			'billet_section' => array(
				'title' => __('Configurações de Boleto', 'wc-belluno'),
				'type' => 'title',
				'description' => '',
			),
			'billet_number_days' => array(
				'title' => __('Dias para vencimento', 'wc-belluno'),
				'type' => 'text',
				'description' => __('Dias de vencimento do boleto após impresso.', 'wc-belluno'),
				'desc_tip' => true,
				'placeholder' => '5',
				'default' => '5'
			),
			'discount' => array(
				'title' => __('Desconto', 'wc-belluno'),
				'type' => 'number',
				'desc_tip' => true,
				'custom_attributes' => array('step' => 'any', 'min' => '0'),
				'description' => sprintf(__('Caso não queria aplicar desconto apenas deixe zero ou vazio. obs: o desconto é aplicado em porcentagem', 'wc-belluno'), 'https://belluno.digital/contato/'),
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
	 * Adiciona uma mensagem de erro ao checkout.
	 *
	 * @param string $mensagem Mensagem de erro.
	 *
	 * @return string Mostra a mensagem de erro.
	 */
	protected function registra_erro($mensagem)
	{
		if (version_compare(WOOCOMMERCE_VERSION, '2.1', '>=')) {
			wc_add_notice($mensagem, 'error');
		} else {
			$this->woocommerce_instance()->add_error($mensagem);
		}
	}

	/**
	 * Process payment and return the result.
	 *
	 * @param int    $order_id Order ID.
	 *
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

	protected function getWPOptions($option)
	{
		return get_option('woocommerce_belluno_bankslip_settings')[$option];
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

	protected function billetPayment($data)
	{
		$order = wc_get_order($data['order_id']);

		if ('yes' == self::getWPOptions('sandbox')) {
			$url = 'https://ws-sandbox.bellunopag.com.br/bankslip';
		} else {
			$url = 'https://api.belluno.digital/bankslip';
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

		$document = (
			$order->get_meta('_billing_cpf') ??
			$order->get_meta('_billing_cpf_1') ??
			$order->get_meta('_billing_client_cpf') ??
			$order->get_meta('_billing_cnpj')
		);

		$person_type =  $order->get_meta('_billing_persontype');
		if ($person_type == "2") {
			$document = $order->get_meta('_billing_cnpj');
			$company_name = $order->get_meta('_billing_company');
			if (($company_name ?? "") !== "" && strlen($company_name) > 5) $client_name = $company_name;
		}

		$transaction = array(
			"bankslip" => array(
				"value" => $order->get_total(),
				"due" => date('Y-m-d', strtotime("+" . self::getWPOptions('billet_number_days') . "days", strtotime(date('Y-m-d')))),
				"document_code" => str_pad($order->get_id(), 4, "0", STR_PAD_LEFT),
				"client" => array(
					"name" => $client_name,
					"document" => $document,
					"email" => $order->get_billing_email(),
					"phone" => self::format_phone($order->get_billing_phone()),
				),
				"billing" => array(
					"postalCode" => $order->get_billing_postcode(),
					"district" => $order->get_meta('billing_neighborhood'),
					"address" => $order->get_billing_address_1(),
					"number" => $order->get_meta('_billing_number'),
					"city" => $order->get_billing_city(),
					"state" => $order->get_billing_state(),
					"country" => "BR"
				),
				"cart" => $cart,
				"postback" => array(
					"url" => get_site_url() . "/wp-json/belluno/v1/billet",
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

	public function billetButton($order_id)
	{
		$order = wc_get_order($order_id);
		if ($order->get_payment_method() === "belluno_bankslip" && $order->has_status('on-hold')) {
			ob_start();
			$order_id = $order->get_id();
			include plugin_dir_path(dirname(__FILE__)) . 'templates/transparent-checkout-belluno-bankslip-button.php';
			$html = ob_get_clean();
			echo $html;
		}
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
			return admin_url('admin.php?page=wc-settings&tab=checkout&section=belluno_bankslip');
		}

		return admin_url('admin.php?page=woocommerce_settings&tab=payment_gateways&section=belluno_bankslip');
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
		$message = __('Boleto Belluno: Informe o token de acesso.', 'wc-belluno');
		$description = __('É necessário informar o token de acesso para que a forma de pagamento via cartão funcione corretamente.', 'wc-belluno');
		$linkMessage = __('Clique aqui para configurar!', 'wc-belluno');
		$link = $this->admin_url();
		printf('<div class="%1$s"><strong>%2$s</strong><p>%3$s <a href="%4$s">%5$s</a></p></div>', esc_attr($class), esc_html($message), esc_html($description), esc_html($link), esc_html($linkMessage));
	}

	public function noticeHttps()
	{
		//echo '<div class="error belluno belluno-https"><img style="max-width: 25px; min-width: 25px;border-radius: 50px; float:left; margin-top: 5px" src="'.plugins_url( 'assets/images/belluno1.png', plugin_dir_path( __FILE__ )).'"><div class="message" style="margin-left: 30px;margin-top: 4px;position:relative;top:5px">' . sprintf( __( 'Para segurança das transações, é <b>altamente</b> recomendável o uso de certificado SSL para o uso do plugin Belluno.', 'wc-belluno' )) . '</div></p></div>';
		$class = 'notice notice-warning belluno belluno-https is-dismissible';
		$message = __('Boleto Belluno: Certificado SSL não encontrado.', 'wc-belluno');
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
			ob_start();
			include plugin_dir_path(dirname(__FILE__)) . 'templates/transparent-checkout-belluno-bankslip.php';
			$html = ob_get_clean();
			echo $html;
		} else {
			// Mensagens caso algum erro ocorra.
			$html = '<p>' . __('Ocorreu um erro ao processar o seu pagamento, tente novamente. Ou entre em contato conosco para obter ajuda.', 'wc-belluno') . '</p>';
			echo $html;
		}
	}

	public static function belluno_add_info_email($order, $sent_to_admin)
	{
		if (!$sent_to_admin &&  $order->get_payment_method() === "belluno_bankslip") {
			echo '<a class="' . esc_attr("button") . '" href="' . esc_url($order->get_meta('_belluno_bankslip_url')) . '" target="' . esc_attr("_blank") . '">' . esc_html("Imprimir boleto") . '</a><br><br>';
		}
	}

	protected function belluno_add_notice($message, $type = 'error')
	{
		return wc_add_notice($message, $type);
	}
}
