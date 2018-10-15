<?php
namespace Dyode\InventoryUpdate\Model;

use \Magento\Framework\Model\AbstractModel;

class Inventory extends \Magento\Framework\View\Element\Template {

   protected $_productCollectionFactory;

   protected $_productRepository;

   protected $products;

   //Not Domestic locations
   public $locations = array('01', '09', '16', '22', '29', '33', '35', '38', '40', '51', '57', '64');  

   public $productSKUs = array();

   public $productIDs = array();

   public $list = array(); 

   public $batchInventory = array();

   public $pending = array();  

   public $thresh = array();

   public $pendingthreshold = array();

   public function __construct(
	\Magento\Framework\View\Element\Template\Context $context,  
	\Magento\Catalog\Model\ResourceModel\Product\CollectionFactory $productCollectionFactory,  
    \Magento\Catalog\Api\ProductRepositoryInterface $productRepository,
	\Magento\Sales\Model\ResourceModel\Order\CollectionFactory $orderCollectionFactory, 
	\Dyode\InventoryLocation\Model\LocationFactory  $inventoryLocation,
	\Magento\CatalogInventory\Api\StockRegistryInterface $stockRegistry, 
	\Dyode\InventoryUpdate\Helper\Data $helper,
	\Dyode\AuditLog\Model\ResourceModel\AuditLog $auditLog,
	\Dyode\Threshold\Model\Threshold $thresholdModel,
	array $data = []
	) {
	    $this->_productCollectionFactory = $productCollectionFactory;  
	    $this->_orderCollectionFactory = $orderCollectionFactory;
	    $this->_productRepository = $productRepository;
	    $this->helper = $helper;
	    $this->inventorylocation = $inventoryLocation;
	    $this->threshold = $thresholdModel;
	    $this->_stockRegistry = $stockRegistry;	
        $this->auditLog = $auditLog;
	    parent::__construct($context, $data);
	}

	public function updateInventory() {
		try {
			$clientIP = $_SERVER['REMOTE_ADDR'];
			$products = $this->getProducts();
			foreach ($products as $product) {
				$productSKU = trim($product->getSku());
				$productId = trim($product->getId());
				$this->list[] = $productSKU;
				$this->productSKUs[$productSKU] = array();
				$this->productIDs[$productSKU] = $productId;
			}
			$this->processBatchInventory();
			$this->getAllPending();	
			$this->getAllThreshold();
			$this->processThreshold();
			$this->executeProductSkus();
	        $this->auditLog->saveAuditLog([
	            'user_id' => 'admin',
	            'action' => 'non set inventory update',
	            'description' => 'inventory successfully updated',
	            'client_ip' => $clientIP,
	            'module_name' => 'dyode_inventoryupdate'
        	]);
	    } catch (\Exception $exception) {
	        $this->auditLog->saveAuditLog([
	            'user_id' => 'admin',
	            'action' => 'non set inventory update',
	            'description' => $exception->getMessage(),
	            'client_ip' => $clientIP,
	            'module_name' => 'dyode_inventoryupdate'
        	]);
	    }
	}	

	//get product collection
	public function getProducts() {
	    $productCollection = $this->_productCollectionFactory->create();
        $productCollection->addAttributeToSelect('*')
                          ->addAttributeToFilter('inventorylookup', 500)
					      ->addAttributeToFilter('set', 0)
					      ->addAttributeToFilter('vendorId', 2139); 
        return $productCollection;
	}

	//processing the API responses
	public function processBatchInventory(){
		$arInventory = $this->helper->getStock();
		if($arInventory){
		if($arInventory->OK){
			foreach ($arInventory->LIST as $item) {
				$sku = trim( $item->item_id ); //product sku
				foreach ($item->stock as $location => $quantity) {
					$store = $location; //store location
			        $stock = $quantity; //current stock from AR
			        $this->productSKUs[$sku][$store] = $stock;
				}
			}
		}
		if ($arInventory->CONTINUE) {
			$this->processBatchInventory();
		}
	    }
	}	

	// Get all pending order items
	public function getAllPending(){	
		$to = date("Y-m-d h:i:s"); // current date
    	$from = strtotime('-60 day', strtotime($to));
    	$from = date('Y-m-d h:i:s', $from); //60 days before
    	$orders = $this->_orderCollectionFactory->create()->addFieldToSelect('*')
    	->addFieldToFilter('status',['nin' => array('incomplete','canceled','complete','closed')])
	    ->addFieldToFilter('created_at', array('from'=>$from, 'to'=>$to));

	    $this->pending = unserialize(serialize($this->productSKUs));
	   
	    foreach ($orders as $order) {
	    	$orderItems = $order->getAllItems();
		    foreach ($orderItems as $items) {
		    	$pending = $items['qty_ordered'] - $items['qty_invoiced']; 
		    	$sku = trim( $items['sku'] );
				$store = $items['store_id'];
				if(isset($this->pending[$sku][$store])){
					$this->pending[$sku][$store] -= $pending;
					if ($this->pending[$sku][$store] < 0){ $this->pending[$sku][$store] = 0; }
				}
		    }
	    }

	}

