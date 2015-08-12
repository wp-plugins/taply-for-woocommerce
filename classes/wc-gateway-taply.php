<?php

class WC_Gateway_Taply extends WC_Payment_Gateway {

    /**
     * __construct function.
     *
     * @access public
     * @return void
     */
    public function __construct() {
        $this->id = 'taply';
        $this->title = 'Apple Pay';
        $this->method_title = __('Taply Checkout ', 'taply-for-woocommerce');
        $strSuccessUrl = got_url_rewrite()? home_url() . '/wc-api/taply_success/' :  home_url() . '?wc-api=taply_success/';
        $this->method_description = __('Taply enables e-commerce websites to accept Apple Pay. It’s easy, quick and secure for both your business and your customers. Install the taply plugin to accept Apple Pay on your website now. Register your taply account at www.paybytaply.com/signup. <br><br><b>Set Success Page in your store settings to ' . $strSuccessUrl . '</b>', 'taply-for-woocommerce');
        $this->has_fields = false;
        $this->supports = array(
            'products',
            'refunds'
        );
        // Load the form fields
        $this->init_form_fields();
        // Load the settings.
        $this->init_settings();
        // Get setting values
        $this->enabled = FALSE;
        $this->description = $this->settings['description'];
        $this->taply_merchant_id = $this->settings['merchant_id'];
        $this->customer_id = get_current_user_id();
        
        //Save settings
        add_action('woocommerce_update_options_payment_gateways', array($this, 'process_admin_options'));
        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
    }

    /**
     * get_icon function.
     *
     * @access public
     * @return string
     */
    public function get_icon() {

        $image_path = WP_PLUGIN_URL . "/" . plugin_basename(dirname(dirname(__FILE__))) . '/assets/images/taply.png';

        $icon = "<img src=\"$image_path\" alt='" . __('Pay with Taply', 'taply-for-woocommerce') . "'/>";
        return apply_filters('woocommerce_gateway_icon', $icon, $this->id);
    }

    /**
     * Override this method so this gateway does not appear on checkout page
     *
     * @since 1.0.0
     */
    public function admin_options() {
        ?>

        <h3><?php echo isset($this->method_title) ? $this->method_title : __('Settings', 'taply-for-woocommerce'); ?></h3>
        <?php echo isset($this->method_description) ? wpautop($this->method_description) : ''; ?>
        <table class="form-table">
            <?php $this->generate_settings_html(); ?>
        </table>
        <?php
        $this->scriptAdminOption();
    }

    public function scriptAdminOption() {
        ?>
        <script type="text/javascript">
            
        </script>
        <?php
    }

    public function get_confirm_order($order) {
        $this->confirm_order_id = $order->id;
    }

    function is_available() {
        if ( $this->enabled == 'yes')
            return true;
        return false;
    }

    /**
     * Use WooCommerce logger if debug is enabled.
     */
    function add_log($message) {
        if ($this->debug == 'yes') {
            if (empty($this->log))
                $this->log = new WC_Logger();
            $this->log->add('taply', $message);
        }
    }

    /**
     * Check if site is SSL ready
     *
     */
    function is_ssl() {
        if (is_ssl() || get_option('woocommerce_force_ssl_checkout') == 'yes' || class_exists('WordPressHTTPS'))
            return true;
        return false;
    }

