<?php
/*
Plugin Name:       WooCommerce Wise Payment Gateway
Plugin URI:        https://wordpress.org/plugins/woocommerce-simple-payment-gateway/
Description:       A WooCommerce Extension that adds payment gateway "Wise Payment Gateway"
Version:           1
Author:            Diana Hakobyan
Author URI:        https://www.fiverr.com/diana_hakobyan?up_rollout=true

*/

function wc_wise_init() {
    global $woocommerce;

    if( !isset( $woocommerce ) ) { return; }

if ( ! defined( 'ABSPATH' ) ) exit;
    if( !class_exists( 'WC_Gateway_Wise_Payment_Gateway' ) ): class WC_Gateway_Wise_Payment_Gateway extends WC_Payment_Gateway {

     public function __construct() {
            $this->id                = 'wise';
            $this->icon              = apply_filters('woocommerce_wise_icon', '');
            $this->has_fields        = false;
            $this->method_title      = __( 'Wise Payment Gateway', 'wc_wise' );
            $this->order_button_text = apply_filters( 'woocommerce_wise_order_button_text', __( 'Place order', 'wc_wise' ) );
            $this->init_form_fields();
            $this->init_settings();
            $this->title              = $this->get_option( 'title' );
            $this->description        = $this->get_option( 'description' );
            $this->instructions       = $this->get_option( 'instructions' );
            $this->enable_for_methods = $this->get_option( 'enable_for_methods', array() );

            add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
            add_action( 'woocommerce_thankyou_wise', array( $this, 'thankyou' ) );
        }

        function admin_options() {
        ?>
            <h3><?php _e('Wise Payment Gateway','wc_wise'); ?></h3>
            <p><?php _e('Extra payment gateway with selection for shipping methods', 'wc_wise' ); ?></p>
            <table class="form-table">
                <?php $this->generate_settings_html(); ?>
            </table>
        <?php
        }

        public function init_form_fields() {
            $shipping_methods = array();

            if ( is_admin() ) {
                foreach ( WC()->shipping->load_shipping_methods() as $method ) {
                    $shipping_methods[ $method->id ] = $method->get_title();
                }
            }

            $this->form_fields = array(
                'enabled' => array(
                    'title' => __('Enable/Disable', 'wc_wise'),
                    'type' => 'checkbox',
                    'label' => __('Enable Wise Payment Gateway', 'wc_wise'),
                    'default' => 'yes'
                ),
                'title' => array(
                    'title' => __('Title', 'wc_wise'),
                    'type' => 'text',
                    'description' => __('Payment method title which the customer will see during checkout', 'wc_wise'),
                    'default' => __('Wise pay', 'wc_wise'),
                    'desc_tip'      => true,
                ),
                'description' => array(
                    'title' => __('Description', 'wc_wise'),
                    'type' => 'textarea',
                    'description' => __('Payment method description which the customer will see during checkout', 'wc_wise'),
                    'default' => __('Pay your order in Wise.', 'wc_wise'),
                    'desc_tip'      => true,
                ),
                'instructions' => array(
                    'title' => __('Instructions', 'wc_wise'),
                    'type' => 'textarea',
                    'description' => __('Instructions that will be added to the thank you page.', 'wc_wise'),
                    'default' => __('Pay your order in Wise.', 'wc_wise'),
                    'desc_tip'      => true,
                ),
                'enable_for_methods' => array(
                    'title'         => __('Enable for shipping methods', 'wc_wise'),
                    'type'          => 'multiselect',
                    'class'         => 'chosen_select',
                    'css'           => 'width: 450px;',
                    'default'       => '',
                    'description'   => __('Set up shipping methods that are available for Simple Payment Gateway. Leave blank to enable for all shipping methods.', 'wc_wise'),
                    'options'       => $shipping_methods,
                    'desc_tip'      => true,
                )
            );
        }

        public function is_available() {
            if ( ! empty( $this->enable_for_methods ) ) {

                $chosen_shipping_methods_session = WC()->session->get( 'chosen_shipping_methods' );

                if ( isset( $chosen_shipping_methods_session ) ) {
                    $chosen_shipping_methods = array_unique( $chosen_shipping_methods_session );
                } else {
                    $chosen_shipping_methods = array();
                }

                $check_method = false;

                if ( is_page( wc_get_page_id( 'checkout' ) ) && ! empty( $wp->query_vars['order-pay'] ) ) {

                    $order_id = absint( $wp->query_vars['order-pay'] );
                    $order    = new WC_Order( $order_id );

                    if ( $order->shipping_method )
                        $check_method = $order->shipping_method;

                } elseif ( empty( $chosen_shipping_methods ) || sizeof( $chosen_shipping_methods ) > 1 ) {
                    $check_method = false;
                } elseif ( sizeof( $chosen_shipping_methods ) == 1 ) {
                    $check_method = $chosen_shipping_methods[0];
                }

                if ( ! $check_method )
                    return false;

                $found = false;

                foreach ( $this->enable_for_methods as $method_id ) {
                    if ( strpos( $check_method, $method_id ) === 0 ) {
                        $found = true;
                        break;
                    }
                }

                if ( ! $found )
                    return false;
            }

            return parent::is_available();
        }

        public function process_payment( $order_id ) {
            $order = new WC_Order( $order_id );
            $order->update_status( apply_filters( 'wc_wise_default_order_status', 'on-hold' ), __( 'Awaiting payment', 'wc_wise' ) );
            $order->reduce_order_stock();
            WC()->cart->empty_cart();
            return array(
                'result'    => 'success',
                'redirect'  => $this->get_return_url( $order )
            );
        }

        public function thankyou() {
            echo $this->instructions != '' ? wpautop( wptexturize( wp_kses_post( $this->instructions ) ) ) : '';
        }
    }
endif;
}

add_action( 'plugins_loaded', 'wc_wise_init' );

function add_wise( $methods ) {
    $methods[] = 'WC_Gateway_Wise_Payment_Gateway';
    return $methods;
}
add_filter( 'woocommerce_payment_gateways', 'add_wise' );

