jQuery( document ).ready( function( $ ) {
    'use strict';

    var $shipping_method    = $('input[name^=shipping_method][type=radio]');
    var $is_shipping_check  = $('#ship-to-different-address-checkbox');
    var $checkout_form      = $('.woocommerce-checkout');

    function handle_np_fields() {
        var method = $( $shipping_method.selector+':checked' ).val();
        var is_ship_diff = $( $is_shipping_check.selector ).is(':checked');

        var $region_select_bill = $( document.body ).find('#billing_np_region');
        var $city_select_bill = $( document.body ).find('#billing_np_city').select2({
            placeholder: uni_woo_novaposhta.i18n.city_place,
            allowClear: true
        });
        var $warehouse_select_bill = $( document.body ).find('#billing_np_warehouse').select2({
            placeholder: uni_woo_novaposhta.i18n.warehouse_place,
            allowClear: true
        });

        var $region_select_ship = $( document.body ).find('#shipping_np_region');
        var $city_select_ship = $( document.body ).find('#shipping_np_city').select2({
            placeholder: uni_woo_novaposhta.i18n.city_place,
            allowClear: true
        });
        var $warehouse_select_ship = $( document.body ).find('#shipping_np_warehouse').select2({
            placeholder: uni_woo_novaposhta.i18n.warehouse_place,
            allowClear: true
        });

        if ( 'novaposhta' === method ) {

            if ( is_ship_diff ) {

                $('#billing_np_region_field').removeClass('validate-required').hide();
                $('#billing_np_city_field').removeClass('validate-required').hide();
                $('#billing_np_warehouse_field').removeClass('validate-required').hide();

                $('#shipping_np_region_field').addClass('validate-required').show();
                $('#shipping_np_city_field').addClass('validate-required').show();
                $('#shipping_np_warehouse_field').addClass('validate-required').show();

                fields_unbinding( $region_select_bill, $city_select_bill, $warehouse_select_bill );
                fields_binding( $region_select_ship, $city_select_ship, $warehouse_select_ship );

                if ( uni_woo_novaposhta.woo_fields_disable ) {
                    $('#billing_city_field').addClass('validate-required').show();
                    $('#billing_state_field').addClass('validate-required').show();
                    $('#billing_postcode_field').addClass('validate-required').show();

                    $('#shipping_city_field').removeClass('validate-required').hide();
                    $('#shipping_state_field').removeClass('validate-required').hide();
                    $('#shipping_postcode_field').removeClass('validate-required').hide();
                }

            } else {

                $('#billing_np_region_field').addClass('validate-required').show();
                $('#billing_np_city_field').addClass('validate-required').show();
                $('#billing_np_warehouse_field').addClass('validate-required').show();

                $('#shipping_np_region_field').removeClass('validate-required').hide();
                $('#shipping_np_city_field').removeClass('validate-required').hide();
                $('#shipping_np_warehouse_field').removeClass('validate-required').hide();

                fields_unbinding( $region_select_ship, $city_select_ship, $warehouse_select_ship );
                fields_binding( $region_select_bill, $city_select_bill, $warehouse_select_bill );

                if ( uni_woo_novaposhta.woo_fields_disable ) {
                    $('#billing_city_field').removeClass('validate-required').hide();
                    $('#billing_state_field').removeClass('validate-required').hide();
                    $('#billing_postcode_field').removeClass('validate-required').hide();

                    $('#shipping_city_field').addClass('validate-required').show();
                    $('#shipping_state_field').addClass('validate-required').show();
                    $('#shipping_postcode_field').addClass('validate-required').show();
                }

            }

        } else {
            $('#billing_np_region_field').removeClass('validate-required').hide();
            $('#billing_np_city_field').removeClass('validate-required').hide();
            $('#billing_np_warehouse_field').removeClass('validate-required').hide();

            $('#shipping_np_region_field').removeClass('validate-required').hide();
            $('#shipping_np_city_field').removeClass('validate-required').hide();
            $('#shipping_np_warehouse_field').removeClass('validate-required').hide();

            if ( uni_woo_novaposhta.woo_fields_disable ) {
                $('#billing_city_field').addClass('validate-required').show();
                $('#billing_state_field').addClass('validate-required').show();
                $('#billing_postcode_field').addClass('validate-required').show();

                $('#shipping_city_field').addClass('validate-required').show();
                $('#shipping_state_field').addClass('validate-required').show();
                $('#shipping_postcode_field').addClass('validate-required').show();
            }

            fields_unbinding( $region_select_bill, $city_select_bill, $warehouse_select_bill );
            fields_unbinding( $region_select_ship, $city_select_ship, $warehouse_select_ship );
        }
    }

    //
    function fields_unbinding( $region_select, $city_select, $warehouse_select ) {
        $region_select.off('change');
        $city_select.off('change');
        $warehouse_select.off('change');
    }

    //
    function fields_binding( $region_select, $city_select, $warehouse_select ) {

        $region_select.on( 'change', function (e) {

            var ref = this.value;

                $.ajax({
                    url: woocommerce_params.ajax_url,
                    method: 'POST',
                    dataType: 'json',
                    beforeSend: function(){
                        $city_select.find('option').remove();
                        $city_select.select2('data', {});
                        $city_select.select2({
                            placeholder: uni_woo_novaposhta.i18n.city_place,
                            allowClear: true
                        });
                        $warehouse_select.find('option').remove();
                        $warehouse_select.select2('data', {});
                        $warehouse_select.select2({
                            placeholder: uni_woo_novaposhta.i18n.warehouse_place,
                            allowClear: true
                        });

	        	        $checkout_form.block({
	        	            message: null,
                            overlayCSS: { background: '#fff', opacity: 0.5 }
                        });
	        	    },
                    data: {
                        'action': 'uni_woo_novaposhta_get_cities',
                        'region_ref': ref
                    },
                    success: function (json) {
                        $checkout_form.unblock();

                        try {

                            $city_select.append($("<option></option>"));

                            $.each(json.data, function (key, value) {
                                $city_select
                                    .append($("<option></option>")
                                        .attr("value", key)
                                        .text(value)
                                    );
                            });

                        } catch (s) {
                            console.log("Error. Response from server was: " + json);
                        }
                    },
                    error: function () {
                        $checkout_form.unblock();
                        console.log('Error.');
                    }
                });

        });

        var init_area = $region_select.val();
        $region_select.val(init_area).trigger('change');

        $city_select.on( 'change', function (e) {

            var city = this.value;

                $.ajax({
                    url: woocommerce_params.ajax_url,
                    method: 'POST',
                    dataType: 'json',
                    beforeSend: function(){

                        $warehouse_select.find('option').remove();
                        $warehouse_select.select2('data', {});
                        $warehouse_select.select2({
                            placeholder: uni_woo_novaposhta.i18n.warehouse_place,
                            allowClear: true
                        });

            	        $checkout_form.block({
            	            message: null,
                            overlayCSS: { background: '#fff', opacity: 0.5 }
                        });
            	    },
                    data: {
                        'action': 'uni_woo_novaposhta_get_warehouses',
                        'city_id': city
                    },
                    success: function (json) {
                        $checkout_form.unblock();

                        try {

                            $warehouse_select.append($("<option></option>"));

                            $.each(json.data, function (key, value) {
                                $warehouse_select
                                    .append($("<option></option>")
                                        .attr("value", key)
                                        .text(value)
                                    );
                            });

                        } catch (s) {
                            console.log("Error. Response from server was: " + json);
                        }
                    },
                    error: function () {
                        $checkout_form.unblock();
                        console.log('Error.');
                    }
                });

        });

    }

    //
    $( document.body ).on( 'updated_checkout', function(){
        handle_np_fields();
    });

});