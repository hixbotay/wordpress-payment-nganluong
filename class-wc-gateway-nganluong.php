<?php
/**
 * Plugin Name: Ngan Luong
 * Plugin URI: https://www.nganluong.vn/nganluong/homeDeveloper/DeveloperWordPress.html
 * Description: Full integration for Ngan Luong payment gateway for WooCommerce
 * Version: 1.2.2
 * Author: nguyencamhue
 * Author URI: https://www.nganluong.vn
 * License: 
 */

add_action('plugins_loaded', 'woocommerce_NganLuongVN_init', 0);

function woocommerce_NganLuongVN_init(){
  if(!class_exists('WC_Payment_Gateway')) return;

  class WC_NganLuongVN extends WC_Payment_Gateway{

    // URL checkout của nganluong.vn - Checkout URL for Ngan Luong
    private $nganluong_url;

    // Mã merchant site code
    private $merchant_site_code;

    // Mật khẩu bảo mật - Secure password
    private $secure_pass;

    // Debug parameters
    private $debug_params;
    private $debug_md5;

    function __construct(){
      
      //$this->icon = 'https://www.nganluong.vn/css/newhome/img/logos/logo-nganluong.png'; // Icon URL
      $this->id = 'nganluong';
      $this->method_title = 'Thanh toán qua Internet banking';
      $this->has_fields = false;

      $this->init_form_fields();
      $this->init_settings();

      $this->title = $this->get_option('title');
      $this->description = $this->get_option('description');

      $this->nganluong_url = 'https://www.nganluong.vn/checkout.php';
      $this->merchant_site_code = $this->get_option('merchant_site_code');
      $this->merchant_id = $this->get_option('merchant_id');
      $this->secure_pass = $this->get_option('secure_pass');
      $this->redirect_page_id = $this->get_option('redirect_page_id');
	  $this->cur_code = $this->get_option('nlcurrency');
      $this->debug = $this->get_option('debug');
      $this->order_button_text = __( 'Thanh toán Ngân Lượng', 'woocommerce' );
	  $this->return_url = WC()->api_request_url( 'wc_nganluong' );
      $this->msg['message'] = "";
      $this->msg['class'] = "";

      if ( version_compare( WOOCOMMERCE_VERSION, '2.0.8', '>=' ) ) {
                add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( &$this, 'process_admin_options' ) );
             } else {
                add_action( 'woocommerce_update_options_payment_gateways', array( &$this, 'process_admin_options' ) );
            } 
   
    add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
	add_action( 'woocommerce_api_wc_nganluong', array( $this, 'capture_payment' ) );
   }
    function init_form_fields(){
        // Admin fields
       $this->form_fields = array(
                'enabled' => array(
                    'title' => __('Activate', 'woocommerce'),
                    'type' => 'checkbox',
                    'label' => __('Activate the payment gateway for Ngan Luong', 'woocommerce'),
                    'default' => 'no'),
                'title' => array(
                    'title' => __('Name:', 'woocommerce'),
                    'type'=> 'text',
                    'description' => __('Name of payment method (as the customer sees it)', 'woocommerce'),
                    'default' => __('Thanh toán qua Internet banking', 'woocommerce')),
                'description' => array(
                    'title' => __('', 'woocommerce'),
                    'type' => 'textarea',
                    'description' => __('Payment gateway description', 'woocommerce'),
                    'default' => __('Click place order and you will be directed to the Ngan Luong website in order to make payment', 'woocommerce')),
                'merchant_id' => array(
                    'title' => __('NganLuong.vn email address', 'woocommerce'),
                    'type' => 'text',
                    'description' => __('Enter the Ngan Luong account email address')),
                'redirect_page_id' => array(
                    'title' => __('Return URL'),
                    'type' => 'select',
                    'options' => $this->get_pages('Hãy chọn...'),
                    'description' => __('Please choose the URL to return to after checking out at NganLuong.vn. Mặc định chọn trang chi tiết giao dịch', 'woocommerce')
                ),
                'nlcurrency' => array(
                    'title' => __('Currency', 'woocommerce'),
                   'type' => 'text',
                   'default' => 'vnd',
                    'description' => __('"vnd" or "usd"', 'woocommerce')
                ),
               'merchant_site_code' => array(
                  'title' => __( 'Merchant Site Code', 'woocommerce'),
                  'type' => 'text'
                ),
                'secure_pass' => array(
                  'title' => __( 'Secure Password', 'woocommerce'),
                  'type' => 'password'
                ),
                'debug' => array(
                    'title' => __('Debug', 'woocommerce'),
                    'type' => 'checkbox',
                    'label' => __('Debug Ngan Luong plugin', 'woocommerce'),
                    'default' => 'no')
            );
    }

    public function admin_options(){
      echo '<h3>'.__('NganLuongVN Payment Gateway', 'woocommerce').'</h3>';
      echo '<table class="form-table">';
      // Generate the HTML For the settings form.
      $this->generate_settings_html();
      echo '</table>';
    }

    /**
     *  There are no payment fields for NganLuongVN, but we want to show the description if set.
     **/
    function payment_fields(){
        if($this->description) echo wpautop(wptexturize(__($this->description, 'woocommerce')));
    }

    /**
     * Process the payment and return the result
     **/
    function process_payment( $order_id ) {
		global $woocommerce;
		$order = new WC_Order( $order_id );

     // $order_items = $order->get_items();
	  $order_items = $order->get_items();

      $return_url = $this->return_url;
	  //$return_url = 'nganluong.vn';
      $receiver = $this->merchant_id;
      $transaction_info = ''; 
      
      $order_description = $order_id;

      $order_quantity = $order->get_item_count();
      //$discount = $order->get_total_discount();
	  $currency = $this->cur_code;
	  $discount = 0;
      $tax = $order->get_cart_tax();
	  //$tax = 0;
      $fee_shipping = $order->get_total_shipping();
	  

     /*  $product_names = '';
      foreach ($order_items as $order_item) {
        $product_names[] = $order_item['name'];
      }
      $order_description = implode(', ', $product_names); */ // this goes into transaction info, which shows up on Ngan Luong as the description of goods

      $price = $order->get_total() - ($tax + $fee_shipping);
	  $buyer_info = $order->billing_first_name." ".$order->billing_last_name.'*|*'. $order->billing_email.'*|*'.$order->billing_phone.'*|*'.$order->billing_address_1.", ".$order->billing_city;
      $checkouturl = $this->buildCheckoutUrlExpand($return_url, $receiver, $transaction_info, $order_id, $price, $currency, $quantity = 1, $tax, $discount, $fee_cal = 0, $fee_shipping, $order_description, $buyer_info);
		
      return array(
            'result'    => 'success',
            'redirect'  => $checkouturl
          );      
    }

    function capture_payment() {
		// Return to site after checking out with Ngan Luong
		// Note this has not been fully-tested
		global $woocommerce;
		$order_id = $_REQUEST['order_code'];
		$order = new WC_Order( $order_id );
		
		// This probably could be written better
		$tx_id = $_GET['payment_id'];
		$transaction_info = ''; // urlencode("Order#".$order_id." | ".$_SERVER['SERVER_NAME']);
		$order_code = $order_id;
		$price =$_GET['price'];
		$payment_id = $_GET['payment_id'];
		$payment_type = $_GET['payment_type'];
		$error_text = $_GET['error_text'];
		$secure_code = $_GET['secure_code'];
		$this->write_log('nganluong.txt',json_encode($_REQUEST));
		// This is from the class provided by Ngan Luong
		// All these parameters should match the ones provided above before checkout
		if ( $this->verifyPaymentUrl($transaction_info, $order_code, $price, $payment_id, $payment_type, $error_text, $secure_code) ) {      
			  //$new_order_status = 'completed';
			  //$order->update_status($new_order_status );
			  // Remove cart
			  $woocommerce->cart->empty_cart();
			  // Empty awaiting payment session
			  unset($_SESSION['order_awaiting_payment']);
		 
		  
			$order->add_order_note("Thanh toán thành công - transaction id {$tx_id}");
			$order->payment_complete( $tx_id );
			$order->reduce_order_stock();
			update_post_meta( $order_id, '_transaction_id', $tx_id );	
			$thankyou_page = $this->redirect_page_id ? get_permalink($this->redirect_page_id) : $this->get_return_url( $order ); 
			wp_redirect($thankyou_page);			
			exit;
		}else{
			$order->add_order_note( "Thanh toán thất bại" );
		}  
		wc_add_notice(__('Payment failed','woocommerce'));
		wp_redirect(wc_get_page_permalink( 'cart' ));
		exit;
    }
	
	function write_log($log_file, $error){
		date_default_timezone_set('Asia/Ho_Chi_Minh');
		$date = date('d/m/Y H:i:s');
		$error = $date.": ".$error."\n";
		if (!is_dir(ABSPATH."logs")) {
			mkdir(ABSPATH."logs", 0755, true);
		}
		
		$log_file = ABSPATH."logs/".$log_file;
		if(filesize($log_file) > 1048576 || !file_exists($log_file)){
			$fh = fopen($log_file, 'w');
		}
		else{
			//echo "Append log to log file ".$log_file;
			$fh = fopen($log_file, 'a');
		}
		
		fwrite($fh, $error);
		fclose($fh);
	}


    function showMessage($content){
            return '<div class="box '.$this->msg['class'].'-box">'.$this->msg['message'].'</div>'.$content;
        }
     // get all pages
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

  public function buildCheckoutUrlExpand($return_url, $receiver, $transaction_info, $order_code, $price, $currency = 'vnd', $quantity = 1, $tax = 0, $discount = 0, $fee_cal = 0, $fee_shipping = 0, $order_description = '', $buyer_info = '', $affiliate_code = '')
  { 
    // This is from the class provided by Ngan Luong. Not advisable to mess.
    //  This one is for advanced checkout, including taxes and discounts
    if ($affiliate_code == "") $affiliate_code = $this->affiliate_code;
    $arr_param = array(
      'merchant_site_code'  =>  strval($this->merchant_site_code),
      'return_url'          =>  strval(strtolower($return_url)),
      'receiver'            =>  strval($receiver),
      'transaction_info'    =>  strval($transaction_info),
      'order_code'          =>  strval($order_code),
      'price'               =>  strval($price),
      'currency'            =>  strval($currency),
      'quantity'            =>  strval($quantity),
      'tax'                 =>  strval($tax),
      'discount'            =>  strval($discount),
      'fee_cal'             =>  strval($fee_cal),
      'fee_shipping'        =>  strval($fee_shipping),
      'order_description'   =>  strval($order_description),
      'buyer_info'          =>  strval($buyer_info),
      'affiliate_code'      =>  strval($affiliate_code)
    );
    $secure_code ='';
    $secure_code = implode(' ', $arr_param) . ' ' . $this->secure_pass;
    $arr_param['secure_code'] = md5($secure_code);
    /* */
    $redirect_url = $this->nganluong_url;
    if (strpos($redirect_url, '?') === false) {
      $redirect_url .= '?';
    } else if (substr($redirect_url, strlen($redirect_url)-1, 1) != '?' && strpos($redirect_url, '&') === false) {
      $redirect_url .= '&';     
    }
        
    /* */
    $url = '';
    foreach ($arr_param as $key=>$value) {
      $value = urlencode($value);
      if ($url == '') {
        $url .= $key . '=' . $value;
      } else {
        $url .= '&' . $key . '=' . $value;
      }
    }
    
    return $redirect_url.$url;
  }
  
  /*Hàm thực hiện xác minh tính đúng đắn của các tham số trả về từ nganluong.vn*/
  
  public function verifyPaymentUrl($transaction_info, $order_code, $price, $payment_id, $payment_type, $error_text, $secure_code)
  {
    // This is from the class provided by Ngan Luong. Not advisable to mess.
    // Checks the returned URL from Ngan Luong to see if it matches
    // Tạo mã xác thực từ chủ web
    $str = '';
    $str .= ' ' . strval($transaction_info);
    $str .= ' ' . strval($order_code);
    $str .= ' ' . strval($price);
    $str .= ' ' . strval($payment_id);
    $str .= ' ' . strval($payment_type);
    $str .= ' ' . strval($error_text);
    $str .= ' ' . strval($this->merchant_site_code);
    $str .= ' ' . strval($this->secure_pass);

         // Mã hóa các tham số
    $verify_secure_code = '';
    $verify_secure_code = md5($str);
    
    // Xác thực mã của chủ web với mã trả về từ nganluong.vn
    if ($verify_secure_code === $secure_code) return true;
    
    return false;
  }

}

  function woocommerce_add_NganLuongVN_gateway($methods) {
      $methods[] = 'WC_NganLuongVN';
      return $methods;
  }

    add_filter('woocommerce_payment_gateways', 'woocommerce_add_NganLuongVN_gateway' );
}

