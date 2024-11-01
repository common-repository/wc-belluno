<?php

/**
 * Plugin Name: Gateway de pagamento Belluno
 * Description: Gateway de pagamento Belluno para WooCommerce.
 * Version: 5.0.11
 * Author: Belluno Digital Bank
 * Author URI: https://belluno.digital/
 * Text Domain: wc-belluno
 * Tested up to: 6.5.3
 * WC requires at least: 4.8.0
 * WC tested up to: 8.9.0
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}
//Check if WooCommerce is active
if (in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {

    define('WC_BELLUNO_PLUGIN', __FILE__);

    if (!class_exists('WC_Belluno')) :
        /**
         * Plugin's main class.
         */
        class WC_Belluno
        {
            /**
             * Plugin version.
             *
             * @var string
             */
            const VERSION = '1.0';

            /**
             * Integration id.
             *
             * @var string
             */
            protected static $gateway_id = 'belluno';

            /**
             * Instance of this class.
             *
             * @var object
             */
            protected static $instancia = null;

            public function __construct()
            {

                // Checks with WooCommerce is installed.
                if (class_exists('WC_Payment_Gateway')) {
                    $this->includes();
                    add_filter('woocommerce_payment_gateways', array($this, 'add_gateway'));
                    add_filter('plugin_action_links_' . plugin_basename(__FILE__), array($this, 'plugin_action_links'));
                    add_action( 'before_woocommerce_init', function() {
                        if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
                            \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
                        }
                    } );
                } else {
                    add_action('admin_notices', array($this, 'woocommerce_missing_notice'));
                }
            }

            /**
             * Retorna a instancia dessa classe
             *
             * @return object Retorna a instancia dessa classe
             */
            public static function get_belluno_instacia()
            {
                // se não existe instancia da classe, seta agora.
                if (null == self::$instancia) {
                    self::$instancia = new self;
                }
                return self::$instancia;
            }

            /**
             * Retornar o slug do gateway
             *
             * @return string.
             */
            public static function get_gateway_id()
            {
                return self::$gateway_id;
            }

            /**
             * Action links.
             *
             * @param  array $links Plugin links.
             *
             * @return array
             */
            public function plugin_action_links($links)
            {
                $plugin_links = array();

                $plugin_links[] = '<a href="' . esc_url(admin_url('admin.php?page=wc-settings&tab=checkout')) . '">' . __('Configurações', 'wc-belluno') . '</a>';

                return array_merge($plugin_links, $links);
            }

            /**
             * Add the gateway to WooCommerce.
             *
             * @param  array $methods WooCommerce payment methods.
             *
             * @return array Payment methods with Belluno.
             */
            public function add_gateway($methods)
            {
                $methods[] = 'WC_Belluno_Card';
                $methods[] = 'WC_Belluno_Pix';
                $methods[] = 'WC_Belluno_Bankslip';
                return $methods;
            }
            /**
             * Includes.
             */
            private function includes()
            {
                include_once 'includes/class-wc-belluno-rest.php';
                include_once 'includes/functions-belluno.php';
                include_once 'includes/rest-belluno.php';
                require_once plugin_dir_path(__FILE__) . 'vendor/autoload.php';
                include_once 'includes/class-wc-belluno-card.php';
                include_once 'includes/class-wc-belluno-bankslip.php';
                include_once 'includes/class-wc-belluno-pix.php';
                include_once($_SERVER['DOCUMENT_ROOT'].'/wp-load.php');
            }
            /**
             * WooCommerce fallback notice.
             *
             * @return string
             */
            public function woocommerce_missing_notice()
            {
                echo '<div class="error"><p>' . sprintf(__('O Plugin de pagamento Belluno para Woocommerce depende da última versão de %s para funcionar corretamente!', 'wc-belluno'), '<a href="http://wordpress.org/extend/plugins/woocommerce/">WooCommerce</a>') . '</p></div>';
            }
        }

        add_action('plugins_loaded', array('WC_Belluno', 'get_belluno_instacia'), 0);
    endif;
}
/**
 * Adds support to legacy IPN.
 *
 * @return void
 */
function wcbelluno_legacy_ipn()
{
    if (isset($_POST['cod_belluno']) && !isset($_GET['wc-api'])) {
        global $woocommerce;

        $woocommerce->payment_gateways();

        do_action('woocommerce_api_wc_belluno_gateway');
    }
}

add_action('init', 'wcbelluno_legacy_ipn');
