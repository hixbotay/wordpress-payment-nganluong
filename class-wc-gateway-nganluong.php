<?php
/**
 * Plugin Name: Woocommerce Payment Gateway MOMO
 * Plugin URI: https://wordpress.org/plugins/woocommerce-payment-gateway-momo
 * Description: MOMO E-wallet from momo.vn for Woocommerce
 * Version: 1.0.0
 * Author: Duong
 * Author URI: http://woocommerce.com/
 * Developer: Duong
 * Developer URI: 
 * Text Domain: woocommerce-payment-gateway-momo
 * Domain Path: /languages
 *
 * Copyright: © 2009-2015 WooCommerce.
 * License: GNU General Public License v3.0
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
add_action( 'plugins_loaded', 'init_wc_payment_momo_gateway' );

function init_wc_payment_momo_gateway(){
	if(!class_exists('WC_Payment_Gateway')) return;
	
	class WC_Gateway_Momo extends WC_Payment_Gateway {
		
	
		public static $log_enabled = false;
		public static $log = false;
	
		public function __construct() {
			$this->id                 = 'wc_payment_momo';
			$this->has_fields         = true;//if need some option in checkout page
			$this->order_button_text  = __( 'Pay', 'woocommerce-payment-gateway-momo' );
			$this->method_title       = __( 'Pay with Momo', 'woocommerce-payment-gateway-momo' );
			$this->method_description = 'MOMO';
			$this->supports           = array(
				'products'
			);
	
			// Load the settings.
			$this->init_form_fields();
			$this->init_settings();
	
			// Define user set variables.
			$this->title          = $this->get_option( 'title' );
			$this->description    = $this->get_option( 'description' );
			
			$this->testmode       = 'yes' === $this->get_option( 'testmode', 'no' );			
			$this->debug          = 'yes' === $this->get_option( 'debug', 'no' );			
			$this->return_url = WC()->api_request_url( 'wc_payment_momo' );
			$this->thankyou_url = $this->get_option( 'thankyou_page' );

			$this->requestType = 'captureMoMoWallet';
			$this->endpoint_checkout = 'https://momo.vn/gw_payment/transactionProcessor';
			$this->partnerCode = $this->get_option( 'partnerCode' );
			$this->accessKey = $this->get_option( 'accessKey' );
			$this->serectkey = $this->get_option( 'serectkey' );
			
			if($this->testmode){
				$this->endpoint_checkout = 'https://test-payment.momo.vn/gw_payment/transactionProcessor';
				$this->partnerCode = 'MOMO0HGO20180417';
				$this->accessKey = 'E8HZuQRy2RsjVtZp';
				$this->serectkey = 'fj00YKnJhmYqahaFWUgkg75saNTzMrbO';
			}
	
			self::$log_enabled    = $this->debug;
			
				
			
			add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
			add_action( 'woocommerce_api_wc_payment_momo', array( $this, 'capture_payment' ) );
			add_action('woocommerce_receipt_public_bank', array($this, 'checkout_form'));
			$plugin = plugin_basename( __FILE__ );
			add_filter( "plugin_action_links_$plugin", array( $this, 'add_admin_section' ) );
	
		}

		function execPostRequest($url, $data)
		{
			if(is_array($data) || is_object($data)){
				$data = json_encode($data);
			}
			$ch = curl_init($url);
			curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
			curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($ch, CURLOPT_HTTPHEADER, array(
				'Content-Type: application/json',
				'Content-Length: ' . strlen($data))
			);
			curl_setopt($ch, CURLOPT_TIMEOUT, 5);
			curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);

			//execute post
			$result = curl_exec($ch);

			//close connection
			curl_close($ch);

			return $result;
		}
		
		
		
		public static function log( $error, $level = 'info') {
			if ( self::$log_enabled ) {
				date_default_timezone_set('Asia/Ho_Chi_Minh');
				$date = date('d/m/Y H:i:s');
				$error = $date.": ".$error."\n";
				
				$log_file = __DIR__."/log.txt";
				if(!file_exists($log_file) || filesize($log_file) > 1048576){
					$fh = fopen($log_file, 'w');
				}
				else{
					//echo "Append log to log file ".$log_file;
					$fh = fopen($log_file, 'a');
				}
				
				fwrite($fh, $error);
				fclose($fh);
			}
		}
	
		public function get_icon() {
			$icon_html = '';
			return apply_filters( 'woocommerce_gateway_icon', $icon_html, $this->id );
		}
	
		
		public function is_valid_for_use() {			
			return in_array( get_woocommerce_currency(), apply_filters( 'woocommerce_momo_supported_currencies', array( 'VND' ) ) );
		}
		
		public function admin_options() {
			if ( $this->is_valid_for_use() ) {
				parent::admin_options();
			} else {
				?>
				<div class="inline error"><p><strong><?php _e( 'Gateway Disabled', 'woocommerce' ); ?></strong>: <?php _e( 'Momo does not support your store currency.', 'woocommerce' ); ?></p></div>
				<?php
			}
		}
		
		//form in checkout page
		public function payment_fields(){
			return '';
		}
		
		//check form is valid
		public function validate_fields(){
			return true;
		}
	
		/**
		 * Initialise Gateway Settings Form Fields.
		 */
		public function init_form_fields() {
			$this->form_fields = array(
				'enabled' => array(
					'title' => __( 'Enable/Disable', 'woocommerce-payment-gateway-momo' ),
					'type' => 'checkbox',
					'label' => __( 'Enable Momo Payment', 'woocommerce-payment-gateway-momo' ),
					'default' => 'yes'
				),
				'title' => array(
					'title' => __( 'Title', 'woocommerce-payment-gateway-momo' ),
					'type' => 'text',
					'description' => __( 'This controls the title which the user sees during checkout.', 'woocommerce-payment-gateway-momo' ),
					'default' => __( 'Public Bank', 'woocommerce-payment-gateway-momo' ),
					'desc_tip'      => true,
				),
				'description' => array(
					'title' => __( 'Customer Message', 'woocommerce-payment-gateway-momo' ),
					'type' => 'textarea',
					'default' => ''
				),
				'thankyou_page' => array(
						'title' => __('Thank you page','woocommerce'),
						'type' => 'select',
						'options' => $this -> get_pages('Choose...'),
						'description' =>__("Chooseing page/url to redirect after Payment Success.",'woocommerce-payment-gateway-momo')
				),
				'partnerCode' => array(
					'title' => __( 'Partner Code', 'woocommerce-payment-gateway-momo' ),
					'type' => 'text',
					'description' => __( 'Partner Code', 'woocommerce-payment-gateway-momo' ),
					'default' => ''
				),
				'accessKey' => array(
						'title' => __( 'Access Key', 'woocommerce-payment-gateway-momo' ),
						'type' => 'text',
						'description' => __( 'Access Key', 'woocommerce-payment-gateway-momo' ),
						'default' => ''
				),
				'serectkey' => array(
					'title' => __( 'Secret Key', 'woocommerce-payment-gateway-momo' ),
					'type' => 'text',
					'description' => __( 'Secret Key', 'woocommerce-payment-gateway-momo' ),
					'default' => ''
				),
				'testmode' => array(
						'title' => __( 'Testmode', 'woocommerce-payment-gateway-momo' ),
						'type' => 'checkbox',
						'description' => __( 'Enable test mode', 'woocommerce-payment-gateway-momo' ),
						'default' => 'no',
				),
				'debug' => array(
						'title' => __( 'Debug', 'woocommerce-payment-gateway-momo' ),
						'type' => 'checkbox',
						'description' => sprintf( __( 'Log Momo events, inside %s', 'woocommerce-payment-gateway-momo' ), '<code>'.__DIR__.'/log.txt</code>' ),
						'default' => 'no',
				),
				'Donate' => array(
						'title' => __( 'Donate', 'woocommerce-payment-gateway-momo' ),
						'type' => 'note',
						'description' => "If this plugin help you please donate me ",
						'default' => 'no',
				),
			);
		}
	
	
		/**
		 * Process the payment and return the result.
		 * @param  int $order_id
		 * @return array
		 */
		public function process_payment( $order_id ) {
			
			 global $woocommerce;
			$order = new WC_Order( $order_id );			
			
			$params = array(
					'currency_code' => get_woocommerce_currency(),
					'return' => $this->return_url,
					'merID' => $merchant_id,
					'invoiceNo' => sprintf('%020d',$order->get_id()),
					'amount' => $this->number_format($order->get_total(),$order),
					'postURL' => str_replace('http://','https://',$this->return_url),
					'securityMethod' => 'SHA1',
					'securityKeyReq' => $securityKeyReq
			);
			$data =  array('partnerCode' => $this->partnerCode,
                  'accessKey' => $this->accessKey,
                  'requestId' => $this->requestId,
                  'amount' => $this->number_format($order->get_total(),$order),
                  'orderId' => $order->get_id(),
                  'orderInfo' => sprintf('%020d',$order->get_id()),
                  'returnUrl' => $this->return_url,
                  'notifyUrl' => $this->notify_url,
                  'extraData' => $extraData,
			);
			$data['signature'] = hash_hmac("sha256", http_build_query($data), $this->serectkey);
			$data['requestType'] = $this->requestType;
			$result = $this->execPostRequest($this->endpoint, $data);
			
			return array(
	          'result'  =>$result['status']  == '' ? 'success' : 'failed',
	          'redirect'  => $result['']
	        );
		}
		
		function checkout_form($order_id){
			$merchant = $_GET['wc_pb_merchant'];
			if($merchant =='visa'){
				$merchant_id = $this->merid_visa;
			}else{
				$merchant_id = $this->merid_master;
			}
			
			global $woocommerce;
			$order = new WC_Order( $order_id );
			
			$has_key = sprintf('%020d',$order->get_id()).$this->number_format($order->get_total(),$order).$this->secretCode.$merchant_id;
			//$has_key = 'PUBLICBANKBERHAD0001000000888800PBBSECRET3300000888';
			$securityKeyReq = base64_encode(sha1($has_key,true));
			
			
			$params = array(
					'currency_code' => get_woocommerce_currency(),
					'return' => $this->return_url,
					'merID' => $merchant_id,
					'invoiceNo' => sprintf('%020d',$order->get_id()),
					'amount' => $this->number_format($order->get_total(),$order),
					'postURL' => str_replace('http://','https://',$this->return_url),
					'securityMethod' => 'SHA1',
					'securityKeyReq' => $securityKeyReq
			);

// 			$this->debug($has_key);
// 			$this->debug($params);die;
			
			
			// Mark as on-hold (we're awaiting the cheque)
// 			$order->update_status('on-hold', __( 'Awaiting Public Bank payment', 'woocommerce' ));
			if(0){
				echo $has_key.'<br>'.$securityKeyReq;
				echo '<form action="'.$this->return_url.'" method="POST" name="jb_payment_form" id="jb_payment_form">';
				echo "return url: {$this->return_url}<br>";
				echo '<input name="result" value="00T54321PUBLICBANKBERHAD00018888121800000088880005" type="text" />';
				echo '<input name="securityKeyRes" value="ih2K8LiVguw50GEV2gjXfKOGAlk=" type="text" />';
				echo '<center><button type="submit">'.__('continue', 'woocommerce').'</button></center>';
				echo '</form>';
			}else{
				$woocommerce->cart->empty_cart();
				echo '<form action="'.$this->endpoint_checkout.'" method="POST" name="jb_payment_form" id="jb_payment_form">';
				foreach($params as $key=>$val){
					echo '<input name="'.$key.'" value="'.$val.'" type="hidden" />';
				}
				echo '<center><button type="submit">'.__('continue', 'woocommerce').'</button></center>';
				echo '</form>';
				
				echo '<script>document.jb_payment_form.submit();</script>';
			}
			
			return;
		}
		
		//process in return
		public function capture_payment() {
			$result = $_POST['result'];
			$status = substr($result,0,2);
			$tx_id = substr($result,2,6);
			$order_id = substr($result,8,20);
			$card_number = substr($result,28,4);
			$exp = substr($result,32,4);
			$amount = substr($result,36,12);
			
			$order = wc_get_order( (int)$order_id);
			//$this->debug($_POST);
			//$this->debug($status);
			$this->log( 'Capture Result: ' . wc_print_r( $result, true ) );
			$this->log( 'Response: ' . json_encode($_REQUEST) );
			
			if($status == '00' && $order){
				$hash_key = $tx_id.$status.$order_id.$this->secretCode.$card_number.$exp.$amount;
				$securityKeyRes = base64_encode(sha1($hash_key,true));
				//$this->debug($hash_key);				
				//$this->debug($securityKeyRes);
				
				if($_POST['securityKeyRes'] == $securityKeyRes){
					$order->add_order_note( sprintf( __( 'Payment of %1$s was captured - Credit card: %2$s, Transaction ID: %3$s', 'woocommerce' ), $this->id, $card_number, $tx_id ) );
					$order->payment_complete( $tx_id );
					$order->reduce_order_stock();
					update_post_meta( $order->get_id(), '_transaction_id', $tx_id );	
					$thankyou_page = $this->thankyou_page ? get_permalink($this->thankyou_page) : esc_url_raw( add_query_arg( 'utm_nooverride', '1', $this->get_return_url( $order ) )); 
					wp_redirect($thankyou_page);
					exit;
				}else{
					$order->add_order_note( "Authentication failed. code: {$status} securityKeyRes {$_POST['securityKeyRes']} result{$result}" );
				}
			}else{
				$order->add_order_note( "Payment failed. {$result}" );
			}
			wc_add_notice(__('Payment failed','woocommerce'));
			wp_redirect(wc_get_page_permalink( 'cart' ));
			exit;
			
		}
		
		
	
		/**
		 * Can the order be refunded via Public Bank?
		 * @param  WC_Order $order
		 * @return bool
		 */
		public function can_refund_order( $order ) {
			return false;
		}
	
		/**
		 * Process a refund if supported.
		 * @param  int    $order_id
		 * @param  float  $amount
		 * @param  string $reason
		 * @return bool True or false based on success, or a WP_Error object
		 */
		public function process_refund( $order_id, $amount = null, $reason = '' ) {
			$order = wc_get_order( $order_id );
	
			if ( ! $this->can_refund_order( $order ) ) {
				return new WP_Error( 'error', 'Momo chưa chỗ trợ refund, vui lòng trả tiền bằng tay' );
			}
			// $order->add_order_note( sprintf( __( 'Refunded %s - Refund ID: %s', 'woocommerce' ), $result['GROSSREFUNDAMT'], $result['REFUNDTRANSACTIONID'] ) );
			
		}
		
		protected function number_format( $price, $order ) {
			$decimals = 2;
			return sprintf('%012d',(number_format( $price, $decimals, '.', '' )*100));
		}
		
		function get_pages($title = false, $indent = true) {
			$wp_pages = get_pages('sort_column=menu_order');
			$page_list = array();
			if ($title) $page_list[] = $title;
			foreach ($wp_pages as $page) {
				$prefix = '';
				// show indented child pages?
				if ($indent) {
					$has_parent = $page->post_parent;
					while($has_parent) {
						$prefix .=  ' - ';
						$next_page = get_page($has_parent);
						$has_parent = $next_page->post_parent;
					}
				}
				// add to page list array array
				$page_list[$page->ID] = $prefix . $page->post_title;
			}
			return $page_list;
		}
		
		function debug($value){
			echo '<pre>';
			print_r($value);
			echo '</pre>';
		}

		function add_admin_section($links ){
			$plugin_links = array();
	
			if ( version_compare( WC()->version, '2.6', '>=' ) ) {
				$section_slug = strtolower(substr(__CLASS__,0,12));
			} else {
				$section_slug = strtolower( __CLASS__ );
			}
			$setting_url = admin_url( 'admin.php?page=wc-settings&tab=checkout&section=' . $section_slug );
			$plugin_links[] = '<a href="' . esc_url( $setting_url ) . '">' . esc_html__( 'MOMO', 'woocommerce-payment-momo' ) . '</a>';
			
			return array_merge( $plugin_links, $links );
		}
	}
	
	
}
function woocommerce_add_payment_gateway_momo($methods) {
	$methods[] = 'WC_Gateway_Momo';
	return $methods;
}

add_filter('woocommerce_payment_gateways', 'woocommerce_add_payment_gateway_momo' );

