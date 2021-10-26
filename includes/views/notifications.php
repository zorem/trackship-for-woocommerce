<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="outer_form_table ts_notifications_outer_table">
	<?php 
	$wcast_enable_late_shipments_admin_email = trackship_for_woocommerce()->ts_actions->get_option_value_from_array('late_shipments_email_settings', 'wcast_enable_late_shipments_admin_email', '');
	
	$tab_type = isset( $_GET['type'] ) ? sanitize_text_field($_GET['type']) : 'email';
	
	$ts_notifications = $this->trackship_shipment_status_notifications_data();
	//echo '<pre>';print_r($ts_notifications);echo '</pre>';
	?>				
	<div class="trackship_tab_name">
		<input id="tab_email_notifications" type="radio" name="ts_notification_tabs" class="inner_tab_input" data-type="email" <?php echo 'email' == $tab_type ? 'checked' : ''; ?> >
		<label for="tab_email_notifications" class="inner_tab_label ts_tabs_label inner_email_tab"><?php esc_html_e( 'Email Notifications', 'trackship-for-woocommerce' ); ?></label>				
    
		<input id="tab_sms_notifications" type="radio" name="ts_notification_tabs" class="inner_tab_input" data-type="sms" <?php echo 'sms' == $tab_type ? 'checked' : ''; ?> >
		<label for="tab_sms_notifications" class="inner_tab_label ts_tabs_label inner_sms_tab"><?php esc_html_e( 'SMS Notifications', 'trackship-for-woocommerce' ); ?></label>
    
		<input id="tab_admin_notifications" type="radio" name="ts_notification_tabs" class="inner_tab_input" data-type="late-email" <?php echo 'admin' == $tab_type ? 'checked' : ''; ?> >
		<label for="tab_admin_notifications" class="inner_tab_label ts_tabs_label inner_admin_tab"><?php esc_html_e( 'Admin Notifications', 'trackship-for-woocommerce' ); ?></label>
    </div>
    
    <section class="inner_tab_section shipment-status-email-section">
        <?php $nonce = wp_create_nonce( 'tswc_shipment_status_email'); ?>
        <input type="hidden" id="tswc_shipment_status_email" name="tswc_shipment_status_email" value="<?php echo esc_attr( $nonce ); ?>" />

        <table class="form-table shipment-status-email-table">
            <tbody>
                <?php foreach ( $ts_notifications as $key => $val ) { ?>
					<?php $ast_enable_email = trackship_for_woocommerce()->ts_actions->get_option_value_from_array( $val['option_name'], $val['enable_status_name'], ''); ?>
                    <tr class="<?php echo 1 == $ast_enable_email ? 'enable' : 'disable'; ?> ">
                        <td class="forminp status-label-column">
                        	<?php $image_name = in_array( $val['slug'], array( 'failed-attempt', 'exception' ) ) ? 'failure' : $val['slug']; ?>
                            <?php $image_name = 'delivered-status' == $image_name ? 'delivered' : $image_name; ?>
                        	<img src="<?php echo esc_url( trackship_for_woocommerce()->plugin_dir_url() ); ?>assets/css/icons/<?php echo esc_html( $image_name ); ?>.png">
                            <strong class="shipment-status-label <?php echo esc_html( $val['slug'] ); ?>"><?php echo esc_html( $val['title'] ); ?></strong>
                            <?php if ( 'delivered' == $key ) { ?>
								<label for="all-shipment-status-<?php echo $key; ?>">
									<input type="hidden" name="all-shipment-status-<?php echo $key?>" value="no">
									<input name="all-shipment-status-<?php echo $key?>" type="checkbox" id="all-shipment-status-<?php echo $key?>" value="yes" <?php echo get_option( 'all-shipment-status-'.$key ) == 1 ? 'checked' : '' ?> >
									<?php echo $val['title2'] ?>
                                    <?php $nonce = wp_create_nonce( 'all_status_delivered'); ?>
                                    <input type="hidden" id="all_status_delivered" name="all_status_delivered" value="<?php echo esc_attr( $nonce ); ?>" />
								</label>
							<?php } ?>
                        </td>
                        <td class="forminp">
                            <a class="button-primary btn_ts_sidebar edit_customizer_a" href="<?php echo esc_html( $val['customizer_url'] ); ?>"><?php esc_html_e('Edit', 'trackship-for-woocommerce'); ?></a>
                            <span class="shipment_status_toggle">
                                <input type="hidden" name="<?php echo esc_html( $val['enable_status_name'] ); ?>" value="0"/>
                                <input class="ast-tgl ast-tgl-flat" id="<?php echo esc_html( $val['enable_status_name'] ); ?>" name="<?php echo esc_html( $val['enable_status_name'] ); ?>" data-settings="<?php echo esc_html( $val['option_name'] ); ?>" type="checkbox" <?php echo 1 == $ast_enable_email ? 'checked' : ''; ?> value="yes"/>
                                <label class="ast-tgl-btn ast-tgl-btn-green" for="<?php echo esc_html( $val['enable_status_name'] ); ?>"></label>	
                            </span>
                        </td>
                    </tr>
                <?php } ?>										
            </tbody>
        </table>	
        
        <?php do_action( 'after_shipment_status_email_notifications' ); ?>
	</section>
    
    <section class="inner_tab_section shipment-status-late-email-section">
        <form method="post" id="trackship_late_shipments_form" action="" enctype="multipart/form-data">					
            <table class="form-table heading-table shipment-status-email-table">
                <tbody>
                    <tr class="late-shipment-tr <?php echo 1 == $wcast_enable_late_shipments_admin_email ? 'enable' : 'disable'; ?> ">
                        <td class="forminp status-label-column">
                        	<img src="<?php echo esc_url( trackship_for_woocommerce()->plugin_dir_url() ); ?>assets/css/icons/late-shipment.png">
                            <strong><?php esc_html_e('Late Shipments', 'trackship-for-woocommerce'); ?></strong>
                        </td>
                        <td class="forminp">
                            <a class="edit_customizer_a late_shipments_a button-primary btn_ts_transparent btn_ts_sidebar" href="javascript:void(0);"><?php esc_html_e('Edit', 'trackship-for-woocommerce'); ?></a>
                        </td>
                    </tr>
                </tbody>
            </table>
            <?php 
            $late_shipments_email_settings = get_option('late_shipments_email_settings');
            $wcast_late_shipments_days = isset( $late_shipments_email_settings['wcast_late_shipments_days'] ) ? $late_shipments_email_settings['wcast_late_shipments_days'] : '';
            $wcast_late_shipments_email_to = isset( $late_shipments_email_settings['wcast_late_shipments_email_to'] ) ? $late_shipments_email_settings['wcast_late_shipments_email_to'] : '';			
            $wcast_late_shipments_daily_digest_time = isset( $late_shipments_email_settings['wcast_late_shipments_daily_digest_time'] ) ? $late_shipments_email_settings['wcast_late_shipments_daily_digest_time'] : '' ;
            ?>
            <div class="late-shipments-email-content-table">
				<div class="late_shipment_before_table">
					<span class="shipment_status_toggle">
							<input type="hidden" name="wcast_enable_late_shipments_admin_email" value="0"/>
    	                <input class="ast-tgl ast-tgl-flat" id="wcast_enable_late_shipments_admin_email" name="wcast_enable_late_shipments_admin_email" data-settings="late_shipments_email_settings" type="checkbox" <?php echo 1 == $wcast_enable_late_shipments_admin_email ? 'checked' : ''; ?> value="1"/>
						<label class="ast-tgl-btn ast-tgl-btn-green" for="wcast_enable_late_shipments_admin_email"></label>
						<?php esc_html_e('Late Shipments Daily Digest', 'trackship-for-woocommerce'); ?>
					</span>
					<span class="late_shipment_save_button">
						<div class="spinner"></div>
						<button name="save" class="button-primary woocommerce-save-button btn_green2 btn_large" type="submit" value="Save & close"><?php esc_html_e( 'Save & close', 'trackship-for-woocommerce' ); ?></button>
                
						<?php wp_nonce_field( 'ts_late_shipments_email_form', 'ts_late_shipments_email_form_nonce' ); ?>
						<input type="hidden" name="action" value="ts_late_shipments_email_form_update">
					</span>
				</div>
				<table class="form-table hide_table">
					<tr class="">
						<th scope="row" class="titledesc">
							<label for=""><?php esc_html_e('Recipient(s)', 'trackship-for-woocommerce'); ?></label>	
						</th>	
						<td class="forminp">
							<fieldset>
								<input class="input-text regular-input " style="width: 60%;" type="text" name="wcast_late_shipments_email_to" id="wcast_late_shipments_email_to" placeholder="<?php esc_html_e( 'E.g. {admin_email}, admin@example.org' ); ?>" value="<?php echo esc_html($wcast_late_shipments_email_to, get_option( 'admin_email' ) ); ?>">
							</fieldset>
						</td>
					</tr>
					<?php 
					$send_time_array = array();										
					for ( $hour = 0; $hour < 24; $hour++ ) {
						for ( $min = 0; $min < 60; $min = $min + 30 ) {
							$this_time = gmdate( 'H:i', strtotime( "$hour:$min" ) );
							$send_time_array[ $this_time ] = $this_time;
						}	
					}
					?>
					<tr class="">
						<th scope="row" class="titledesc" style="width:30%">
							<label for=""><?php esc_html_e('Send email at', 'trackship-for-woocommerce'); ?></label>	
						</th>
						<td class="forminp" style="width:70%">
							<select class="select daily_digest_time" name="wcast_late_shipments_daily_digest_time">
								<?php foreach ( (array) $send_time_array as $key1 => $val1 ) { ?>
									<option <?php echo $wcast_late_shipments_daily_digest_time == $key1 ? 'selected' : ''; ?> value="<?php echo esc_html( $key1 ); ?>" ><?php echo esc_html( $val1 ); ?></option>
								<?php } ?>
							</select>
						</td>
					</tr>
				</table>
			</div>
        </form>
    </section>
    <section class="inner_tab_section shipment-status-sms-section">
		<?php if ( ! function_exists( 'SMSWOO' ) && in_array( get_option( 'user_plan' ), array( 'Free Trial', 'Free 50', 'No active plan' ) ) ) { ?>
			<input type="hidden" class="disable_pro" name="disable_pro" value="disable_pro">
		<?php }
		do_action( 'shipment_status_sms_section' );
		?>
	</section>
</div>				
