<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Atualiza arquivo principal
$active_plugins = get_option( 'active_plugins', array() );

foreach ( $active_plugins as $key => $active_plugin ) {
	if ( strstr( $active_plugin, '/wc-belluno.php' ) ) {
		$active_plugins[ $key ] = str_replace( '/wc-belluno.php', '/woocommerce-belluno.php', $active_plugin );
	}
}

update_option( 'active_plugins', $active_plugins );
