<?php
$completed_order_with_tracking = $this->completed_order_with_tracking();		
$completed_order_with_zero_balance = $this->completed_order_with_zero_balance();							
$completed_order_with_do_connection = $this->completed_order_with_do_connection();
$total_orders = $completed_order_with_tracking + $completed_order_with_zero_balance + $completed_order_with_do_connection; ?>

<table class="form-table heading-table">
    <tbody>				
        <tr valign="top">
            <td>
                <h1 style=""><?php esc_html_e( 'Tools', 'trackship-for-woocommerce' ); ?></h1>
            </td>
        </tr>
    </tbody>
</table>
<div class="tools_tab_ts4wc">
    <div class="trackship-notice">
        <?php //%s used for replacement ?>
        <p><?php echo sprintf( esc_html( 'We detected %s Shipped orders from the last 30 days that were not sent to TrackShip, you can bulk send them to TrackShip', 'trackship-for-woocommerce'), esc_html( $total_orders ) ) ; ?><button class="button-primary button-trackship bulk_shipment_status_button" <?php echo 0 == $total_orders ? 'disabled' : ''; ?>><?php esc_html_e( 'Get Shipment Status', 'trackship-for-woocommerce' ); ?></button></p>
        
    </div>
    <div class="tracking-event-delete-notice">
        <?php //%s used for replacement ?>
        <p style="line-height:35px;"><?php esc_html_e( 'Delete tracking events for orders delivered more then ', 'trackship-for-woocommerce' ); ?>
            <select name="delete_time" id="delete_time" style="height: 35px;"> 
                <option value="30" selected>30</option>
                <option value="60">60</option>
                <option value="90">90</option>
                <option value="180">180</option>
            </select>
			<?php esc_html_e( ' Days.', 'trackship-for-woocommerce' ); ?>
            <button class="button-primary button-trackship-red bulk_shipment_status_button"><?php esc_html_e( 'Delete Tracking Event details', 'trackship-for-woocommerce' ); ?></button>
            <?php $nonce = wp_create_nonce( 'wc_ast_tools'); ?>
        	<input type="hidden" id="wc_ast_tools" name="wc_ast_tools" value="<?php echo esc_attr( $nonce ); ?>" />
		</p>
    </div>
</div>