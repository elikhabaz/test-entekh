<?php
class novin_credit_manager
{
    private static $instance = null;
    private $user_id;
    public static $sources = array(
        array('name' => 'اعتبار عمومی انتخاب', 'slug' => 'source_1', 'percent' => 0, 'rechargeable' => false),
        array('name' => 'بانک مهر ایران', 'slug' => 'source_2', 'percent' => 0, 'rechargeable' => true),
        array('name' => 'بانک ملی ایران', 'slug' => 'source_6', 'percent' => 0, 'rechargeable' => true),
        array('name' => 'بانک سینا', 'slug' => 'source_8', 'percent' => 0, 'rechargeable' => false),
        //array('name' => 'بانک مهر ایران ۱۸ ماهه','slug' => 'source_3','percent' => 20,'rechargeable' => true),
        array('name' => 'بانک رفاه', 'slug' => 'source_4', 'percent' => 0, 'rechargeable' => true),
        array('name' => 'بانک توسعه ی تعاون', 'slug' => 'source_5', 'percent' => 0, 'rechargeable' => true),
        array('name' => 'کیپا', 'slug' => 'source_7', 'percent' => 0, 'rechargeable' => true),
        array('name' => 'تالی', 'slug' => 'source_10', 'percent' => 0, 'rechargeable' => true),
        array('name' => 'درگاه انتخابی نو', 'slug' => 'source_11', 'percent' => 0, 'rechargeable' => true),
        array('name' => 'درگاه CPG بانک ملی', 'slug' => 'source_12', 'percent' => 0, 'rechargeable' => true),
        array('name' => 'درگاه ستاره اول', 'slug' => 'source_13', 'percent' => 0, 'rechargeable' => true)
    );
    public function __construct()
    {
        $this->user_id = get_current_user_id();
    }
    public static function instance()
    {
        if (self::$instance == null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    private function get_user_id()
    {
        return $this->user_id;
    }
    public function get_company_percent($type)
    {
        if ($type === 'credit') {
            return intval(um_number_to_english(get_post_meta(get_user_company($this->user_id), 'um_cash_credit_percent', true)));
        } else if ($type === 'cheque') {
            //return intval(um_number_to_english(get_post_meta(get_user_company($this->user_id),'um_cash_cheque_percent',true)));
            return intval(WC()->session->get('cheque_percent'));
        } else {
            return 0;
        }
    }
    public function get_cheque_commission_percent()
    {
        return WC()->session->get('cheque_commission');
    }
    public function get_company_max_cheque_price()
    {
        return intval(um_number_to_english(get_post_meta(get_user_company($this->user_id), 'um_max_cheque_price', true)));
    }
    public function add_percent($target, $percent)
    {
        return (1 + ($percent / 100)) * $target;
    }

    private function get_total_order_price()
    {
        $args = array(
            'status' => array('wc-processing', 'wc-completed'),
            'limit' => -1,
            'customer' => $this->user_id,
        );
        $orders = wc_get_orders($args);
        $total = 0;
        if (is_array($orders) && count($orders) > 0) {
            foreach ($orders as $order) {
                $total += $order->get_total();
            }
        }
        return $total;
    }
    private function get_total_cpg()
    {
        $args = array(
            'status' => array('wc-processing', 'wc-completed'),
            'limit' => -1,
            'customer' => $this->user_id,

        );
        $orders = wc_get_orders($args);
        $total = 0;
        if (is_array($orders) && count($orders) > 0) {
            foreach ($orders as $order) {
                $total += $order->get_total();
            }
        }
        return $total;
    }
    private function get_credit_info($cart)
    {
        $result = array();
        $result['is_enough'] = false;
        $result['steps'] = array();
        $company_credit_percent = $this->get_company_percent('credit');
        //  var_dump( $company_credit_percent);
        $company_cheque_percent = $this->get_company_percent('cheque');
        // var_dump($company_cheque_percent);
        $company_max_cheque_price = $this->get_company_max_cheque_price();
        $total_credit_price = 0;
        $total_cheque_price = 0;
        $total_cheque_price_without_commission = 0;
        $total_cheque_commission = 0;
        $total_cheque_discount = 0;
        $label = 'روش خرید';
        $index = 'attribute_' . sanitize_title($label);
        $coupons = WC()->cart->get_applied_coupons();
        $coupon_percent = 0;

        $order_type = '';
        $discount_code_amount = 0;
        foreach ( WC()->cart->get_coupons() as $code => $coupon ){
            $discount_code_amount +=	WC()->cart->get_coupon_discount_amount( $coupon->get_code(), WC()->cart->display_cart_ex_tax ) ;
        }

        if (count($coupons) > 0) {
            foreach ($coupons as $coupon_code) {
                $coupon = new WC_Coupon($coupon_code);
                if ($coupon->get_discount_type() == 'percent') {
                    $coupon_percent += $coupon->get_amount();
                }
            }
        }
        foreach ($cart->get_cart() as $item) {
            if (isset($item['variation'])) {
                if ($item['variation'][$index] === 'اعتباری') {
                    $order_type = 'credit';
                    $total_credit_price += um_add_nine_percent($item['data']->get_price()) * $item['quantity'];
                }
                if ($item['variation'][$index] === 'چک') {
                    $item_price=$item['data']->get_price() * $item['quantity'];
                    $total_cheque_price_without_commission += $item_price;
                    $total_cheque_discount= ($coupon_percent / 100) * $item_price;
                }
            }
        }
        $price_with_discount = $total_cheque_price_without_commission - $total_cheque_discount;
        $min_cash=  $price_with_discount * ($company_cheque_percent / 100);

        $result['total_cheque_price_raw'] = $total_cheque_price_without_commission;
        $result['total_cheque_price_raw_without_commission'] = $total_cheque_price_without_commission;
        $total_credit_price = (1 - ($company_credit_percent / 100)) * $total_credit_price;
        //  var_dump($total_credit_price);
        $total_cheque_price = (1 - ($company_cheque_percent / 100)) * $total_cheque_price_without_commission;
        // var_dump($total_cheque_price);

        $result['total_cheque_price_raw_after_cash'] = $total_cheque_price;
        $result['max_total_cheque_price'] = 100000000000;
        if ($company_max_cheque_price > 0) {
            $result['max_total_cheque_price'] = $company_max_cheque_price;
        }
        if ($result['max_total_cheque_price'] < $total_cheque_price) {
            $total_cheque_price = $result['max_total_cheque_price'];
        }

        $result['company_cheque_percent'] = $company_cheque_percent;
        $result['cheque_commission_percent'] = $this->get_cheque_commission_percent();
        $result['total_credit_price'] = max($total_credit_price - $discount_code_amount ,0);// $total_credit_price;

        ///eli
        $total_price_by_commission = $price_with_discount - $min_cash;
        $total_cheque_price_commission_vat = $total_price_by_commission * ($this->get_cheque_commission_percent()/100);
        $result['total_cheque_price_commission_vat'] = $total_cheque_price_commission_vat;
        $total_cheque_price_by_commission = $total_price_by_commission + $total_cheque_price_commission_vat;
        $result['total_cheque_price_by_commission'] = $total_cheque_price_by_commission;

        
        $cheque_price_with_vat=um_add_nine_percent($price_with_discount - $min_cash);
        $total_cheque_commission =$cheque_price_with_vat * ($this->get_cheque_commission_percent()/100) ;
        $cheque_price_with_commission_and_vat=$cheque_price_with_vat + $total_cheque_commission;

        $result['total_cheque_commission'] = $total_cheque_commission;
        $result['total_cheque_price_with_commission'] = $cheque_price_with_commission_and_vat;
        if($item['variation'][$index] === 'چک'){
            
            $result['total_cash_price'] =  $min_cash - $total_credit_price;
        }
        if($item['variation'][$index] === 'نقدی' || $item['variation'][$index] === 'اعتباری'){
            $result['total_cash_price'] = max(($cart->total + $result['total_cheque_commission'] ) - $total_credit_price - $min_cash ,0);
        }
       
        // var_dump( $result['total_cash_price']);
        $result['total_cheque_price'] = $cheque_price_with_vat;//$total_cheque_price;
        
        $sources = $this->get_user_sources();
        if (is_array($sources) && count($sources) > 0) {
            foreach ($sources as $key => $amount) {
                $info = $this->get_source_info_by_slug($key);
                if ($info !== false) {
                    $step = $this->create_step($info, $amount, $result['total_credit_price'], $result['total_cash_price']);
                    $result['steps'][] = $step;
                    $result['is_enough'] = $step['is_enough'];
                    $result['total_credit_price'] = $step['total_credit_price'];
                    $result['total_cash_price'] = $step['total_cash_price'];
                }
                if ($result['is_enough']) {
                    break;
                }
            }
        }
        if (is_array($result['steps']) && count($result['steps']) > 0) {
            $sub_credit = 0;
            foreach ($result['steps'] as $step) {
                $sub_credit += $step['sub'];
            }
            $result['sub_credit'] = $sub_credit;

        } else {
            $result['sub_credit'] = 0;
            
        }
        $result['total_credit_price_raw'] = $result['sub_credit'] + $result['total_credit_price'];
        $result['total_cash_price_raw'] = $result['total_cash_price'];
        $result['total_cash_price'] = $result['total_cash_price'] + $result['total_credit_price'];


        $mellat_cash = $this->get_user_cash();
        $result['update'] = $mellat_cash;
        $lendtech_cash = $this->get_user_cash_lendtech();
        $result['update_lendtech'] = $lendtech_cash;

        // $total_discount = $cart->get_cart_discount_total();
        // $continue = true;
        $sub = 0;
        $lendtech_sub = 0;
        $remaining = $result['total_cash_price'] - $lendtech_cash;

        $continue = true;
        if ($remaining <= 0) {
            $lendtech_sub = $result['total_cash_price'];
            $result['update_lendtech'] = $lendtech_cash - $result['total_cash_price'];
            $result['total'] = 0;
            $continue = false;
        } else {
            $lendtech_sub = $lendtech_cash;
            $result['update_lendtech'] = 0;
            $result['total'] = $remaining;
        }


        if ($continue) {
            $remaining_total = $remaining;
            $remaining = $remaining - $mellat_cash;
            if ($remaining <= 0) {
                $sub = $result['total_cash_price'] - $lendtech_sub;
                $result['update'] = $mellat_cash - $remaining_total;
                $result['total'] = 0;
            } else {
                $sub = $mellat_cash - $lendtech_sub;
                $result['update'] = 0;
                $result['total'] = $remaining_total - $mellat_cash;
            }
        }
        $result['sub_cash'] = $sub;
        $result['lendtech_sub'] = $lendtech_sub;

        $result['total_credit_price_raw'] = ceil($result['total_credit_price_raw']);
        $result['total_cash_price_raw'] = ceil($result['total_cash_price_raw']);
        $result['total_cash_price'] = ceil($result['total_cash_price']);
        $result['sub_cash'] = ceil($result['sub_cash']);
        $result['lendtech_sub'] = ceil($result['lendtech_sub']);
        $result['update'] = ceil($result['update']);
        $result['update_lendtech'] = ceil($result['update_lendtech']);
        $result['total'] = ceil($result['total']);
        $result['total_cheque_price'] = ceil($result['total_cheque_price']);
        $result['total_credit_price'] = ceil($result['total_credit_price']);

        $result['total_coupon_percent'] = $total_cheque_discount;//ceil($total_cheque_commission - $result['total_cheque_price_raw']);
        // $result['success'] = 'true';
        //echo wp_send_json($result);
        
        return $result;
    }
    public function woocommerce_after_calculate_totals($cart)
    {
        
        if (is_admin() && !defined('DOING_AJAX')) {
            return;
        }
        if (did_action('woocommerce_after_calculate_totals') >= 2) {
            return;
        }
        $info = $this->get_credit_info($cart);
        WC()->session->set('credit_info', $info);
        //entekhab_helper::log( WC()->session->get('credit_info'),'info');
        $cart->total = $info['total'];
        if ($cart->total < 10) {
            $cart->total = 0;
        }
    }
    public function template_redirect()
    {
        if (is_checkout()) {
            WC()->session->set('cheque_percent', 0);
            WC()->session->set('cheque_commission', 0);
            update_user_meta(get_current_user_id(), 'checkout_files_result', 'unset');
        }
    }
    public function woocommerce_checkout_order_processed($order_id)
    {
        if (is_admin() && !defined('DOING_AJAX')) {
            return;
        }
        if (did_action('woocommerce_checkout_order_processed') >= 2) {
            return;
        }
        if (!metadata_exists('post', $order_id, 'credit_info')) {
            $info = WC()->session->get('credit_info');
            update_post_meta($order_id, 'credit_info', $info);
            $this->update_user_cash($info['update']);
            $this->update_user_cash_lendtech($info['update_lendtech']);
            if (is_array($info['steps']) && count($info['steps']) > 0) {
                foreach ($info['steps'] as $step) {
                    $this->update_user_credit($step['update'], $step['source']);
                }
            }
        }

        $order = wc_get_order($order_id);
        foreach ($order->get_items() as $item) {
            $variation_id = $item->get_variation_id();
            $variation = new WC_Product_Variation($variation_id);
            $sku = $variation->get_sku();
            $item->add_meta_data('_VARIANT_SKU', $sku);
        }
        $order->save();
    }
    private function update_user_credit($amount, $source)
    {
        update_user_meta($this->user_id, 'nw_' . $source['slug'] . 'credit', intval(um_fix_internal_currency_back(um_number_to_english($amount))));
    }
    private function update_user_cash($amount)
    {
        update_user_meta($this->user_id, 'um_cash_credit', intval(um_fix_internal_currency_back(um_number_to_english($amount))));
    }
    private function update_user_cash_lendtech($amount)
    {
        update_user_meta($this->user_id, 'um_cash_lendtech', intval(um_fix_internal_currency_back(um_number_to_english($amount))));
    }
    public static function update_user_credit_sa($user_id, $amount, $source)
    {
        update_user_meta($user_id, 'nw_' . $source['slug'] . 'credit', intval(um_fix_internal_currency_back(um_number_to_english($amount))));
    }
    public static function update_user_cash_sa($user_id, $amount)
    {
        update_user_meta($user_id, 'um_cash_credit', intval(um_fix_internal_currency_back(um_number_to_english($amount))));
    }
    public static function update_user_cash_lendtech_sa($user_id, $amount)
    {
        update_user_meta($user_id, 'um_cash_lendtech', intval(um_fix_internal_currency_back(um_number_to_english($amount))));
    }
    public function get_user_cash()
    {
        $cash = intval(um_fix_internal_currency(um_number_to_english(get_user_meta($this->user_id, 'um_cash_credit', true))));
        return $cash;
    }
    public function get_user_cash_lendtech()
    {
        $cash = intval(um_fix_internal_currency(um_number_to_english(get_user_meta($this->user_id, 'um_cash_lendtech', true))));
        return $cash;
    }
    private function create_step($source, $amount, $total_credit_price, $total_cash_price)
    {
        $result = array();
        $result['source'] = $source;
        $result['total_credit_price'] = (1 - ($source['percent'] / 100)) * $total_credit_price;
        $result['total_cash_price'] = $total_cash_price + (($source['percent'] / 100) * $total_credit_price);
        $result['is_enough'] = false;
        if ($amount >= $result['total_credit_price']) {
            $result['sub'] = $result['total_credit_price'];
            $result['update'] = $amount - $result['total_credit_price'];
            $result['total_credit_price'] = 0;
            $result['is_enough'] = true;
        } else {
            $result['sub'] = $amount;
            $result['update'] = 0;
            $result['total_credit_price'] = $result['total_credit_price'] - $amount;
        }
        return $result;
    }
    public function get_source_info_by_slug($slug)
    {
        $res = false;
        foreach (self::$sources as $source) {
            if ($source['slug'] === $slug) {
                $res = $source;
                break;
            }
        }
        return $res;
    }
    public function get_user_sources()
    {
        $user_sources = array();
        foreach (self::$sources as $source) {
            if (metadata_exists('user', $this->user_id, 'nw_' . $source['slug'] . 'credit')) {
                $data = intval(um_fix_internal_currency(um_number_to_english(get_user_meta($this->user_id, 'nw_' . $source['slug'] . 'credit', true))));
                if ($data > 0) {
                    $user_sources[$source['slug']] = $data;
                }
            }
        }
        return $this->sort_user_sources($user_sources);
    }
    public function get_total_user_credit()
    {
        $sources = $this->get_user_sources();
        if (is_array($sources) && count($sources) > 0) {
            $total = 0;
            foreach ($sources as $key => $amount) {
                $total += $amount;
            }
            return $total;
        }
        return 0;
    }
    private function sort_user_sources($sources)
    {
        $order = get_user_meta($this->user_id, 'nw_credit_order', true);
        if (is_array($order)) {
            $ordered = array();
            foreach ($order as $key) {
                if (array_key_exists($key, $sources)) {
                    $ordered[$key] = $sources[$key];
                    unset($sources[$key]);
                }
            }
            return $ordered + $sources;
        }
        return $sources;
    }
    public function get_next_step()
    {
        $result = array();
        $slug = $_POST['slug'];
        $cart = WC()->cart;
        $info = $this->get_credit_info($cart);
        $steps = $info['steps'];
        $done = false;
        if (count($steps) > 0) {
            foreach ($steps as $step) {
                if ($step['source']['slug'] === $slug) {
                    $done = true;
                    break;
                }
            }
        }
        if ($done) {
            $result['msg'] = $info['total_credit_price'];
        } else {
            $source = $this->get_source_info_by_slug($slug);
            $result['msg'] = (1 - ($source['percent'] / 100)) * $info['total_credit_price'];
        }
        wp_send_json_success($result);
    }
    public function update_user_source_order()
    {
        $string = $_POST['string'];
        $order = explode('*', $string);
        if (count($order) > 1) {
            update_user_meta($this->user_id, 'nw_credit_order', $order);
        }
        wp_send_json_success();
    }
    public function set_cheque_percent()
    {
        $index = $_POST['index'];
        $rules = get_post_meta(get_user_company($this->user_id), 'cheque_rules', true);
        if (is_array($rules) && isset($rules[$index])) {
            $rule = $rules[$index];
            $percent = $rule['rule_percent'];
            WC()->session->set('cheque_percent', intval($percent));
            WC()->session->set('cheque_commission', ($rule['total_cheques'] * $rule['fee_percent']));
            WC()->cart->calculate_totals();
        }
        wp_send_json_success();
    }
}