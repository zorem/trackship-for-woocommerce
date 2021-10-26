<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WC_Trackship_Actions {
	
	/**
	 * Initialize the main plugin function
	*/
	public function __construct() {
		
	}
	
	/**
	 * Instance of this class.
	 *
	 * @var object Class Instance
	*/
	private static $instance;		
	
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
	
	/*
	 * init function
	 *
	 * @since  1.0
	*/
	public function init() {	
		
		//load trackship css js 
		add_action( 'admin_enqueue_scripts', array( $this, 'trackship_styles' ), 100 );
		
		//ajax save admin trackship settings
		add_action( 'wp_ajax_wc_ast_trackship_form_update', array( $this, 'wc_ast_trackship_form_update_callback' ) );
		add_action( 'wp_ajax_trackship_tracking_page_form_update', array( $this, 'trackship_tracking_page_form_update_callback' ) );
		
		$api_enabled = get_option( 'wc_ast_api_enabled', 0 );
		
		if ( true == $api_enabled ) {
			//add Shipment status column after tracking
			add_filter( 'manage_edit-shop_order_columns', array( $this, 'wc_add_order_shipment_status_column_header'), 20 );
			add_action( 'manage_shop_order_posts_custom_column', array( $this, 'wc_add_order_shipment_status_column_content') );
			
			//add bulk action - get shipment status
			add_filter( 'bulk_actions-edit-shop_order', array( $this, 'add_bulk_actions_get_shipment_status'), 10, 1 );
			
			// Make the action from selected orders to get shipment status
			add_filter( 'handle_bulk_actions-edit-shop_order', array( $this, 'get_shipment_status_handle_bulk_action_edit_shop_order'), 10, 3 );
			
			// Bulk shipment status sync ajax call from settings
			add_action( 'wp_ajax_bulk_shipment_status_from_settings', array( $this, 'bulk_shipment_status_from_settings_fun' ) );
			
			// The results notice from bulk action on orders
			add_action( 'admin_notices', array( $this, 'shipment_status_bulk_action_admin_notice' ) );
			
			// add 'get_shipment_status' order meta box order action
			add_action( 'woocommerce_order_actions', array( $this, 'add_order_meta_box_get_shipment_status_actions' ) );
			add_action( 'woocommerce_order_action_get_shipment_status_edit_order', array( $this, 'process_order_meta_box_actions_get_shipment_status' ) );
			
			// add bulk order filter for exported / non-exported orders
			if ( get_option( 'wc_ast_show_shipment_status_filter' ) ) {
				add_action( 'restrict_manage_posts', array( $this, 'filter_orders_by_shipment_status') , 20 );
				add_filter( 'request', array( $this, 'filter_orders_by_shipment_status_query' ) );
			}
		}
		
		// trigger when order status changed to shipped or completed
		
		// filter for shipment status
		add_filter( 'trackship_status_filter', array($this, 'trackship_status_filter_func' ), 10 , 1 );
		
		// filter for shipment status icon
		add_filter( 'trackship_status_icon_filter', array( $this, 'trackship_status_icon_filter_func' ), 10 , 2 );
		
		add_action( 'wp_ajax_update_shipment_status_email_status', array( $this, 'update_shipment_status_email_status_fun') );
		add_action( 'wp_ajax_update_all_shipment_status_delivered', array( $this, 'update_all_shipment_status_delivered_fun') );
	
		add_action( 'ast_shipment_tracking_end', array( $this, 'display_shipment_tracking_info'), 10, 2 );
		
		add_action( 'delete_tracking_number_from_trackship', array( $this, 'delete_tracking_number_from_trackship'), 10, 3 );
		
		//fix shipment tracking for deleted tracking
		add_action( 'fix_shipment_tracking_for_deleted_tracking', array( $this, 'func_fix_shipment_tracking_for_deleted_tracking' ), 10, 3 );
				
		add_action( 'admin_footer', array( $this, 'footer_function'), 1 );
		
		// if trackship is connected
		if ( ! $this->get_trackship_key() ) {
			return;
		}
		
		//filter in shipped orders
		add_filter( 'is_order_shipped', array( $this, 'check_order_status' ), 5, 2 );
		add_filter( 'is_order_shipped', array( $this, 'check_tracking_exist' ), 10, 2 );
		
		// CSV / manually
		add_action( 'send_order_to_trackship', array( $this, 'schedule_while_adding_tracking' ), 10, 1 );
		
		//run cron action
		add_action( 'wcast_retry_trackship_apicall', array( $this, 'trigger_trackship_apicall' ) );
		
		$valid_order_statuses = get_option( 'trackship_trigger_order_statuses', array() );
		foreach ( $valid_order_statuses as $order_status ) {
			// trigger Trackship for spacific order
			add_action( 'woocommerce_order_status_' . $order_status, array( $this, 'schedule_when_order_status_changed' ), 8, 2 );
		}
		
		add_action( 'admin_init', array( $this, 'register_scheduled_cron') );
		
		//Cron for update shipment length 
		add_action( 'scheduled_cron_shipment_length', array( $this, 'scheduled_cron_shipment_length_callback') );
		add_action( 'update_shipment_length', array( $this, 'update_shipment_length' ) );
		//add_action( 'wp_ajax_remove_delete_data', array( $this, 'remove_delete_data' ) );
		//add_action( 'wp_ajax_update_shipment_length', array( $this, 'update_shipment_length' ) );
	}
	
	
	/**
	* Delete trackship_shipment table 
	*/
	public function remove_delete_data() {
		global $wpdb;
		$woo_trackship_shipment = $wpdb->prefix . 'trackship_shipment';
		$total_order = $wpdb->get_results("
			SELECT *
				FROM {$woo_trackship_shipment}
		");
		foreach ( $total_order as $key => $value ) {
			echo esc_html( $value->order_id ) . '<br>';
			delete_post_meta( $value->order_id, 'shipment_table_updated' );
		}
		update_option( 'trackship_db', '1.4' );
		$wpdb->query("DROP TABLE $woo_trackship_shipment");
		exit;
	}
	
	/**
	* Load trackship styles.
	*/
	public function trackship_styles( $hook ) {
		$screen = get_current_screen(); 
		
		$suffix = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';
		
		wp_register_style( 'trackshipcss', trackship_for_woocommerce()->plugin_dir_url() . 'assets/css/trackship.css', array(), trackship_for_woocommerce()->version );
		wp_register_style( 'smswoo_ts', trackship_for_woocommerce()->plugin_dir_url() . 'assets/css/smswoo_ts.css', array(), trackship_for_woocommerce()->version );
		wp_register_style( 'woocommerce_admin_styles', WC()->plugin_url() . '/assets/css/admin.css', array(), WC_VERSION );
		
		wp_register_script( 'jquery-tiptip', WC()->plugin_url() . '/assets/js/jquery-tiptip/jquery.tipTip.min.js', array( 'jquery' ), WC_VERSION, true );
		wp_register_script( 'jquery-blockui', WC()->plugin_url() . '/assets/js/jquery-blockui/jquery.blockUI' . $suffix . '.js', array( 'jquery' ), '2.70', true );
		wp_register_script( 'trackship_script', trackship_for_woocommerce()->plugin_dir_url() . 'assets/js/trackship.js', array( 'jquery', 'wp-util' ), trackship_for_woocommerce()->version );
		wp_register_script( 'smswoo_ts', trackship_for_woocommerce()->plugin_dir_url() . 'assets/js/smswoo_ts.js', array( 'jquery', 'wp-util' ), trackship_for_woocommerce()->version );
		
		wp_localize_script( 'trackship_script', 'trackship_script', array(
			'i18n' => array(				
				'data_saved'	=> __( 'Your settings have been successfully saved.', 'trackship-for-woocommerce' ),				
			),
		) );
		
		wp_register_style( 'front_style', trackship_for_woocommerce()->plugin_dir_url() . 'assets/css/front.css', array(), trackship_for_woocommerce()->version );
		
		if ( 'shop_order' === $screen->post_type ) {
			wp_enqueue_style( 'trackshipcss' );
			wp_enqueue_script( 'trackship_script' );
			
			//front_style for tracking widget
			
			wp_enqueue_style( 'front_style' );
		}
		
		$page = isset( $_GET['page'] ) ? sanitize_text_field( $_GET['page'] ) : '';
		
		if ( 'trackship-for-woocommerce' != $page && 'trackship-shipments' != $page && 'trackship-dashboard' != $page ) {
			return;
		}
		// remove code in future, added by hitesh
		wp_dequeue_style( 'ast_styles' );
		wp_dequeue_style( 'trackship_styles' );
		// remove code in future				
		
		wp_enqueue_style( 'front_style' );
					
		wp_enqueue_style( 'wp-color-picker' );
		wp_enqueue_style( 'woocommerce_admin_styles' );
		wp_enqueue_style( 'trackshipcss' );

		wp_enqueue_script( 'wp-color-picker' );	
		wp_enqueue_script( 'jquery-tiptip' );
		wp_enqueue_script( 'jquery-blockui' );
		
		$suffix = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';	

		wp_register_script( 'select2', WC()->plugin_url() . '/assets/js/select2/select2.full' . $suffix . '.js', array( 'jquery' ), '4.0.3' );
		wp_enqueue_script( 'select2');
		
		wp_register_script( 'selectWoo', WC()->plugin_url() . '/assets/js/selectWoo/selectWoo.full' . $suffix . '.js', array( 'jquery' ), '1.0.4' );
		wp_register_script( 'wc-enhanced-select', WC()->plugin_url() . '/assets/js/admin/wc-enhanced-select' . $suffix . '.js', array( 'jquery', 'selectWoo' ), WC_VERSION );
		
		wp_enqueue_script( 'selectWoo');
		wp_enqueue_script( 'wc-enhanced-select');				
		
		wp_enqueue_script( 'trackship_script' );
		
		if ( !class_exists( 'SMS_for_WooCommerce' ) ) {
			wp_enqueue_script( 'smswoo_ts' );
			wp_enqueue_style( 'smswoo_ts' );
		}
	}
	
	/*
	* Scheduled cron
	*/
	public function register_scheduled_cron() {
		if ( ! wp_next_scheduled( 'scheduled_cron_shipment_length' ) ) {
			wp_schedule_event( time(), 'daily', 'scheduled_cron_shipment_length' );
		}
	}
	
	/*
	* Set shipment length
	*/
	public function scheduled_cron_shipment_length_callback() {
		global $wpdb;
		$woo_trackship_shipment = $wpdb->prefix . 'trackship_shipment';
		$total_order = $wpdb->get_var("
			SELECT 				
				COUNT(*)
				FROM {$woo_trackship_shipment}			
			WHERE 
				shipment_status NOT LIKE ( 'delivered')
		");
		$total_cron = ( int ) ( $total_order/300 ) + 1;
		for ( $i = 1; $i <= $total_cron; $i++ ) {
			as_schedule_single_action( time(), 'update_shipment_length' );
		}
	}
	
	/*
	* Update shipment length
	*/
	public function update_shipment_length() {
		global $wpdb;
		$woo_trackship_shipment = $wpdb->prefix . 'trackship_shipment';
		$today = gmdate('Y-m-d');
		$total_order = $wpdb->get_results("
			SELECT *
				FROM {$woo_trackship_shipment}
			WHERE
				shipment_status NOT LIKE ( 'delivered')
				AND ( updated_date < '{$today}' OR updated_date IS NULL )
			LIMIT 300
		");
		foreach ( $total_order as $key => $value ) {
			$order_id = $value->order_id;
			$order = wc_get_order( $order_id );
			$tracking_items = trackship_for_woocommerce()->get_tracking_items( $order_id );
			$shipment_status = $order->get_meta( 'shipment_status' );

			foreach ( (array) $tracking_items as $key1 => $item ) {
				$shipment_length = trackship_for_woocommerce()->shipments->get_shipment_length( $shipment_status[$key1] );
				$where = array(
					'order_id'			=> $order_id,
					'tracking_number'	=> $value->tracking_number,
				);
				$wpdb->update( $woo_trackship_shipment, array( 'shipping_length' => $shipment_length, 'updated_date' => $today ), $where );
			}
		}
	}
	
	/*
	* settings form save
	*/
	public function wc_ast_trackship_form_update_callback() {
		
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			exit( 'You are not allowed' );
		}
		if ( ! empty( $_POST ) && check_admin_referer( 'wc_ast_trackship_form', 'wc_ast_trackship_form_nonce' ) ) {
			
			$admin = WC_Trackship_Admin::get_instance();
			
			$data = $this->get_delivered_data();								
			
			$wcast_late_shipments_days = isset( $_POST['wcast_late_shipments_days'] ) ? sanitize_text_field( $_POST['wcast_late_shipments_days'] ) : '';
			
			$late_shipments_email_settings = get_option( 'late_shipments_email_settings', array() );
			$late_shipments_email_settings[ 'wcast_late_shipments_days' ] = $wcast_late_shipments_days;
			update_option( 'late_shipments_email_settings', $late_shipments_email_settings );
			
			foreach ( $data as $key => $val ) {
				if ( 'wcast_enable_delivered_email' == $key ) {					
					if ( isset( $_POST['wcast_enable_delivered_email'] ) ) {											
						
						if ( 1 == $_POST['wcast_enable_delivered_email'] ) {
							update_option( 'customizer_delivered_order_settings_enabled', wc_clean( $_POST['wcast_enable_delivered_email'] ) );
							$enabled = 'yes';
						} else {
							update_option( 'customizer_delivered_order_settings_enabled', '');	
							$enabled = 'no';
						}
						
						$wcast_enable_delivered_email = get_option('woocommerce_customer_delivered_order_settings'); 
						$wcast_enable_delivered_email['enabled'] = $enabled;												
						
						update_option( 'woocommerce_customer_delivered_order_settings', $wcast_enable_delivered_email );	
					}	
				}
				
				if ( isset( $_POST[ $key ] ) ) {						
					update_option( $key, wc_clean($_POST[ $key ]) );
				}	
			}
			
			$data2 = $admin->get_trackship_general_data();
			foreach ( $data2 as $key2 => $val2 ) {
				if ( 'multiple_select' == $val2[ 'type' ] ) {
					$posted_val = isset( $_POST[ $key2 ] ) ? wc_clean( $_POST[ $key2 ] ) : array();
					update_option( $key2, $posted_val );
				} else {
					update_option( $key2, wc_clean( $_POST[ $key2 ] ) );
				}
			}
			
			echo json_encode( array('success' => 'true') );
			die();

		}
	}
	
	/*
	* tracking page form save
	*/
	public function trackship_tracking_page_form_update_callback() {
		if ( ! empty( $_POST ) && check_admin_referer( 'trackship_tracking_page_form', 'trackship_tracking_page_form_nonce' ) ) {
			
			$admin = WC_Trackship_Admin::get_instance();
			$data1 = $admin->get_tracking_page_data();
			
			foreach ( $data1 as $key1 => $val1 ) {
				
				if ( 'button' == $val1[ 'type' ] ) {
					continue;
				}
				$post_key1 = isset( $_POST[ $key1 ] ) ? sanitize_text_field( $_POST[ $key1 ] ) : '';
				update_option( $key1, sanitize_text_field( $post_key1 ) );
			}
			
			wp_send_json( array('success' => 'true') );
		}
	}
	
	/*
	* get settings tab array data
	* return array
	*/
	public function get_delivered_data() {		
		$form_data = array(			
			'wc_ast_status_delivered' => array(
				'type'		=> 'checkbox',
				'title'		=> __( 'Enable custom order status “Delivered"', '' ),				
				'show'		=> true,
				'class'     => '',
			),			
			'wc_ast_status_label_color' => array(
				'type'		=> 'color',
				'title'		=> __( 'Delivered Label color', '' ),				
				'class'		=> 'status_label_color_th',
				'show'		=> true,
			),
			'wc_ast_status_label_font_color' => array(
				'type'		=> 'dropdown',
				'title'		=> __( 'Delivered Label font color', '' ),
				'options'   => array( 
									'' =>__( 'Select', 'woocommerce' ),
									'#fff' =>__( 'Light', '' ),
									'#000' =>__( 'Dark', '' ),
								),			
				'class'		=> 'status_label_color_th',
				'show'		=> true,
			),
			'wcast_enable_delivered_email' => array(
				'type'		=> 'checkbox',
				'title'		=> __( 'Enable the Delivered order status email', '' ),				
				'class'		=> 'status_label_color_th',
				'show'		=> true,
			),				
		);
		return $form_data;

	}	
	
	/**
	 * Adds 'shipment_status' column header to 'Orders' page immediately after 'woocommerce-advanced-shipment-tracking' column.
	 *
	 * @param string[] $columns
	 * @return string[] $new_columns
	 */
	public function wc_add_order_shipment_status_column_header( $columns ) {
		wp_enqueue_style( 'trackshipcss' );
		wp_enqueue_script( 'trackship_script' );
		
		//front_style for tracking widget
		wp_register_style( 'front_style', trackship_for_woocommerce()->plugin_dir_url() . 'assets/css/front.css', array(), trackship_for_woocommerce()->version );
		wp_enqueue_style( 'front_style' );
		
		$columns['shipment_status'] = __( 'Shipment status', 'trackship-for-woocommerce' );
		return $columns;
	}
	
	/**
	 * Adds 'shipment_status' column content to 'Orders' page.
	 *
	 * @param string[] $column name of column being displayed
	 */
	public function wc_add_order_shipment_status_column_content( $column ) {		
		global $post;
		
		if ( 'shipment_status' === $column ) {
						
			$tracking_items = trackship_for_woocommerce()->get_tracking_items( $post->ID );
			$shipment_status = get_post_meta( $post->ID, 'shipment_status', true);
							
			$date_format = $this->get_date_format();

			if ( count( $tracking_items ) > 0 ) {
				?>
					<ul class="wcast-shipment-status-list">
						<?php
						foreach ( $tracking_items as $key => $tracking_item ) { 
							if ( !isset( $shipment_status[$key] ) ) {
								echo '<li class="tracking-item-';
								esc_html_e( $tracking_item['tracking_id'] );
								echo '">-</li>';
								continue;
							}
							$has_est_delivery = false;
							
							if ( isset( $shipment_status[$key]['pending_status'] ) ) {
								$status = $shipment_status[$key]['pending_status'];
							} else {
								$status = $shipment_status[$key]['status'];	
							}
							
							$status_date = $shipment_status[$key]['status_date'];
							
							if ( isset( $shipment_status[$key]['est_delivery_date'] ) ) {
								$est_delivery_date = $shipment_status[$key]['est_delivery_date'];
								$est_delivery_date1 = $this->get_est_delivery( $est_delivery_date );
							}
							
							if ( 'delivered' != $status && 'return_to_sender' != $status && !empty($est_delivery_date) ) {
								$has_est_delivery = true;
							}
							?>
							<li id="shipment-item-<?php esc_html_e( $tracking_item['tracking_id'] ); ?>" class="tracking-item-<?php esc_html_e( $tracking_item['tracking_id'] ); ?>" >                            	
								<div class="ast-shipment-status shipment-<?php esc_html_e( sanitize_title($status) ); ?> has_est_delivery_<?php esc_html_e( $has_est_delivery ? 1 : 0 ); ?>">
									<?php $class = in_array( $status, array( 'in_transit', 'on_hold', 'pre_transit', 'delivered', 'out_for_delivery', 'available_for_pickup', 'return_to_sender', 'exception', 'failure', 'unknown' ) )  ? 'open_tracking_details' : 'open_more_info_popup'; ?>             
									<span style="display:block;">
										<span class="shipment-icon icon-default icon-<?php esc_html_e( $status ); ?> ast-shipment-tracking-status"> <?php esc_html_e( apply_filters( 'trackship_status_filter', $status ) ); ?></span>
										<?php if ( $has_est_delivery ) { ?>
											<span class="wcast-shipment-est-delivery ft11">Est. Delivery <?php esc_html_e( $est_delivery_date1 ); ?> <a class="ts4wc_track_button ft12 <?php echo esc_html( $class ); ?>" data-orderid="<?php esc_html_e( $post->ID ); ?>" data-tracking_id="<?php esc_html_e( $tracking_item['tracking_id'] ); ?>" data-nonce="<?php esc_html_e( wp_create_nonce( 'tswc-' . $post->ID ) ); ?>" ><?php esc_html_e( 'Track', 'trackship-for-woocommerce' ); ?></a></span>
										<?php } ?>
										<?php if ( '' != $status_date && !$has_est_delivery ) { ?>
											<span class="showif_has_est_delivery_0 ft11"><?php esc_html_e( 'Updated ', 'trackship-for-woocommerce' ); ?><?php esc_html_e( gmdate( $date_format, strtotime($status_date) ) ); ?> 
                                            
												<?php if ( in_array( $status, array( 'in_transit', 'on_hold', 'pre_transit', 'delivered', 'out_for_delivery', 'available_for_pickup', 'return_to_sender', 'exception', 'failure', 'unknown' ) ) ) { ?>
													<a class="ts4wc_track_button ft12 <?php echo esc_html( $class ); ?>" data-orderid="<?php esc_html_e( $post->ID ); ?>" data-tracking_id="<?php esc_html_e( $tracking_item['tracking_id'] ); ?>" data-nonce="<?php esc_html_e( wp_create_nonce( 'tswc-' . $post->ID ) ); ?>" ><?php esc_html_e( 'Track', 'trackship-for-woocommerce' ); ?></a>
												<?php } ?>
												
												<?php if ( 'pending_trackship' == $status ) { ?>
                                                    <a href="javascript:;" class="trackship-tip  <?php echo esc_html( $class ); ?>" title="<?php esc_html_e( 'Pending TrackShip is a temporary status that will display for a few minutes until we update the order with the first tracking event from the shipping provider. Please refresh the orders admin in 2-3 minutes.', 'trackship-for-woocommerce' ); ?>" ><?php esc_html_e( 'more info', 'trackship-for-woocommerce' ); ?></a>
												<?php } ?>
                                                
												<?php if ( !in_array( $status, array( 'in_transit', 'on_hold', 'pre_transit', 'delivered', 'out_for_delivery', 'available_for_pickup', 'return_to_sender', 'exception', 'failure', 'pending_trackship', 'unknown' ) ) ) { ?>
													<a class="<?php echo esc_html( $class ); ?>" href="https://trackship.info/docs/trackship-resources/shipment-tracking-status-reference/#trackship-status-messages" target="_blank"><?php esc_html_e( 'more info', 'trackship-for-woocommerce' ); ?></a>
												<?php } ?>
                                                
											</span>
										<?php } ?>
									</span>
								</div>
							</li>
						<?php } ?>
					</ul>
				<?php
			} else {
				echo '–';
			}
		}
	}
	
	/*
	* add bulk action
	* Change order status to delivered
	*/
	public function add_bulk_actions_get_shipment_status( $bulk_actions ) {
		$bulk_actions['get_shipment_status'] = 'Get Shipment Status';
		return $bulk_actions;
	}
	
	/*
	* order bulk action for get shipment status
	*/
	public function get_shipment_status_handle_bulk_action_edit_shop_order( $redirect_to, $action, $post_ids ) {
		
		if ( 'get_shipment_status' !== $action ) {
			return $redirect_to;
		}
	
		$processed_ids = array();
		
		$order_count = count($post_ids);
		
		foreach ( $post_ids as $post_id ) {
			
			$this->schedule_trackship_trigger( $post_id );
			$processed_ids[] = $post_id;
			
		}
	
		$redirect_to = add_query_arg( array(
			'get_shipment_status' => '1',
			'processed_count' => count( $processed_ids ),
			'processed_ids' => implode( ',', $processed_ids ),
		), $redirect_to );
		return $redirect_to;
	}
	
	/*
	* bulk shipment status action for completed order with tracking details and without shipment status
	*/
	public function bulk_shipment_status_from_settings_fun() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			exit( 'You are not allowed' );
		}
		$args = array(
			'status' => 'wc-completed',
			'limit'	 => 100,	
			'date_created' => '>' . ( time() - 2592000 ),
		);		
		$orders = wc_get_orders( $args );		
		foreach ( $orders as $order ) {
			$order_id = $order->get_id();
			
			$tracking_items = trackship_for_woocommerce()->get_tracking_items( $order_id );
			
			if ( $tracking_items ) {
				$shipment_status = get_post_meta( $order_id, 'shipment_status', true);				
				foreach ( $tracking_items as $key => $tracking_item ) { 
					
					//bulk shipment status action for completed order with tracking details and without shipment status
					if ( !isset( $shipment_status[$key] ) ) {
						$this->schedule_trackship_trigger( $order_id );
					}
					
					//bulk shipment status action for "TrackShip balance is 0" status
					if ( isset( $shipment_status[$key]['pending_status'] ) && 'TrackShip balance is 0' == $shipment_status[$key]['pending_status'] ) {
						$this->schedule_trackship_trigger( $order_id );
					}
					
					//bulk shipment status action for "TrackShip balance is 0" status
					if ( isset( $shipment_status[$key]['pending_status'] ) && 'TrackShip connection issue' == $shipment_status[$key]['pending_status'] ) {
						$this->schedule_trackship_trigger( $order_id );
					}
				}									
			}			
		}
		$url = admin_url('/edit.php?post_type=shop_order');		
		echo esc_url( $url );
		die();		
	}
	
	/*
	* The results notice from bulk action on orders
	*/
	public function shipment_status_bulk_action_admin_notice() {
		if ( empty( $_REQUEST['get_shipment_status'] ) ) {
			return; // Exit
		}
	
		//$count = intval( $_REQUEST['processed_count'] );
		
		echo '<div id="message" class="updated fade"><p>';
		esc_html_e( 'The shipment status updates will run in the background, please refresh the page in a few minutes.', 'trackship-for-woocommerce' );
		echo '</p></div>';
	}

	/**
	 * Add 'get_shipment_status' link to order actions select box on edit order page
	 *
	 * @since 1.0
	 * @param array $actions order actions array to display
	 * @return array
	 */
	public function add_order_meta_box_get_shipment_status_actions( $actions ) {

		// add download to CSV action
		$actions['get_shipment_status_edit_order'] = __( 'Get Shipment Status', 'trackship-for-woocommerce' );
		return $actions;
	}

	/*
	* order details meta box action
	*/
	public function process_order_meta_box_actions_get_shipment_status( $order ) {
		$this->trigger_trackship_apicall( $order->get_id() );
	}	
	
	/**
	 * Add bulk filter for Shipment status in orders list
	 *
	 * @since 2.4
	 */
	public function filter_orders_by_shipment_status() {
		global $typenow;

		if ( 'shop_order' === $typenow ) {

			$terms = array(
				'pending_trackship' => (object) array( 'term' => __( 'Pending TrackShip', 'trackship-for-woocommerce' ) ),
				'unknown' => (object) array( 'term' => __( 'Unknown', 'trackship-for-woocommerce' ) ),
				'pre_transit' => (object) array( 'term' => __( 'Pre Transit', 'trackship-for-woocommerce' ) ),
				'in_transit' => (object) array( 'term' => __( 'In Transit', 'trackship-for-woocommerce' ) ),
				'available_for_pickup' => (object) array( 'term' => __( 'Available For Pickup', 'trackship-for-woocommerce' ) ),
				'out_for_delivery' => (object) array( 'term' => __( 'Out For Delivery', 'trackship-for-woocommerce' ) ),
				'delivered' => (object) array( 'term' => __( 'Delivered', 'trackship-for-woocommerce' ) ),
				'failure' => (object) array( 'term' => __( 'Failed Attempt', 'trackship-for-woocommerce' ) ),
				'cancelled' => (object) array( 'term' => __( 'Cancelled', 'woocommerce' ) ),
				'carrier_unsupported' => (object) array( 'term' => __( 'Carrier Unsupported', 'trackship-for-woocommerce' ) ),
				'return_to_sender' => (object) array( 'term' => __( 'Return To Sender', 'trackship-for-woocommerce' ) ),
				'exception' => (object) array( 'term' => __( 'Exception', 'trackship-for-woocommerce' ) ),				
				'invalid_tracking' => (object) array( 'term' => __( 'Invalid Tracking', 'trackship-for-woocommerce' ) ),
			);

			?>
			<select name="_shop_order_shipment_status" id="dropdown_shop_order_shipment_status">
				<option value=""><?php esc_html_e( 'Filter by shipment status', 'trackship-for-woocommerce' ); ?></option>
				<?php foreach ( $terms as $value => $term ) : ?>
				<option value="<?php echo esc_attr( $value ); ?>" <?php echo esc_attr( isset( $_GET['_shop_order_shipment_status'] ) ? selected( $value, sanitize_text_field( $_GET['_shop_order_shipment_status'] ), false ) : '' ); ?>>
					<?php printf( '%1$s', esc_html( $term->term ) ); ?>
				</option>
				<?php endforeach; ?>
			</select>
			<?php
		}
	}
	
	/**
	 * Process bulk filter action for shipment status orders
	 *
	 * @since 3.0.0
	 * @param array $vars query vars without filtering
	 * @return array $vars query vars with (maybe) filtering
	 */
	public function filter_orders_by_shipment_status_query( $vars ) {
		global $typenow;		
		if ( 'shop_order' === $typenow && isset( $_GET['_shop_order_shipment_status'] ) && '' != $_GET['_shop_order_shipment_status'] ) {
			$vars['meta_key']   = 'ts_shipment_status';
			$vars['meta_value'] = sanitize_text_field( $_GET['_shop_order_shipment_status'] );
			$vars['meta_compare'] = 'LIKE';						
		}

		return $vars;
	}	
	
	/*
	* filter for shipment status
	*/
	public function trackship_status_filter_func( $status ) {
		switch ($status) {
			case 'in_transit':
				$status = __( 'In Transit', 'trackship-for-woocommerce' );
				break;
			case 'on_hold':
				$status = __( 'On Hold', 'trackship-for-woocommerce' );
				break;
			case 'pre_transit':
				$status = __( 'Pre Transit', 'trackship-for-woocommerce' );
				break;
			case 'delivered':
				$status = __( 'Delivered', 'trackship-for-woocommerce' );
				break;
			case 'out_for_delivery':
				$status = __( 'Out For Delivery', 'trackship-for-woocommerce' );
				break;
			case 'available_for_pickup':
				$status = __( 'Available For Pickup', 'trackship-for-woocommerce' );
				break;
			case 'return_to_sender':
				$status = __( 'Return To Sender', 'trackship-for-woocommerce' );
				break;
			case 'exception':
				$status = __( 'Exception', 'woocommerce' );
				break;
			case 'failure':
				$status = __( 'Failed Attempt', 'trackship-for-woocommerce' );
				break;
			case 'unknown':
				$status = __( 'Unknown', 'trackship-for-woocommerce' );
				break;
			case 'pending_trackship':
				$status = __( 'Pending TrackShip', 'trackship-for-woocommerce' );
				break;
			case 'carrier_unsupported':
				$status = __( 'Carrier Unsupported', 'trackship-for-woocommerce' );
				break;
			case 'unauthorized':
				$status = __( 'Unauthorized', 'trackship-for-woocommerce' );
				break;	
			case 'deleted':
				$status = __( 'Deleted', 'woocommerce' );
				break;
			case 'insufficient_balance':
				$status = __( 'Insufficient Balance', 'woocommerce' );
				break;
			case 'connection_issue':
				$status = __( 'Connection Issue', 'woocommerce' );
				break;
			case 'invalid_tracking':
				$status = __( 'Invalid Tracking', 'woocommerce' );
				break;
			case 'ssl_error':
				$status = __( 'SSL Error', 'woocommerce' );
				break;
			case 'invalid_carrier':
				$status = __( 'Invalid Carrier', 'woocommerce' );
				break;
			/* Added Version 1.2 - To be removed in future wrong_shipping_provider and INVALID_TRACKING_NUM */
			case 'wrong_shipping_provider':
				$status = __( 'Invalid Carrier', 'trackship-for-woocommerce' );
				break;
			case 'INVALID_TRACKING_NUM':
				$status = __( 'Invalid Tracking', 'trackship-for-woocommerce' );
				break;
		}
		return $status;
	}
	
	/*
	* filter for shipment status icon
	*/
	public function trackship_status_icon_filter_func( $html, $status ) {
		switch ($status) {
			case 'in_transit':
				$html = '<span class="shipment-icon icon-' . $status . '">';
				break;
			case 'on_hold':
				$html = '<span class="shipment-icon icon-' . $status . '">';
				break;	
			case 'pre_transit':
				$html = '<span class="shipment-icon icon-' . $status . '">';
				break;
			case 'delivered':
				$html = '<span class="shipment-icon icon-' . $status . '">';
				break;
			case 'out_for_delivery':
				$html = '<span class="shipment-icon icon-' . $status . '">';
				break;
			case 'available_for_pickup':
				$html = '<span class="shipment-icon icon-' . $status . '">';
				break;
			case 'return_to_sender':
				$html = '<span class="shipment-icon icon-' . $status . '">';
				break;
			case 'failure':
				$html = '<span class="shipment-icon icon-' . $status . '">';
				break;
			case 'unknown':
				$html = '<span class="shipment-icon icon-' . $status . '">';
				break;
			case 'pending_trackship':
				$html = '<span class="shipment-icon icon-' . $status . '">';
				break;	
			case 'invalid_user_key':
				$html = '<span class="shipment-icon icon-' . $status . '">';
				break;
			case 'carrier_unsupported':
				$html = '<span class="shipment-icon icon-' . $status . '">';
				break;
			case 'invalid_tracking':
				$html = '<span class="shipment-icon icon-' . $status . '">';
				break;
			case 'invalid_carrier':
				$html = '<span class="shipment-icon icon-' . $status . '">';
				break;
			/* Added Version 1.2 - To be removed in future wrong_shipping_provider and INVALID_TRACKING_NUM */
			case 'INVALID_TRACKING_NUM':
				$html = '<span class="shipment-icon icon-' . $status . '">';
				break;
			case 'wrong_shipping_provider':
				$html = '<span class="shipment-icon icon-' . $status . '">';
				break;
			default:
				$html = '<span class="shipment-icon icon-default">';
				break;

		}
		return $html;
	}

	/*
	* update all shipment status email status
	*/
	public function update_shipment_status_email_status_fun() {
		check_ajax_referer( 'tswc_shipment_status_email', 'security' );
		$settings_data = isset( $_POST['settings_data'] ) ? wc_clean( $_POST['settings_data'] ) : '';
		$status_settings = get_option( $settings_data );
		$enable_status_email = isset( $_POST['wcast_enable_status_email'] ) ? wc_clean( $_POST['wcast_enable_status_email'] ) : '';
		$p_id = isset( $_POST['id'] ) ? wc_clean( $_POST['id'] ) : '';
		$status_settings[$p_id] = wc_clean( $enable_status_email );
		update_option( wc_clean( $settings_data ), $status_settings );		
		
		exit;
	}
	
	/*
	* update all shipment status email status
	*/
	public function update_all_shipment_status_delivered_fun() {
		check_ajax_referer( 'all_status_delivered', 'security' );
		$all_status = isset( $_POST['shipment_status_delivered'] ) ? wc_clean( $_POST['shipment_status_delivered'] ) : '';
		update_option( 'all-shipment-status-delivered', $all_status );
		exit;
	}
	
	/*
	* get completed order with tracking that not sent to TrackShip
	* return number
	*/
	public function completed_order_with_tracking() {
		// Get orders completed.
		$args = array(
			'status' => 'wc-completed',
			'limit'	 => 100,	
			'date_created' => '>' . ( time() - 2592000 ),
		);
		
		$orders = wc_get_orders( $args );
		
		$completed_order_with_tracking = 0;
		
		foreach ( $orders as $order) {
			$order_id = $order->get_id();
			
			$ast = new WC_Advanced_Shipment_Tracking_Actions();
			$tracking_items = $ast->get_tracking_items( $order_id, true );
			if ( $tracking_items ) {
				$shipment_status = get_post_meta( $order_id, 'shipment_status', true);
				foreach ( $tracking_items as $key => $tracking_item ) { 				
					if ( !isset( $shipment_status[$key] ) ) {						
						$completed_order_with_tracking++;		
					}
				}									
			}			
		}
		return $completed_order_with_tracking;
	}
	
	/*
	* get completed order with Trackship Balance 0 status
	* return number
	*/
	public function completed_order_with_zero_balance() {
		
		// Get orders completed.
		$args = array(
			'status' => 'wc-completed',
			'limit'	 => 100,	
			'date_created' => '>' . ( time() - 2592000 ),
		);		
		
		$orders = wc_get_orders( $args );
		
		$completed_order_with_zero_balance = 0;
		
		foreach ( $orders as $order ) {
			$order_id = $order->get_id();
			
			$ast = new WC_Advanced_Shipment_Tracking_Actions();
			$tracking_items = $ast->get_tracking_items( $order_id, true );
			if ( $tracking_items ) {				
				$shipment_status = get_post_meta( $order_id, 'shipment_status', true);				
				foreach ( $tracking_items as $key => $tracking_item ) { 					
					if ( isset( $shipment_status[$key]['pending_status'] ) && 'TrackShip balance is 0' == $shipment_status[$key]['pending_status'] ) {
						$completed_order_with_zero_balance++;		
					}
				}									
			}			
		}				
		return $completed_order_with_zero_balance;
	}
	
	/*
	* get completed order with Trackship connection issue status
	* return number
	*/
	public function completed_order_with_do_connection() {
		
		// Get orders completed.
		$args = array(
			'status' => 'wc-completed',
			'limit'	 => 100,	
			'date_created' => '>' . ( time() - 2592000 ),
		);		
		
		$orders = wc_get_orders( $args );
		
		$completed_order_with_do_connection = 0;
		
		foreach ( $orders as $order ) {
			$order_id = $order->get_id();
			
			$ast = new WC_Advanced_Shipment_Tracking_Actions();
			$tracking_items = $ast->get_tracking_items( $order_id, true );
			if ( $tracking_items ) {				
				$shipment_status = get_post_meta( $order_id, 'shipment_status', true);				
				foreach ( $tracking_items as $key => $tracking_item ) { 					
					if ( isset( $shipment_status[$key]['pending_status'] ) && 'TrackShip connection issue' == $shipment_status[$key]['pending_status'] ) {
						$completed_order_with_do_connection++;		
					}
				}									
			}			
		}				
		return $completed_order_with_do_connection;
	}
	
	public function get_date_format() {
		$wp_date_format = get_option( 'date_format' );
		
		switch ($wp_date_format) {
			case 'd/m/Y':
				$date_format = 'd/m';
				break;
			case 'm/d/Y':
				$date_format = 'm/d';
				break;
			case 'Y-m-d':
				$date_format = 'm-d';
				break;
			case 'F j, Y':
				$date_format = 'F j';
				break;
			default:
				$date_format = 'm/d';
		}
		return $date_format;
	}
	
	public function get_est_delivery( $est_delivery_date ) {
		$date_format = $this->get_date_format();
		$today_date = gmdate($date_format);
		$est_delivery_date1 = gmdate( $date_format, strtotime($est_delivery_date) ) ;
		if ( $today_date == $est_delivery_date1 ) {
			return 'Today';
		} else {
			return $est_delivery_date1;
		}
	}
	
	/**
	 * Shipment tracking info html in orders details page
	 */
	public function display_shipment_tracking_info( $order_id, $item ) {
		$shipment_status = get_post_meta( $order_id, 'shipment_status', true);		
		$tracking_id = $item['tracking_id'];
		
		$tracking_items = trackship_for_woocommerce()->get_tracking_items( $order_id );
		
		$wp_date_format = get_option( 'date_format' );
		
		$date_format = $this->get_date_format();
		
		if ( count( $tracking_items ) > 0 ) {
			foreach ( $tracking_items as $key => $tracking_item ) {
				if ( $tracking_id == $tracking_item['tracking_id'] ) {
					if ( isset( $shipment_status[$key] ) ) {
						$has_est_delivery = false;
						$data = $shipment_status[$key];						
						
						if ( isset( $data['pending_status'] ) ) {
							$status = $data['pending_status'];
						} else {
							$status = $data['status'];	
						}
						
						$status_date = $data['status_date'];
						
						if ( !empty( $data['est_delivery_date'] ) ) {
							$est_delivery_date = $data['est_delivery_date'];
							$est_delivery_date1 = $this->get_est_delivery( $est_delivery_date );
						}
						
						if ( 'delivered' != $status  && 'return_to_sender' != $status && !empty($est_delivery_date) ) {
							$has_est_delivery = true;
						}
						$class = in_array( $status, array( 'in_transit', 'on_hold', 'pre_transit', 'delivered', 'out_for_delivery', 'available_for_pickup', 'return_to_sender', 'exception', 'failure', 'unknown' ) )  ? 'open_tracking_details' : 'open_more_info_popup';
						?>
						<div class="ast-shipment-status-div">	
							<span class="ast-shipment-status shipment-<?php echo esc_html( sanitize_title($status) ); ?>">

								<span class="shipment-icon icon-default icon-<?php esc_html_e( $status ); ?>" >
									<strong><?php echo esc_html( apply_filters('trackship_status_filter', $status) ); ?></strong>
								</span>
								<?php if ( '' != $status_date && !$has_est_delivery ) { ?>
									<span style="display: block; margin-top: 8px;"><?php esc_html_e( 'Updated ', 'trackship-for-woocommerce' ); ?><?php esc_html_e( gmdate( $date_format, strtotime($status_date) ) ); ?>
                                    
										<?php if ( in_array( $status, array( 'in_transit', 'on_hold', 'pre_transit', 'delivered', 'out_for_delivery', 'available_for_pickup', 'return_to_sender', 'exception', 'failure', 'unknown' ) ) ) { ?>
											<a class="ts4wc_track_button ft12 <?php esc_html_e( $class ); ?>"  data-orderid="<?php esc_html_e( $order_id ); ?>" data-tracking_id="<?php echo esc_html( $tracking_id ); ?>" data-nonce="<?php esc_html_e( wp_create_nonce( 'tswc-' . $order_id ) ); ?>"><?php esc_html_e( 'Track', 'trackship-for-woocommerce' ); ?></a>
										<?php } ?>
                                        
										<?php if ( 'pending_trackship' == $status ) { ?>
											<a href="javascript:;" class="trackship-tip <?php esc_html_e( $class ); ?>" title="Pending TrackShip is a temporary status that will display for a few minutes until we update the order with the first tracking event from the shipping provider. Please refresh the orders admin in 2-3 minutes." ><?php esc_html_e( 'more info', 'trackship-for-woocommerce' ); ?></a>
										<?php } ?>
										
										<?php if ( !in_array( $status, array( 'in_transit', 'on_hold', 'pre_transit', 'delivered', 'out_for_delivery', 'available_for_pickup', 'return_to_sender', 'exception', 'failure', 'pending_trackship', 'unknown' ) ) ) { ?>
											<a class="<?php echo esc_html( $class ); ?>" href="https://trackship.info/docs/trackship-resources/shipment-tracking-status-reference/#trackship-status-messages" target="_blank"><?php esc_html_e( 'more info', 'trackship-for-woocommerce' ); ?></a>
										<?php } ?>
									</span>
								<?php } ?>
								<?php if ( $has_est_delivery ) { ?>
									<span class="wcast-shipment-est-delivery ft11" style="display: block; margin-top: 8px;">Est. Delivery <?php esc_html_e( $est_delivery_date1 ); ?> <a class="ts4wc_track_button ft12 <?php esc_html_e( $class ); ?>"  data-orderid="<?php esc_html_e( $order_id ); ?>" data-tracking_id="<?php echo esc_html( $tracking_id ); ?>" data-nonce="<?php esc_html_e( wp_create_nonce( 'tswc-' . $order_id ) ); ?>"> <?php esc_html_e( 'Track', 'trackship-for-woocommerce' ); ?></a></span>
								<?php } ?>
							</span>
						</div>	
					<?php } else { ?>
						<button type="button" class="button metabox_get_shipment_status"><?php esc_html_e( 'Get Shipment Status', 'trackship-for-woocommerce' ); ?></span></button>
						<div class="ast-shipment-status-div temp-pending_trackship" style="display:none;">	
							<span class="open_tracking_details ast-shipment-status shipment-pending_trackship" data-orderid="<?php esc_html_e( $order_id ); ?>" data-tracking_id="<?php esc_html_e( $tracking_id ); ?>" >
								<span class="shipment-icon icon-pending_trackship">
									<strong><?php esc_html_e( apply_filters( 'trackship_status_filter', 'pending_trackship' ) ); ?></strong>
								</span>
							</span>
						</div>
					<?php
					}
				}
			}
		}
	}

	/**
	 * Delete tracking information from TrackShip when tracking deleted from AST
	 * Delete tracking information from shipment table when tracking deleted from AST
	 */
	public function delete_tracking_number_from_trackship( $tracking_items, $tracking_id, $order_id ) {
		global $wpdb;
		$shipment_table = $wpdb->prefix . 'trackship_shipment';
		
		$api_enabled = get_option( 'wc_ast_api_enabled', 0);
		if ( $api_enabled ) {			
			foreach ( $tracking_items as $tracking_item ) {
				if ( $tracking_item['tracking_id'] == $tracking_id ) {					
					$tracking_number = $tracking_item['tracking_number'];
					$tracking_provider = $tracking_item['tracking_provider'];					
					$api = new WC_TrackShip_Api_Call();
					$array = $api->delete_tracking_number_from_trackship( $order_id, $tracking_number, $tracking_provider );
					$wpdb->delete( $shipment_table, array( 'order_id' => $order_id, 'tracking_number' => $tracking_number ) );
				}
			}						
		}	
	}
		
	/*
	* fix shipment tracking for deleted tracking
	*/
	public function func_fix_shipment_tracking_for_deleted_tracking( $order_id, $key, $item ) {
		$shipment_status = get_post_meta( $order_id, 'shipment_status', true);
		if ( isset( $shipment_status[$key] ) ) {
			unset( $shipment_status[$key] );
			update_post_meta( $order_id, 'shipment_status', $shipment_status);
		}
	}
	
	/**
	 * Code for check if order id is delivered or not
	*/
	public function is_all_shipments_delivered( $order_id ) {
		$shipment_status = get_post_meta( $order_id, 'shipment_status', true );
		
		foreach ( (array) $shipment_status as $shipment ) {
			$status = isset( $shipment['status'] ) ? $shipment['status'] : '';
			if ( 'delivered' != $status ) {
				return false;
			}
		}
		
		return true;
	}
	
	/**
	 * Code for check if tracking number in order is delivered or not
	*/
	public function check_tracking_delivered( $order_id ) {
		$delivered_status_enabled = get_option('wc_ast_status_delivered');
		if ( ! $delivered_status_enabled ) {
			return;
		}
		
		$delivered = true;
		$shipment_status = get_post_meta( $order_id, 'shipment_status', true);
		
		foreach ( (array) $shipment_status as $shipment ) {
			$status = isset( $shipment['status'] ) ? $shipment['status'] : '';
			if ( 'delivered' != $status ) {
				$delivered = false;
				break;
			}
		}
		
		if ( count( $shipment_status ) > 0 && true == $delivered ) {
			//trigger order deleivered
			$order = wc_get_order( $order_id );
			$order_status  = $order->get_status();
			
			if ( in_array( $order_status, apply_filters( 'allowed_order_status_for_delivered', array( 'completed', 'updated-tracking', 'shipped' ) ) ) ) {
				$order->update_status( 'delivered' );
			}
		}
	}

	/**
	 * Code for trigger shipment status email
	*/
	public function trigger_tracking_email( $order_id, $old_status, $new_status, $tracking_item, $shipment_status ) {
		
		$order = wc_get_order( $order_id );					
		
		if ( $old_status != $new_status ) {
			if ( 'delivered' == $new_status ) {
				wc_trackship_email_manager()->delivered_shippment_status_email_trigger($order_id, $order, $old_status, $new_status, $tracking_item, $shipment_status );
			} elseif ( in_array( $new_status, array( 'failure', 'in_transit', 'on_hold', 'out_for_delivery', 'available_for_pickup', 'return_to_sender', 'exception' ) ) ) {
				wc_trackship_email_manager()->shippment_status_email_trigger( $order_id, $order, $old_status, $new_status, $tracking_item, $shipment_status );
			}
			
			/* translators: %s: search for a tag */
			$note = sprintf( __( 'Tracking Status (%s - %s) was updated to %s. (TrackShip)' ), $tracking_item['tracking_provider'], $tracking_item['tracking_number'], apply_filters('trackship_status_filter', $new_status) );
			
			// Add the note
			$order->add_order_note( $note );
			
			do_action( 'ast_trigger_ts_status_change', $order_id, $old_status, $new_status, $tracking_item, $shipment_status );
		}
	}	
		
	/**
	* Create tracking page after store is connected
	*/
	public function create_tracking_page() {
		if ( version_compare( get_option( 'wc_advanced_shipment_tracking_ts_page' ), '1.0', '<' ) ) {
			$new_page_title = 'Shipment Tracking';
			$new_page_slug = 'ts-shipment-tracking';		
			$new_page_content = '[wcast-track-order]';       
			//don't change the code below, unless you know what you're doing
			$page_check = get_page_by_title($new_page_title);		
	
			if ( !isset( $page_check->ID ) ) {
				$new_page = array(
					'post_type' => 'page',
					'post_title' => $new_page_title,
					'post_name' => $new_page_slug,
					'post_content' => $new_page_content,
					'post_status' => 'publish',
					'post_author' => 1,
				);
				$new_page_id = wp_insert_post($new_page);	
				update_option( 'wc_ast_trackship_page_id', $new_page_id );	
			}
			update_option( 'wc_advanced_shipment_tracking_ts_page', '1.0');					
		}	
	}
	
	/*
	* Return option value for customizer
	*/
	public function get_option_value_from_array( $array, $key, $default_value) {		
		$array_data = get_option($array);	
		$value = '';
		
		if ( isset( $array_data[$key] ) ) {
			$value = $array_data[$key];	
		}					
		
		if ( '' == $value ) {
			$value = $default_value;
		}
		return $value;
	}
	
	/*
	* Return checkbox option value for customizer
	*/
	public function get_checkbox_option_value_from_array( $array, $key, $default_value) {		
		$array_data = get_option($array);	
		$value = '';
		
		if ( isset( $array_data[$key] ) ) {
			$value = $array_data[$key];				
			return $value;
		}							
		if ( '' == $value ) {
			$value = $default_value;
		}		
		return $value;
	}
	
	/*
	* change style of delivered order label
	*/	
	public function footer_function() {
		if ( !is_plugin_active( 'woocommerce-order-status-manager/woocommerce-order-status-manager.php' ) ) {
			$bg_color = get_option('wc_ast_status_label_color', '#212c42');
			$color = get_option('wc_ast_status_label_font_color', '#fff');						
			?>
			<style>
			.order-status.status-delivered,.ts4wc_delivered_color .order-label.wc-delivered{
				background: <?php echo esc_html( $bg_color ); ?>;
				color: <?php esc_html_e( $color ); ?>;
			}			
			</style>
			<?php
		}
	}

	/*
	 * tracking number filter
	 * if number not found. return false
	 * if number found. return true
	*/
	public function check_tracking_exist( $bool, $order ) {
		
		if ( true == $bool ) {
				
			$tracking_items = $order->get_meta( '_wc_shipment_tracking_items', true );
			if ( $tracking_items ) {
				return true;
			} else {
				return false;
			}
		}
		return $bool;
	}		
	
	/*
	 * check order status?
	 * is it valid for TS trigger
	*/
	public function check_order_status( $bool, $order ) {
		$valid_order_statuses = get_option( 'trackship_trigger_order_statuses' );
		$bool = in_array( $order->get_status(), $valid_order_statuses );
		return $bool;
	}
	
	/*
	 * order status change
	 * schedule to trigger trackship
	*/
	public function schedule_when_order_status_changed( $order_id, $order ) {
		$this->trigger_trackship_apicall( $order_id );
	}
	
	/*
	 * schedule trackship trigger in action scheduler
	*/
	public function schedule_trackship_trigger( $order_id ) {
		$order = wc_get_order( $order_id );
		$order_shipped = apply_filters( 'is_order_shipped', false, $order );
		if ( $order_shipped ) {
			as_schedule_single_action( time() + 1, 'wcast_retry_trackship_apicall', array( $order_id ), 'TrackShip' );
			$this->set_temp_pending( $order_id );
			return true;
		}
		return false;
	}
	
	public function set_temp_pending( $order_id ) {
		
		$tracking_items = trackship_for_woocommerce()->get_tracking_items( $order_id, false );
		$shipment_statuses = $this->get_shipment_status( $order_id, false );
		//echo '<pre>';print_r($tracking_items);echo '</pre>';
		//echo '<pre>';print_r($shipment_statuses);echo '</pre>';
		
		foreach ( $tracking_items as $key => $tracking_item ) {
			
			if ( isset( $shipment_statuses[$key]['status'] ) && 'delivered' == $shipment_statuses[$key]['status'] ) {
				continue;
			}
			
			$shipment_statuses[$key]['pending_status'] = 'pending_trackship';
			$shipment_statuses[$key]['status_date'] = gmdate( 'y-m-d' );

			// set temp pending in shipment table 
			$args = array(
				'shipment_status' => 'pending_trackship',
			);
			trackship_for_woocommerce()->actions->update_shipment_data( $order_id, $tracking_item['tracking_number'], $args );
		}
		//echo '<pre>';print_r($shipment_statuses);echo '</pre>';
		update_post_meta( $order_id, 'shipment_status', $shipment_statuses );
	}
	
	/*
	* trigger trackship api call
	*/
	public function trigger_trackship_apicall( $order_id ) {
		
		$order = wc_get_order( $order_id );
		$order_shipped = apply_filters( 'is_order_shipped', false, $order );
		if ( $order_shipped ) {
			$api = new WC_TrackShip_Api_Call();
			$array = $api->get_trackship_apicall( $order_id );
		}
	}
	
	/*
	* trigger when order status changed to shipped or completed or update tracking
	* param $order_id
	*/	
	public function schedule_while_adding_tracking( $order_id ) {
		$this->schedule_trackship_trigger( $order_id );
	}
		
	/*
	* Get formated order id
	*/
	public function get_formated_order_id( $order_id ) {
		
		if ( is_plugin_active( 'custom-order-numbers-for-woocommerce/custom-order-numbers-for-woocommerce.php' ) ) {
			$alg_wc_custom_order_numbers_enabled = get_option( 'alg_wc_custom_order_numbers_enabled' );
			$alg_wc_custom_order_numbers_prefix  = get_option( 'alg_wc_custom_order_numbers_prefix' );
			$new_order_id = str_replace( $alg_wc_custom_order_numbers_prefix, '', $order_id );
						
			if ( 'yes' == $alg_wc_custom_order_numbers_enabled ) {
				$args = array(
					'post_type'		=>	'shop_order',			
					'posts_per_page'    => '1',
					'meta_query'        => array(
						'relation' => 'AND', 
						array(
						'key'       => '_alg_wc_custom_order_number',
						'value'     => $new_order_id,
						),
					),
					'post_status' => array_keys( wc_get_order_statuses() ) , 	
				);
				$posts = get_posts( $args );
				$my_query = new WP_Query( $args );				
				
				if ( $my_query->have_posts() ) {
					while ( $my_query->have_posts()) {
						$my_query->the_post();
						if ( get_the_ID() ) {
							$order_id = get_the_ID();
						}									
					} // end while
				} // end if
				$order_id;
				wp_reset_postdata();	
			}			
		}		
		
		if ( is_plugin_active( 'woocommerce-sequential-order-numbers/woocommerce-sequential-order-numbers.php' ) ) {
						
			$s_order_id = wc_sequential_order_numbers()->find_order_by_order_number( $order_id );			
			if ( $s_order_id ) {
				$order_id = $s_order_id;
			}
		}
		
		if ( is_plugin_active( 'woocommerce-sequential-order-numbers-pro/woocommerce-sequential-order-numbers-pro.php' ) ) {
			
			// search for the order by custom order number
			$query_args = array(
				'numberposts' => 1,
				'meta_key'    => '_order_number_formatted',
				'meta_value'  => $order_id,
				'post_type'   => 'shop_order',
				'post_status' => 'any',
				'fields'      => 'ids',
			);
			
			$posts = get_posts( $query_args );			
			if ( !empty( $posts ) ) {
				list( $order_id ) = $posts;			
			}			
		}
		
		if ( is_plugin_active( 'woocommerce-jetpack/woocommerce-jetpack.php' ) ) {
			
			$wcj_order_numbers_enabled = get_option( 'wcj_order_numbers_enabled' );
			// Get prefix and suffix options
			$prefix = do_shortcode( get_option( 'wcj_order_number_prefix', '' ) );
			$prefix .= date_i18n( get_option( 'wcj_order_number_date_prefix', '' ) );
			$suffix = do_shortcode( get_option( 'wcj_order_number_suffix', '' ) );
			$suffix .= date_i18n( get_option( 'wcj_order_number_date_suffix', '' ) );
	
			// Ignore suffix and prefix from search input
			$search_no_suffix            = preg_replace( "/\A{$prefix}/i", '', $order_id );
			$search_no_suffix_and_prefix = preg_replace( "/{$suffix}\z/i", '', $search_no_suffix );
			$final_search                = empty( $search_no_suffix_and_prefix ) ? $search : $search_no_suffix_and_prefix;	
			
			if ( 'yes' == $wcj_order_numbers_enabled ) {
				$query_args = array(
					'numberposts' => 1,
					'meta_key'    => '_wcj_order_number',
					'meta_value'  => $final_search,
					'post_type'   => 'shop_order',
					'post_status' => 'any',
					'fields'      => 'ids',
				);
				
				$posts = get_posts( $query_args );
				if ( !empty( $posts ) ) {	
					list( $order_id ) = $posts;			
				}			
			}
		}
		
		if ( is_plugin_active( 'wp-lister-amazon/wp-lister-amazon.php' ) ) {
			$wpla_use_amazon_order_number = get_option( 'wpla_use_amazon_order_number' );
			if ( 1 == $wpla_use_amazon_order_number ) {
				$query_args = array(
					'numberposts' => 1,
					'meta_key'    => '_wpla_amazon_order_id',
					'meta_value'  => $order_id,
					'post_type'   => 'shop_order',
					'post_status' => 'any',
					'fields'      => 'ids',
				);
				
				$posts = get_posts( $query_args );			
				if ( !empty( $posts ) ) {	
					list( $order_id ) = $posts;			
				}	
			}			
		}	
		
		if ( is_plugin_active( 'wp-lister/wp-lister.php' ) || is_plugin_active( 'wp-lister-for-ebay/wp-lister.php' ) ) {
			$args = array(
				'post_type'		=>	'shop_order',			
				'posts_per_page'    => '1',
				'meta_query'        => array(
					'relation' => 'OR', 
					array(
						'key'       => '_ebay_extended_order_id',
						'value'     => $order_id
					),
					array(
						'key'       => '_ebay_order_id',
						'value'     => $order_id
					),					
				),	
				'post_status' => 'any',	
			);
			
			$posts = get_posts( $args );
			$my_query = new WP_Query( $args );				
			
			if ( $my_query->have_posts() ) {
				while ( $my_query->have_posts() ) {
					$my_query->the_post();
					if ( get_the_ID() ) {
						$order_id = get_the_ID();
					}									
				} // end while
			} // end if
			wp_reset_postdata();
		}
		
		if ( is_plugin_active( 'yith-woocommerce-sequential-order-number-premium/init.php' ) ) {
			$query_args = array(
				'numberposts' => 1,
				'meta_key'    => '_ywson_custom_number_order_complete',
				'meta_value'  => $order_id,
				'post_type'   => 'shop_order',
				'post_status' => 'any',
				'fields'      => 'ids',
			);
			
			$posts = get_posts( $query_args );			
			if ( !empty( $posts ) ) {	
				list( $order_id ) = $posts;			
			}	
		}
		
		if ( is_plugin_active( 'wt-woocommerce-sequential-order-numbers/wt-advanced-order-number.php' ) ) {						
			$query_args = array(
				'numberposts' => 1,
				'meta_key'    => '_order_number',
				'meta_value'  => $order_id,
				'post_type'   => 'shop_order',
				'post_status' => 'any',
				'fields'      => 'ids',
			);
			
			$posts = get_posts( $query_args );			
			if ( !empty( $posts ) ) {
				list( $order_id ) = $posts;			
			}			
		}
		
		return apply_filters( 'ast_formated_order_id', $order_id );
	}
	
	public function get_shipment_status( $order_id, $formatted = true ) {
		$shipment_statuses = get_post_meta( $order_id, 'shipment_status', true );
		
		if ( is_array( $shipment_statuses ) ) {
			if ( $formatted ) {
				$tracking_page = $this->get_tracking_page_link( $order_id );
				foreach ( $shipment_statuses as &$item ) {
					if ( isset( $item[ 'pending_status' ] ) ) {
						$item[ 'status' ] = $item[ 'pending_status' ];
					}
					if ( 'carrier_unsupported' != $item[ 'status' ] ) {
						$array	= array( 'tracking_page' => $tracking_page );
						$item	= array_merge( $item, $array );
					}
				}
			}
			return $shipment_statuses;
		} else {
			return array();
		}
	}
	
	public function update_shipment_data( $order_id, $tracking_number, $args = array() ) {
		global $wpdb;
		$shipment_table = $wpdb->prefix . 'trackship_shipment';
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $shipment_table WHERE order_id = %d AND tracking_number = %s", $order_id, $tracking_number ) );
		if ( $row ) {
			$where = array(
				'order_id'			=> $order_id,
				'tracking_number'	=> $tracking_number,
			);
			$wpdb->update( $shipment_table, $args, $where );
		} else {
			$args['order_id'] = $order_id;
			$args['order_number'] =  wc_get_order( $order_id )->get_order_number();
			$args['tracking_number'] = $tracking_number;
			$wpdb->insert( $shipment_table, $args );
		}	
	}
	
	public function get_tracking_page_link( $order_id ) {
		
		$page_id = get_option( 'wc_ast_trackship_page_id' );
		$order = wc_get_order( $order_id );
		
		return add_query_arg( array(
			'order_id'	=> $order_id,
			'order_key'	=> $order->get_order_key(),
		), get_permalink( $page_id ) );
		
	}
	
	/*
	 * get trackship key
	 *
	 * @since   1.0
	 *
	 * Return @void
	 *
	 */
	public function get_trackship_key() {
		$wc_ast_api_key = get_option( 'wc_ast_api_key', false );
		return $wc_ast_api_key;
	}
}
