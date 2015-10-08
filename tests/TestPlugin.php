<?php
use Niteoweb\WP7GHQ\CF7GHQ;

$WPCF7_Submission_mock = null;

class WPCF7_Submission {
	public static function get_instance( $contact_form = null ) {
		global $WPCF7_Submission_mock;

		return $WPCF7_Submission_mock;
	}
}

class TestIntegration extends \Xpmock\TestCaseTrait {

	function setUp() {
		\WP_Mock::setUp();
	}

	function tearDown() {
		global $WPCF7_Submission_mock;
		\WP_Mock::tearDown();
		$WPCF7_Submission_mock = null;
	}

	public function test_init() {
		\WP_Mock::wpFunction( 'get_option', array(
				'called' => 1,
				'args'   => array( 'pluginGCF7GHQ_settings' ),
				'return' => array( 'cf7ghq_prevent_email' => "1" )
			)
		);
		$plugin = new CF7GHQ;
		$plugin->__construct();
	}

	public function test_init_default() {
		\WP_Mock::wpFunction( 'get_option', array(
				'called' => 1,
				'args'   => array( 'pluginGCF7GHQ_settings' ),
				'return' => array( 'cf7ghq_none' => true )
			)
		);
		$plugin = new CF7GHQ;
		$plugin->__construct();
	}

	public function test_submit_missing_cf() {
		global $WPCF7_Submission_mock;
		$WPCF7_Submission_mock = $mock = $this->mock()
		                                      ->get_posted_data( false )
		                                      ->new();
		\WP_Mock::wpFunction( 'get_option', array(
				'called' => 1,
				'args'   => array( 'pluginGCF7GHQ_settings' ),
				'return' => array( 'cf7ghq_none' => true )
			)
		);
		$plugin = new CF7GHQ;
		$mock   = $mock = $this->mock()
		                       ->in_demo_mode( false )
		                       ->additional_setting( false )
		                       ->new();
		$plugin->submitForm( $mock );
	}

	public function test_submit_demo_mode() {
		global $WPCF7_Submission_mock;
		$WPCF7_Submission_mock = $mock = $this->mock()
		                                      ->get_posted_data( false )
		                                      ->new();
		\WP_Mock::wpFunction( 'get_option', array(
				'called' => 1,
				'args'   => array( 'pluginGCF7GHQ_settings' ),
				'return' => array( 'cf7ghq_none' => true )
			)
		);
		$plugin = new CF7GHQ;
		$mock   = $mock = $this->mock()
		                       ->in_demo_mode( true )
		                       ->additional_setting( false )
		                       ->new();
		$plugin->submitForm( $mock );
	}

	public function test_submit_full() {
		global $WPCF7_Submission_mock;
		$WPCF7_Submission_mock = $mock = $this->mock()
		                                      ->get_posted_data( array(
			                                      "your-email" => "user@test.tld",
			                                      "test-key"   => "something"
		                                      ) )
		                                      ->new();
		\WP_Mock::wpFunction( 'get_option', array(
				'called' => 1,
				'args'   => array( 'pluginGCF7GHQ_settings' ),
				'return' => array( 'cf7ghq_none' => true )
			)
		);
		\WP_Mock::wpFunction( 'wp_mail', array(
				'called' => 1,
				'args'   => array( null, "Test: user@test.tld", "something" ),
			)
		);
		\WP_Mock::wpFunction( 'remove_filter', array(
				'called' => 1,
			)
		);
		$plugin = new CF7GHQ;
		$mock   = $mock = $this->mock()
		                       ->in_demo_mode( false )
		                       ->additional_setting( false )
		                       ->title( "Test" )
		                       ->prop( "[input test-key something]" )
		                       ->new();
		$plugin->submitForm( $mock );
	}

	public function test_submit_full_admin_email() {
		global $WPCF7_Submission_mock;
		$WPCF7_Submission_mock = $mock = $this->mock()
		                                      ->get_posted_data( array(
			                                      "test-key" => "something"
		                                      ) )
		                                      ->new();
		\WP_Mock::wpFunction( 'get_option', array(
				'called' => 1,
				'args'   => array( 'pluginGCF7GHQ_settings' ),
				'return' => array( 'cf7ghq_none' => true )
			)
		);
		\WP_Mock::wpFunction( 'get_option', array(
				'called' => 1,
				'args'   => array( 'admin_email' ),
				'return' => "admin@domain.tld"
			)
		);
		\WP_Mock::wpFunction( 'wp_mail', array(
				'called' => 1,
				'args'   => array( null, "Test: admin@domain.tld", "something" ),
			)
		);
		\WP_Mock::wpFunction( 'remove_filter', array(
				'called' => 1,
			)
		);
		$plugin = new CF7GHQ;
		$mock   = $mock = $this->mock()
		                       ->in_demo_mode( false )
		                       ->additional_setting( false )
		                       ->title( "Test" )
		                       ->prop( "[input test-key something]" )
		                       ->new();
		$plugin->submitForm( $mock );
	}


}
