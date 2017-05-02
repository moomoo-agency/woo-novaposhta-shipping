<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/*
 * Check if WooCommerce is active
 */
if ( in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {

        if ( ! class_exists( 'Uni_NewPost_Shipping_Method' ) ) {

            class Uni_NewPost_Shipping_Method extends WC_Shipping_Method {
                /**
                 * Constructor for your shipping class
                 *
                 * @access public
                 * @return void
                 */
                public function __construct() {
                    $this->id                   = 'novaposhta';
                    $this->method_title         = esc_html__( '"New Post" (Nova Poshta)', 'novaposhta' );
                    $this->method_description   = esc_html__( '"New Post" (Nova Poshta) is an extremely popular delivery company in Ukraine', 'novaposhta' );

                    // Availability & Countries
                    $this->availability         = 'including';
                    $this->countries            = array(
                        'UA',
                    );

                    $this->init();

                    $this->enabled              = isset( $this->settings['enabled'] ) ? $this->settings['enabled'] : 'yes';
                    $this->title                = isset( $this->settings['title'] ) ? $this->settings['title'] : __( 'Nova Poshta', 'novaposhta' );
                    $this->flat_fee             = isset( $this->settings['flat_fee'] ) ? $this->settings['flat_fee'] : 0;
                }

                /**
                 * Init your settings
                 *
                 * @access public
                 * @return void
                 */
                function init() {
                    // Load the settings API
                    $this->init_form_fields();
                    $this->init_settings();

                    // User defined variables
                    $this->api_key              = $this->get_option( 'api_key' );
                    $this->debug                = ( $bool = $this->get_option( 'debug' ) ) && $bool == 'yes' ? true : false;

                    // Save settings in admin if you have any defined
                    add_action( 'woocommerce_update_options_shipping_' . $this->id, array( $this, 'process_admin_options' ) );

                    $this->get_regions_and_cities();
                }

                public function get_regions_and_cities() {
                    $this->get_regions();
                    $this->get_cities();
                }

                public function get_regions() {
    				if ( isset( $this->api_key ) && ! empty( $this->api_key ) ) {

                        if ( ! get_transient('uni_woo_novaposhta_regions') ) {
        				    $data = array(
                                'body' => array(
                                    'apiKey' => $this->api_key,
                                    'modelName' => 'Address',
                                    'calledMethod' => 'getAreas'
                                )
                            );
                            $result = UniWooNovaPoshta()->api_query( $data, 'https://api.novaposhta.ua/v2.0/json/Address/getAreas' );

                            if ( 'success' === $result['status'] ) {
                                set_transient( 'uni_woo_novaposhta_regions', $result['response']->data, 2592000 );
                            }
                        }
    				}
    			}

                public function get_cities() {
    				if ( isset( $this->api_key ) && ! empty( $this->api_key ) ) {

                        if ( ! get_transient('uni_woo_novaposhta_cities') ) {
        				    $data = array(
                                'body' => array(
                                    'apiKey' => $this->api_key,
                                    'modelName' => 'Address',
                                    'calledMethod' => 'getCities'
                                )
                            );
                            $result = UniWooNovaPoshta()->api_query( $data, 'https://api.novaposhta.ua/v2.0/json/Address/getCities' );

                            if ( 'success' === $result['status'] ) {
                                set_transient( 'uni_woo_novaposhta_cities', $result['response']->data, 2592000 );
                            }
                        }
    				}
    			}

                /**
                 * Define settings field for this shipping
                 * @return void
                 */
                function init_form_fields() {

                    $this->form_fields = array(

                     'enabled' => array(
                          'title' => __( 'Enable', 'novaposhta' ),
                          'type' => 'checkbox',
                          'description' => __( 'Enable this shipping.', 'novaposhta' ),
                          'default' => 'yes'
                          ),

                     'title' => array(
                        'title' => __( 'Title', 'novaposhta' ),
                          'type' => 'text',
                          'description' => __( 'Title to be display on site', 'novaposhta' ),
                          'default' => __( '"Nova Poshta"', 'novaposhta' )
                          ),

                     'flat_fee' => array(
                        'title' => __( 'Flat shipping fee', 'novaposhta' ),
                          'type' => 'text',
                          'description' => __( 'Define flat shipping fee', 'novaposhta' ),
                          'default' => 0
                          ),

                     'api_key' => array(
                        'title' => __( 'API Key', 'novaposhta' ),
                          'type' => 'text',
                          'description' => __( 'Define your API key', 'novaposhta' ),
                          'default' => ''
                          ),

                     );

                }

                /**
                 * calculate_shipping function.
                 *
                 * @access public
                 * @param mixed $package
                 * @return void
                 */
                public function calculate_shipping( $package = array() ) {
                    $rate = array(
                        'id' => $this->id,
                        'label' => $this->title,
                        'cost' => $this->flat_fee,
                        'calc_tax' => 'per_item'
                    );

                    // Register the rate
                    $this->add_rate( $rate );
                }

            }

        }

}