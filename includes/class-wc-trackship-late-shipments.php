<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WC_TrackShip_Late_Shipments {
	
	/**
	 * Instance of this class.
	 *
	 * @var object Class Instance
	*/
	private static $instance;	

	const CRON_HOOK = 'ast_late_shipments_cron_hook';	
	
	/**
	 * Get the class instance
	 *
	 * @since  1.0
	 * @return smswoo_license
	*/
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}
	
	/**
	 * Initialize the main plugin function
	 * 
	 * @since  1.0
	 * @return  void
	*/
	public function __construct() {
		$this->init();
	}
	
	/*
	 * init function
	 *
	 * @since  1.0
	*/
	public function init() {
		
		$ts_actions = new WC_Trackship_Actions();
		
		$wcast_enable_late_shipments_email = $ts_actions->get_option_value_from_array( 'late_shipments_email_settings', 'wcast_enable_late_shipments_admin_email', '');
		
		$wc_ast_api_key = get_option('wc_ast_api_key');
		if ( !$wcast_enable_late_shipments_email || !$wc_ast_api_key ) {
			return;
		}
		
		//cron schedule added
		add_action( 'wp_ajax_send_late_shipments_email', array( $this, 'send_late_shipments_email') );
		
		//Send Late Shipments Email
		add_action( self::CRON_HOOK, array( $this, 'send_late_shipments_email' ) );				
		
		if (!wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( time() , 'ast_late_shipments_cron_events', self::CRON_HOOK );			
		}
	}
	
	/**
	 * Remove the Cron
	 *
	 * @since  1.0.0
	 */
	public function remove_cron() {
		wp_clear_scheduled_hook( self::CRON_HOOK );
	}

	/**
	 * Setup the Cron
	 * 
	 * @since  1.0.0
	 */
	public function setup_cron() {

		$late_shipments_email_settings = get_option('late_shipments_email_settings');
		
		if ( isset( $late_shipments_email_settings['wcast_late_shipments_daily_digest_time'] ) ) {
			
			$wcast_late_shipments_daily_digest_time = $late_shipments_email_settings['wcast_late_shipments_daily_digest_time'];
			
			// Create a Date Time object when the cron should run for the first time
			$first_cron = new DateTime( gmdate( 'Y-m-d' ) . ' ' . $wcast_late_shipments_daily_digest_time . ':00', new DateTimeZone( wc_timezone_string() ) );	
			
			$first_cron->setTimeZone(new DateTimeZone('GMT'));
			
			$time = new DateTime( gmdate( 'Y-m-d H:i:s' ), new DateTimeZone( wc_timezone_string() ) );
			
			if ( $time->getTimestamp() >  $first_cron->getTimestamp() ) {
				$first_cron->modify( '+1 day' );
			}

			wp_schedule_event( $first_cron->format( 'U' ) + $first_cron->getOffset(), 'daily', self::CRON_HOOK );					
		
		} else {
			if (!wp_next_scheduled( self::CRON_HOOK ) ) {
				wp_schedule_event( time() , 'daily', self::CRON_HOOK );			
			}
		}
	}
	
	/**
	 *
	 * Send Late Shipments Email
	 *
	 */
	public function send_late_shipments_email() {	
		
		if ( in_array( get_option( 'user_plan' ), array( 'Free Trial', 'Free 50', 'No active plan' ) ) ) {
			return;
		}
		global $wpdb;
		$woo_trackship_shipment = $wpdb->prefix . 'trackship_shipment';
		
		$late_ship_day = trackship_for_woocommerce()->ts_actions->get_option_value_from_array('late_shipments_email_settings', 'wcast_late_shipments_days', 7 );
		$day = $late_ship_day - 1;
		
		//total late shipment count
		$count = $wpdb->get_var("
			SELECT 				
				COUNT(*)
				FROM {$woo_trackship_shipment}			
			WHERE 
				shipment_status NOT LIKE ( 'delivered')
				AND shipment_status NOT LIKE ( 'available_for_pickup')
				AND late_shipment_email = 0
				AND shipping_length > {$day}
		");
		
		if ( in_array( get_option( 'user_plan' ), array( 'Free Trial', 'Free 50', 'No active plan' ) ) ) {
			return;
		}
		// late shipment query in trackship_shipment table
		$total_order = $wpdb->get_results("
			SELECT *
				FROM {$woo_trackship_shipment}
			WHERE 
				shipment_status NOT LIKE ( 'delivered')
				AND shipment_status NOT LIKE ( 'available_for_pickup')
				AND late_shipment_email = 0
				AND shipping_length > {$day}
			LIMIT 10
		");
		
		//Send email for late shipment
		$email_send = $this->late_shippment_email_trigger( $total_order, $count );
		
		foreach ( $total_order as $key => $orders ) {
			if ( in_array( 1, $email_send ) ) {
				$where = array(
					'order_id'			=> $orders->order_id,
					'tracking_number'	=> $orders->tracking_number,
				);
				$wpdb->update( $woo_trackship_shipment, array( 'late_shipment_email' => 1 ), $where );
			}
		}
		exit;
	}

	/**
	 * Code for send late shipment status email
	 */
	public function late_shippment_email_trigger( $orders, $count ) {
		if ( in_array( get_option( 'user_plan' ), array( 'Free Trial', 'Free 50', 'No active plan' ) ) ) {
			return;
		}
		$logger = wc_get_logger();
		$sent_to_admin = false;
		$plain_text = false;
		
		$wcast_late_shipments_settings = new TSWC_Late_Shipments_Customizer_Email();
		//Email Subject
		$subject =  __( 'Late shipment', 'trackship-for-woocommerce' );
		// Email heading
		$email_heading = sprintf( __( 'We detected %d late shipments:', 'trackship-for-woocommerce' ) , $count );
		
		//Email Content
		$email_content = __( 'The following shipments are late:', 'trackship-for-woocommerce' );
		$email_content .= wc_get_template_html( 'emails/late-shipment-email.php', array(
			'orders' => $orders,
		), 'woocommerce-advanced-shipment-tracking/', trackship_for_woocommerce()->get_plugin_path() . '/templates/' );
				
		$mailer = WC()->mailer();
		// create a new email
		$email = new WC_Email();
	
		// wrap the content with the email template and then add styles
		$email_content = apply_filters( 'woocommerce_mail_content', $email->style_inline( $mailer->wrap_message( $email_heading, $email_content ) ) );
		
		add_filter( 'wp_mail_from', array( wc_trackship_email_manager(), 'get_from_address' ) );
		add_filter( 'wp_mail_from_name', array( wc_trackship_email_manager(), 'get_from_name' ) );
		
		$email_to = trackship_for_woocommerce()->ts_actions->get_option_value_from_array('late_shipments_email_settings', 'wcast_late_shipments_email_to', $wcast_late_shipments_settings->defaults['wcast_late_shipments_email_to']);
		$email_to = explode( ',', $email_to );
		$email_send = array();
		foreach ( $email_to as $email_addr ) {
			if ( in_array( get_option( 'user_plan' ), array( 'Free Trial', 'Free 50', 'No active plan' ) ) ) {
				return;
			}
			//string replace for '{admin_email}'
			$recipient = str_replace( '{admin_email}', get_option('admin_email'), $email_addr );
			//Send Email
			$email_send[] = wp_mail( $recipient, $subject, $email_content, $email->get_headers() );
			$context = array( 'source' => 'trackship_late_shipments_email' );
			$logger->info( 'Late Shipments email sent' . print_r($orders), $context );
		}
		return $email_send;
	}
}
