<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once 'app/Mage.php';
Mage::app();
umask(0);
if(isset($_REQUEST['page']) && $_REQUEST['page'] > 0){
    $page = $_REQUEST['page'];
}else{
    $page=1;
}
$final_array = array();
$_orders = Mage::getModel('sales/order')->getCollection()->setPageSize(400)->setCurPage($page); 
foreach($_orders as $_order){
$order_id = $_order->getId();
$order = Mage::getModel('sales/order')->load($order_id);
$last_transaction_id = $order->getPayment()->getMethodInstance()->getLastTransId();
$billing_address = $order->getBillingAddress()->getData();
$shipping_address = $order->getShippingAddress()->getData();
$street = $billing_address['street'];
$street = preg_replace(array('/\n/', '/\r/'), '#PH#', $street);
$street1 = $shipping_address['street'];
$street1 = preg_replace(array('/\n/', '/\r/'), '#PH#', $street1);
$street_billing = explode('#PH#', $street);
$street_shipping = explode('#PH#', $street1);

$is_guest = 1;
if($order->getData('customer_id') && $order->getData('customer_id')>0){
    $is_guest = 0;
}
$items = [];
foreach ($order->getAllVisibleItems() as $item) {
    $items[] = array(
        'product_id'        =>  $item->getProductId(),
        'sku'			    =>  $item->getSku(),
        'name'              =>  $item->getName(),
        'qty'               =>  $item->getQtyOrdered(),
		'price'			    =>  $item->getPrice(),
		'tax_percent'	    =>  $item->getTaxPercent(),
		'tax_amount'	    =>  $item->getTaxAmount(),
		'discount_percent'  =>  $item->getDiscountPercent(),
		'discount_amount'   =>	$item->getDiscountAmount(),
		'row_total'         =>  $item->getRowTotal(),
        'options'           =>  $item->getProductOptions()
    );
}
$array_res = [
    'currency_id'  => $order->getData('order_currency_code'),
    'email'        => $order->getData('customer_email'),
    'is_guest'	=> $is_guest,
    'customer' => [
        'firstname'    => $order->getData('customer_firstname'),
        'lastname'     => $order->getData('customer_lastname')
    ],
    'address' =>[
        'billing' => [
            'firstname'    => $billing_address['firstname'],
            'lastname'     => $billing_address['lastname'],
            'company' => $billing_address['company'],
            'street' => $street_billing,
            'city' => $billing_address['city'],
            'country_id' =>  $billing_address['country_id'],
            'region' => (isset($billing_address['region'])?$billing_address['region']:''),
			'region_id' => (isset($billing_address['region_id'])?$billing_address['region_id']:''),
            'postcode' => $billing_address['postcode'],
            'telephone' => $billing_address['telephone'],
            'fax' => ''
        ],
        'shipping' => [
            'firstname'    => $shipping_address['firstname'],
            'lastname'     => $shipping_address['lastname'],
            'company' => $shipping_address['company'],
            'street' => $street_shipping,
            'city' => $shipping_address['city'],
            'country_id' =>  $shipping_address['country_id'],
            'region' => (isset($shipping_address['region'])?$shipping_address['region']:''),
			'region_id' => (isset($shipping_address['region_id'])?$shipping_address['region_id']:''),
            'postcode' => $shipping_address['postcode'],
            'telephone' => $shipping_address['telephone'],
            'fax' => ''
        ]
    ],
    'shipping'=>[
        'shipping_method'=> $order->getShippingMethod(),
        'shipping_description' => $order->getShippingDescription()
    ],
    'items'=> $items,
    'increment_id' => $order->getIncrementId(),
    'payment' => [
        'method' => $order->getPayment()->getMethodInstance()->getCode(),
        'transaction_id' => $last_transaction_id,
        'additional_information' => $order->getPayment()->getAdditionalInformation()
    ],
	'base_grand_total'=>$order->getBaseGrandTotal(),
	'grand_total'=>$order->getGrandTotal(),
	'base_subtotal'=>$order->getBaseSubtotal(),
	'subtotal'=>$order->getSubtotal(),
	'base_tax_amount'=>$order->getBaseTaxAmount(),
	'tax_amount'=>$order->getTaxAmount(),
	'base_discount_amount'=>$order->getBaseDiscountAmount(),
	'discount_amount'=>$order->getDiscountAmount(),
	'base_subtotal'=>$order->getBaseSubtotal(),
	'subtotal'=>$order->getSubtotal(),
	'total_qty_ordered'=>$order->getTotalQtyOrdered(),
	'shipping_amount'=>$order->getShippingAmount(),
	'shipping_tax_amount'=>$order->getShippingTaxAmount(),
	'total_paid'=>$order->getTotalPaid(),
	'status'=>$order->getStatus(),
	'state'=>$order->getState(),
	'created_at'=>$order->getCreatedAt(),
];
$orderComments = $order->getAllStatusHistory();
if(count($orderComments)){
	$comment_arr = [];
    foreach ($orderComments as $comment) {
        $body = $comment->getData('comment');
		if($body==''){
			continue;
		}
        $comment_arr[] = array('comment'=>$body,'created_at'=>$comment->getCreatedAt());
    }
	$array_res['comments'] = $comment_arr;
}
	$final_array['orders'][$order->getIncrementId()] = $array_res;
	if ($order->hasInvoices()) {
		$invoices = array();
		foreach ($order->getInvoiceCollection() as $invoice) {
	//		$final_array['orders'][$order->getIncrementId()]['invoice'][$invoice->getIncrementId()]
			$invoices[$invoice->getIncrementId()]['id'] = $invoice->getId();
			foreach ($invoice->getAllItems() as $item) {
				$invoices[$invoice->getIncrementId()]['item'][$item->getId()] = array('sku'=>$item->getSku(),'qty'=>$item->getQty(),'options'=>$item->getProductOptions());
            }
            $invoices[$invoice->getIncrementId()]['shipping_amount'] = $invoice->getShippingAmount();
            $invoices[$invoice->getIncrementId()]['subtotal'] = $invoice->getSubtotal();
            $invoices[$invoice->getIncrementId()]['base_subtotal'] = $invoice->getBaseSubtotal();
            $invoices[$invoice->getIncrementId()]['grand_total'] = $invoice->getGrandTotal();
            $invoices[$invoice->getIncrementId()]['base_grand_total'] = $invoice->getBaseGrandTotal();
			$invoices[$invoice->getIncrementId()]['created_at'] = $invoice->getCreatedAt();
		}
		$final_array['orders'][$order->getIncrementId()]['invoices'] = $invoices;
	}
    $shipments_arr = array();
    
	foreach ($order->getShipmentsCollection() as $shipment) {
	//		$final_array['orders'][$order->getIncrementId()]['invoice'][$invoice->getIncrementId()]
			$shipments_arr[$shipment->getIncrementId()]['id'] = $shipment->getId();
			foreach ($shipment->getAllItems() as $item) {
				$shipments_arr[$shipment->getIncrementId()]['item'][$item->getId()] = array('sku'=>$item->getSku(),'qty'=>$item->getQty());
            }
            $tracknums=[];
            foreach($shipment->getAllTracks() as $tracknum)
            {
                if($tracknum->getNumber()==''){continue;}
                $tracknums[$tracknum->getNumber()]=array('title'=>$tracknum->getTitle(),'carrier_code'=>$tracknum->getCarrierCode(),'created_at'=>$tracknum->getCreatedAt());
            }
			$shipments_arr[$shipment->getIncrementId()]['created_at'] =  $shipment->getCreatedAt();;
            $shipments_arr[$shipment->getIncrementId()]['tracking_numbers'] = $tracknums;
		}
	$final_array['orders'][$order->getIncrementId()]['shipments'] = $shipments_arr;
}
//echo json_encode($final_array);
$filename  = "myfile_$page.json";
file_put_contents($filename, json_encode($final_array));
/* Read json file */
//$strJsonFileContents = file_get_contents("myfile.json");
echo "<pre>";
//print_r(json_decode($strJsonFileContents,true));
echo "</pre>";
die('findesss');

if ($order->getData('entity_id')) {
    $csv = Mage::helper('emailattachments')->getCsv($order_id);
    header("Content-type: text/csv");
    header("Content-Disposition: attachment; filename=" . $order->getIncrementId() . ".csv");
    header("Pragma: no-cache");
    header("Expires: 0");
    echo $csv;
} else {
    die('Order not found!');
}

?>