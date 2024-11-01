<?php
/**
 * Belluno Routes
 **/

 // Exit if accessed directly
if (!defined('ABSPATH'))
	exit;

add_action('rest_api_init',function(){
    register_rest_route(
    'belluno/v1',
    'billet/',
    array(
        'methods' => 'POST',
        'callback' => array(new WC_Belluno_Rest,'billetBelluno'),
        'permission_callback' => '__return_true',
      )
    );
});

add_action('rest_api_init',function(){
    register_rest_route(
    'belluno/v1',
    'card/',
    array(
        'methods' => 'POST',
        'callback' => array(new WC_Belluno_Rest,'cardBelluno'),
        'permission_callback' => '__return_true',
      )
    );
});

add_action('rest_api_init',function(){
  register_rest_route(
  'belluno/v2',
  'pix/',
  array(
      'methods' => 'POST',
      'callback' => array(new WC_Belluno_Rest,'pixBelluno'),
      'permission_callback' => '__return_true',
    )
  );
});
