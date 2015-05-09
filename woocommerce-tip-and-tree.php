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
			
		// Get customer country and zip
		if ( 'geolocation' === get_option( 'woocommerce_default_customer_address' ) ) {
			$customer_geolocated_ip = WC_Geolocation::geolocate_ip();
			$customer_country = $customer_geolocated_ip['country'];
		} else {
			$customer_country = $woocommerce->customer->get_shipping_country();
			$customer_zipcode = $woocommerce->customer->get_shipping_postcode();
		}
		
		// Get country name from country code
		foreach( WC()->countries->get_shipping_countries() as $key => $value ) {
			if( $key == $customer_country ) {
				$customer_country = $value;
			}
		}		
		
		// Get shop base location and city
		$shop_base_country = $woocommerce->countries->get_base_country();
		$shop_base_city    = $woocommerce->countries->get_base_country();

		$donate_or_not = WC()->session->get( 'donate_charity' );
		
		// Get cart weight
		$cart_weight = $woocommerce->cart->cart_contents_weight;
		
		// Get cart total
		$cart_total = $woocommerce->cart->cart_contents_total + $woocommerce->cart->shipping_total;
		
		// Get distance between shop base location and customer location
		$distance = $this->woocommerce_get_distance( $shop_base_country, $customer_country );
		
		$percentage = $this->woocommerce_get_fee_percentage( $distance, $cart_total, $cart_weight );

		//echo 'Distance ' . $distance . '<br />Percent ' . $percentage . '<br />Cart total: ' . $cart_total . '<br />Cart weight : ' . $cart_weight;

		// Adds donation fee % based on order weight, shipping distance
		if ( true == $donate_or_not ) {
			$surcharge = ( $woocommerce->cart->cart_contents_total + $woocommerce->cart->shipping_total ) * $percentage;
			$woocommerce->cart->add_fee( __('Donation', 'woocommerce-tip-and-tree' ), $surcharge, true, 'standard' );
			wc_add_notice( 
					 sprintf( _n( "Thank you, your donation will allow 1 tree to be planted.", "Thank you, your donation will allow %s trees to be planted.", ceil( $this->woocommerce_currency_in_trees( $surcharge ) ), "text_domain" ), ceil( $this->woocommerce_currency_in_trees( $surcharge ) ) )
			);
		}
	}
	
	/**
	 * Calculate fee percentage
	 */
	public function woocommerce_get_fee_percentage( $distance, $cart_total, $cart_weight ) {
	  
		// Define fee percentage
		$percentage =  0;
		
		// Adjust percentage fee based on distance
		// the higher $distance is, the higher $percentage is
		if( $distance >= 0 &&  $distance < 1000 ) {
		    $percentage += 0.01;
		} elseif( $distance >= 1000 &&  $distance < 5000 ) {
			$percentage += 0.015;
		} elseif( $distance >= 5000 &&  $distance < 20000 ) {
			$percentage += 0.02;
		} elseif( $distance >= 20000 &&  $distance < 100000 ) {
			$percentage += 0.025;
		} elseif( $distance >= 100000 &&  $distance < 500000 ) {
			$percentage += 0.03;
		} elseif( $distance >= 500000 &&  $distance < 1000000 ) {
			$percentage += 0.035;
		} elseif( $distance >= 1000000 &&  $distance < 2000000 ) {
			$percentage += 0.04;
		} elseif( $distance >= 2000000 &&  $distance < 5000000 ) {
			$percentage += 0.045;
		} else {
			$percentage += 0.05;
		}
		
		// Adjust percentage fee based on cart total
		// the higher $cart_total is, the lower $percentage is
		if( $cart_total >= 0 &&  $cart_total < 100 ) {
		    $percentage += 0.05;
		} elseif( $cart_total >= 100 &&  $cart_total < 500 ) {
			$percentage += 0.045;
		} elseif( $cart_total >= 500 &&  $cart_total < 2000 ) {
			$percentage += 0.04;
		} elseif( $cart_total >= 2000 &&  $cart_total < 10000 ) {
			$percentage += 0.035;
		} elseif( $cart_total >= 10000 &&  $cart_total < 50000 ) {
			$percentage += 0.03;
		} elseif( $cart_total >= 50000 &&  $cart_total < 100000 ) {
			$percentage += 0.025;
		} elseif( $cart_total >= 100000 &&  $cart_total < 200000 ) {
			$percentage += 0.02;
		} elseif( $cart_total >= 200000 &&  $cart_total < 500000 ) {
			$percentage += 0.015;
		} else {
			$percentage += 0.01;
		}
		
		// Adjust percentage fee based on cart weight
		// the higher $cart_weight is, the lower $percentage is
		if( $cart_weight >= 0 &&  $cart_weight < 10 ) {
		    $percentage += 0.01;
		} elseif( $cart_weight >= 10 &&  $cart_weight < 50 ) {
			$percentage += 0.015;
		} elseif( $cart_weight >= 50 &&  $cart_weight < 200 ) {
			$percentage += 0.02;
		} elseif( $cart_weight >= 200 &&  $cart_weight < 1000 ) {
			$percentage += 0.025;
		} elseif( $cart_weight >= 1000 &&  $cart_weight < 5000 ) {
			$percentage += 0.03;
		} elseif( $cart_weight >= 5000 &&  $cart_weight < 10000 ) {
			$percentage += 0.035;
		} elseif( $cart_weight >= 10000 &&  $cart_weight < 20000 ) {
			$percentage += 0.04;
		} elseif( $cart_weight >= 20000 &&  $cart_weight < 50000 ) {
			$percentage += 0.045;
		} else {
			$percentage += 0.05;
		}
		
		return $percentage;
	  
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
	
	/*
	 * Get distance between shop base location & customer location
	*/
	public function woocommerce_get_distance( $location_origin, $location_destination ) {
		
		// Get user Googe Matric API key
		$api_key = get_option( 'woocommerce_matrix_api_key' );
			
		// Get details from Google Distance Matric API
		$xml_url = "https://maps.googleapis.com/maps/api/distancematrix/json?origins=" . $location_origin . "&destinations=" . $location_destination . "&language=" . str_replace( '_', '-', get_locale() ) . "&key=" . $api_key;

		$matrix_output = json_decode( file_get_contents( $xml_url ), true );
		
		$distance =  $matrix_output['rows'][0]['elements'][0]['distance']['value'];
						
		return $distance;
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
							'label' => __('Donate for reforestation to balance the carbon impact of your order.', 'woocommerce-tip-and-tree' ),
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
			'title'    => __( 'Distance Matrix API key', 'woocommerce-tip-and-tree' ),
			'desc'     => __( 'Enter your API key here. Get you key <a href="https://console.developers.google.com/flows/enableapi?apiid=distance_matrix_backend&keyType=SERVER_SIDE">here</a>', 'woocommerce-tip-and-tree' ),
			'id'       => 'woocommerce_matrix_api_key',
			'type'     => 'text',
			'autoload' => false
		);

		$settings[] = array(
			'title'    => __( 'Carbon Impact Compensation', 'woocommerce-tip-and-tree' ),
			'desc'     => __( 'Enable the addition of fee for a charity for reforestation', 'woocommerce-tip-and-tree' ),
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
