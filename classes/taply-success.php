<?php

class Taply_Success extends WC_Payment_Gateway {

    /**
     * __construct function.
     *
     * @access public
     * @return void
     */
    public function __construct() {
                
        // Actions
        add_action('woocommerce_api_' . strtolower(get_class()), array($this, 'success'), 12);
    }
    
    public function success(){  
        $strApiUrl = 'https://api.paybytaply.com/payment/';
        $strMethod = "get-payment-info";

        if( !isset( $_REQUEST['payment'] ) ) {
            echo json_encode(array('error' => 'bad request'));
            exit;
        }
        $dst_settings = get_option( 'woocommerce_taply_settings' );

        $strPayment = $_REQUEST['payment'];

        $strMerchantId = $dst_settings['merchant_id']; //'cef9a8e6cb7e3a';


        $process = curl_init($strApiUrl . $strMethod);
        curl_setopt($process, CURLOPT_RETURNTRANSFER, 1); 
        curl_setopt($process, CURLOPT_FOLLOWLOCATION, 1); 
        curl_setopt($process, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($process, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($process, CURLOPT_FOLLOWLOCATION, 1); 
        curl_setopt($process, CURLOPT_POST, 1); 
        curl_setopt($process, CURLOPT_POSTFIELDS, 'payment='. $strPayment . '&merchantid='. $strMerchantId); 

        $strResponseJson = curl_exec( $process ); 
        curl_close($process); 

        $arrResponse = json_decode( $strResponseJson, TRUE );

        if(!isset($arrResponse['result']['cart'])){
            echo json_encode(array('error' => 'Carts not matched'));
            exit();
        }
        
        if(!isset($arrResponse['result']['transaction'])){
            echo json_encode(array('error' => 'Transaction not matched'));
            exit();
        }
        $sOrderCartJson = $arrResponse['result']['cart'];
        $sOrderTransaction =  $arrResponse['result']['transaction'];

        $arrOrderCart =  json_decode($sOrderCartJson, TRUE);
        $arrOrderTransaction =  json_decode( $sOrderTransaction, TRUE);
        WC()->cart->empty_cart();
        foreach( $arrOrderCart['items'] as $aOrderItem ) {
            if(isset($aOrderItem['item_prod_id'])){
                WC()->cart->add_to_cart( $aOrderItem['item_prod_id'],  $aOrderItem['item_qty'] ? $aOrderItem['item_qty'] : 1);
            }
        }
        
        $aUser = $arrOrderTransaction['user_info'];

        if(isset($aUser['billingAddress']['email'])){
            $sPassword = wp_generate_password();
            $oUser = get_user_by( 'email', $aUser['billingAddress']['email'] );
            if( !is_object( $oUser )) {
               $nCustId = wc_create_new_customer( $aUser['billingAddress']['email'], $aUser['billingAddress']['email'], $sPassword );
               wp_update_user( array(
                    'ID' => $nCustId,
                    'first_name' => $aUser['billingAddress']['firstName'],
                    'last_name' => $aUser['billingAddress']['lastName'],
                    'display_name' => $aUser['billingAddress']['firstName'] . ' ' .  $aUser['billingAddress']['lastName'],
                ) );
            } else {
               $nCustId = $oUser->data->ID;
            }

            if( is_int( $nCustId )) {
               wc_set_customer_auth_cookie( $nCustId );
            }
        }

        $mOrder = WC()->checkout->create_order();
        if($nCustId) {
            update_post_meta( $mOrder, '_customer_user', $nCustId );
        }

        $order = new WC_Order();
        $order->get_order( $mOrder );
        $order->set_address( array( 
            'first_name' => $aUser['billingAddress']['firstName'],
            'last_name' => $aUser['billingAddress']['lastName'],
            'email' => $aUser['billingAddress']['email'],
            'country' => $aUser['billingAddress']['country'],
            'state' => $aUser['billingAddress']['state'],
            'postcode' => $aUser['billingAddress']['zip'],
            'address_1' => $aUser['billingAddress']['street1'],
            'address_2' => $aUser['billingAddress']['street2'],
            'city' => $aUser['billingAddress']['city']
        ), 'billing' );

        $order->set_address( array( 
            'first_name' => $aUser['shippingAddress']['firstName'],
            'last_name' => $aUser['shippingAddress']['lastName'],
            'country' => $aUser['shippingAddress']['country'],
            'state' => $aUser['shippingAddress']['state'],
            'postcode' => $aUser['shippingAddress']['zip'],
            'address_1' => $aUser['shippingAddress']['street1'],
            'address_2' => $aUser['shippingAddress']['street2'],
            'city' => $aUser['shippingAddress']['city']
        ), 'shipping' );

        $fTotal = (float) preg_replace( '@[^\d\.]@', '', strip_tags( html_entity_decode( WC()->cart->get_cart_total() )));
        $fShippingCost = (float) $arrOrderTransaction['shipping']['amount'];
        $fTax = (float) $arrOrderTransaction['tax'];
        update_post_meta( $mOrder, '_order_shipping', $fShippingCost );
        update_post_meta( $mOrder, '_order_total', $fTotal + $fShippingCost + $fTax );
        update_post_meta( $mOrder, '_order_tax', $fTax );

        $nCheckoutPageId = get_option( 'woocommerce_checkout_page_id' );
        
        echo json_encode(array('order_id' => $mOrder, 'redirect_url' =>  get_site_url() . "/?wc-api=taply_success_redirect&CheckoutPageId&order-received=$mOrder&key=" . $order->order_key));
        exit;
    }
}

