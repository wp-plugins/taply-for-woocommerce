<?php
/**
 * @wordpress-plugin
 * Plugin Name:       Taply for WooCommerce
 * Plugin URI:        http://www.paybytaply.com/plugin
 * Description:       Easily enable e-commerce websites to accept Apple Pay. It’s easy, quick and secure for both your business and your customers. 
 * Version:           0.1.0
 * Author:            Taply
 * Author URI:        http://www.paybytaply.com/
 * License:           GNU General Public License v3.0
 * License URI:       http://www.gnu.org/licenses/gpl-3.0.html
 * Domain Path:       /i18n/languages/
 * GitHub Plugin URI: 
 *
 *************
 * Attribution
 *************
 * Taply for WooCommerce is a derivative work of the code from WooThemes / SkyVerge,
 * which is licensed with GPLv3.  This code is also licensed under the terms
 * of the GNU Public License, version 3.
 */

/**
 * Exit if accessed directly.
 */
if (!defined('ABSPATH'))
{
    exit();
}

/**
 * Set global parameters
 */
global $woocommerce, $dst_settings, $wp_version;

/**
 * Get Settings
 */
$dst_settings = get_option( 'woocommerce_taply_settings' );

if(!class_exists('DS_Taply')){
    class DS_Taply
    {
        /**
         * General class constructor where we'll setup our actions, hooks, and shortcodes.
         *
         */
        const VERSION_PFW = '0.1.0';
        
        public function __construct()
        {
            $woo_version = $this->getWooCommerceVersion();
            if(version_compare($woo_version,'2.1','<'))
            {
                exit( __('Taply for WooCommerce requires WooCommerce version 2.1 or higher.  Please update WooCommerce, and try again.','taply-for-woocommerce'));
            }
            
            add_action( 'plugins_loaded', array($this, 'init'));
            register_activation_hook( __FILE__, array($this, 'activateTaplyForWoocommerce' ));
            register_deactivation_hook( __FILE__,array($this,'deactivateTaplyForWoocommerce' ));
            add_action( 'wp_enqueue_scripts', array($this, 'woocommerceTaplyInitStyles'), 12 );

            $basename = plugin_basename(__FILE__);
            $prefix = is_network_admin() ? 'network_admin_' : '';
            add_filter("{$prefix}plugin_action_links_$basename",array($this,'pluginActionLinks'),10,4);

        }

        /**
         * Get WooCommerce Version Number
         * http://wpbackoffice.com/get-current-woocommerce-version-number/
         */
        function getWooCommerceVersion()
        {
            // If get_plugins() isn't available, require it
            if ( ! function_exists( 'get_plugins' ) )
            {
                require_once( ABSPATH . 'wp-admin/includes/plugin.php' );
            }

            // Create the plugins folder and file variables
            $plugin_folder = get_plugins( '/' . 'woocommerce' );
            $plugin_file = 'woocommerce.php';

            // If the plugin version number is set, return it
            if ( isset( $plugin_folder[$plugin_file]['Version'] ) )
            {
                return $plugin_folder[$plugin_file]['Version'];
            }
            else
            {
                // Otherwise return null
                return NULL;
            }
        }

        /**
         * Return the plugin action links.  This will only be called if the plugin
         * is active.
         *
         * @since 1.0.6
         * @param array $actions associative array of action names to anchor tags
         * @return array associative array of plugin action links
         */
        public function pluginActionLinks($actions, $plugin_file, $plugin_data, $context)
        {
            $custom_actions = array(
                'configure' => sprintf( '<a href="%s">%s</a>', admin_url( 'admin.php?page=wc-settings&tab=checkout&section=wc_gateway_taply' ), __( 'Configure', 'taply-for-woocommerce' ) ),
                'docs'      => sprintf( '<a href="%s" target="_blank">%s</a>', 'http://www.angelleye.com/category/docs/taply-for-woocommerce/?utm_source=taply_for_woocommerce&utm_medium=docs_link&utm_campaign=taply_for_woocommerce', __( 'Docs', 'taply-for-woocommerce' ) ),
                'support'   => sprintf( '<a href="%s" target="_blank">%s</a>', 'http://wordpress.org/support/plugin/taply-for-woocommerce/', __( 'Support', 'taply-for-woocommerce' ) ),
                'review'    => sprintf( '<a href="%s" target="_blank">%s</a>', 'http://wordpress.org/support/view/plugin-reviews/taply-for-woocommerce', __( 'Write a Review', 'taply-for-woocommerce' ) ),
            );

            // add the links to the front of the actions list
            return array_merge( $custom_actions, $actions );
        }

        //init function
        function init(){
            global $dst_settings;
            
            if (!class_exists("WC_Payment_Gateway")) return;
            
            load_plugin_textdomain('taply-for-woocommerce', false, dirname(plugin_basename(__FILE__)). '/i18n/languages/');
            
            add_filter( 'woocommerce_payment_gateways', array($this, 'addTaplyGateway'),1000 );
            if( isset($dst_settings['button_position']) && ($dst_settings['button_position'] == 'top' || $dst_settings['button_position'] == 'both')){
                add_action( 'woocommerce_before_cart', array( 'WC_Gateway_Taply', 'woocommerceTaplyButton'), 12 );
            }
            if( isset($dst_settings['button_position']) && ($dst_settings['button_position'] == 'bottom' || $dst_settings['button_position'] == 'both')){
                add_action( 'woocommerce_proceed_to_checkout', array( 'WC_Gateway_Taply', 'woocommerceTaplyButton'), 9999 );
            }
            
            add_action( 'woocommerce_after_add_to_cart_button', array('WC_Gateway_Taply', 'buyNowButton'));


            require_once('classes/wc-gateway-taply.php');
            require_once('classes/taply-success.php');
            require_once('classes/taply-success-redirect.php');
        }
        
        function addRoutes( ) {

            add_rewrite_rule(
                '.*/taply.*',
                'index.php?page_id=5',
                'top' 
            );


        }

        /**
         * woocommerceTaplyInitStyles function.
         *
         * @access public
         * @return void
         */
        function woocommerceTaplyInitStyles() {
            global $dst_settings;
            wp_register_script( 'taply_button', 'http://www.paybytaply.com/js/taply.1.1.js' , array( 'jquery' ), WC_VERSION, true );
            if ( ! is_admin()){
                wp_enqueue_style( 'taply_css', plugins_url( 'assets/css/taply-dialog.css' , __FILE__ ) );
                wp_enqueue_script('taply_button');
            }

        }

        /**
         * Run when plugin is activated
         */
        function activateTaplyForWoocommerce()
        {
            // If WooCommerce is not enabled, deactivate plugin.
            if(!in_array( 'woocommerce/woocommerce.php',apply_filters('active_plugins',get_option('active_plugins'))) && !is_plugin_active_for_network( 'woocommerce/woocommerce.php' ))
            {
                deactivate_plugins(plugin_basename(__FILE__));
            }
        }

        /**
         * Run when plugin is deactivated.
         */
        function deactivateTaplyForWoocommerce()
        {
        }

        /**
         * Adds Taply gateway options for Payments into the WooCommerce checkout settings.
         *
         */
        function addTaplyGateway( $methods ) {
            if ( is_admin() ){
                $methods[] = 'WC_Gateway_Taply';
            } 
            return $methods;
        }

    }
}

new DS_Taply();