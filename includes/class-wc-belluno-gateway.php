<?php
/**
 * Classe WC Belluno Gateway .
 *
 * Built the Belluno method.
 */
use phpseclib\Crypt\RSA;
class WC_Belluno_Gateway extends WC_Payment_Gateway {

	/**
	 * Constructor for the gateway.
	 *
	 * @return void
	 */
	public function __construct() {
        $this->id             = 'belluno';
        //$this->icon           = apply_filters( 'woocommerce_belluno_icon', plugins_url( 'assets/images/logoBelluno.png', plugin_dir_path( __FILE__ ) ) );
        $this->has_fields     = false;
		$this->method_title   = __( 'Belluno', 'wc-belluno' );
		$this->method_description = __('Utilize o gateway de pagamento da Belluno e simplifique as finanças do seu E-commerce.', 'wc-belluno');
		$this->order_button_text  = __( 'Finalizar Compra', 'wc-belluno' );
        // Load the settings.
        $this->init_form_fields();
		$this->init_settings();

        // Display options.
        $this->title       = $this->get_option( 'title' );
		$this->description = $this->get_option( 'description' );

		$this->api = 'tc';

        // Gateway options.
        $this->login          = $this->get_option( 'login' );
        $this->invoice_prefix = $this->get_option( 'invoice_prefix', 'WC-' );

		$this->token = $this->get_option( 'token' );
        $this->key   = $this->get_option( 'key' );

		// Payment methods.
		$this->billet_banking    = $this->get_option( 'billet_banking' );
		$this->credit_card       = $this->get_option( 'credit_card' );


        // Installments options.
		$this->installments          = $this->get_option( 'installments', 'no' );
		$this->installments_mininum  = $this->get_option( 'installments_mininum', 2 );
		$this->installments_maxium   = $this->get_option( 'installments_maxium', 12 );

		// Billet options.
		$this->billet                   = $this->get_option( 'billet', 'no' );
		$this->billet_type_term         = $this->get_option( 'billet_type_term', 'no' );
		$this->billet_number_days       = $this->get_option( 'billet_number_days', '7' );

		// Debug options.
		$this->sandbox = $this->get_option( 'sandbox' );


		// Actions.

        add_action( 'woocommerce_api_wc_belluno_gateway', array( $this, 'check_ipn_response' ) );
		add_action( 'woocommerce_receipt_belluno', array( $this, 'receipt_page' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'scripts' ) );
        add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
		add_filter( 'woocommerce_thankyou',array($this,'billetButton'), 4 );
		// Display admin notices.
		$this->admin_notices();
	}

	protected function transparentCheckoutPayment($data) {

		if(self::searchMeta($data)) self::recordMeta($data);

		$json = json_decode(self::generate_payment($data));
		
		if($json->error->status == 401) return -1;

		if($json->status== 'error') return -2;

		self::updateMeta($data,$json);

		self::updateOrderStatus($data,$json);

		return $json;
	}

