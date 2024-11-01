<?php

use Automattic\WooCommerce\Utilities\OrderUtil;

/**
 * Classe WC Belluno Rest .
 *
 *
 */
class WC_Belluno_Rest
{
    public function billetBelluno($data)
    {
        $transaction_id = $data['bankslip']['id'];
        $order_id = self::getOrderID($transaction_id);
        $order = wc_get_order($order_id);
        switch ($data['bankslip']['status']) {
            case 'Paid':
                // do not change order status if it is already processing
                if (!$order->has_status('processing')) {
                    $order->update_status('processing', __('Notificação Belluno: O Pagamento foi realizado com sucesso!', 'wc-belluno'));
                    $order->payment_complete($transaction_id);
                }
                break;
            case 'Expired':
                // do not change order status if it is already cancelled
                if (!$order->has_status('cancelled')) {
                    $order->update_status('cancelled', __('Notificação Belluno: O pagamento do pedido não pode mais ser realizado! ', 'wc-belluno'));
                }
                break;
            case 'Inactivated':
                // do not change order status if it is already failed
                if (!$order->has_status('failed')) {
                    $order->update_status('failed', __('Notificação Belluno: O pagamento do pedido não pode mais ser realizado!', 'wc-belluno'));
                }
                break;
            case "Open":
            case "Unpaid":
                // do not change order status if it is already on-hold
                if (!$order->has_status('on-hold')) {
                    $order->update_status('on-hold', __('Notificação Belluno: Aguardando o pagamento.', 'wc-belluno'));
                }
                break;
            default:
                break;
        }
    }

    public function cardBelluno($data)
    {
        $transaction_id = $data['transaction']['transaction_id'];
        $order_id = self::getOrderID($transaction_id);
        $order = wc_get_order($order_id);
        switch ($data['transaction']['status']) {
            case 'Paid':
                // do not change order status if it is already processing
                if (!$order->has_status('processing')) {
                    $order->update_meta_data('_nsu_payment', $data['transaction']['nsu_payment']);
                    $order->update_meta_data('_installments_number', $data['transaction']['installments_number']);

                    $order->update_meta_data('_card', $data['transaction']['card']);
                    $order->update_meta_data('_card_brand', $data['transaction']['brand']);
                    $order->update_meta_data('_card_holder', $data['transaction']['cardholder']);
                    $order->update_meta_data('_card_holder_document', $data['transaction']['cardholder_document']);
                    $order->update_meta_data('_card_holder_cellphone', $data['transaction']['cardholder_cellphone']);
                    $order->update_meta_data('_card_holder_birthday', $data['transaction']['cardholder_birthday']);

                    $order->save();

                    $order->update_status('processing', __('Notificação Belluno: O Pagamento foi realizado com sucesso!', 'wc-belluno'));
                    $order->payment_complete($transaction_id);
                }
                break;
            case "Refused":
                // do not change order status if it is already failed
                if (!$order->has_status('failed')) {
                    $order->update_status('failed', __('Notificação Belluno: O Pagamento foi recusado!', 'wc-belluno'));
                    //Pagamento recusado...Estoque volta.
                    if (function_exists('wc_increase_stock_levels')) {
                        wc_increase_stock_levels($order_id);
                    }
                }
                break;
            case "Manual Analysis":
                // do not change order status if it is already on-hold
                if (!$order->has_status('on-hold')) {
                    $order->update_status('on-hold', __('Notificação Belluno: O Pagamento está sendo analisado!', 'wc-belluno'));
                }
                break;
            case "Client Manual Analysis":
                if (!$order->has_status('on-hold')) {
                    // do not change order status if it is already on-hold
                    $order->update_status('on-hold', __('Notificação Belluno: O Pagamento está sendo analisado!', 'wc-belluno'));
                }
                break;
            case "Open":
            case "Unpaid":
                if (!$order->has_status('on-hold')) {
                    // do not change order status if it is already on-hold
                    $order->update_status('on-hold', __('Notificação Belluno: Aguardando o pagamento.', 'wc-belluno'));
                }
                break;
            default:
                break;
        }
    }

    public function pixBelluno($data)
    {
        $transaction_id = $data['transaction']['transaction_id'];
        $order_id = self::getOrderID($transaction_id);
        $order = wc_get_order($order_id);
        switch ($data['transaction']['status']) {
            case 'Paid':
                // do not change order status if it is already processing
                if (!$order->has_status('processing')) {
                    $order->update_status('processing', __('Notificação Belluno: O Pagamento foi realizado com sucesso!', 'wc-belluno'));
                    $order->payment_complete($transaction_id);
                }
                break;
            case 'Expired':
                // do not change order status if it is already cancelled
                if (!$order->has_status('cancelled')) {
                    $order->update_status('cancelled', __('Notificação Belluno: O pagamento do pedido não pode mais ser realizado! ', 'wc-belluno'));
                }
                break;
            case "Refused":
                // do not change order status if it is already failed
                if (!$order->has_status('failed')) {
                    $order->update_status('failed', __('Notificação Belluno: O Pagamento foi recusado!', 'wc-belluno'));
                    //Pagamento recusado...Estoque volta.
                    if (function_exists('wc_increase_stock_levels')) {
                        wc_increase_stock_levels($order_id);
                    }
                }
                break;
            case "Manual Analysis":
                // do not change order status if it is already on-hold
                if (!$order->has_status('on-hold')) {
                    $order->update_status('on-hold', __('Notificação Belluno: O Pagamento está sendo analisado!', 'wc-belluno'));
                }
                break;
            case "Client Manual Analysis":
                if (!$order->has_status('on-hold')) {
                    // do not change order status if it is already on-hold
                    $order->update_status('on-hold', __('Notificação Belluno: O Pagamento está sendo analisado!', 'wc-belluno'));
                }
                break;
            case "Open":
            case "Unpaid":
                if (!$order->has_status('on-hold')) {
                    // do not change order status if it is already on-hold
                    $order->update_status('on-hold', __('Notificação Belluno: Aguardando o pagamento.', 'wc-belluno'));
                }
                break;
            default:
                break;
        }
    }

    private function getOrderID($transaction_id)
    {
        global $wpdb;
        if (OrderUtil::custom_orders_table_usage_is_enabled()) {
            // HPOS usage is enabled.
            $result =  $wpdb->get_results(
                $wpdb->prepare("select order_id from {$wpdb->prefix}wc_orders_meta where meta_value = %d and meta_key = '_belluno_transaction_id' ", $transaction_id),
                ARRAY_A
            );
            return $result[0]['order_id'];
        } else {
            // Traditional CPT-based orders are in use.

            $result =  $wpdb->get_results(
                $wpdb->prepare("select post_id from {$wpdb->prefix}postmeta where meta_value = %d and meta_key = '_belluno_transaction_id' ", $transaction_id),
                ARRAY_A
            );
            return $result[0]['post_id'];
        }
    }
}
