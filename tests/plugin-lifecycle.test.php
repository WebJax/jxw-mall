<?php

define( 'ABSPATH', __DIR__ );
define( 'CENTERSHOP_VERSION', 'test-version' );

require_once dirname( __DIR__ ) . '/includes/plugin-lifecycle.php';

function assert_true( $condition, $message ) {
	if ( ! $condition ) {
		throw new RuntimeException( $message );
	}
}

function assert_same( $expected, $actual, $message ) {
	if ( $expected !== $actual ) {
		throw new RuntimeException(
			$message . "\nExpected: " . var_export( $expected, true ) . "\nActual: " . var_export( $actual, true )
		);
	}
}

function test_activation_runs_setup_migrations_and_flush() {
	$calls = array();

	centershop_plugin_activate(
		array(
			'setup_post_types'   => function () use ( &$calls ) { $calls[] = 'setup'; },
			'run_db_migrations'  => function () use ( &$calls ) { $calls[] = 'migrate'; },
			'flush_rewrite_rules' => function () use ( &$calls ) { $calls[] = 'flush'; },
		)
	);

	assert_same( array( 'setup', 'migrate', 'flush' ), $calls, 'Activation should run setup, migrations and flush in order.' );
}

function test_deactivation_clears_crons_and_flushes() {
	$calls = array();

	centershop_plugin_deactivate(
		array(
			'get_cron_hooks'      => function () {
				return array( 'jxw_event_one', 'jxw_event_two', 'jxw_event_one', '' );
			},
			'clear_cron_hook'     => function ( $hook ) use ( &$calls ) { $calls[] = 'clear:' . $hook; },
			'flush_rewrite_rules' => function () use ( &$calls ) { $calls[] = 'flush'; },
		)
	);

	assert_same(
		array( 'clear:jxw_event_one', 'clear:jxw_event_two', 'flush' ),
		$calls,
		'Deactivation should clear each cron once and flush rewrite rules.'
	);
}

test_activation_runs_setup_migrations_and_flush();
test_deactivation_clears_crons_and_flushes();

assert_true( true, 'Lifecycle tests passed.' );
echo "plugin-lifecycle tests passed\n";
