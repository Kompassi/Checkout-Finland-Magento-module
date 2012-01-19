<?php 
class Kompassi_CheckoutFinland_Block_Standard_Redirect extends Mage_Core_Block_Abstract
{

	protected function _toHtml()
	{
		$session = Mage::getSingleton('checkout/session');
		$standard = Mage::getModel('checkoutfinland/standard');
		$order = Mage::getModel('sales/order');
    	$order->loadByIncrementId($session->getLastRealOrderId());
		
		$standard->setOrder($order);
		
		try {
			$xml = $standard->getCheckoutForms();	
		} catch (Exception $ex)
		{
			throw new Exception($ex);
		}
		
		$html = '<div class="block" style="padding: 10px; background-color: white;">';
		$html .= "<h1>{$this->__('Choose payment method')}</h1>";
		foreach($xml->payments->payment->banks as $bankX) 
		{
			foreach($bankX as $bank) 
			{
				$html .= "<div style='float: left; margin-right: 20px; min-height: 100px;' text-align: center;><form action='{$bank['url']}' method='post'><p>\n";
				foreach($bank as $key => $value) 
				{
					$html .= "<input type='hidden' name='$key' value='$value' />\n";
				}
				$html .= "<input type='image' src='{$bank['icon']}' /></p></form></div>\n";
			}
		}
		$html .= '<div style="clear:both;"></div></div>';
		return $html;
	}
	

}
?>