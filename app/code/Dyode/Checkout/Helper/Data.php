<?php
/*
* @Date: 26/09/2018
* @package   Dyode_Checkout
* @author    kavitha@dyode.com
*/

namespace Dyode\Checkout\Helper;

class Data extends \Magento\Framework\App\Helper\AbstractHelper
{
    protected $_locationRepo;
    public function __construct(
      \Dyode\StoreLocator\Model\GeoCoordinateRepository $locationRepo
    ){
        $this->_locationRepo = $locationRepo;
    }
  /* function for setting the price for quote item */

  public function setQuoteItemPrice($weight){

    // Default Shipping Values
    $price = 10.28;
    $upsSwith = 3;
    $adsSwitch = 88;

    $zipcode = '90255';

    // Set Default Dimension Values in case they are missing from request
    $width  =  10;
    $height = 10;
    $length = 10;
    $zipcode = ($zipcode>0) ? $zipcode : '90015';

  //  $stateInfo = $this->getStateDetails($zipcode);
    $result = $this->_locationRepo->getById($zipcode);


    $countryCode = $result['abbr'];
    if(in_array($countryCode, ['CA','NV','AZ']))
    {
        $upsSwith = 11;
        $adsSwitch = 133;
        $volume = $width * $height * $length;
        // Price is same for volume up to 4000 inch Square for local deliveries
        if( $volume <= 4000 ) {
            $width = 20;
            $height = 20;
            $length = 10;
        }
    }

    if ($weight >= $adsSwitch) {
        if($this->checkMomentum($zipcode)){
          $price = 99;
          $carrier = 'Momentum';
          $service = 'Ground';
        }
    }
     return $price;
  }

  /* OutOfBoundsException to get the city code*/
  public function getStateDetails($zipcode){
      $result = $this->_locationRepo->getById($zipcode);
      $returnVal = array();
      $returnVal['abbr'] = $result->getAbbr();
      $returnVal['city'] = $result->getCity();
      return $returnVal;
  }

  public function checkMomentum($zipcode){
    $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
    $resource = $objectManager->get('Magento\Framework\App\ResourceConnection');
    $connection = $resource->getConnection();
    $momentumTable = $resource->getTableName('momentum_zipcodes');

    $query ="SELECT * FROM ".$momentumTable."  WHERE zip_code = '".$zipcode."' ORDER BY id DESC LIMIT 1";
    $result = $connection->query($query);
    if($result)
      return 1;
    else
      return 0;
  }
}
?>