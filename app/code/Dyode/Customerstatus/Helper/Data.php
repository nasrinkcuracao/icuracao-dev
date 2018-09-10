<?php
namespace Dyode\Customerstatus\Helper;

/**
 * CustomerStatus Helper
 * @category Dyode
 * @package  Dyode_Customerstatus
 * @module   Customerstatus
 * @author   Nithin
 */
class Data extends \Magento\Framework\App\Helper\AbstractHelper
{
    public $soft;

    public $customerStatus;

    public $addressMismatch;

    //Check status of a customer
    public function checkCustomerStatus($order, $customerId)
    {
        $Customer_Status = $this->isCustomerActive($customerId);
        $this->soft = $Customer_Status->DATA->SOFT;
        $transactionAllowed = $Customer_Status->OK;
        if ($transactionAllowed && !$this->soft) {
            $this->customerStatus = true;
            //shipping address validation
            $this->validateAddress($order, $customerId);
        } else {
            //activate customer account
            $this->reActivateAccount($order, $customerId);
        }

        $status['customerstatus'] = $this->customerStatus;
        $status['addressmismatch'] = $this->addressMismatch;
        $status['soft'] = $this->soft;
        return json_encode($status);
    }

    //Reactivate a customer account
    public function reActivateAccount($order, $customerId)
    {
        $activateAccount = $this->estimateOk($customerId);
        $resultCode = $activateAccount->DATA->CODE;
        //set Customer_status if resultCode is 0
        if ($resultCode == '0') {
            $this->customerStatus = true;
            $this->validateAddress($order, $customerId);
        } else {
            $this->customerStatus = false;
            //Cancel order with reason 'UA-014'
            $order->cancel();
            $order->addStatusHistoryComment('UA-014');
            $order->save();
            //Notify Customer
        }
    }

    //validate shipping address with default customer address from AR
    public function validateAddress($order, $customerId)
    {
        $shippingAddress = $order->getShippingAddress()->getData();
        $shipping_street = $shippingAddress['street'];
        $shipping_zip = substr($shippingAddress['postcode'], 0, 5);
        //Get customer address from AR
        $defaultCustomerAddress = $this->getCustomerContact($customerId);
        $defaultZip = substr($defaultCustomerAddress->ZIP, 0, 5);
        $defaultStreet = $defaultCustomerAddress->STREET;
        if ($shipping_street == $defaultStreet && $shipping_zip == $defaultZip) {
            //Mark Address in Magento as valid
            $this->addressMismatch = false;
        } else {
            //Set Address Mismatch
            $this->addressMismatch = true;
        }
    }

    //API for getting customer contactaddress
    public function getCustomerContact($customerId)
    {
        $httpHeaders = new \Zend\Http\Headers();
        $httpHeaders->addHeaders([
           'Accept' => 'application/json',
           'Content-Type' => 'application/json',
           'X-Api-Key' => 'TEST-WNNxLUjBxA78J7s'
        ]);

        $request = new \Zend\Http\Request();
        $request->setHeaders($httpHeaders);
        $request->setUri("https://exchangeweb.lacuracao.com:2007/ws2/test/restapi/ecommerce/GetCustomerContact?cust_id=$customerId");
        $request->setMethod(\Zend\Http\Request::METHOD_GET);

        $client = new \Zend\Http\Client();
        $options = [
           'adapter'   => 'Zend\Http\Client\Adapter\Curl',
           'curloptions' => [CURLOPT_FOLLOWLOCATION => true],
           'maxredirects' => 0,
           'timeout' => 30
        ];
        $client->setOptions($options);
        $response = $client->send($request);
        $data = json_decode($response->getBody());
        return $data->DATA;
    }

    //API for checking whether a customer account is active
    public function isCustomerActive($customerId)
    {
          $httpHeaders = new \Zend\Http\Headers();
          $httpHeaders->addHeaders([
             'Accept' => 'application/json',
             'Content-Type' => 'application/json',
             'X-Api-Key' => 'TEST-WNNxLUjBxA78J7s'
          ]);

          $request = new \Zend\Http\Request();
          $request->setHeaders($httpHeaders);
          $request->setUri("https://exchangeweb.lacuracao.com:2007/ws2/test/restapi/ecommerce/IsCustomerActive?cust_id=$customerId");
          $request->setMethod(\Zend\Http\Request::METHOD_GET);

          $client = new \Zend\Http\Client();
          $options = [
             'adapter'   => 'Zend\Http\Client\Adapter\Curl',
             'curloptions' => [CURLOPT_FOLLOWLOCATION => true]
          ];
          $client->setOptions($options);
          $response = $client->send($request);
          $data = json_decode($response->getBody());
          return $data;
    }

    //API for reactivating a customer account
    public function estimateOk($customerId)
    {
        $httpHeaders = new \Zend\Http\Headers();
        $httpHeaders->addHeaders([
           'Accept' => 'application/json',
           'Content-Type' => 'application/json',
           'X-Api-Key' => 'TEST-WNNxLUjBxA78J7s'
        ]);

        $request = new \Zend\Http\Request();
        $request->setHeaders($httpHeaders);
        $request->setUri("https://exchangeweb.lacuracao.com:2007/ws2/test/restapi/ecommerce/EstimateOk?cust_id=$customerId");
        $request->setMethod(\Zend\Http\Request::METHOD_GET);

        $client = new \Zend\Http\Client();
        $options = [
           'adapter'   => 'Zend\Http\Client\Adapter\Curl',
           'curloptions' => [CURLOPT_FOLLOWLOCATION => true],
           'maxredirects' => 0,
           'timeout' => 30
        ];
        $client->setOptions($options);
        $response = $client->send($request);
        $data = json_decode($response->getBody());
        return $data;
    }
}