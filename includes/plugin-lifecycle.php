<?php

/**
 * Plugin lifecycle
 * 
 * @package Centershop
 * @subpackage Plugin Lifecycle
 * @version 1.0.0
 * @since 1.0.0
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Run the database migrations
 * 
 * @return void
 */
function centershop_run_db_migrations() {
	if ( function_exists( 'do_action' ) ) {
		do_action( 'centershop_run_db_migrations' );
	}

	if ( function_exists( 'update_option' ) && defined( 'CENTERSHOP_VERSION' ) ) {
		update_option( 'centershop_db_version', CENTERSHOP_VERSION );
	}
}

/**
 * Get the cron hooks to clear
 * 
 * @return array The cron hooks
 */
function centershop_get_cron_hooks_to_clear() {
	if ( ! function_exists( 'apply_filters' ) ) {
		return array();
	}

	return (array) apply_filters( 'centershop_cron_hooks', array() );
}

/**
 * Plugin activation
 * 
 * @param array $deps The dependencies
 * @return void
 */
function centershop_plugin_activate( array $deps = array() ) {
	$setup_post_types   = $deps['setup_post_types'] ?? 'centershop_setup_post_types';
	$run_db_migrations  = $deps['run_db_migrations'] ?? 'centershop_run_db_migrations';
	$flush_rewrite_rule = $deps['flush_rewrite_rules'] ?? 'flush_rewrite_rules';

	if ( is_callable( $setup_post_types ) ) {
		call_user_func( $setup_post_types );
	}

	if ( is_callable( $run_db_migrations ) ) {
		call_user_func( $run_db_migrations );
	}

	if ( is_callable( $flush_rewrite_rule ) ) {
		call_user_func( $flush_rewrite_rule );
	}
}

/**
 * Plugin deactivation
 * 
 * @param array $deps The dependencies
 * @return void
 */
function centershop_plugin_deactivate( array $deps = array() ) {
	$get_cron_hooks     = $deps['get_cron_hooks'] ?? 'centershop_get_cron_hooks_to_clear';
	$clear_cron_hook    = $deps['clear_cron_hook'] ?? 'wp_clear_scheduled_hook';
	$flush_rewrite_rule = $deps['flush_rewrite_rules'] ?? 'flush_rewrite_rules';

	$cron_hooks = is_callable( $get_cron_hooks ) ? (array) call_user_func( $get_cron_hooks ) : array();

	foreach ( array_unique( $cron_hooks ) as $hook ) {
		if ( is_string( $hook ) && $hook !== '' && is_callable( $clear_cron_hook ) ) {
			call_user_func( $clear_cron_hook, $hook );
		}
	}

	if ( is_callable( $flush_rewrite_rule ) ) {
		call_user_func( $flush_rewrite_rule );
	}
}

/**
 * On activation
 * 
 * @return void
 */
function centershop_on_activation() {
	centershop_plugin_activate();
}

/**
 * On deactivation
 * 
 * @return void
 */
function centershop_on_deactivation() {
	centershop_plugin_deactivate();
}

/**
 * Register the lifecycle hooks
 * 
 * @param string $plugin_file The plugin file
 * @return void
 */
function centershop_register_lifecycle_hooks( $plugin_file ) {
	if ( function_exists( 'register_activation_hook' ) ) {
		register_activation_hook( $plugin_file, 'centershop_on_activation' );
	}

	if ( function_exists( 'register_deactivation_hook' ) ) {
		register_deactivation_hook( $plugin_file, 'centershop_on_deactivation' );
	}
}