	protected function updateOrderStatus($data,$json){
		$order = new WC_Order( $data['orderID'] );
		$method = $data['paymentMethod'];
		$transactionID = self::searchMeta($data);
		$transaction = "";
		if($transactionID) $transaction = "ID da Transação Belluno: $transactionID";

		if ($method == 'CartaoCredito') {
			switch ($json->transaction->status) {
				case "Paid":
					$order->add_order_note( __( 'Belluno: O Pagamento foi aprovado com sucesso! ' . $transaction, 'wc-belluno' ) );
					$order->payment_complete();
					$order->update_status( 'processing', __( 'Pagamento recebido e estoque reduzido - o pedido está aguardando atendimento', 'wc-belluno' ) );
					break;

				case "Refused":
					$order->add_order_note( __( 'Belluno: O Pagamento foi recusado! ' . $transaction, 'wc-belluno' ) );
					$order->update_status( 'failed', __( 'Pagamento recusado!', 'wc-belluno' ) );
					//Pagamento recusado...Estoque volta.
					if ( function_exists( 'wc_increase_stock_levels' ) ) {
						wc_increase_stock_levels( $order_id );
					}
					break;

				case "Manual Analysis":
					$order->add_order_note( __( 'Belluno: O Pagamento está sendo analisado! ' . $transaction, 'wc-belluno' ) );
					$order->update_status( 'on-hold', __( 'O Pagamento está sendo analisado!', 'wc-belluno' ) );
					break;

				case "Client Manual Analysis":
					$order->add_order_note( __( 'Belluno: O Pagamento está sendo analisado! ' . $transaction, 'wc-belluno' ) );
					$order->update_status( 'on-hold', __( 'O Pagamento está sendo analisado!', 'wc-belluno' ) );
					break;
				case "Open":
				case "Unpaid":
					$order->add_order_note( __( 'Belluno: Aguardando o pagamento.. ' . $transaction, 'wc-belluno' ) );
					break;
				default:
					$order->add_order_note( __( 'Status Belluno: '.$json->transaction->status ." ".$transaction, 'wc-belluno' ) );
					break;
			}
		} else {
			$order->add_order_note( __( 'Belluno: Aguardando Pagamento do Boleto. ' . $transaction, 'wc-belluno' ) );
			$order->update_status( 'on-hold', __( 'Aguardando Pagamento do Boleto.', 'wc-belluno' ) );
		}
		return;
	}
	protected function searchMeta($data) {
		$transactionID =  get_post_meta($data['orderID'],'_belluno_transaction_id' );
		if($transactionID){
			$previusMethod = get_post_meta($data['orderID'],'_belluno_method', true );
			if($previusMethod == $data['paymentMethod']){
				return false;
			}else{
				update_post_meta($data['orderID'], "_belluno_method", $data['paymentMethod']);
				if($data['paymentMethod'] == 'BoletoBancario')  add_post_meta($data['orderID'], "_belluno_billet_url","");
				return false;
			}
		}else{
			return true;
		}
	}
	protected function recordMeta($data) {
		add_post_meta($data['orderID'], "_belluno_status","");
		add_post_meta($data['orderID'], "_belluno_method", $data['paymentMethod']);
		add_post_meta($data['orderID'], "_belluno_transaction_id","");
		if($data['paymentMethod'] == 'BoletoBancario')  add_post_meta($data['orderID'], "_belluno_billet_url","");
	}

	protected function updateMeta($data,$response) {
		$key = $data['paymentMethod'] == 'CartaoCredito' ? 'transaction' : 'bankslip';
		$id =  $data['paymentMethod'] == 'CartaoCredito' ? 'transaction_id' : 'id';
		
		update_post_meta($data['orderID'], "_belluno_status", $response->{$key}->status);
		update_post_meta($data['orderID'], "_belluno_transaction_id", $response->{$key}->{$id});

		if ($data['paymentMethod'] == 'BoletoBancario')
			update_post_meta($data['orderID'], '_belluno_billet_url', $response->bankslip->url);

	}

	/**
	 * Compatibilidade com versões anteriores à versão 2.1.
	 *
	 * @return object Returns the main instance of WooCommerce class.
	 */
	protected function woocommerce_instance() {
		if ( function_exists( 'WC' ) ) {
			return WC();
		} else {
			global $woocommerce;
			return $woocommerce;
		}
	}

	/**
	 * Chama os plugins de script do front-end
	 *
	 * @return void
	 */
	public function scripts() {
		if ( 'tc' == $this->api && is_checkout() ) {
			if($this->installments=='no') $this->installments_maxium = 1;
			wp_enqueue_style( 'wc-belluno-checkout', plugins_url( 'assets/css/belluno.min.css', plugin_dir_path( __FILE__ ) ), array(), '', 'all' );
			// wp_enqueue_style( 'grid-system', plugins_url( 'assets/css/grid.min.css', plugin_dir_path( __FILE__ ) ), array(), '', 'all' );
		}
	}
	/**
	 * Exibe notificações quando há algo errado com a configuração.
	 *
	 * @return void
	 */
	protected function admin_notices() {
		if ( is_admin() ) {
			static $has_run = false;
			if (!$has_run) {			
				// Verifica se o token não está vazio
				if ( empty( $this->token ) ) {
					add_action( 'admin_notices', array( $this, 'token_missing_message' ) );
				}
				// Verifica se o token antifraude não está vazio.
				if ( empty( $this->key ) ) {
					add_action( 'admin_notices', array( $this, 'key_missing_message' ) );
				}
				// Verifica se a moeda é valida
				if ( ! $this->moedas_suportadas() ) {
					add_action( 'admin_notices', array( $this, 'currency_not_supported_message' ) );
				}
				// Verifica se o site possui SSL
				if ((empty($_SERVER['HTTPS']) || $_SERVER['HTTPS'] == 'off')) {
					add_action('admin_notices', array($this, 'noticeHttps'));
				}
				$has_run = true;
			}
		}
	}