    /**
     * Initialize Gateway Settings Form Fields
     */
    function init_form_fields() {

//        $require_ssl = '';
//        if (!$this->is_ssl()) {
//            $require_ssl = __('This image requires an SSL host.  Please upload your image to <a target="_blank" href="http://www.sslpic.com">www.sslpic.com</a> and enter the image URL here.', 'taply-for-woocommerce');
//        }

        $woocommerce_enable_guest_checkout = get_option('woocommerce_enable_guest_checkout');
        if (isset($woocommerce_enable_guest_checkout) && ( $woocommerce_enable_guest_checkout === "no" )) {
            $skip_final_review_option_not_allowed = ' (This is not available because your WooCommerce orders require an account.)';
        } else {
            $skip_final_review_option_not_allowed = '';
        }

        $args = array(
            'sort_order' => 'ASC',
            'sort_column' => 'post_title',
            'hierarchical' => 1,
            'exclude' => '',
            'include' => '',
            'meta_key' => '',
            'meta_value' => '',
            'authors' => '',
            'child_of' => 0,
            'parent' => -1,
            'exclude_tree' => '',
            'number' => '',
            'offset' => 0,
            'post_type' => 'page',
            'post_status' => 'publish'
        );
        $pages = get_pages($args);
        $cancel_page = array();
        foreach ($pages as $p) {
            $cancel_page[$p->ID] = $p->post_title;
        }
        $this->form_fields = array(
            'merchant_id' => array(
                'title' => __('Taply Store ID', 'taply-for-woocommerce'),
                'type' => 'text',
                'description' => __('Create Taply accounts and obtain merchant id from within your <a href="http://paybytaply.com">Taply merchant account</a>.', 'taply-for-woocommerce'),
                'default' => ''
            ),

            'description' => array(
                'title' => __('Description', 'taply-for-woocommerce'),
                'type' => 'textarea',
                'description' => __('This controls the description which the user sees during checkout.', 'taply-for-woocommerce'),
                'default' => __("Pay via Taply", 'taply-for-woocommerce')
            ),
            
            'view_type' => array(
                'title' => __('Block type', 'taply-for-woocommerce'),
                'label' => __('How to display Pay By Taply block.', 'taply-for-woocommerce'),
                'description' => __('Set how to display the Pay By Taply button.', 'taply-for-woocommerce'),
                'type' => 'select',
                'options' => array(
                    'popup' => 'with Popup window.',
                    'block' => 'single block.',
                ),
                'default' => 'popup'
            ),
            
            'button_position' => array(
                'title' => __('Cart Button Position', 'taply-for-woocommerce'),
                'label' => __('Where to display Pay By Taply button.', 'taply-for-woocommerce'),
                'description' => __('Set where to display the Pay By Taply block.'),
                'type' => 'select',
                'options' => array(
                    'top' => 'At the top, above the shopping cart details.',
                    'bottom' => 'At the bottom, below the shopping cart details.',
                    'both' => 'Both at the top and bottom, above and below the shopping cart details.'
                ),
                'default' => 'bottom'
            ),
            'show_on_cart' => array(
                'title' => __('Cart Page', 'taply-for-woocommerce'),
                'label' => __('Show Pay by Taply button on shopping cart page.', 'taply-for-woocommerce'),
                'type' => 'checkbox',
                'default' => 'yes'
            ),
            'show_on_product_page' => array(
                'title' => __('Product Page', 'taply-for-woocommerce'),
                'type' => 'checkbox',
                'label' => __('Show Pay by Taply button on product detail pages.', 'taply-for-woocommerce'),
                'default' => 'no',
                'description' => __('Allows customers to checkout using Taply directly from a product page.', 'taply-for-woocommerce')
            ),
        );
    }


    
    /**
     * get_state
     *
     * @param $country - country code sent by Taply
     * @param $state - state name or code sent by Taply
     */
    function get_state_code($country, $state) {
        // If not US address, then convert state to abbreviation
        if ($country != 'US') {
            $local_states = WC()->countries->states[WC()->customer->get_country()];
            if (!empty($local_states) && in_array($state, $local_states)) {
                foreach ($local_states as $key => $val) {
                    if ($val == $state) {
                        $state = $key;
                    }
                }
            }
        }
        return $state;
    }

