<?php 
class Kompassi_CheckoutFinland_Model_Standard extends Mage_Payment_Model_Method_Abstract
{
	protected $_isGateway = false;
	protected $_canAuthorize = true;
	protected $_canCapture = true;
	protected $_canCapturePartial = true;
	protected $_canRefund = true;
	protected $_canVoid = true;
	protected $_canUseInternal = false;
	protected $_canUseCheckout = true;
	protected $_canUseForMultishipping = false;
    protected $_canRefundInvoicePartial = true; // changed PJS to true 
    protected $_canFetchTransactionInfo = true; // changed PJS to true 
    protected $_canReviewPayment = true; // changed PJS to true 

	
	protected $_code  = 'checkoutfinland';
    protected $_formBlockType = 'checkoutfinland/standard_form';
    
    protected $_order = null;
    
	public function getOrderPlaceRedirectUrl()
    {
        return Mage::getUrl('checkoutfinland/standard/redirect');
    }
    
    public function getSession()
    {
    	return Mage::getSingleton('checkoutfinland/session');
    }
    
 	public function getConfig()
    {
        return Mage::getSingleton('checkoutfinland/config');
    }
    
    public function getCheckout()
    {
    	return Mage::getSingleton('checkout/session');
    }
    
	public function getReturnURL ()
    {
        return Mage::getUrl('checkoutfinland/standard/response').'?';
    }
    
	public function getTotalAmount()
    {
    	return (int)($this->getOrder()->getBaseGrandTotal() * 100);
    }
    
	protected function getCustomerId()
    {
        return $this->getOrder()->getCustomerId();
    }
    
	public function getQuote()
    {
        return $this->getCheckout()->getQuote();
    }
    
	private function checkCurrencyCode()
    {
        if ($this->getOrder()->getBaseCurrencyCode() != "EUR") {
            throw new Exception('Only payments in EUR is allowed');
        }
        return $this->getOrder()->getBaseCurrencyCode();
    }

    public function getCheckoutForms()
    {
        $addr = $this->getOrder()->getShippingAddress();

        if(!$addr)
            $addr = $this->getOrder()->getBillingAddress();
        
        $this->checkCurrencyCode();
    	$merchant_id       = Mage::getStoreConfig('payment/checkoutfinland/merchant_id');
    	$merchant_secret   = Mage::getStoreConfig('payment/checkoutfinland/merchant_secret');
    	$delivery_time     = Mage::getStoreConfig('payment/checkoutfinland/delivery_time');
        
    	$data = $this->getPostData($merchant_id, $merchant_secret, $addr, $delivery_time);
    	$checkout_forms_xml = $this->doPost($data);

    	return simplexml_load_string($checkout_forms_xml);
    }
    
    private function getPostData($merchant_id, $merchant_secret, $addr, $delivery_time)
    {
		$post['VERSION']		= "0001";
		$post['STAMP']			= $this->getOrder()->getRealOrderId();
		$post['AMOUNT']			= $this->getTotalAmount();
		$post['REFERENCE']		= $this->createReferenceNumber();
		$post['MESSAGE']		= "";
		$post['LANGUAGE']		= "FI";
		$post['MERCHANT']		= $merchant_id;
		$post['RETURN']			= substr($this->getReturnURL(), 0, 300);
		$post['CANCEL']			= substr($this->getReturnURL(), 0, 300);
		$post['REJECT']			= substr($this->getReturnURL(), 0, 300);
		$post['DELAYED']		= substr($this->getReturnURL(), 0, 300);
		$post['COUNTRY']		= "FIN";
		$post['CURRENCY']		= $this->getOrder()->getBaseCurrencyCode();
		$post['DEVICE']			= "10";
		$post['CONTENT']		= "1";
		$post['TYPE']			= "0";
		$post['ALGORITHM']		= "3";
		$post['DELIVERY_DATE']	= date('Ymd', strtotime("+$delivery_time days"));
		$post['FIRSTNAME']		= !$addr ? '' : substr($addr->getFirstname(), 0, 40);
		$post['FAMILYNAME']		= !$addr ? '' :substr($addr->getLastname(), 0, 40);
		$post['ADDRESS']		= !$addr ? '' :substr($addr->getStreet(1), 0, 40);
		$post['POSTCODE']		= !$addr ? '' :substr($addr->getPostcode(), 0, 5);
		$post['POSTOFFICE']		= !$addr ? '' :substr($addr->getCity(), 0, 18);
		
		$mac = "";
		foreach($post as $value) {
			$mac .= "$value+";
		}	
		$mac .= $merchant_secret;
		
		$post['MAC'] = strtoupper(md5($mac));

		return $post;
    }
    
    private function doPost($postData)
    {

        if(ini_get('allow_url_fopen'))
        {
        	$context = stream_context_create(array(
        		'http' => array(
        			'method' => 'POST',
        			'header' => 'Content-Type: application/x-www-form-urlencoded',
        			'content' => http_build_query($postData)
        		)
        	));
        	
        	return file_get_contents('https://payment.checkout.fi', false, $context);
        } 
        elseif(in_array('curl', get_loaded_extensions()) ) 
        {
            $options = array(
                CURLOPT_POST            => 1,
                CURLOPT_HEADER          => 0,
                CURLOPT_URL             => 'https://payment.checkout.fi',
                CURLOPT_FRESH_CONNECT   => 1,
                CURLOPT_RETURNTRANSFER  => 1,
                CURLOPT_FORBID_REUSE    => 1,
                CURLOPT_TIMEOUT         => 4,
                CURLOPT_POSTFIELDS      => http_build_query($postData)
            );
        
            $ch = curl_init();
            curl_setopt_array($ch, $options);
            $result = curl_exec($ch);
            curl_close($ch);

            return $result;
        }
        else 
        {
            throw new Exception("No valid method to post data. Set allow_url_fopen setting to On in php.ini file or install curl extension.");
        }
    }
    
	private function createReferenceNumber()
    {
    	$result = '';
        $orderId = ltrim($this->getOrder()->getRealOrderId(), '0');
        if (strlen($orderId) < 3) {
            $orderId = $orderId.'000000';
        }
            
        foreach (str_split($orderId . $this->countRef($orderId), 5) as $part) $result .= $part;
    	return $result;
    }

	private function countRef($refnumber)
	{
	    $multipliers = array(7,3,1);
	    $length = strlen($refnumber);
	    $refnumber = str_split($refnumber);
	    $sum = 0;
	    for ($i = $length - 1; $i >= 0; --$i) {
	      $sum += $refnumber[$i] * $multipliers[($length - 1 - $i) % 3];
	    }
	    return (10 - $sum % 10) % 10;
	}
    
    
	public function authorize(Varien_Object $payment, $amount)
    {
			return $this;
    }
    
	public function capture(Varien_Object $payment, $amount)
    {
			return $this;
    }
    
	public function refund(Varien_Object $payment, $amount)
    {
			return $this;
    }
    
	public function void(Varien_Object $payment)
    {
			return $this;
    }
    
	public function cancel(Varien_Object $payment)
    {
			return $this;
    }
}
?>