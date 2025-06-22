<?php

defined( 'ABSPATH' ) || exit;

add_action( 'plugins_loaded', function () {
	if ( is_admin() ) {
		\ErrorLogViewer\ErrorLogViewer::get_instance();
	}
} );

add_action( 'admin_enqueue_scripts', function ( $hook_suffix ) {
	if ( $hook_suffix === 'toplevel_page_php-error-log-viewer' ) {
		$suffix = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';
		wp_enqueue_style( 'tabulator', "https://unpkg.com/tabulator-tables@6.3.0/dist/css/tabulator{$suffix}.css", [], NULL );
		wp_enqueue_script( 'tabulator', "https://unpkg.com/tabulator-tables@6.3.0/dist/js/tabulator{$suffix}.js", [ 'jquery' ], NULL, FALSE );
		wp_enqueue_script( 'jquery-small-pubsub', "https://unpkg.com/jquery-small-pubsub@0.2.0/dist/pubsub{$suffix}.js", [ 'jquery' ], NULL, TRUE );
		wp_enqueue_script( 'tabulator-modules', "https://unpkg.com/tabulator-modules@1.6.0/dist/tabulator-modules{$suffix}.js", [ 'jquery', 'tabulator' ], NULL, TRUE );
	}
}, 1, 1 );