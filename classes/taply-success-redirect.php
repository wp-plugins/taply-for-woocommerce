<?php

class Taply_Success_Redirect extends WC_Payment_Gateway {

    /**
     * __construct function.
     *
     * @access public
     * @return void
     */
    public function __construct() {
                
        // Actions
        add_action('woocommerce_api_' . strtolower(get_class()), array($this, 'redirect'), 12);
    }
    
    public function redirect(){        
        if( $_REQUEST['user_id'] ) {
           wc_set_customer_auth_cookie( $_REQUEST['user_id'] );
        }
        if($_REQUEST['order-received']){
            $mOrder = $_REQUEST['order-received'];
            $order = new WC_Order();
            $order->get_order( $mOrder );
            $fTotal = (float) preg_replace( '@[^\d\.]@', '', strip_tags( html_entity_decode( WC()->cart->get_cart_total() )));
            $fShippingCost = (float) $arrOrderTransaction['shipping']['amount'];
            $fTax = (float) $arrOrderTransaction['tax'];
            update_post_meta( $mOrder, '_order_shipping', $fShippingCost );
            update_post_meta( $mOrder, '_order_total', $fTotal + $fShippingCost + $fTax );
            update_post_meta( $mOrder, '_order_tax', $fTax );
            WC()->cart->empty_cart();
            $nCheckoutPageId = get_option( 'woocommerce_checkout_page_id' );
            wp_redirect(get_site_url() . (isset($mOrder) ? "/?page_id=$nCheckoutPageId&order-received=$mOrder&key=" . $order->order_key : "/?page_id=$nCheckoutPageId") );
        }

        $nCartPageId = get_option( 'woocommerce_cart_page_id' );
        wp_redirect(get_site_url() . "/?page_id=$nCartPageId");
    }
}

