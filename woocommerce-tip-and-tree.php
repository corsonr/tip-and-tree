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
     * Constructor
     */
    public function __construct() {
    	add_filter( 'woocommerce_get_settings_checkout', array( $this, 'checkout_for_charity_settings' ), 20, 1 );

    	if ( 'yes' == get_option( 'woocommerce_enable_charity' ) ) {
			add_action( 'woocommerce_cart_contents', array( $this, 'woocommerce_charity_toggle' ) );
			add_action( 'woocommerce_cart_calculate_fees', array( $this, 'woocommerce_custom_surcharge' ) );
			add_action( 'init', array( $this, 'woocommerce_donate_set_session' ) );
			add_action( 'wp_enqueue_scripts', array( $this, 'woocommerce_donate_load_js' ) );
			add_action( 'woocommerce_cart_emptied', array( $this, 'woocommerce_donate_clear_session' ) );
    	}
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

		if ( true == $donate_or_not ) {
			$percentage = 0.01;
			$surcharge = ( $woocommerce->cart->cart_contents_total + $woocommerce->cart->shipping_total ) * $percentage;
			$woocommerce->cart->add_fee( 'Donation', $surcharge, true, 'standard' );
		}
	}

	public function woocommerce_charity_toggle() {

		global $woocommerce;

		$donate_or_not = WC()->session->get( 'donate_charity' );

		?>
		<tr>
			<td colspan="6">
				<div class="tip-and-tree">
					<h3><?php _e( 'Compensate Carbon Impact' ); ?></h3>
					<?php
						woocommerce_form_field( 'donate_charity', array(
							'type' => 'checkbox',
							'class' => array('donate_charity'),
							'label' => __('Donate 1% for reforestation to balance the carbon impact of your order.'),
							'required' => false,
						), $donate_or_not );
					?>
				</div>
			</td>
		</tr>
		<?php
	}

	public function woocommerce_donate_set_session() {
		if ( ( ! empty( $_POST['apply_coupon'] ) || ! empty( $_POST['update_cart'] ) || ! empty( $_POST['proceed'] ) ) && isset( $_POST['_wpnonce'] ) && wp_verify_nonce( $_POST['_wpnonce'], 'woocommerce-cart' ) ) {
			if ( isset( $_POST['donate_charity'] ) ) {
				WC()->session->set( 'donate_charity', true );
			} else {
				WC()->session->set( 'donate_charity', false );
			}
		}
	}

	public function woocommerce_donate_load_js() {
        wp_enqueue_script(
            'wocommerce-tip-and-tree', // Give the script an ID
            untrailingslashit( plugins_url( basename( plugin_dir_path( __FILE__ ) ), basename( __FILE__ ) ) ) . '/assets/scripts.js', //Point to file
            array( 'jquery' ),
            '20150508',
            true
        );
	}

	public function woocommerce_donate_clear_session() {
		WC()->session->set( 'donate_charity', false );
	}


	public function checkout_for_charity_settings( $settings ) {

		$settings[] = array(
			'type' => 'title',
			'title' => __( 'Tip and Tree Options', 'woocommerce' ),
			'id' => 'woocommerce_tip_and_tree_options',
		);

		$settings[] = array(
			'title'    => __( 'Charity', 'woocommerce' ),
			'desc'     => __( 'Enable the addition of 1% of cart total to charity', 'woocommerce' ),
			'id'       => 'woocommerce_enable_charity',
			'default'  => 'no',
			'type'     => 'checkbox',
			'desc_tip' =>  __( 'Users will be presented with an option to add a donation to charity for reforestation', 'woocommerce' ),
			'autoload' => false
		);

		$settings[] =  array( 'type' => 'sectionend', 'id' => 'woocommerce_tip_and_tree_options');

		return $settings;

	}
}

$GLOBALS['WC_Tip_And_Tree'] = new WC_Tip_And_Tree();