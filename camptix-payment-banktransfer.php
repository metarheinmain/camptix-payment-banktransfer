<?php // encoding: utf-8
/*
Plugin Name: CampTix Payment Method: Bank transfer
Description: Extends CampTix with support for Bank transfer
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
		
		$ident = uniqid(mt_rand(100,999));
		
		$order = $this->get_order( $payment_token );
		
		$attendees = get_posts( array(
			'posts_per_page' => 100,
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
		foreach($attendees as $a) {
			$ID = $a->ID;
			update_post_meta( $ID, 'tix_banktransfer_token', $ident );
		}
		
		return $this->payment_result( $payment_token, $camptix::PAYMENT_STATUS_PENDING, $payment_data );
    }
	
	// Fired when CampTix initialized, you don't have to call this
	function camptix_init() {
		$this->options = array_merge( array(
			'bankdetails' => '',
		), $this->get_payment_options() );
		
		add_action( 'camptix_notices',  array($this, 'payment_pending_information'), 11 );
		
		add_action( 'camptix_init_email_templates_shortcodes', array( $this, 'init_email_templates_shortcodes' ), 9 );
		
		add_action( 'admin_menu', array( $this, 'admin_menu' ) );
		
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts' ) );
	}

	function admin_enqueue_scripts() {
		global $wp_query;

		if ( ! $wp_query->query_vars ) { // only on singular admin pages
			if ( 'tix_ticket' == get_post_type() || 'tix_coupon' == get_post_type() ) {
			}
		}

		// Let's see whether to include admin.css and admin.js
		if ( is_admin() ) {
			$post_types = array( 'tix_ticket', 'tix_coupon', 'tix_email', 'tix_attendee' );
			$pages = array( 'camptix_options', 'camptix_tools' );
			if (
				( in_array( get_post_type(), $post_types ) ) ||
				( isset( $_REQUEST['post_type'] ) && in_array( $_REQUEST['post_type'], $post_types ) ) ||
				( isset( $_REQUEST['page'] ) && in_array( $_REQUEST['page'], $pages ) )
			) {
				wp_enqueue_style( 'camptix-payment-banktransfer-admin', plugins_url( '/admin.css', __FILE__ ), array(), $this->css_version );
			}
		}

		$screen = get_current_screen();
		if ( 'tix_ticket_page_camptix_options' == $screen->id ) {
			wp_enqueue_script( 'jquery-ui-datepicker' );
			wp_enqueue_style( 'jquery-ui', plugins_url( '/external/jquery-ui.css', __FILE__ ), array(), $this->version );
		}
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
	
	function admin_menu() {
		global $camptix;
		
		add_submenu_page( 'edit.php?post_type=tix_ticket', __( 'Bank Transfer', 'camptixpaymentbanktransfer' ), __( 'Bank Transfer', 'camptixpaymentbanktransfer' ), $camptix->caps['manage_attendees'], 'camptix_banktransfer', array( $this, 'menu_tools' ) );
	}
	
	function markpayed($payment_token) {
		global $camptix;
		$attendees = get_posts( array(
			'posts_per_page' => 100,
			'post_type' => 'tix_attendee',
			'post_status' => array( 'pending' ),
			'meta_query' => array(
				array(
					'key' => 'tix_payment_token',
					'value' => mysql_real_escape_string($payment_token),
					'compare' => '=',
					'type' => 'CHAR',
				),
			),
			'cache_results' => false,
		) );
		foreach ($attendees as $attendee) {
			$attendee->post_status = 'publish';
			wp_update_post( $attendee );
			$camptix->log( sprintf( 'Attendee status has been changed to %s', $attendee->post_status ), $attendee->ID );
		}
		$camptix->email_tickets($payment_token, 'pending', 'publish');
	}
	
	function menu_tools() {
		global $camptix;
		
		if(isset($_POST['reference'])) {
			if ( !wp_verify_nonce($_POST['single_reference_nonce'],'camptix_banktransfer') ) {
				print 'Sorry, your nonce did not verify.';
				exit;
			}
			else {
				$attendees = get_posts( array(
					'posts_per_page' => 100,
					'post_type' => 'tix_attendee',
					'post_status' => array( 'pending' ),
					'meta_query' => array(
						array(
							'key' => 'tix_banktransfer_token',
							'value' => mysql_real_escape_string($_POST['reference']),
							'compare' => '=',
							'type' => 'CHAR',
						),
					),
					'cache_results' => false,
				) );
				if(count($attendees) > 0){
					$_POST['amount'] = str_replace(',', '.', $_POST['amount']);
					$pt = get_post_meta( $attendees[0]->ID, 'tix_payment_token', true );
					$pf = floatval (get_post_meta( $attendees[0]->ID, 'tix_order_total', true ));
					$price = $camptix->append_currency( $pf, false );
					if ( $pf !== floatval($_POST['amount']) ) {
						add_settings_error(
							'',
							'tixBanktransferFailed',
							sprintf( __( 'Value %s does not match order total of %s!', 'camptixpaymentbanktransfer' ), floatval($_POST['amount']), $price),
							'error'
						);
					} else {
						$this->markpayed($pt);
						add_settings_error(
							'',
							'tixBanktransferSuccess',
							sprintf( __( '<strong>%d</strong> Tickets for an order total of <strong>%s</strong> marked as payed.', 'camptixpaymentbanktransfer' ), count($attendees), $price),
							'updated'
						);
					}
				} else {
					add_settings_error(
						'',
						'tixBanktransferNotFound',
						__( 'Payment reference not found or already marked as payed.', 'camptixpaymentbanktransfer' ),
						'error'
					);
				}
			}
		}

		?>
		<div class="wrap">
			<?php screen_icon( 'tools' ); ?>
			<h2><?php _e( 'Bank Transfer payment status import', 'camptixpaymentbanktransfer' ); ?></h2>
			<?php settings_errors(); ?>
			<p><?php _e( 'This page is a tool to mark payments which you recieved via wire transfer as payed. You can either enter a single payment reference or import a whole CSV from your bank. Actions on this page can take long because many emails might be to send...', 'camptixpaymentbanktransfer' ); ?></p>
			<h3><?php _e( 'Single reference', 'camptixpaymentbanktransfer' ); ?></h3>
			<form action="" method="post">
				<input type="text" name="reference" placeholder="<?php _e( 'Payment reference key', 'camptixpaymentbanktransfer' ) ?>" value="" />
				<input type="text" name="amount" placeholder="<?php _e( 'Amount (e.g. 100.5) in your currency', 'camptixpaymentbanktransfer' ) ?>" value="" />
				<input type="submit" value="<?php _e( 'Mark as payed', 'camptixpaymentbanktransfer' ); ?>" />
				<?php wp_nonce_field('camptix_banktransfer','single_reference_nonce'); ?>
			</form>
			<h3><?php _e( 'CSV import', 'camptixpaymentbanktransfer' ); ?></h3>
			<p><?php _e( 'Attention: This is very beta. If you\'re not the one I made this for, better don\'t use in a real environment. Also, this takes LONG! Make sure your server allows PHP scripts to run long. <strong>AND DO BACKUPS!</strong>', 'camptixpaymentbanktransfer' ); ?></p>
			<form action="" method="post" enctype="multipart/form-data">
				<input type="file" name="csv" value="" />
				<input type="submit" value="<?php _e( 'Mark as payed', 'camptixpaymentbanktransfer' ); ?>" />
				<?php wp_nonce_field('camptix_banktransfer','csv_reference_nonce'); ?>
			</form>
			<?php
			if(isset($_FILES['csv'])) {
				?>
				<h3><?php _e( 'CSV import results', 'camptixpaymentbanktransfer' ); ?></h3>
				<?php
				if ( !wp_verify_nonce($_POST['csv_reference_nonce'],'camptix_banktransfer') ) {
					print 'Sorry, your nonce did not verify.';
					exit;
				} else {
					$file = $_FILES['csv']['tmp_name'];
					$handle = fopen($file,"r");
					echo '<table class="tix-bank-import">';
					while ( $row = fgets ( $handle ) ) {
						$row = utf8_encode(trim($row));
						if ( $row == "" ) continue;
						$money = null;
						$res_class = '';
						$srow = '<td colspan="2"></td>';
						
						if ( preg_match ( '/[^a-zA-Z0-9]([abcdefABCDEF0-9]{14,17})[^a-zA-Z0-9]/', $row, $sub ) ) {
							$html_reference = $reference = $sub[1];
							if ( preg_match ( '/["\';,]([0-9]+[.,]?[0-9]*)["\';,]/', $row, $subm ) ) {
								$money = floatval( str_replace( ",", ".", $subm[1] ) );
								$attendees = get_posts( array(
									'posts_per_page' => 1,
									'post_type' => 'tix_attendee',
									'post_status' => array( 'pending', 'publish' ),
									'meta_query' => array(
										array(
											'key' => 'tix_banktransfer_token',
											'value' => mysql_real_escape_string($reference),
											'compare' => '=',
											'type' => 'CHAR',
										),
									),
									'cache_results' => false,
								) );
								if ( count( $attendees ) > 0 ) {
									if( $attendees[0]->post_status == "publish" ) {
										$res_text = __( 'Already payed', 'camptixpaymentbanktransfer' );
										$res_class = "payed";
									} else {
										$pf = floatval ( get_post_meta( $attendees[0]->ID, 'tix_order_total', true ) );
										$price = $camptix->append_currency( $pf, false );
										if($pf == $money) {
											$pt = get_post_meta( $attendees[0]->ID, 'tix_payment_token', true );
											$this->markpayed($pt);
											$res_text = sprintf ( __( 'Marked as payed.', 'camptixpaymentbanktransfer' ), $price );
											$res_class = "success";
										} else {
											$res_text = sprintf ( __( 'Wrong amount of money. Order total is %s!', 'camptixpaymentbanktransfer' ), $price );
											$res_class = "error";
										}
									}
								} else {
									$res_text = __( 'Invalid payment reference', 'camptixpaymentbanktransfer' );
									$res_class = "error";
								}
							} else {
								$res_text = __( 'No money amount found', 'camptixpaymentbanktransfer' );
								$res_class = "error";
							}
							$srow = '<td>'.$html_reference.'</td>';
							if($money)
								$srow .= '<td>' . $camptix->append_currency( $money ) . '</td>';
						} else {
							$res_text = __( 'No payment reference found', 'camptixpaymentbanktransfer' );
						}
						
						echo '<tr class="raw">';
						echo '<td colspan="2">'.$row.'</td>';
						echo '<td rowspan="2" class="'.$res_class.'">'.$res_text.'</td>';
						echo '</tr>';
						echo '<tr>';
						echo $srow;
						echo '</tr>';
					}
					echo '</table>';
				}
			}
			?>
		</div>
		<?php
	}
}

load_plugin_textdomain( 'camptixpaymentbanktransfer', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
camptix_register_addon( 'CampTix_Payment_Method_Banktransfer' );
