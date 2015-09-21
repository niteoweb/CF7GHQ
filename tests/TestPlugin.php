<?php
use Niteoweb\WP7GHQ\CF7GHQ;

class TestIntegration extends PHPUnit_Framework_TestCase {

		function setUp() {
			\WP_Mock::setUsePatchwork( true );
			\WP_Mock::setUp();
		}

		function tearDown() {
			\WP_Mock::tearDown();
		}

		public function test_init() {
			$plugin = new CF7GHQ;

			\WP_Mock::expectActionAdded( 'wpcf7_submit', array( $plugin, 'submitForm' ) );

			$plugin->__construct();
			\WP_Mock::assertHooksAdded();
		}


	}