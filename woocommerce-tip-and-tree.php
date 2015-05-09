<?php

/**
 * Plugin Name: WooCommerce Tip and Tree
 * Plugin URI: http://woothemes.com
 * Description: Plant trees to compensate order carbon impact
 * Author: Remi, Cedric
 * Author URI: http://www.remicorson.com/
 * Version: 1.0
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
 */

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * WC_Tip_And_Tree class
 */
class WC_Tip_And_Tree {
	
	/**
	 * Plugin version.
	 *
	 * @var string
	 */
	const PLUGIN_VERSION = '1.0';

    /**
     * Constructor
     */
    public function __construct() {
	    
	    // Load plugin text domain
		add_action( 'init', array( $this, 'load_plugin_textdomain' ) );
		
    	add_filter( 'woocommerce_get_settings_checkout', array( $this, 'checkout_for_charity_settings' ), 20, 1 );

    	if ( 'yes' == get_option( 'woocommerce_enable_charity' ) ) {
			add_action( 'woocommerce_cart_contents', array( $this, 'woocommerce_charity_toggle' ) );
			add_action( 'woocommerce_cart_calculate_fees', array( $this, 'woocommerce_custom_surcharge' ) );
			add_action( 'init', array( $this, 'woocommerce_donate_set_session' ) );
			add_action( 'wp_enqueue_scripts', array( $this, 'woocommerce_donate_load_js' ) );
			add_action( 'woocommerce_cart_emptied', array( $this, 'woocommerce_donate_clear_session' ) );
			add_action( 'init', array( $this, 'woocommerce_adaptive_payment_cleaning' ) );
			add_action( 'admin_enqueue_scripts', array( $this, 'woocommerce_load_admin_scripts' ) );
    	}
    }
    