	//Get all threshold values for the products
	public function getAllThreshold(){

		$data = $this->threshold->getThreshold();
		if($data)
		{
			foreach ($data as $item) {	
				$department = $item['Sub Departments Name'];
				$sku = trim( $item['Sub Code'] );
				
				if(!isset($sku) || empty($sku)){				
					// Department
					$this->thresh[$department] = $item['Threshold'];
				}else{				
					// Sku
					$this->thresh[$sku] = $item['Threshold'];
				}
			}
		}
	}

	public function processThreshold(){
		$this->pendingthreshold = unserialize(serialize($this->pending));
		foreach ($this->pendingthreshold as $sku => $locations){
			foreach ($locations as $location => $pendingInLoc){
				if ($location !== 33){
					$trimmedSku = substr($sku, 0, 3);
					$trimmedSku = trim($trimmedSku); // To fix threshold 0 issue with 2-character departments
					if (isset($this->thresh[$sku])){
						$threshold = $this->thresh[$sku];
					}
					else if (isset($this->thresh[$trimmedSku])){
						$threshold = $this->thresh[$trimmedSku];
					}else{
						$threshold = 0;
					}

					$this->pendingthreshold[$sku][$location] -= (int)$threshold;
					if ($this->pendingthreshold[$sku][$location] < 0){	
						$this->pendingthreshold[$sku][$location] = 0; 
					}
				}
			}
		}
	}

	//execute all available products in the inventory
	public function executeProductSkus(){
		$products = $this->getProducts();
		foreach ($products as $product)
		{		
			if (isset($this->productSKUs[$product->getSku()]))
			{
				$sku = $product->getSku();
				$eid = $this->productIDs[$sku];
				if (count($this->productSKUs[$sku]) == 0)
				{
										
				}
				else
				{
					$jsonAR_inv = json_encode($this->productSKUs[$sku], true);
					$jsonAR_invAfterPending = json_encode($this->pending[$sku], true);
					$jsonAR_invAfterPendingAndThreshold = json_encode($this->pendingthreshold[$sku], true);
					$finalInv = max($this->pendingthreshold[$sku]);
					$finalLocation = array_search($finalInv, $this->pendingthreshold[$sku]);
					
					// Company-wide Inventory
					$inventory_values = array_values($this->pendingthreshold[$sku]);
					$company_wide_inventory = array_sum($inventory_values);

					$locationInventory = $this->inventorylocation->create();
					$categoryModel = $locationInventory->load($product->getID(), 'productid');
					$data = $categoryModel->getData();
					if($data){
						$model = $locationInventory->load($data['id']);
        				$model->setArinventory($jsonAR_inv);
        				$model->setInventoryafterpending($jsonAR_invAfterPending);
        				$model->setFinalinventory($jsonAR_invAfterPendingAndThreshold);
        				$model->setProductid($product->getID());
        				$model->setProductsku($product->getSku());
        				$saveData = $model->save();	
					} else {
						$locationInventory->addData([
						"productid" => $product->getID(),
						"productsku" => $sku,
						"isset" => 0,
						"arinventory" => $jsonAR_inv,
						"inventoryafterpending" => $jsonAR_invAfterPending,
						"finalinventory" => $jsonAR_invAfterPendingAndThreshold
						]);
			        	$saveData = $locationInventory->save();
					}
					
			        $stockItem=$this->_stockRegistry->getStockItem($product->getID());

			        if ($product->getArStatus() =='D') {
			        	$product->setStatus(0);
			        	$product->setVisibiity(1);
			        	$product->setInventorylookup('499');
			        	//$product->setCron('15');
			        	$product->save();
			        	continue;
			        }

			        if ($product->getArStatus() =='Z') {
			        	$product->setStatus(0);
			        	$product->setVisibiity(1);
			        	$product->save();
			        	continue;
			        }

			        if ($product->getArStatus() =='R' && $company_wide_inventory < 5) {
			        	$product->setStatus(0);
			        	$product->setVisibiity(1);
			        	$product->save();
			        	continue;
			        }

			        if($finalInv > '0'){
			        	$product->setStatus(1);
			        	$product->setVisibiity(4);
			        	$product->setOosDate('');
			        	$product->save();
			        	$stockItem->setQty($finalInv);
			        } else {
			        	$product->setStatus(0);
			        	$product->setVisibiity(1);
			        	$product->setOosDate(date("Y-m-d 00:00:00"));
			        	$product->setInventorylookup('500');
			        	$product->save();
			        	$stockItem->setQty('0');
			        }
					$stockItem->setIsInStock((bool)$finalInv); 
					$stockItem->save();
					unset($jsonAR_inv);
					unset($jsonAR_invAfterPending);
					unset($jsonAR_invAfterPendingAndThreshold);
				}
			}
		}
	}
	
}
