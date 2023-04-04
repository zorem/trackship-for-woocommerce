<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$nonce = wp_create_nonce( 'wc_ast_tools');
?>
<input type="hidden" id="wc_ast_dashboard_tab" name="wc_ast_dashboard_tab" value="<?php echo esc_attr( $nonce ); ?>" />
<?php
$ship_status = array(
	'active'				=> __( 'All Shipments', 'trackship-for-woocommerce' ),
	'in_transit'			=> __( 'In Transit', 'trackship-for-woocommerce' ),
	'out_for_delivery'		=> __( 'Out For Delivery', 'trackship-for-woocommerce' ),
	'pre_transit'			=> __( 'Pre Transit', 'trackship-for-woocommerce' ),
	'exception'				=> __( 'Exception', 'trackship-for-woocommerce' ),
	'delivered'				=> __( 'Delivered', 'trackship-for-woocommerce' ),
	'return_to_sender'		=> __( 'Return To Sender', 'trackship-for-woocommerce' ),
	'available_for_pickup'	=> __( 'Available For Pickup', 'trackship-for-woocommerce' ),
	'late_shipment'			=> __( 'Late Shipments', 'trackship-for-woocommerce' ),
	'tracking_issues'		=> __( 'Tracking Issues', 'trackship-for-woocommerce' ),
);
$columns = array(
	1 => 'Order',
	2 => 'Shipping date',
	3 => 'Tracking Number',
	4 => 'Shipping provider',
	5 => 'Shipment status',
	6 => 'Ship from',
	7 => 'Ship to',
	8 => 'Last Event',
	9 => 'Customer',
	10=> 'Shipping time',
	11 => 'Delivery date',
	12 => 'Actions',
);
$status = isset( $_GET['status'] ) ? sanitize_text_field( $_GET['status'] ) : '';
$url_provider = isset( $_GET['provider'] ) ? sanitize_text_field( $_GET['provider'] ) : '';


global $wpdb;
$woo_trackship_shipment = $wpdb->prefix . 'trackship_shipment';

$res = $wpdb->get_results( "SELECT shipment_status, COUNT(*) AS status_count FROM $woo_trackship_shipment GROUP BY shipment_status", ARRAY_A );
$statuses = array_column($res, "shipment_status");
$status_count = array_column($res, "status_count");
$shipment_count = array_combine($statuses, $status_count); // combine the two arrays using shipment_status as keys
$late_ship_day = trackship_for_woocommerce()->ts_actions->get_option_value_from_array('late_shipments_email_settings', 'wcast_late_shipments_days', 7 );
$days = $late_ship_day - 1 ;
$issues_count = $wpdb->get_row( "SELECT
	COUNT(*) AS active,
	SUM( IF(shipment_status NOT IN ( 'delivered', 'in_transit', 'out_for_delivery', 'pre_transit', 'exception', 'return_to_sender', 'available_for_pickup' ) OR pending_status IS NOT NULL, 1, 0) ) as tracking_issues,
	SUM( IF(shipping_length > ".$days.", 1, 0) ) as late_shipment
FROM {$woo_trackship_shipment}", ARRAY_A);

$shipment_count = array_merge($shipment_count, $issues_count);

$res = $wpdb->get_results( "SELECT shipping_provider, COUNT(*) AS provider_count FROM $woo_trackship_shipment GROUP BY shipping_provider", ARRAY_A );
$provider_array = array_column($res, "shipping_provider");
$provider_count_array = array_column($res, "provider_count");
$provider_count = array_combine($provider_array, $provider_count_array);
?>
<div>
	<span class="shipment_status">
		<select class="select_option" name="shipment_status" id="shipment_status">
			<?php foreach ( $ship_status as $key => $val ) { ?>
				<?php $count = isset($shipment_count[$key]) ? $shipment_count[$key] : 0; ?>
				<option value="<?php echo esc_html( $key ); ?>" <?php echo $status == $key ? 'selected' : ''; ?>><?php echo esc_html( $val . ' (' . $count . ') ' ); ?></option>
			<?php } ?>
		</select>
	</span>
	<?php
	$all_providers = $wpdb->get_results( "SELECT shipping_provider FROM {$woo_trackship_shipment} WHERE shipping_provider NOT LIKE ( '%NULL%') GROUP BY shipping_provider" );
	?>
	<span class="shipping_provider">
		<select class="select_option" name="shipping_provider" id="shipping_provider">
			<option value="all"><?php esc_html_e( 'All shipping providers', 'trackship-for-woocommerce' ); ?></option>
			<?php foreach ( $all_providers as $provider ) { ?>
				<?php $count = isset($provider_count[$provider->shipping_provider]) ? $provider_count[$provider->shipping_provider] : 0; ?>
				<?php $formatted_provider = trackship_for_woocommerce()->actions->get_provider_name( $provider->shipping_provider ); ?>
				<?php $provider_name = isset($formatted_provider) && $formatted_provider ? $formatted_provider : $provider->shipping_provider; ?>
				<option value="<?php echo esc_html( $provider->shipping_provider ); ?>" <?php echo $url_provider == $provider->shipping_provider ? 'selected' : ''; ?>><?php echo esc_html( $provider_name . ' (' . $count . ') ' ); ?></option>
		<?php } ?>
		</select>
	</span>
</div>
<div class="bulk_action_div">
	<select class="select_option" name="bulk_actions" id="bulk_actions">
		<option><?php esc_html_e( 'Bulk actions', 'trackship-for-woocommerce' ); ?></option>
		<option value="get_shipment_status"><?php esc_html_e( 'Get shipment status', 'trackship-for-woocommerce' ); ?></option>
	</select>
	<button class="bulk_action_button button-trackship button-primary" type="button"><?php esc_html_e( 'Apply', 'trackship-for-woocommerce' ); ?></button>
</div>
<div class="filters_div">
	<span class="filter_data status_filter"><span class="status_name"></span><span class="dashicons dashicons-no-alt"></span></span>
	<span class="filter_data provider_filter"><span class="provider_name"></span><span class="dashicons dashicons-no-alt"></span></span>
</div>
<div class="shipments_custom_data custom_data">
	<span class="shipment_search_bar">
		<input type="text" id="search_bar" name="search_bar" placeholder="<?php esc_html_e( 'Search by Tracking Number, Shipping carrier, Order number', 'trackship-for-woocommerce' ); ?>">
		<span class="dashicons dashicons-no"></span>
		<span class="dashicons dashicons-search serch_icon"></span>
	</span>
	<span class="export_shipment"><span class="dashicons dashicons-download" title="CSV download"></span></span>
	<span class="more_info_shipment">
		<span class="dashicons dashicons-ellipsis"></span>
		<div class="popover__content">
			<?php foreach ( $columns as $key => $val) { ?>
				<div class="column_toogle">
					<input type="hidden" name="<?php echo 'column_' . $key; ?>" value="0"/>
					<input class="ast-tgl ast-tgl-flat" id="<?php echo 'column_' . $key; ?>" name="<?php echo 'column_' . $key; ?>" data-number="<?php echo $key; ?>" type="checkbox" checked value="1"/>
					<label class="ast-tgl-btn ast-tgl-btn-green" for="<?php echo 'column_' . $key; ?>"></label>
					<label for="<?php echo 'column_' . $key; ?>"><span><?php echo $val; ?></span></label>
				</div>
			<?php } ?>
		</div>
	</span>
</div>
<?php require_once( trackship_for_woocommerce()->get_plugin_path() . '/includes/shipments/views/trackship_shipments.php' ); ?>