	/**
	 * Load the plugin text domain for translation.
	 *
	 * @return void
	 */
	public function load_plugin_textdomain() {
		$locale = apply_filters( 'plugin_locale', get_locale(), 'woocommerce-tip-and-tree' );

		load_textdomain( 'woocommerce-tip-and-tree', trailingslashit( WP_LANG_DIR ) . 'tip-and-tree/woocommerce-tip-and-tree-' . $locale . '.mo' );
		load_plugin_textdomain( 'woocommerce-tip-and-tree', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
	}

	/**
	 * Add a x% surcharge to your cart / checkout
	 * change the $percentage to set the surcharge to a value to suit
	 * Uses the WooCommerce fees API
	 */
	public function woocommerce_custom_surcharge() {
	  global $woocommerce;

		if ( is_admin() && ! defined( 'DOING_AJAX' ) )
			return;

		$donate_or_not = WC()->session->get( 'donate_charity' );

		// Adds donation fee % based on order weight, shipping distance
		if ( true == $donate_or_not ) {
			$percentage = 0.01;
			$surcharge = ( $woocommerce->cart->cart_contents_total + $woocommerce->cart->shipping_total ) * $percentage;
			$woocommerce->cart->add_fee( __('Donation', 'woocommerce-tip-and-tree' ), $surcharge, true, 'standard' );
		}
	}
	
	/**
	 * Remove PayPal Adaptive Payment un-necessary options
	 */
	public function woocommerce_adaptive_payment_cleaning() {
	  
	  // Remove Product Tab
	  add_filter( 'woocommerce_product_data_tabs', array( $this, 'woocommerce_remove_adaptive_payment_product_tab' ) );
	  
	}
	
	/**
	 * Delete PayPal Adaptive Payment prodct tab
	 */
	public function woocommerce_remove_adaptive_payment_product_tab( $tabs ) {
	   
	    unset( $tabs['paypal-adaptive-payments'] );
	    
	    return( $tabs );
	}
	
	/**
	 * Convert Currency in Trees
	 */
	public function woocommerce_currency_in_trees( $amount ) {
		
		if( $amount ) {
			$tree_cost = apply_filters( 'tree_cost', 2.5 );
			$trees_equivalence = ( $amount / $tree_cost );
		
			return( $trees_equivalence );
		} else {
			return;
		}
	}
	
	/**
	 * Load admin scripts
	 */
	public function woocommerce_load_admin_scripts() {
		
		wp_register_style( 
			'woocommerce-tip-and-tree-css', 
			untrailingslashit( plugins_url( basename( plugin_dir_path( __FILE__ ) ), basename( __FILE__ ) ) ) . '/assets/admin/style.css',
			false, 
			self::PLUGIN_VERSION 
		);
        wp_enqueue_style( 'woocommerce-tip-and-tree-css' );
        
	}
	
	/**
	 * Display Charity Fee on Cart Page
	 */
	public function woocommerce_charity_toggle() {

		global $woocommerce;

		$donate_or_not = WC()->session->get( 'donate_charity' );

		?>
		<tr>
			<td colspan="6">
				<div class="tip-and-tree">
					<h3><?php _e( 'Compensate Carbon Impact', 'woocommerce-tip-and-tree' ); ?></h3>
					<?php
						woocommerce_form_field( 'donate_charity', array(
							'type' => 'checkbox',
							'class' => array('donate_charity'),
							'label' => sprintf( __('Donate for reforestation to balance the carbon impact of your order. This will allow %s trees to be planted.', 'woocommerce-tip-and-tree' ), number_format( $this->woocommerce_currency_in_trees( WC()->cart->total ) ) ),
							'required' => false,
						), $donate_or_not );
					?>
				</div>
			</td>
		</tr>
		<?php
	}

	/**
	 * Set WC Session
	 */
	public function woocommerce_donate_set_session() {
		if ( ( ! empty( $_POST['apply_coupon'] ) || ! empty( $_POST['update_cart'] ) || ! empty( $_POST['proceed'] ) ) && isset( $_POST['_wpnonce'] ) && wp_verify_nonce( $_POST['_wpnonce'], 'woocommerce-cart' ) ) {
			if ( isset( $_POST['donate_charity'] ) ) {
				WC()->session->set( 'donate_charity', true );
			} else {
				WC()->session->set( 'donate_charity', false );
			}
		}
	}

	/**
	 * Load frontend scripts
	 */
	public function woocommerce_donate_load_js() {
        wp_enqueue_script(
            'woocommerce-tip-and-tree', // Give the script an ID
            untrailingslashit( plugins_url( basename( plugin_dir_path( __FILE__ ) ), basename( __FILE__ ) ) ) . '/assets/tip_and_tree.js', //Point to file
            array( 'jquery' ),
            self::PLUGIN_VERSION,
            true
        );
	}

	/**
	 * Clear WC session
	 */
	public function woocommerce_donate_clear_session() {
		WC()->session->set( 'donate_charity', false );
	}

	/**
	 * Register Tip on Tree Settings under checkout admin tab
	 */
	public function checkout_for_charity_settings( $settings ) {

		$settings[] = array(
			'type' => 'title',
			'title' => __( 'Tip and Tree Options', 'woocommerce', 'woocommerce-tip-and-tree' ),
			'id' => 'woocommerce_tip_and_tree_options',
		);

		$settings[] = array(
			'title'    => __( 'Carbon Impact Compensation', 'woocommerce-tip-and-tree' ),
			'desc'     => __( 'Enable the addition of 1% of cart total to charity for reforestation', 'woocommerce-tip-and-tree' ),
			'id'       => 'woocommerce_enable_charity',
			'default'  => 'no',
			'type'     => 'checkbox',
			'desc_tip' =>  __( 'Users will be presented with an option to add a donation to charity for reforestation', 'woocommerce-tip-and-tree' ),
			'autoload' => false
		);
		
		$settings[] = array(
			'title'    => __( 'Charity to give to', 'woocommerce-tip-and-tree' ),
			'desc'     => __( 'Choose the charity your customer will give to', 'woocommerce-tip-and-tree' ),
			'id'       => 'woocommerce_charity_name',
			'type'     => 'select',
			'desc_tip' =>  __( 'Your customers will give to the charity you choose here', 'woocommerce-tip-and-tree' ),
			'options'  => apply_filters( 'charities_list', array(
							'tipandtree@greenpeace.com' => 'GreenPeace',
							'tipandtree@eden.com'       => 'Eden Reforestation',
							)
						)
		);

		$settings[] =  array( 'type' => 'sectionend', 'id' => 'woocommerce_tip_and_tree_options');

		return $settings;

	}
}

$GLOBALS['WC_Tip_And_Tree'] = new WC_Tip_And_Tree();