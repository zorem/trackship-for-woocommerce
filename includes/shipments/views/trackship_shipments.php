<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="trackship_admin_content">	
	<section class="trackship_analytics_section">
		<div class="woocommerce trackship_admin_layout">		
			<div class="">			
				<input type="hidden" id="nonce_trackship_shipments" value="<?php echo wp_create_nonce( "_trackship_shipments" );?>">
                <table class="widefat dataTable fixed fullfilments_table hover" cellspacing="0" id="active_shipments_table" style="width: 100%;">
                    <thead>
                        <tr class="tabel_heading_th">
                            <th id="columnname" class="manage-column column-columnname" scope="col"><?php esc_html_e('Shipping date', 'trackship-for-woocommerce'); ?></th>
                            <th id="columnname" class="manage-column column-columnname" scope="col"><?php esc_html_e('Order', 'woocommerce'); ?></th>
                            <th id="columnname" class="manage-column column-columnname" scope="col"><?php esc_html_e('Shipment status', 'trackship-for-woocommerce'); ?></th>
                            <th id="columnname" class="manage-column column-columnname" scope="col"><?php esc_html_e('Shipping provider', 'trackship-for-woocommerce'); ?></th>
                            <th id="columnname" class="manage-column column-destination" scope="col"><?php esc_html_e('Tracking number', 'trackship-for-woocommerce'); ?></th>
							<th id="columnname" class="manage-column column-columnname" scope="col"><?php esc_html_e('Ship to', 'trackship-for-woocommerce'); ?></th>
							<th id="columnname" class="manage-column column-columnname" scope="col"><?php esc_html_e('Shipping time', 'trackship-for-woocommerce'); ?></th>
							<th id="columnname" class="manage-column column-destination" scope="col"><?php esc_html_e('Delivery date', 'trackship-for-woocommerce'); ?></th>
                            <th id="columnname" class="manage-column column-columnname" scope="col"><?php esc_html_e('Actions', 'trackship-for-woocommerce'); ?></th>
						</tr>
                    </thead>
                    <tbody></tbody>				
                </table>
			</div>		
		</div>
	</section>
</div>
