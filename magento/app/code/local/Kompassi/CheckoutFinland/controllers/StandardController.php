<?php 
class Kompassi_CheckoutFinland_StandardController extends Mage_Core_Controller_Front_Action
{	
    
	protected function _expireAjax()
    {
        if (!Mage::getSingleton('checkout/session')->getQuote()->hasItems()) {
            $this->getResponse()->setHeader('HTTP/1.1','403 Session Expired');
            exit;
        }
    }
    
    public function redirectAction()
    {
    	$session = Mage::getSingleton('checkout/session');
    	
    	$standard = Mage::getModel('checkoutfinland/standard');
		$order = Mage::getModel('sales/order');
    	$order->loadByIncrementId($session->getLastRealOrderId());
		$standard->setOrder($order);

        $order->setStatus(Mage_Sales_Model_Order::STATE_PENDING_PAYMENT);
        $order->save();
		
		if(!$standard->getConfigData('allow_payments_under_1_eur'))
		{
			
			if($standard->getTotalAmount() < 100)
			{
				throw new Exception('Payments under 1€ are not allowed.');
			}
		}
    	
    	$this->loadLayout();
		$this->getLayout()->getBlock('content')->append($this->getLayout()->createBlock('checkoutfinland/standard_redirect'));
      	$this->renderLayout();
    }
    
    public function responseAction()
    {
    	$merchant_secret = Mage::getStoreConfig('payment/checkoutfinland/merchant_secret');

    	$version   = $_GET['VERSION'];
    	$stamp     = $_GET['STAMP'];
    	$reference = $_GET['REFERENCE'];
    	$payment   = $_GET['PAYMENT'];
    	$status    = $_GET['STATUS'];
    	$algorithm = $_GET['ALGORITHM'];
    	$mac       = $_GET['MAC'];
    	
    	if($algorithm == 1)
    		$expected_mac = strtoupper(md5("$version+$stamp+$reference+$payment+$status+$algorithm+$merchant_secret"));
    	elseif($algorithm == 2)
    		$expected_mac = strtoupper(md5("$merchant_secret&$version&$stamp&$reference&$payment&$status&$algorithm"));
        elseif($algorithm == 3)
            $expected_mac = strtoupper(hash_hmac('sha256', "$version&$stamp&$reference&$payment&$status&$algorithm", $merchant_secret));
    	else throw new Exception('Unsuported algorithm: '.$algorithm);
    	
    	if($expected_mac == $mac)
    	{
    		switch($status)
    		{
    			case '2':
    			case '5':
    			case '6':
    			case '8':
    			case '9':
    			case '10':
    				$this->success($stamp, $reference, $payment);
    				break;
    			case '3':
    			case '4':
                case '7':
    				$this->delayed($stamp, $reference, $payment);
    				break;
    			case '-1':
    				$this->cancel($stamp, $reference, $payment);
    				break;
    			case '-2':
    			case '-3':
    			case '-4':
    			case '-10':
    				$this->cancel($stamp, $reference, $payment);
    				break;
    		}
    	}
    	else
    	{
    		 throw new Exception('MAC mismatc');
    	}
    }
    
    
	public function cancel($stamp, $reference, $payment_id)
    {
    	$standard = Mage::getModel('Kompassi_CheckoutFinland_Model_Standard');
    	$order = Mage::getModel('sales/order');
    	$order->loadByIncrementId($stamp);
    	
    	if(!$order->getId())
    		throw new Exception($this->__('Error, no order with given id found'));
    		
    	$standard->setOrder($order);
    	$order->setStatus(Mage_Sales_Model_Order::STATE_CANCELED);
    	
        $order->addStatusToHistory($order->getStatus(), Mage::helper('checkoutfinland')->__('Order canceled') ." " .Mage::helper('checkoutfinland')->__('Checkout payment id') ." " .$payment_id );
        $order->cancel();
        $order->save();
        
        $this->_redirect('checkout/onepage/failure');
    }
    
    public function success($stamp, $reference, $payment_id)
    {	
    	$standard = Mage::getModel('Kompassi_CheckoutFinland_Model_Standard');
    	$order = Mage::getModel('sales/order');
    	$order->loadByIncrementId($stamp);
    	
    	if(!$order->getId())
    		throw new Exception('Error, no order with given id found');
    		
       	if ($order->canInvoice()) {
        	// create an invoice for the order
		   	$invoice = $order->prepareInvoice();
			
		   // prepare invoice processing
		   	$invoice->register();
        	$invoice->capture();
			
			Mage::getModel('core/resource_transaction')
			       ->addObject($invoice)
			       ->addObject($invoice->getOrder())
			       ->save();
       	}
    		
    	$standard->setOrder($order);
    	
    	$order->setStatus(Mage_Sales_Model_Order::STATE_PROCESSING);
    
        $newOrderStatus     = 'processing';
        $paymentDescription = 'Payment method: Checkout Finland';
        $notify             = 'false';
        $order->setState(
          Mage_Sales_Model_Order::STATE_PROCESSING, $newOrderStatus,
          $paymentDescription,
          $notify
          );

    	try {
    		if (!$order->getEmailSent()) {
                $order->sendNewOrderEmail();
                $order->setEmailSent(true);
             }
           
    	} catch (Exception $ex) {}
        
        $order->save();
        
        $this->_redirect('checkout/onepage/success');
    }
    
	public function delayed($stamp, $reference, $payment_id)
    {
    	$payment = Mage::getModel('Kompassi_CheckoutFinland_Model_Standard');
    	$order = Mage::getModel('sales/order');
    	$order->loadByIncrementId($stamp);
    	
    	if(!$order->getId())
    		throw new Exception('Error, no order with given id found');
    		
    	$payment->setOrder($order);
    	$order->setState(Mage_Sales_Model_Order::STATE_HOLDED);
        $order->addStatusToHistory($order->getStatus(), Mage::helper('checkoutfinland')->__('Payment delayed') ." "  .Mage::helper('checkoutfinland')->__('Checkout payment id') ." " .$payment_id);
        
        $order->save();
        
        $this->_redirect('checkout/onepage/success');
    }
}
?>