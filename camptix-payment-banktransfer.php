<?php // encoding: utf-8
/*
Plugin Name: CampTix Payment Method: Bank transfer
Plugin Description: Extends CampTix with support for Bank transfer
Version: 1.0.0
Author: Raphael Michel
*/

require_once __DIR__.'/../camptix/camptix.php';

if(!class_exists('CampTix_Payment_Method'))
	die('Plugin "CampTix Payment Method: Bank transfer" needs CampTix to be installed.');

class CampTix_Payment_Method_Banktransfer extends CampTix_Payment_Method {
    public $id = 'banktransfer';
    public $name = 'Bank Transfer';
    public $description = 'Bank transfer checkout.';
	public $supported_currencies = array( 'AUD', 'CAD', 'EUR', 'GBP', 'JPY', 'USD', 'NZD', 'CHF', 'HKD', 'SGD', 'SEK', 
		'DKK', 'PLN', 'NOK', 'HUF', 'CZK', 'ILS', 'MXN', 'BRL', 'MYR', 'PHP', 'TWD', 'THB', 'TRY');
	
	protected $options = array();

	public function __construct () {
		$this->name = __('Bank Transfer', 'camptixpaymentbanktransfer');
		$this->description = __('Bank transfer checkout. Please fill in your bank details below, the user will be prompted to transfer money according to this details. <strong>Make sure to include the shortcode [bankdetails] in your e-mail templates!</strong>', 'camptixpaymentbanktransfer');
		parent::__construct();
	}

    public function payment_checkout( $payment_token ) {
		global $camptix;
		
		$ident = uniqid();
		
		$order = $this->get_order( $payment_token );
		
		$attendees = get_posts( array(
			'posts_per_page' => 1,
			'post_type' => 'tix_attendee',
			'post_status' => 'any',
			'meta_query' => array(
				array(
					'key' => 'tix_payment_token',
					'compare' => '=',
					'value' => $payment_token,
					'type' => 'CHAR',
				),
			),
		) );
		$ID = $attendees[0]->ID;
		
		update_post_meta( $ID, 'tix_banktransfer_token', $ident );

		return $this->payment_result( $payment_token, $camptix::PAYMENT_STATUS_PENDING, $payment_data );
    }
	
	// Fired when CampTix initialized, you don't have to call this
	function camptix_init() {
		$this->options = array_merge( array(
			'bankdetails' => '',
		), $this->get_payment_options() );
		
		add_action( 'camptix_notices',  array($this, 'payment_pending_information'), 11 );
		
		add_action( 'camptix_init_email_templates_shortcodes', array( $this, 'init_email_templates_shortcodes' ), 9 );
	}
	
	function init_email_templates_shortcodes () {
		add_shortcode( 'bankdetails', array( $this, 'shortcode_bankdetails' ) );
	}
	
	function shortcode_bankdetails( $attr ) {
		global $camptix;
		
		$price = $camptix->append_currency( get_post_meta( $camptix->tmp('attendee_id'), 'tix_order_total', true ), false );
		$reference = get_post_meta( $camptix->tmp('attendee_id'), 'tix_banktransfer_token', true );
		$bankdetails = __( "Please transfer your money to the following bank account:", 'camptixpaymentbanktransfer' )."\n";
		$bankdetails .= $this->options['bankdetails'];
		$bankdetails .= sprintf( "\n" . __( "Reference: %s", 'camptixpaymentbanktransfer' ), $reference);
		$bankdetails .= sprintf( "\n" . __( "Amount: %s", 'camptixpaymentbanktransfer' ),  $price );
		
		return $bankdetails;
	}
	
	function payment_pending_information() {
		global $camptix;

		if ( 'edit_attendee' == get_query_var( 'tix_action' ) ) {
			$attendee_id = intval( $_REQUEST['tix_attendee_id'] );
			$attendee = get_post( $attendee_id );
		} else if ( 'access_tickets' == get_query_var( 'tix_action' ) ) {
			$access_token = $_REQUEST['tix_access_token'];
			$is_refundable = false;

			// Let's get one attendee
			$attendees = get_posts( array(
				'posts_per_page' => 1,
				'post_type' => 'tix_attendee',
				'post_status' => array( 'publish', 'pending' ),
				'meta_query' => array(
					array(
						'key' => 'tix_access_token',
						'value' => $access_token,
						'compare' => '=',
						'type' => 'CHAR',
					),
				),
				'cache_results' => false,
			) );
			$attendee = $attendees[0];
			$attendee_id = $attendee->ID;
		} else {
			return;
		}

		if ( $attendee->post_status != 'pending' )
			return;
		
		if ( get_post_meta( $attendee_id, 'tix_payment_method', true ) == $this->id ){
			
			$price = $camptix->append_currency( get_post_meta( $attendee_id, 'tix_order_total', true ) );
			$bankdetails = nl2br($this->options['bankdetails']);
			$bankdetails .= sprintf( "<br />" . __( "Reference: <strong>%s</strong>", 'camptixpaymentbanktransfer' ), get_post_meta( $attendee_id, 'tix_banktransfer_token', true ));
			$bankdetails .= sprintf( "<br />" . __( "Amount: %s", 'camptixpaymentbanktransfer' ),  $price );
			
			echo '<p class="tix-notice">';
			echo  __( "Please transfer your money to the following bank account:", 'camptixpaymentbanktransfer' ) . "<br />" . $bankdetails;
			echo '</p>';
		}
	}

	// This is also called by CampTix when rendering your payment method sections
	function payment_settings_fields() {
		global $camptix;
		// You can use the helper function along with the helper callback function
		$this->add_settings_field_helper( 'bankdetails', __('Bank details', 'camptixpaymentbanktransfer'), array( $camptix, 'field_textarea' ) );
	}

	// Called by CampTix when your payment settings are being saved
	function validate_options( $input ) {
		$output = $this->options;

		if ( isset( $input['bankdetails'] ) )
			$output['bankdetails'] = $input['bankdetails'];

		return $output;
	}
}

load_plugin_textdomain( 'camptixpaymentbanktransfer', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
camptix_register_addon( 'CampTix_Payment_Method_Banktransfer' );