    /**
     * Checkout Button
     *
     * Triggered from the 'woocommerce_proceed_to_checkout' action.
     * Displays the Taply button.
     */
    static function buyNowButton() {
        global $dst_settings,$product;

        if ((empty($dst_settings['show_on_product_page']) || $dst_settings['show_on_product_page'] == 'yes') ) { 
            $arrItems = array(
                array(
                'item_prod_id'      => $product->id,
                'item_name'         => $product->get_title(),
                'item_img'          => preg_replace( '@^.+src="(.+)".+$@simU', '$1', $product->get_image() ),
                'item_description'  => $product->get_title(),
                'item_qty'          => 1,
                'item_price'        => $product->get_price()
                )
            );
            $arrCart = array('merchant' => $dst_settings['merchant_id'],'description' => $dst_settings['description'],'currency'=>'USD','items' => $arrItems);
            echo '<br>';
            self::getButtonCode($arrCart, $dst_settings['view_type']);
        }
    }

    /**
     * Checkout Button
     *
     * Triggered from the 'woocommerce_proceed_to_checkout' action.
     * Displays the Taply button.
     */
    static function woocommerceTaplyButton() {
        global $dst_settings;

        $arrItems = array();
        foreach ( WC()->cart->get_cart() as $strItemKey => $arrCartItem ) {
            $_product     = apply_filters( 'woocommerce_cart_item_product', $arrCartItem['data'], $arrCartItem, $strItemKey );
            $arrItems[] = array(
                'item_prod_id'      => $_product->id,
                'item_name'         => $_product->get_title(),
                'item_img'          => preg_replace( '@^.+src="(.+)".+$@simU', '$1', $_product->get_image() ),
                'item_description'  => $_product->get_title(),
                'item_qty'          => $arrCartItem['quantity'],
                'item_price'        => $_product->get_price()
            );
        }
        $arrCart = array('merchant' => $dst_settings['merchant_id'],'description' => $dst_settings['description'],'currency'=>'USD','items' => $arrItems);
        
        if ((empty($dst_settings['show_on_cart']) || $dst_settings['show_on_cart'] == 'yes') && 0 < WC()->cart->total) { 
            self::getButtonCode($arrCart, $dst_settings['view_type']);
        }
    }
    
    static function getButtonCode($arrCart, $strType){
        if ($strType == 'block'){ ?>
        <div class="module pay-module taply-block" data-view-type="block" data-type="cart" data-cart='<?php echo htmlspecialchars(json_encode($arrCart), ENT_QUOTES, 'UTF-8'); ?>'>
                <h4>Have Apply Pay?</h4>
                <div class="field opt note"></div>
                <div class="field text">
                  <label class="scr-only" for="phone">Phone number</label>
                  <input class="input-text" id="phone" name="phone" type="tel">
                </div>
                <div class="field opt">
                  <input class="input-checkbox" id="save-phone-number-2" name="save-phone" type="checkbox">
                  <label for="save-phone-number-2">Save phone number</label>
                </div>
                <a class="taply-apply-pay taply-btn" href="#">Pay by taply using Apple Pay</a>
                <p>Use the <a href="/">mobile app</a> to pay</p>
            </div>
        <?php } else { ?>
            <div class="module pay-module taply-block" data-cart='<?php echo htmlspecialchars(json_encode($arrCart), ENT_QUOTES, 'UTF-8'); ?>'  data-type="cart" data-view-type="popup">
                <h4>Have Apply Pay?</h4>
                <a href="#" class="taply-apply-pay taply-btn">Pay by taply using Apple Pay</a>
                <p>Use the <a href="https://www.paybytaply.com/app/">mobile app</a> to pay</p>
            </div>
        <?php } 
    }
    
    static function get_button_locale_code() {
        $locale_code = defined("WPLANG") && get_locale() != '' ? get_locale() : 'en_US';
        switch ($locale_code) {
            case "de_DE": $locale_code = "de_DE/DE";
                break;
        }
        return $locale_code;
    }

    /**
     * Process a refund if supported
     * @param  int $order_id
     * @param  float $amount
     * @param  string $reason
     * @return  bool|wp_error True or false based on success, or a WP_Error object
     */
    public function process_refund($order_id, $amount = null, $reason = '') {
        
    }

    function top_cart_button() {
        if (!empty($this->settings['button_position']) && ($this->settings['button_position'] == 'top' || $this->settings['button_position'] == 'both')) {
            $this->woocommerce_taply_button();
        }
    }

}