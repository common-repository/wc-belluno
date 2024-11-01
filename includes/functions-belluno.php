<?php

/**
 * Belluno Functions
 **/

// Exit if accessed directly
if (!defined('ABSPATH')) exit;

function belluno_konduto_meta_tags()
{
    if (is_product()) echo "<meta name:'kdt:page' content='produto'/>";
    if (is_product_category()) echo "<meta name:'kdt:page' content='categoria-produto'/>";
    if (is_cart()) echo "<meta name:'kdt:page' content='carrinho'/>";
    if (is_checkout()) echo "<meta name:'kdt:page' content='finalizar-compra'/>";
}

function belluno_kondutojs()
{
    echo "<script type='text/javascript'>
            var __kdt = __kdt || [];
            __kdt.push({'public_key': '" . get_option('woocommerce_belluno_card_settings')['key'] . "'});
            (function() {
                var kdt = document.createElement('script');
                kdt.id = 'kdtjs'; kdt.type = 'text/javascript';
                kdt.async = true;    kdt.src = 'https://i.k-analytix.com/k.js';
                var s = document.getElementsByTagName('body')[0];
                s.parentNode.insertBefore(kdt, s);
            })();

            var visitorID; 
            (function() {     
                var period = 300;     
                var limit = 20 * 1e3;     
                var nTry = 0;     
                var intervalID = setInterval(function() {         
                    var clear = limit/period <= ++nTry;         
                    if ((typeof(Konduto) !== 'undefined') && (typeof(Konduto.getVisitorID) !== 'undefined')) {             
                        visitorID = window.Konduto.getVisitorID();   
                        if (document.getElementById('belluno_visitor_id')) document.getElementById('belluno_visitor_id').value = visitorID;          
                        clear = true;         
                    }         
                    if (clear) {
                        clearInterval(intervalID); 
                    }     
                }, period);
            })(visitorID);
        </script>";
}
function belluno_identify_visitor_konduto($order)
{
    wp_enqueue_script("<script type='text/javascript'>
            var customerID = '$order'; // define o ID do cliente
            (function() {
                var period = 300;
                var limit = 20 * 1e3;
                var nTry = 0;
                var intervalID = setInterval(function() { // loop para retentar o envio
                    var clear = limit/period <= ++nTry;
                    if ((typeof(Konduto) !== 'undefined') &&
                        (typeof(Konduto.setCustomerID) !== 'undefined')) {
                    window.Konduto.setCustomerID(customerID); // envia o ID para a Konduto
                    clear = true;
                }
                if (clear) {
            clearInterval(intervalID);
            }
            }, period);
            })(customerID);
        </script>");
}


// Our hooked in function – $fields is passed via the filter!
function belluno_custom_override_checkout_fields($fields)
{
    if (!isset($fields['shipping']['shipping_number'])) {
        $fields['shipping']['shipping_number'] = array(
            'label'     => __('Número', 'woocommerce'),
            'placeholder'   => _x('Número', 'placeholder', 'woocommerce'),
            'required'  => true,
            'class'     => array('form-row-wide'),
            'clear'     => true
        );
    }

    if (!isset($fields['billing']['billing_number'])) {
        $fields['billing']['billing_number'] = array(
            'label'     => __('Número', 'woocommerce'),
            'placeholder'   => _x('Número', 'placeholder', 'woocommerce'),
            'required'  => true,
            'class'     => array('form-row-wide'),
            'clear'     => true
        );
    }

    if (!isset($fields['billing']['billing_neighborhood'])) {
        $fields['billing']['billing_neighborhood'] = array(
            'label'     => __('Bairro', 'woocommerce'),
            'placeholder'   => _x('Digite o seu bairro', 'placeholder', 'woocommerce'),
            'required'  => true,
            'class'     => array('form-row-wide'),
            'clear'     => true
        );
    }

    $pluginList = get_option('active_plugins');
    $plugin = 'woocommerce-extra-checkout-fields-for-brazil/woocommerce-extra-checkout-fields-for-brazil.php';
    if (!in_array($plugin, $pluginList)) {
        $fields['billing']['billing_client_cpf'] = array(
            'label'     => __('CPF/CNPJ', 'woocommerce'),
            'placeholder'   => _x('Digite o seu CPF ou CNPJ', 'placeholder', 'woocommerce'),
            'required'  => true,
            'class'     => array('form-row-wide'),
            'clear'     => true
        );
    }

    // $fields['billing']['billing_address_1']['placeholder'] = _x('Digite o seu Endereço', 'placeholder', 'woocommerce');
    // $fields['shipping']['shipping_address_1']['placeholder'] = _x('Digite o seu Endereço', 'placeholder', 'woocommerce');
    return $fields;
}

function belluno_custom_shipping_number_display_admin_order_meta($order)
{
    echo '<p><strong>' . __('Número') . ':</strong> ' . $order->get_meta('_shipping_number') . '</p>';
}
function belluno_custom_billing_number_display_admin_order_meta($order)
{
    echo '<p><strong>' . __('Número') . ':</strong> ' . $order->get_meta('_billing_number') . '</p>';
}
function belluno_custom_billing_neighborhood_display_admin_order_meta($order)
{
    echo '<p><strong>' . __('Bairro') . ':</strong> ' . $order->get_meta('_billing_neighborhood') . '</p>';
}
function belluno_custom_billing_client_cpf_admin_order_meta($order)
{
    echo '<p><strong>' . __('CPF') . ':</strong> ' . (
        $order->get_meta('_billing_cpf') ??
        $order->get_meta('_billing_cpf_1') ??
        $order->get_meta('_billing_cnpj') ??
        $order->get_meta('_billing_client_cpf')
    ) . '</p>';
}

function display_order_data_in_admin($order)
{
    if ($order->get_meta('_nsu_payment')) {
?>
        <div class="clear"></div>
        <h3><?php echo __('Pagamento'); ?></h3>
        <?php if ($order->get_meta('_nsu_payment')) echo '<p><strong>' . __('NSU') . ':</strong> ' . $order->get_meta('_nsu_payment') . '</p>'; ?>
        <?php if ($order->get_meta('_belluno_transaction_id')) echo '<p><strong>' . __('ID da transação') . ':</strong> ' . $order->get_meta('_belluno_transaction_id') . '</p>'; ?>

        <?php if ($order->get_meta('_card')) echo '<p><strong>' . __('Cartão') . ':</strong> ' . $order->get_meta('_card') . '</p>'; ?>
        <?php if ($order->get_meta('_card_brand')) echo '<p><strong>' . __('Bandeira') . ':</strong> ' . $order->get_meta('_card_brand') . '</p>'; ?>
        <?php if ($order->get_meta('_installments_number')) echo '<p><strong>' . __('Número de parcelas') . ':</strong> ' . $order->get_meta('_installments_number') . '</p>'; ?>
        <?php if ($order->get_meta('_card_holder')) echo '<p><strong>' . __('Titular do cartão') . ':</strong> ' . $order->get_meta('_card_holder') . '</p>'; ?>
        <?php if ($order->get_meta('_card_holder_document')) echo '<p><strong>' . __('Documento do titular do cartão') . ':</strong> ' . $order->get_meta('_card_holder_document') . '</p>'; ?>
        <?php if ($order->get_meta('_card_holder_cellphone')) echo '<p><strong>' . __('Celular do titular do cartão') . ':</strong> ' . $order->get_meta('_card_holder_cellphone') . '</p>'; ?>
    <?php
    }
}

function shop_order_columns($order_columns)
{
    unset($order_columns['order_total']);
    unset($order_columns['wc_actions']);
    unset($order_columns['origin']);
    $order_columns['payment'] = "Dados do pagamento";
    $order_columns['order_total'] = "Total";
    $order_columns['wc_actions'] = "Ações";
    return $order_columns;
}
add_filter('manage_edit-shop_order_columns', 'shop_order_columns');


function shop_order_posts_custom_column($colname)
{
    if ($colname == 'payment') {
        global $the_order; // the global order object
        if ($the_order->get_meta('_nsu_payment') && $the_order->get_transaction_id()) {
            echo '<p><strong>' . __('NSU') . ':</strong> ' . $the_order->get_meta('_nsu_payment') . '<br>';
            echo '<p><strong>' . __('ID da transação') . ':</strong> ' . $the_order->get_transaction_id();
        }
    }
}
add_action('manage_shop_order_posts_custom_column', 'shop_order_posts_custom_column');

function belluno_add_discount($cart_object)
{
    if (is_admin() && !defined('DOING_AJAX')) return;

    $chosen_payment_method = WC()->session->get('chosen_payment_method');
    global $woocommerce;
    $sub_total = $woocommerce->cart->get_subtotal();
    if ('belluno_pix' == $chosen_payment_method) {
        $discount = (float)get_option('woocommerce_belluno_pix_settings')['discount'];
        // Adding the discount
        if ($discount != 0) {
            $cart_object->add_fee(__('Desconto PIX', 'wc-belluno'), - ($sub_total / 100 * $discount), false);
            WC()->session->set('pix_discount', ($sub_total / 100 * (float)$discount));
        } else {
            WC()->session->set('pix_discount', 0);
        }
    }

    if ('belluno_bankslip' == $chosen_payment_method) {
        $discount = (float)get_option('woocommerce_belluno_bankslip_settings')['discount'];
        if ($discount != 0) {
            // Adding the discount
            $cart_object->add_fee(__('Desconto Boleto', 'wc-belluno'), - ($sub_total / 100 * (float)$discount), false);
            WC()->session->set('bankslip_discount', ($sub_total / 100 * (float)$discount));
        } else {
            WC()->session->set('bankslip_discount', 0);
        }
    }
}

function belluno_refresh_payment_method()
{
    ?>
    <script type="text/javascript">
        (function($) {
            $('form.checkout').on('change', 'input[name^="payment_method"]', function() {
                $('body').trigger('update_checkout');
            });
        })(jQuery);
    </script>
<?php
}
add_filter('woocommerce_checkout_fields', 'belluno_custom_override_checkout_fields');
add_action('woocommerce_admin_order_data_after_billing_address', 'belluno_custom_billing_number_display_admin_order_meta', 10, 1);
add_action('woocommerce_admin_order_data_after_billing_address', 'belluno_custom_billing_neighborhood_display_admin_order_meta', 10, 1);
add_action('woocommerce_admin_order_data_after_billing_address', 'belluno_custom_billing_client_cpf_admin_order_meta', 10, 1);
add_action('woocommerce_admin_order_data_after_shipping_address', 'belluno_custom_shipping_number_display_admin_order_meta', 10, 1);

add_action('woocommerce_cart_calculate_fees', 'belluno_add_discount', 20, 1);
add_action('woocommerce_review_order_before_payment', 'belluno_refresh_payment_method');
add_action('wp_footer', 'belluno_kondutojs', 10, 1);
add_action('wp_konduto', 'belluno_identify_visitor_konduto', 20, 1);
add_action('wp_head', 'belluno_konduto_meta_tags', -1000);

add_action('woocommerce_email_after_order_table', array('WC_Belluno_Bankslip', 'belluno_add_info_email'), 10, 2);

add_action('woocommerce_admin_order_data_after_order_details', 'display_order_data_in_admin');
// Add custom discount based on the selection
function apply_custom_discount($cart)
{
    $chosen_payment_method = WC()->session->get('chosen_payment_method');

    if (!is_null(WC()->session->get('installment_number'))) {
        $installment_number = WC()->session->get('installment_number');
    } else {
        $installment_number = 1;
    }

    if (!is_null(WC()->session->get('chosen_payment_method'))) {
        $chosen_payment_method = WC()->session->get('chosen_payment_method');
    } else {
        $chosen_payment_method = '';
    }


    if (
        $chosen_payment_method != 'belluno_card' ||
        (is_admin() && !defined('DOING_AJAX')) ||
        did_action('woocommerce_before_calculate_totals') >= 2
    ) {
        return;
    }

    $sub_total = (float)$cart->get_subtotal() - (float)$cart->get_discount_total()  + (float)$cart->get_shipping_total();

    //TODO take to config default by client/user
    if ($installment_number == 1) {
        WC()->session->set('installment_fee', 0);

        $fee_name = __('Desconto à vista');

        $discount = (float)get_option('woocommerce_belluno_card_settings')['discount'];
        $fee_amount = (($sub_total * ($discount / 100)) * -1);
    } else {
        $instalment_fee = (float)get_option('woocommerce_belluno_card_settings')['installments_fee_' . $installment_number];

        if ($instalment_fee > 0) {
            $fee_name = __('Taxa de parcelamento');
            $fee_amount = $sub_total * ($instalment_fee / 100);
            WC()->session->set('installment_fee', $fee_amount);
        } else {
            WC()->session->set('installment_fee', 0);
            $fee_amount = 0;
        }
    }

    // Remove all instances of the discount to avoid duplicates
    foreach ($cart->get_fees() as $key => $fee) {
        if ($fee->name === $fee_name) {
            unset($cart->fees_api()->fees[$key]);
        }
    }
    if ($fee_amount != 0) {
        // Add fee with updated amount
        $cart->add_fee($fee_name, $fee_amount, false);
    }
}

add_action('woocommerce_cart_calculate_fees', 'apply_custom_discount', 20, 5);

// Handle AJAX request to set the custom selection
function set_custom_discount_session()
{
    if (isset($_POST['installment_number'])) {
        WC()->session->set('installment_number', $_POST['installment_number']);
    }
    wp_die();
}

add_action('wp_ajax_set_custom_discount', 'set_custom_discount_session');
add_action('wp_ajax_nopriv_set_custom_discount', 'set_custom_discount_session');