	/**
	 * Retorna true caso a moeda estiver entre as suportadas.
	 *
	 * @return bool
	 */
	public function moedas_suportadas() {
		return ( 'BRL' == get_woocommerce_currency() );
	}

	/**
	 * Retorna um valor indicando que o Gateway está disponível ou não.
	 * É chamado automaticamente pelo WooCommerce antes de permitir
	 * que os clientes usem o gateway para pagamento.
	 *
	 * @return bool
	 */
	public function is_available() {
		if ( 'html' != $this->api ) {
			$api = ( ! empty( $this->token ) && ! empty( $this->key ) );
		} else {
			$api = ( ! empty( $this->login ) );
		}

		$available = ( 'yes' == $this->settings['enabled'] ) && $api && $this->moedas_suportadas();

		return $available;
	}

	/**
	 * Opções do Painel Admin.
	 */
	public function admin_options() {
		wp_enqueue_script( 'wc-belluno', plugins_url( 'assets/js/admin.min.js', plugin_dir_path( __FILE__ ) ), array( 'jquery' ), '', true );

		echo '<h3>' . __( 'Belluno', 'wc-belluno' ) . '</h3>';
		echo '<p>' . __( 'Para impulsionar o crescimento do seu negócio.', 'wc-belluno' ) . '</p>';

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
	public function check_ipn_response() {
		@ob_clean();
		if ( isset( $_POST['id_transacao'] ) ) {
			header( 'HTTP/1.0 200 OK' );
			do_action( 'valid_belluno_ipn_request', stripslashes_deep( $_POST ) );
		} else {
			wp_die( __( 'Erro de solicitação à API Belluno', 'wc-belluno' ) );
		}
	}

	/**
	 * Initialise Gateway Settings Form Fields.
	 *
	 * @return void
	 */
	public function init_form_fields() {
		$this->form_fields = array(
			'enabled' => array(
				'title' => __( 'Ativar/Inativar', 'wc-belluno' ),
				'type' => 'checkbox',
				'label' => __( 'Ativar o módulo Belluno', 'wc-belluno' ),
				'default' => 'yes'
			),
			'title' => array(
				'title' => __( 'Título', 'wc-belluno' ),
				'type' => 'text',
				'description' => __( 'Define o título que o usuário vê durante o checkout.', 'wc-belluno' ),
				'desc_tip' => true,
				'default' => __( 'Belluno', 'wc-belluno' )
			),
			'description' => array(
				'title' => __( 'Descrição', 'wc-belluno' ),
				'type' => 'textarea',
				'description' => __( 'Define a descrição que o usuário vê durante o checkout.', 'wc-belluno' ),
				'default' => __( 'Pagamento via Belluno', 'wc-belluno' )
			),
			'api_section' => array(
				'title' => __( 'API de Pagamento', 'wc-belluno' ),
				'type' => 'title',
				'description' => '',
			),
			'api' => array(
				'title' => __( 'API de Pagamento Belluno', 'wc-belluno' ),
				'type' => 'select',
				'description' => '',
				'default' => 'tc',
				'options' => array(
					'tc' => __( 'Transparent Checkout', 'wc-belluno' )
				)
			),
			'token' => array(
				'title' => __( 'Token de Acesso', 'wc-belluno' ),
				'type' => 'text',
				'description' => __( 'Por favor, digite o seu Token de acesso;  isso é necessário para receber o pagamento.', 'wc-belluno' ),
				'desc_tip' => true,
				'default' => ''
			),
			'key' => array(
				'title' => __( 'Token Konduto Antifraude', 'wc-belluno' ),
				'type' => 'text',
				'description' => __( 'Por favor, digite o seu Token Antifraude;  isso é necessário para a segurança da sua transação.', 'wc-belluno' ),
				'desc_tip' => true,
				'default' => ''
			),
			'payment_section' => array(
				'title' => __( 'Cofigurações de Pagamento', 'wc-belluno' ),
				'type' => 'title',
				'description' => __( 'Defina as configurações de pagamento de acordo com sua conta Belluno', 'wc-belluno' ),
			),
			'billet_banking' => array(
				'title' => __( 'Boleto Bancário', 'wc-belluno' ),
				'type' => 'checkbox',
				'label' => __( 'Ativar Boleto Bancário', 'wc-belluno' ),
				'default' => 'yes'
			),
			'credit_card' => array(
				'title' => __( 'Cartão de Crédito', 'wc-belluno' ),
				'type' => 'checkbox',
				'label' => __( 'Ativar Cartão de Crédito', 'wc-belluno' ),
				'default' => 'yes'
			),
			'installments_section' => array(
				'title' => __( 'Configurações de Cartão de Crédito', 'wc-belluno' ),
				'type' => 'title',
				'description' => '',
			),
			'installments' => array(
				'title' => __( 'Configurações de Parcelas', 'wc-belluno' ),
				'type' => 'checkbox',
				'label' => __( 'Ativar a Configuração de Parcelas', 'wc-belluno' ),
				'default' => 'no'
			),
			'installments_mininum' => array(
				'title' => __( 'Parcela Mínima', 'wc-belluno' ),
				'type' => 'select',
				'description' => __( 'Indique a menor quantidade de parcelas.', 'wc-belluno' ),
				'desc_tip' => true,
				'default' => '2',
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
			'installments_maxium' => array(
				'title' => __( 'Parcela Máxima', 'wc-belluno' ),
				'type' => 'select',
				'description' => __( 'Indique o máximo em que o pedido poderá ser parcelado.', 'wc-belluno' ),
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
			'billet_section' => array(
				'title' => __( 'Configurações de Boleto', 'wc-belluno' ),
				'type' => 'title',
				'description' => '',
			),
			'billet_number_days' => array(
				'title' => __( 'Dias para vencimento', 'wc-belluno' ),
				'type' => 'text',
				'description' => __( 'Dias de vencimento do boleto após impresso.', 'wc-belluno' ),
				'desc_tip' => true,
				'placeholder' => '5',
				'default' => '5'
			),
			'sandbox_section' => array(
				'title' => __( 'Configurações Sandbox', 'wc-belluno' ),
				'type' => 'title',
				'description' => '',
			),
			'sandbox' => array(
				'title' => __( 'Modo de Testes', 'wc-belluno' ),
				'type' => 'checkbox',
				'label' => __( 'Habilitar o modo de testes da API Belluno', 'wc-belluno' ),
				'default' => 'no',
				'description' => sprintf( __( 'O modo de testes da API Belluno pode ser usado para testar pagamentos. Entre em contato conosco para saber mais clicando <a href="%s">aqui</a>.', 'wc-belluno' ), 'https://belluno.digital/contato/' ),
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
	protected function registra_erro( $mensagem ) {
		if ( version_compare( WOOCOMMERCE_VERSION, '2.1', '>=' ) ) {
			wc_add_notice( $mensagem, 'error' );
		} else {
			$this->woocommerce_instance()->add_error( $mensagem );
		}
	}

	/**
	 * Process the payment and return the result.
	 *
	 * @param int    $order_id Order ID.
	 *
	 * @return array Redirect.
	 */
	public function process_payment( $order_id ) {
		$order = new WC_Order( $order_id );
		//$data = $_POST;

		/* Sanitizing $_POST */
		$data['credit-card-expiration'] = sanitize_text_field($_POST['credit-card-expiration']);
		$data['paymentMethod'] = sanitize_text_field($_POST['paymentMethod']);
		$data['credit_card_number'] = sanitize_text_field($_POST['credit_card_number']);
		$data['credit_card_security_code'] = sanitize_text_field($_POST['credit_card_security_code']);
		$data['credit_card_name'] = sanitize_text_field($_POST['credit_card_name']);
		$data['credit_card_cpf'] = sanitize_text_field($_POST['credit_card_cpf']);
		$data['credit_card_phone'] = sanitize_text_field($_POST['credit_card_phone']);
		$data['credit-card-birthdate'] = sanitize_text_field($_POST['credit-card-birthdate']);
		$data['flagCard'] = sanitize_text_field($_POST['flagCard']);
		$data['credit_card_installments'] = sanitize_text_field($_POST['credit_card_installments']);

		if(isset($_POST['ship_to_different_address'])) $data['ship_to_different_address'] = sanitize_text_field($_POST['ship_to_different_address']);
		

		$data['orderID'] = $order_id;
		$data['credit-card-expiration'] = $this->formatDate($data['credit-card-expiration']);

		do_action('wp_konduto',$order->get_billing_email());

		$response = $this->transparentCheckoutPayment($data);

		if($response == -1){
			wc_add_notice('Acesso Não Autorizado! Tente novamente mais tarde','error');
			$order->update_status( 'failed', 'Acesso Não Autorizado!');
			//WC()->cart->empty_cart();
			return;
		}elseif($response == -2){
			$this->belluno_add_notice('Ocorreu um erro ao processar seu pedido. Tente novamente mais tarde!');
			$order->update_status( 'failed', 'Ocorreu um erro ao processar o pedido.');
			//WC()->cart->empty_cart();
			return;
		}else{
			if($response->error){
				$this->belluno_add_notice($response->error->status."-".$response->error->detail);
				$order->update_status( 'failed', $response->error->detail);
				return;
			}elseif($response->errors){
				$details="";
				foreach($response->errors as $error){
					$this->belluno_add_notice($error->status.".-".$error->detail);
					$details.=$error->detail."<br>";
				}
				$order->update_status( 'failed', $details);
				return;
			}elseif($response->status== 'error'){
				$this->belluno_add_notice('Ocorreu um erro ao processar seu pedido. Tente novamente mais tarde!');
				$order->update_status( 'failed', $response->message);
				WC()->cart->empty_cart();
				return;
			}else{
				WC()->cart->empty_cart();
				return array(
					'result'   => 'success',
					'redirect' => $this->get_return_url( $order )
				);
			}
		}
		
	}

	protected function getWPOptions($option){
		return get_option('woocommerce_belluno_settings')[$option];
	}

	protected function generate_cardHash($data){

		if ( 'yes' == self::getWPOptions('sandbox') ) {
			$url = 'https://ws-sandbox.bellunopag.com.br/transaction/card_hash_key/';
		} else {
			$url = 'https://api.belluno.digital/transaction/card_hash_key';
		}

		$responseDecoded =  self::geturl($url,self::getWPOptions('token'));

		if($responseDecoded->error) return;

		$card_number = preg_replace("/[^0-9]/", "", $data['credit_card_number']);
		$card_expiration_date = str_replace('/','',$data['credit-card-expiration']);
		$card_cvv = $data['credit_card_security_code'];

		//cartão de testes liberado pela API Belluno
		if ( 'yes' == self::getWPOptions('sandbox') ) {
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
		$request = wp_remote_get( $url, $args );
		$body    = wp_remote_retrieve_body( $request );
		$http_code = wp_remote_retrieve_response_code( $request );

		if($http_code == 200) return json_decode($body);

	}

	protected function generate_payment($data) {
		if ($data['paymentMethod'] == 'CartaoCredito') {
			return self::creditPayment($data);
		} elseif($data['paymentMethod'] == 'BoletoBancario') {
			return self::billetPayment($data);
		}
	}

	protected function billetPayment($data) {
		$order = new WC_Order( $data['orderID'] );
		
		if ( 'yes' == self::getWPOptions('sandbox') ) {
			$url = 'https://ws-sandbox.bellunopag.com.br/bankslip';
		} else {
			$url = 'https://api.belluno.digital/bankslip';
		}

		if ( sizeof( $order->get_items() ) > 0 ) {
			foreach ( $order->get_items() as $item ) {
				if ($item['qty'] ) {
					$cart[] = array(
						"product_name" => $item['name'],
						"quantity" => $item['qty'],
						"unit_value" => $item['total']/$item['qty']
					);
				}
			}
		}
		$transaction = array(
			"bankslip" => array(
				"value"=> $order->get_total(),
				"due" => date('Y-m-d', strtotime("+".self::getWPOptions('billet_number_days') . "days", strtotime(date('Y-m-d')))),
				"document_code"=> str_pad($order->get_id(), 10, "0", STR_PAD_LEFT),
				"client" => array(
					"name" => $order->get_billing_first_name()." ".$order->get_billing_last_name(),
					"document" => "'".get_post_meta( $order->get_id(), '_billing_client_cpf', true ) . "'",
					"email" => $order->get_billing_email(),
					"phone" => self::format_phone($order->get_billing_phone()),
				),
				"billing"=>array(
					"postalCode"=>$order->get_billing_postcode(),
					"district"=>"'".get_post_meta( $order->get_id(), 'billing_neighborhood', true )."'",
					"address"=>$order->get_billing_address_1(),
					"number"=>"'".get_post_meta( $order->get_id(), '_billing_number', true )."'",
					"city"=>$order->get_billing_city(),
					"state"=>$order->get_billing_state(),
					"country"=>"BR"
				),
				"cart"=>$cart,
				"postback"=>array(
					"url"=>get_site_url()."/wp-json/belluno/v1/billet",
				)
			));

		$transaction= json_encode($transaction);

		$args = array(
			'body' => $transaction,
			'headers' => array(
				'Content-Type'  => 'application/json',
				'Authorization' => 'Bearer ' . self::getWPOptions('token'),
			),
		);

		$response = wp_remote_post( $url, $args );

		$body    = wp_remote_retrieve_body( $response );

		return $body;
	}

	protected function creditPayment($data){
		$order = new WC_Order( $data['orderID'] );

		$cardHash = self::generate_cardHash($data);

		$ship_to = isset( $data['ship_to_different_address'] ) ? true : false;

		$shipping = $this->addShipping($order,$ship_to);

		if ( 'yes' == self::getWPOptions('sandbox') ) {
			$url = 'https://ws-sandbox.bellunopag.com.br/transaction';
		} else {
			$url = 'https://api.belluno.digital/transaction';
		}

		if ( sizeof( $order->get_items() ) > 0 ) {
			foreach ( $order->get_items() as $item ) {
				if ($item['qty'] ) {
					$cart[]=array(
						"product_name"=>$item['name'],
						"quantity"=>$item['qty'],
						"unit_value"=>$item['total']/$item['qty']
					);
				}
			}
		}
		$transaction = array(
			"transaction"=>array(
				"value" => $order->get_total(),
				"capture" => 1,
				"card_hash" => $cardHash,
				"cardholder_name" =>$data['credit_card_name'],
				"cardholder_document" =>self::formatCnpjCpf($data['credit_card_cpf']),
				"cardholder_cellphone"=>$data['credit_card_phone'],
				"cardholder_birth"=>$data['credit-card-birthdate'],
				"brand"=>intval(self::getFlag($data['flagCard'])),
				"installment_number"=>$data['credit_card_installments'],
				"visitor_id"=>"'".$order->get_billing_email()."'",
				"payer_ip"=>$_SERVER['REMOTE_ADDR'],
				"client_name"=>$order->get_billing_first_name()." ".$order->get_billing_last_name(),
				"client_document"=>"'".get_post_meta( $order->get_id(), '_billing_client_cpf', true )."'",
				"client_email"=>$order->get_billing_email(),
				"client_cellphone"=>self::format_phone($order->get_billing_phone()),
				"details"=>$order->get_billing_address_2(),
				"shipping" => array(
									"postalCode"=>$shipping['postalCode'],
									"street"=>$shipping['street'],
									"number"=>$shipping['number'],
									"city"=>$shipping['city'],
									"state"=>$shipping['state']
								),
				"billing"=>array(
									"postalCode"=>$order->get_billing_postcode(),
									"street"=>$order->get_billing_address_1(),
									"number"=>"'".get_post_meta( $order->get_id(), '_billing_number', true )."'",
									"city"=>$order->get_billing_city(),
									"state"=>$order->get_billing_state()
				),
				"cart"=>$cart,
				"postback"=>array(
					"url"=>get_site_url()."/wp-json/belluno/v1/card",
				)
			));

		$transaction= json_encode($transaction);
		

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

		$response = wp_safe_remote_post( $url, $args );

		$body    = wp_remote_retrieve_body( $response );
		$http_code = wp_remote_retrieve_response_code( $response );

		//if($http_code == 200) return $response;
		return $body;
	}

	protected function addShipping($order,$ship_to=false){
		if($ship_to){
			$shipping['postalCode'] = $order->get_shipping_postcode();
			$shipping['street'] = $order->get_shipping_address_1();
			$shipping['number'] = "'".get_post_meta( $order->get_id(), '_shipping_number', true )."'";
			$shipping['city'] = $order->get_shipping_city();
			$shipping['state'] =  $order->get_shipping_state();
		}else{
			$shipping['postalCode'] = $order->get_billing_postcode();
			$shipping['street'] = $order->get_billing_address_1();
			$shipping['number'] = "'".get_post_meta( $order->get_id(), '_billing_number', true )."'";
			$shipping['city'] = $order->get_billing_city();
			$shipping['state'] =  $order->get_billing_state();			
		}
		return $shipping;
	}


	public function billetButton($order_id ){
		$method = get_post_meta($order_id,'_belluno_method',true);
		if($method=='BoletoBancario'){
			$html = '<a class="'.esc_attr("button").'" href="'.esc_url(get_post_meta($order_id,'_belluno_billet_url',true)).'" target="'.esc_attr("_blank").'">'.esc_html("Imprimir boleto").'</a><br>';
			echo $html;
		}
	}

	protected function getFlag($value){
		$Flags=array(
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
	protected function format_phone($phone){
		if(!(strstr($phone, '('))){
			$ddd = '('.substr($phone,0,2).')';
			$phone = substr($phone,2,strlen($phone));
			return $ddd.$phone;
		}else{
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

	protected function formatDate($date){
		$dt = explode("/",$date);
		return (strlen($dt[1]) == 2) ? $dt[0]."/20".$dt[1] : $date;
	}	

	/**
	 * Gets the admin url.
	 *
	 * @return string
	 */
	protected function admin_url() {
		if ( version_compare( WOOCOMMERCE_VERSION, '2.1', '>=' ) ) {
			return admin_url( 'admin.php?page=wc-settings&tab=checkout&section=wc_belluno_gateway' );
		}

		return admin_url( 'admin.php?page=woocommerce_settings&tab=payment_gateways&section=WC_Belluno_Gateway' );
	}

	/**
	 * Adds error message when not configured the token.
	 *
	 * @return string Error Mensage.
	 */
	public function token_missing_message() {
		//echo '<div class="error belluno belluno-token"><p><strong>' . __( 'Belluno está desabilitado!', 'wc-belluno' ) . '</strong>: ' . sprintf( __( 'É necessário que informe o Token de Acesso. %s', 'wc-belluno' ), '<a href="' . $this->admin_url() . '">' . __( 'Clique aqui para configurar!', 'wc-belluno' ) . '</a>' ) . '</p></div>';
		$class = 'notice notice-error belluno belluno-token is-dismissible';
		$message = __('Belluno está desabilitado!','wc-belluno');
		$description = __( 'É necessário informar o Token de Acesso..', 'wc-belluno' );
		$linkMessage = __('Clique aqui para configurar!', 'wc-belluno');
		$link = $this->admin_url();
		printf( '<div class="%1$s"><strong>%2$s</strong><p>%3$s <a href="%4$s">%5$s</a></p></div>', esc_attr( $class ), esc_html( $message ), esc_html($description),esc_html($link),esc_html($linkMessage) ); 
	}

	/**
	 * Adds error message when not configured the key.
	 *
	 * @return string Error Mensage.
	 */
	public function key_missing_message() {
		//echo '<div class="notice notice-error belluno belluno-key"><p><strong>' . __( 'Belluno está desabilitado!', 'wc-belluno' ) . '</strong>: ' . sprintf( __( 'É necessário informar o Token Antifraude. %s', 'wc-belluno' ), '<a href="' . $this->admin_url() . '">' . __( 'Clique aqui para configurar!', 'wc-belluno' ) . '</a>' ) . '</p></div>';
		$class = 'notice notice-error belluno belluno-key is-dismissible';
		$message = __('Belluno está desabilitado!','wc-belluno');
		$description = __( 'É necessário informar o Token Antifraude.', 'wc-belluno' );
		$linkMessage = __('Clique aqui para configurar!', 'wc-belluno');
		$link = $this->admin_url();
		printf( '<div class="%1$s"><strong>%2$s</strong><p>%3$s <a href="%4$s">%5$s</a></p></div>', esc_attr( $class ), esc_html( $message ), esc_html($description),esc_html($link),esc_html($linkMessage) ); 
	}

	public function noticeHttps() {
		//echo '<div class="error belluno belluno-https"><img style="max-width: 25px; min-width: 25px;border-radius: 50px; float:left; margin-top: 5px" src="'.plugins_url( 'assets/images/belluno1.png', plugin_dir_path( __FILE__ )).'"><div class="message" style="margin-left: 30px;margin-top: 4px;position:relative;top:5px">' . sprintf( __( 'Para segurança das transações, é <b>altamente</b> recomendável o uso de certificado SSL para o uso do plugin Belluno.', 'wc-belluno' )) . '</div></p></div>';
		$class = 'notice notice-error belluno belluno-https is-dismissible';
		$message = __('Belluno: Certificado SSL não encontrado!','wc-belluno');
		$description = __( 'Para segurança das transações, é altamente recomendável o uso de certificado SSL para o uso do plugin Belluno.', 'wc-belluno' );
		printf( '<div class="%1$s"><strong>%2$s</strong><p>%3$s</p></div>', esc_attr( $class ), esc_html( $message ), esc_html($description)); 
	}


	/**
	 * Payment fields.
	 */
	public function payment_fields() {

		$token = self::getWPOptions('token');
		if ( $token ) {

			if ( 'yes' == self::getWPOptions('sandbox') ) {
				$url = 'https://ws-sandbox.bellunopag.com.br/transaction/card_hash_key/';
			} else {
				$url = 'https://api.belluno.digital/transaction/card_hash_key/';
			}

			ob_start();

			include plugin_dir_path( dirname( __FILE__ ) ) . 'templates/transparent-checkout-belluno.php';
			
			$html = ob_get_clean();
			
			$html.= "<script src='".plugins_url( 'assets/js/jquery.mask.min.js', plugin_dir_path( __FILE__ ) )."'></script>
					 <script src='".plugins_url( 'assets/js/belluno-params.js', plugin_dir_path( __FILE__ ) )."'></script>
					 <script src='".plugins_url( 'assets/js/checkout.min.js', plugin_dir_path( __FILE__ ) )."'></script>";

			echo $html;
		} else {
			// Mensagens caso algum erro ocorra.
			$html = '<p>' . __( 'Ocorreu um erro ao processar o seu pagamento, tente novamente. Ou entre em contato conosco para obter ajuda.', 'wc-belluno' ) . '</p>';
			echo $html;
		}
	}
	public static function belluno_add_info_email($order, $sent_to_admin){
		if ( ! $sent_to_admin ) {
			$method = get_post_meta( $order->get_id(), '_belluno_method', true );
			if($method == 'BoletoBancario'){
				$html = '<a class="'.esc_attr("button").'" href="'.esc_url(get_post_meta($order->get_id(),'_belluno_billet_url',true)).'" target="'.esc_attr("_blank").'">'.esc_html("Imprimir boleto").'</a><br>';
				echo $html;
				/*$class = "button";
				$link = get_post_meta($order->get_id(),'_belluno_billet_url',true);
				$message = __('Imprimir boleto','wc-belluno');
				printf('<a class="%1$s" href="%2$s target="_blank">%3$s</a><br>', esc_attr( $class ), esc_link( $link ), esc_html( $message ));*/
				//echo "<a class='button' href=".get_post_meta($order->get_id(),'_belluno_billet_url',true)." target='_blank'>Imprimir boleto</a><br>";
			}
		}
	}

	protected function belluno_add_notice($message,$type='error'){
		return wc_add_notice($message,$type);
	}

}