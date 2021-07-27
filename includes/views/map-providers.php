<div class="d_table" style="">		
	<form method="post" id="trackship_mapping_form" action="" enctype="multipart/form-data">
		<div class="outer_form_table">				
			<table class="form-table heading-table">
				<tbody>
					<tr valign="top">
						<td>
							<h1><?php esc_html_e( 'Map Shipping Providers', 'trackship-for-woocommerce' ); ?></h1>
						</td>
					</tr>
				</tbody>
			</table>
			<table class="form-table fixed map-provider-table">
				<thead>
                	<p><?php esc_html_e( 'If you get different names from your shipping service, you can map the Shipping Providers names to the ones on TrackShip.', 'trackship-for-woocommerce' ); ?></p>
					<tr class="ptw_provider_border">
						<th><?php esc_html_e( 'Shipping Provider', 'trackship-for-woocommerce' ); ?></th>
						<th><?php esc_html_e( 'TrackShip Provider', 'trackship-for-woocommerce' ); ?></th>
					</tr>
				</thead>
			<tbody>
			<?php 
			$trackship_map_provider = get_option( 'trackship_map_provider' );
			$ts_shippment_providers = $this->get_trackship_provider();
			if ( !empty( $trackship_map_provider ) ) :
				foreach ( $trackship_map_provider as $key => $val ) : 
					?>
					<tr>
						<td>
							<input type="text" class="map_shipping_provider_text" name="detected_provider[]" value="<?php esc_html_e( $key ); ?>">
						</td>
						<td>
							<select name="ts_provider[]" class="select2">
								<option value=""><?php esc_html_e( 'Select' ); ?></option>
								<?php foreach ( $ts_shippment_providers as $ts_provider ) { ?>
									<option value="<?php echo esc_html( $ts_provider->ts_slug ); ?>" <?php esc_html_e( $ts_provider->ts_slug == $val ? 'selected' : '' ); ?> ><?php echo esc_html( $ts_provider->provider_name ); ?></option>	
								<?php } ?>
							</select>
							<span class="dashicons dashicons-trash remove_custom_maping_row"></span>
						</td>
					</tr>
				<?php endforeach; ?>
			<?php endif; ?>
		</tbody>
	</table>	
	<button class="button-primary add_custom_mapping_h3 button-trackship"><?php esc_html_e('Add mapping', 'trackship-for-woocommerce' ); ?><span class="dashicons dashicons-plus ptw-dashicons"></span></button><div class="add-custom-mapping spinner" style="float:none;"></div>
		</div>
        <div class="settings_ul_submit">								
            <button name="save" class="button-primary btn_green2 btn_large woocommerce-save-button button-trackship" type="submit"><?php esc_html_e( 'Save Changes', 'trackship-for-woocommerce' ); ?></button>
            <div class="mapping-save spinner"></div>						
            <?php wp_nonce_field( 'trackship_mapping_form', 'trackship_mapping_form_nonce' ); ?>
            <input type="hidden" name="action" value="trackship_mapping_form_update">
        </div>	
	</form>	
    
</div>				