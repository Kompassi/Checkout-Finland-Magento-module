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
	
		$style = "<!-- In case you want to put this css in its proper place this line is 
						in magento/app/code/local/Kompassi/CheckoutFinland/Block/Standard/Redirect.php --><style>.C1 {
			 width: 180px;
			 height: 120px;
			 border: 1pt solid #a0a0a0;
			 display: block;
			 float: left;
			 margin: 7px;
			 -moz-border-radius: 5px; -webkit-border-radius: 5px; border-radius: 5px;
			 clear: none;
			 padding: 0;
			}

			.C1:hover {
			 background-color: #f0f0f0;
			 border-color: black;
			}

			.C1 form {
			 width: 180px;height: 120px;
			}
			.C1 form span {
			 display:table-cell; vertical-align:middle;
			 height: 92px;
			 width: 180px;
			}
			.C1 form span input {
			 margin-left: auto;
			 margin-right: auto;
			 display: block;
			 border: 1pt solid #f2f2f2;
			 -moz-border-radius: 5px; -webkit-border-radius: 5px; border-radius: 5px;
			 padding: 5px;
			 background-color: white;
			}
			.C1:hover form span input {
			 border: 1pt solid black;
			}
			.C1 div {
			 text-align: center;
			 font-family: arial;
			 font-size: 8pt;
			}</style>";

		$html = $style .'<div class="block" style="padding: 10px; background-color: white;">';
		$html .= "<h1>{$this->__('Choose payment method')}</h1>";
		foreach($xml->payments->payment->banks as $bankX) 
		{
			foreach($bankX as $bank) 
			{
				$html .= "<div class='C1' style='float: left; margin-right: 20px; min-height: 100px;' text-align: center;><form action='{$bank['url']}' method='post'><p>\n";
				foreach($bank as $key => $value) 
				{
					$html .= "<input type='hidden' name='$key' value='$value' />\n";
				}
				$html .= "<span><input type='image' src='{$bank['icon']}' /></span><div><p>{$bank['name']}</p></div></form></div>\n";
			}
		}
		$html .= '<div style="clear:both;"></div></div>';
		return $html;
	}
}