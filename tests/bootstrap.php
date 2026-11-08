<?php
/**
 * PHPUnit bootstrap for the plugin tests.
 *
 * @package DeadDove
 */

$tests_dir = getenv( 'WP_TESTS_DIR' ) ? getenv( 'WP_TESTS_DIR' ) : '/wordpress-phpunit';

define( 'WP_TESTS_PHPUNIT_POLYFILLS_PATH', dirname( __DIR__ ) . '/vendor/yoast/phpunit-polyfills' );

require_once $tests_dir . '/includes/functions.php';

tests_add_filter(
    'muplugins_loaded',
    function () {
        require dirname( __DIR__ ) . '/content-warning.php';
    }
);

require $tests_dir . '/includes/bootstrap.php';
