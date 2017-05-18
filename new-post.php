<?php
/*
Plugin Name: WooСommerce - 'New Post' (Nova Poshta) Shipping Method
Plugin URI: http://moomoo.agency/demo/cpo
Description: Adds 'New Post' (Nova Poshta) shipping method to your WooCommerce store
Author: Vitalii 'mr.psiho' Kiiko
Version: 1.0.1
Author URI: http://moomoo.agency
*/

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if (in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) )) {

		final class Uni_Woo_NovaPoshta_Setup {

            protected static $_instance = null;

            public $is_query = false;

        	public static function instance() {
        		if ( is_null( self::$_instance ) ) {
        			self::$_instance = new self();
        		}
        		return self::$_instance;
        	}

			public function __construct() {
                add_action( 'init', array( $this, 'init' ) );
			}

			public function init() {

				load_plugin_textdomain( 'novaposhta', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );

                add_action( 'woocommerce_shipping_init', array( $this, 'novaposhta_shipping_method_init' ) );
				add_filter( 'woocommerce_shipping_methods', array( $this, 'novaposhta_shipping_method_add' ) );

                // checkout page
                add_filter( 'woocommerce_billing_fields', array( $this, 'add_np_billing_fields') );
                add_filter( 'woocommerce_shipping_fields', array( $this, 'add_np_shipping_fields') );
                add_action( 'woocommerce_checkout_process', array( $this, 'validate_np_fields_data'), 10, 2 );
                add_action( 'woocommerce_checkout_update_order_meta', array( $this, 'save_np_fields_data') );

                // cart page
                add_action( 'woocommerce_after_calculate_totals', array( $this, 'calc_fields' ) );

				add_filter( 'wp_enqueue_scripts', array( $this, 'scripts' ) );

                // ajax call - get cities
                add_action( 'wp_ajax_uni_woo_novaposhta_get_cities', array( $this, 'get_cities' ) );
                add_action( 'wp_ajax_nopriv_uni_woo_novaposhta_get_cities', array( $this, 'get_cities' ) );
                // ajax call - get warehouses
                add_action( 'wp_ajax_uni_woo_novaposhta_get_warehouses', array( $this, 'get_warehouses' ) );
                add_action( 'wp_ajax_nopriv_uni_woo_novaposhta_get_warehouses', array( $this, 'get_warehouses' ) );

                add_filter( 'woocommerce_checkout_fields', array( $this, 'disable_default_checkout_fields') );
                //add_filter( 'woocommerce_update_order_review_fragments', array( $this, 'refresh_billing_form' ) );

			}

            /*function refresh_billing_form( $fragments ){

                // Get order review fragment
                ob_start();
                wc_get_template( 'checkout/form-billing.php', array( 'checkout' => WC()->checkout() ) );
                $woocommerce_billing_form = ob_get_clean();

                $fragments['.woocommerce-billing-fields'] = $woocommerce_billing_form;

                return $fragments;
            }*/

            //
            public function is_np() {
				if ( WC()->session->get( 'chosen_shipping_methods' )[0] === 'novaposhta' ) {
                    return true;
                } else {
                    return apply_filters('uni_woo_is_np_method_chosen', false, WC()->session);
                }
			}

            //
            public function calc_fields( $fields ) {
                if ( true === $this->is_np() ) {
                    add_filter('woocommerce_shipping_calculator_enable_city', '__return_false');
                    add_filter('woocommerce_shipping_calculator_enable_postcode', '__return_false');
                }
            }

            //
            public function is_shipping_different() {
                $is_ship_diff = isset( $_POST['ship_to_different_address'] ) ? true : false;

                if ( wc_ship_to_billing_address_only() ) {
                    $is_ship_diff = false;
                }
                return $is_ship_diff;
            }

            //
            public function is_disable_standard_checkout_fields() {
                return apply_filters( 'uni_np_disable_standard_checkout_fields', true );
            }

            //
            public function disable_default_checkout_fields( $fields ) {
				if ( true === $this->is_np() ) {

                    $type = ( $this->is_shipping_different() ) ? 'billing' : 'shipping';

                    $fields[$type][$type.'_np_region']['required']      = false;
                    $fields[$type][$type.'_np_city']['required']        = false;
                    $fields[$type][$type.'_np_warehouse']['required']   = false;

                    if ( true === $this->is_disable_standard_checkout_fields() ) {
                        $type = ( $this->is_shipping_different() ) ? 'shipping' : 'billing';

                        if (array_key_exists($type . '_state', $fields[$type])) {
                            $fields[$type][$type . '_state']['required'] = false;
                        }
                        if (array_key_exists($type . '_city', $fields[$type])) {
                            $fields[$type][$type . '_city']['required'] = false;
                        }
                        if (array_key_exists($type . '_address_1', $fields[$type])) {
                            $fields[$type][$type . '_address_1']['required'] = false;
                        }
                        if (array_key_exists($type . '_postcode', $fields[$type])) {
                            $fields[$type][$type . '_postcode']['required'] = false;
                        }
                    }

                    return $fields;

				    // can be used along with fragment reload
                    /*unset($fields['billing']['billing_state']);
                    unset($fields['billing']['billing_city']);
                    unset($fields['billing']['billing_address_1']);
                    unset($fields['billing']['billing_address_2']);
                    unset($fields['billing']['billing_postcode']);

                    unset($fields['shipping']['shipping_state']);
                    unset($fields['shipping']['shipping_city']);
                    unset($fields['shipping']['shipping_address_1']);
                    unset($fields['shipping']['shipping_address_2']);
                    unset($fields['shipping']['shipping_postcode']);*/
                } else {
                    $fields['billing']['billing_np_region']['required']      = false;
                    $fields['billing']['billing_np_city']['required']        = false;
                    $fields['billing']['billing_np_warehouse']['required']   = false;
                    $fields['shipping']['shipping_np_region']['required']      = false;
                    $fields['shipping']['shipping_np_city']['required']        = false;
                    $fields['shipping']['shipping_np_warehouse']['required']   = false;
                }
                return $fields;
			}

            //
            public function add_np_billing_fields( $fields ){

                //if ( true === $this->is_np() ) {

                    if ( ! get_transient('uni_woo_novaposhta_regions') && ! get_transient('uni_woo_novaposhta_cities') ) {
                        $shipping_methods = WC()->shipping->get_shipping_methods();
                        $shipping_methods['novaposhta']->get_regions_and_cities();
                        $regions = get_transient('uni_woo_novaposhta_regions');
                    } else {
                        $regions = get_transient('uni_woo_novaposhta_regions');
                    }

                    $options_regions = array();
                    if ( isset( $regions ) && is_array( $regions ) ) {
                        foreach ( $regions as $region ) {
                            $options_regions[$region->Ref] = $region->Description;
                        }
                    }

                    $fields['billing_np_region'] = [
                        'label' => esc_html__('Region', 'novaposhta'),
                        'type' => 'select',
                        'required' => true,
                        'default' => '',
                        'options' => $options_regions,
                        'class' => array(),
                        'custom_attributes' => array(),
                    ];
                    $fields['billing_np_city'] = [
                        'label' => esc_html__('City', 'novaposhta'),
                        'type' => 'select',
                        'required' => true,
                        'options' => array('' => esc_html__('Select a City', 'novaposhta') ),
                        'class' => array(),
                        'value' => '',
                        'custom_attributes' => array(),
                    ];
                    $fields['billing_np_warehouse'] = [
                        'label' => esc_html__('Warehouse', 'novaposhta'),
                        'type' => 'select',
                        'required' => true,
                        'options' => array('' => esc_html__('Select a Warehouse', 'novaposhta') ),
                        'class' => array(),
                        'value' => '',
                        'custom_attributes' => array(),
                    ];

                //}

                return $fields;

            }

            //
            public function add_np_shipping_fields( $fields ){

                //if ( true === $this->is_np() ) {

                    if ( ! get_transient('uni_woo_novaposhta_regions') && ! get_transient('uni_woo_novaposhta_cities') ) {
                        $shipping_methods = WC()->shipping->get_shipping_methods();
                        $shipping_methods['novaposhta']->get_regions_and_cities();
                        $regions = get_transient('uni_woo_novaposhta_regions');
                    } else {
                        $regions = get_transient('uni_woo_novaposhta_regions');
                    }

                    $options_regions = array();
                    if ( isset( $regions ) && is_array( $regions ) ) {
                        foreach ( $regions as $region ) {
                            $options_regions[$region->Ref] = $region->Description;
                        }
                    }

                    $fields['shipping_np_region'] = [
                        'label' => esc_html__('Region', 'novaposhta'),
                        'type' => 'select',
                        'required' => true,
                        'default' => '',
                        'options' => $options_regions,
                        'class' => array(),
                        'custom_attributes' => array(),
                    ];
                    $fields['shipping_np_city'] = [
                        'label' => esc_html__('City', 'novaposhta'),
                        'type' => 'select',
                        'required' => true,
                        'options' => array('' => esc_html__('Select a City', 'novaposhta') ),
                        'class' => array(),
                        'value' => '',
                        'custom_attributes' => array(),
                    ];
                    $fields['shipping_np_warehouse'] = [
                        'label' => esc_html__('Warehouse', 'novaposhta'),
                        'type' => 'select',
                        'required' => true,
                        'options' => array('' => esc_html__('Select a Warehouse', 'novaposhta') ),
                        'class' => array(),
                        'value' => '',
                        'custom_attributes' => array(),
                    ];

                //}

                return $fields;

            }

            public function get_cities() {

                if ( true === $this->is_np() ) {

                    $cities = get_transient('uni_woo_novaposhta_cities');

                    if ( isset($_POST['region_ref']) ) {
                        $region_ref = $_POST['region_ref'];
                    } else {
                        wp_send_json_error();
                    }

                    if ( ! get_transient('uni_woo_novaposhta_cities_area_' . $region_ref) ) {

                        $options_cities = array();
                        if ( isset( $cities ) && is_array( $cities ) ) {
                            foreach ( $cities as $city ) {
                                if ( $city->Area === $region_ref ) {
                                    $options_cities[$city->CityID] = $city->Description;
                                }
                            }
                            set_transient( 'uni_woo_novaposhta_cities_area_' . $region_ref, $options_cities, 2592000 );
                            wp_send_json_success( $options_cities );
                        } else {
                            wp_send_json_error();
                        }

                    } else {
                        $options_cities = get_transient('uni_woo_novaposhta_cities_area_' . $region_ref);
                        wp_send_json_success( $options_cities );
                    }

                }

    		}

            public function get_warehouses() {

                if ( true === $this->is_np() ) {

                    if ( isset($_POST['city_id']) ) {
                        $city_id = $_POST['city_id'];
                    } else {
                        wp_send_json_error();
                    }

                    $shipping_methods = WC()->shipping->get_shipping_methods();
                    $api_key = $shipping_methods['novaposhta']->api_key;

                    $city = $this->get_city_by_filter( array('city_id' => $city_id) );
                    if ( isset( $city ) && ! empty( $city ) && is_object( $city ) ) {
                        $city_ref = $city->Ref;
                    }

                    if ( ! isset($city_ref) ) {
                        wp_send_json_error();
                    }

                    $warehouses = $options_warehouses = array();
                    if ( ! get_transient('uni_woo_novaposhta_warehouses_in_' . $city_id) ) {
        				    $data = array(
                                'body' => array(
                                    'apiKey' => $api_key,
                                    'modelName' => 'AddressGeneral',
                                    'calledMethod' => 'getWarehouses',
                                    'methodProperties' => array(
                                        'CityRef' => $city_ref ,
                                    )
                                )
                            );
                            $result = UniWooNovaPoshta()->api_query( $data, 'https://api.novaposhta.ua/v2.0/json/AddressGeneral/getWarehouses' );

                            if ( 'success' === $result['status'] ) {
                                set_transient( 'uni_woo_novaposhta_warehouses_in_' . $city_id, $result['response']->data, 259200 );
                                $warehouses = $result['response']->data;
                            }
                    } else {
                        $warehouses = get_transient('uni_woo_novaposhta_warehouses_in_' . $city_id);
                    }

                    if ( isset( $warehouses ) && is_array( $warehouses ) ) {
                        foreach ( $warehouses as $warehouse ) {
                            $options_warehouses[$warehouse->Description] = $warehouse->Description;
                        }
                        wp_send_json_success( $options_warehouses );
                    } else {
                        wp_send_json_error();
                    }

                }
    		}

            //
            public function validate_np_fields_data(){
                console.log(WC()->session->get( 'chosen_shipping_methods' )[0]);
                if ( true === $this->is_np() ) {
                    if ( ! isset( $_POST['shipping_np_region'] ) && empty( $_POST['shipping_np_region'] ) ) {
                        wc_add_notice( __( 'Please choose your region', 'novaposhta' ), 'error' );
                    }
                    if ( ! isset( $_POST['shipping_np_city'] ) && empty( $_POST['shipping_np_city'] ) ) {
                        wc_add_notice( __( 'Please choose your city for Nova Poshta delivery', 'novaposhta' ), 'error' );
                    }
                    if ( ! isset( $_POST['shipping_np_warehouse'] ) && empty( $_POST['shipping_np_warehouse'] ) ) {
                        wc_add_notice( __( 'Please choose Nova Poshta warehouse', 'novaposhta' ), 'error' );
                    }
                }
            }

            //
            public function save_np_fields_data( $order_id ) {

                $type = ( $this->is_shipping_different() ) ? 'shipping' : 'billing';

                if ( ! empty( $_POST[$type.'_np_region'] ) ) {
                    $region = $this->get_region_by_filter( array('region_ref' => $_POST[$type.'_np_region']) );
                    if ( isset( $region ) && ! empty( $region ) && is_object( $region ) ) {
                        $region_name = $region->Description;
                    }
                    update_post_meta( $order_id, '_'.$type.'_state', sanitize_text_field( $region_name ) );
                }
                if ( ! empty( $_POST[$type.'_np_city'] ) ) {
                    $city = $this->get_city_by_filter( array('city_id' => $_POST[$type.'_np_city']) );
                    if ( isset( $city ) && ! empty( $city ) && is_object( $city ) ) {
                        $city_name = $city->Description;
                    }
                    update_post_meta( $order_id, '_'.$type.'_city', sanitize_text_field( $city_name ) );
                }
                if ( ! empty( $_POST[$type.'_np_warehouse'] ) ) {
                    update_post_meta( $order_id, '_'.$type.'_address_1', sanitize_text_field( $_POST[$type.'_np_warehouse'] ) );
                }
            }

            public function get_region_by_filter( $data ) {
                $regions = get_transient('uni_woo_novaposhta_regions');

                if ( isset( $regions ) && is_array( $regions ) ) {
                    if ( isset( $data['region_ref'] ) ) {
                        foreach ( $regions as $region ) {
                            if ( $data['region_ref'] === $region->Ref ) {
                                return $region;
                            }
                        }
                        return array();
                    } else {
                        return array();
                    }
                } else {
                    return array();
                }
            }

            public function get_city_by_filter( $data ) {
                $cities = get_transient('uni_woo_novaposhta_cities');

                if ( isset( $cities ) && is_array( $cities ) ) {
                    if ( isset( $data['city_id'] ) ) {
                        foreach ( $cities as $city ) {
                            if ( $data['city_id'] === $city->CityID ) {
                                return $city;
                            }
                        }
                        return array();
                    } else {
                        return array();
                    }
                } else {
                    return array();
                }
            }

            public function scripts() {

				wp_enqueue_script('uni-woo-novaposhta', $this->plugin_url() . '/assets/js/script.js',
                    array( 'jquery', 'select2', 'jquery-blockui' ),
                   '1.0.0',
                   true
                );

                $additonal_params = array(
                    'woo_fields_disable' => $this->is_disable_standard_checkout_fields(),
                    'i18n'  => array(
                        'region_place' => esc_html__('Select a Region', 'novaposhta'),
                        'city_place' => esc_html__('Select a City', 'novaposhta'),
                        'warehouse_place' => esc_html__('Select a Warehouse', 'novaposhta'),
                    )
            	);

            	wp_localize_script( 'uni-woo-novaposhta', 'uni_woo_novaposhta', $additonal_params );
			}

			public function novaposhta_shipping_method_init() {
				include_once( 'inc/class-uni-woo-novaposhta-shipping.php' );
			}

			public function novaposhta_shipping_method_add( $methods ) {
				$methods['novaposhta'] = 'Uni_NewPost_Shipping_Method';
				return $methods;
			}

            //
            public function api_query( $data, $url ){

                $result = $this->_r();

                $args = array(
                            'headers' => array(
                                'Content-Type' => 'application/json'
                            ),
                            'body' => json_encode( $data['body'] )
                        );

                $response = wp_remote_post( $url, $args );

                if ( is_wp_error( $response ) ) {
                        $ErrorMsg = $response->get_error_message();
                        $result['status']      = 'error';
                        $result['message']     = $ErrorMsg;
                } else {
                        $response_body_string = wp_remote_retrieve_body( $response );
                        $response_body = json_decode( $response_body_string );

                        if ( $response_body->statusCode === 404 ) {
                            $result['message']     = $response_body->message;
                        } else if ( $response_body->statusCode === 429 ) {
                            $result['message']     = $response_body->message;
                        } else if ( isset( $response_body->errors ) && ! empty( $response_body->errors ) ) {
                            $result['message']     = $response_body->errors;
                        } else {
                            $result['status']      = 'success';
                            $result['response']     = $response_body;
                        }
                }

                return $result;

        	}

            //
            public function plugin_url() {
        		return untrailingslashit( plugins_url( '/', __FILE__ ) );
        	}

            //
            protected function _r() {
                $result = array(
        		    'status' 	=> 'error',
        			'message' 	=> esc_html__('Error!', 'novaposhta'),
                    'response'	=> '',
        			'redirect'	=> ''
        		);
                return $result;
            }

            public function plugin_deactivate(){
            }

		}

        /**
         *  The main object
         */
        function UniWooNovaPoshta() {
        	return Uni_Woo_NovaPoshta_Setup::instance();
        }

        // Global for backwards compatibility.
        $GLOBALS['uniwoonovaposhta'] = UniWooNovaPoshta();

        register_deactivation_hook( __FILE__, array('Uni_Woo_NovaPoshta_Setup', 'plugin_deactivate') );

}
