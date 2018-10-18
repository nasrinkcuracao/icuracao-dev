<?php
/**
 * Dyode_ARWebservice Magento2 Module.
 *
 *
 * @package Dyode
 * @module  Dyode_ARWebservice
 * @author  Kavitha <kavitha@dyode.com>
 * @date    12/07/2018
 * @copyright Copyright © Dyode
 */
namespace Dyode\Checkout\Controller\Curacao;

use Magento\Customer\Model\ResourceModel\CustomerRepository;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Customer\Api\Data\CustomerInterface;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\Controller\ResultFactory;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\Encryption\EncryptorInterface;
use Dyode\ARWebservice\Helper\Data as ARWebserviceHelper;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Model\StoreManagerInterface;

class Scrutinize extends Action
{
    /**
     * @var \Dyode\ARWebservice\Helper\Data
     */
    protected $_helper;

    /**
     * @var \Magento\Framework\Controller\ResultFactory
     */
    protected $_resultFactory;

    /**
     * @var \Magento\Checkout\Model\Session
     */
    protected $_checkoutSession;

    /**
     * @var \stdClass
     */
    protected $verifyResult;

    /**
     * @var \Magento\Customer\Model\Session
     */
    protected $_customerSession;

    /**
     * @var \Magento\Customer\Model\ResourceModel\CustomerRepository
     */
    protected $customerRepository;

    /**
     * @var \Magento\Customer\Api\Data\CustomerInterface
     */
    protected $customerData;

    /**
     * @var \Magento\Framework\Encryption\EncryptorInterface
     */
    protected $encryptor;

    /**
     * @var \Magento\Customer\Model\Customer
     */
    protected $customer;

    /**
     * @var \Magento\Store\Model\StoreManagerInterface
     */
    protected $storeManager;

    /**
     * @var string
     */
    protected $curacaoIdAttribute = 'curacaocustid';

    /**
     * Scrutinize constructor.
     *
     * @param \Magento\Framework\App\Action\Context $context
     * @param \Magento\Framework\Controller\ResultFactory $resultFactory
     * @param \Magento\Store\Model\StoreManagerInterface $storeManager
     * @param \Dyode\ARWebservice\Helper\Data $helper
     * @param \Magento\Checkout\Model\Session $checkoutSession
     * @param \Magento\Customer\Model\Session $customerSession
     * @param \Magento\Customer\Model\ResourceModel\CustomerRepository $customerRepository
     * @param \Magento\Customer\Api\Data\CustomerInterface $customerData
     * @param \Magento\Framework\Encryption\EncryptorInterface $encryptor
     */
    public function __construct(
        Context $context,
        ResultFactory $resultFactory,
        StoreManagerInterface $storeManager,
        ARWebserviceHelper $helper,
        CheckoutSession $checkoutSession,
        CustomerSession $customerSession,
        CustomerRepository $customerRepository,
        CustomerInterface $customerData,
        EncryptorInterface $encryptor
    ) {
        $this->_resultFactory = $resultFactory;
        $this->storeManager = $storeManager;
        $this->_helper = $helper;
        $this->_checkoutSession = $checkoutSession;
        $this->_customerSession = $customerSession;
        $this->customerRepository = $customerRepository;
        $this->customerData = $customerData;
        $this->encryptor = $encryptor;

        parent::__construct($context);
    }

    /**
     * @inheritdoc
     */
    public function execute()
    {
        $postVariables = (array)$this->getRequest()->getPost();

        if (!empty($postVariables)) {
            try {
                $this->validateUserInformation();
                $this->linkUser();
                $this->_checkoutSession->setCuracaoCustomerId($this->customer->getId());
                return $this->successResponse();
            } catch (\Exception $exception) {
                return $this->verificationFailedResponse();
            }
        }

        return $this->verificationFailedResponse();
    }

    /**
     * Send ARWebservice API request in order to verify user information.
     *
     * @return $this
     * @throws \Exception
     */
    public function validateUserInformation()
    {
        /** @var \Magento\Framework\DataObject $curacaoInfo */
        $curacaoInfo = $this->_checkoutSession->getCuracaoInfo();
        $ssnLast = $this->getRequest()->getParam('ssn_last', false);
        $zipCode = $this->getRequest()->getParam('zip_code', false);
        $maidenName = $this->getRequest()->getParam('maiden_name', false);
        $dob = $this->getRequest()->getParam('date_of_birth', false);
        $postData = array(
            'cust_id' => $curacaoInfo->getAccountNumber(),
            'amount'  => 1, //this field is mandatory and hence put a sample value;
            'ssn'     => $ssnLast,
            'zip'     => $zipCode,
            'dob'     => $dob,
            'mmaiden' => $maidenName,
        );

        //send api call to collect user info.
        $this->verifyResult = $this->_helper->verifyPersonalInfm($postData);

        if ($this->verifyResult == false) {
            throw new \Exception('Api failed');
        }

        return $this;
    }

    /**
     * Link a curacao identification number to a customer.
     *
     * Here a customer account will be created if the user does not exist in the website.
     *
     * @return $this
     * @throws \Magento\Framework\Exception\InputException
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws \Magento\Framework\Exception\State\InputMismatchException
     */
    public function linkUser()
    {
        /** @var \Magento\Framework\DataObject $curacaoInfo */
        $curacaoInfo = $this->_checkoutSession->getCuracaoInfo();

        if ($this->_customerSession->isLoggedIn()) {
            //linking curacao id to the logged in customer.
            $customerId = (int)$this->_customerSession->getCustomerId();
            $this->customerData->setId($customerId)
                ->setCustomAttribute($this->curacaoIdAttribute, $curacaoInfo->getAccountNumber());
            $this->customer = $this->customerRepository->save($this->customerData);
        } else {
            $store = $this->storeManager->getStore();
            $hashPassword = $this->encryptor->encrypt($curacaoInfo->getPassword());
            try {
                //this scenario should not occur.
                if (!$curacaoInfo->getEmailAddress()) {
                    throw new NoSuchEntityException(__('Customer email address cannot be empty'));
                }

                //checks whether a customer with the given email address does exists in the website.
                $customerData = $this->customerRepository->get($curacaoInfo->getEmailAddress());
                $customerData->setCustomAttribute($this->curacaoIdAttribute, $curacaoInfo->getAccountNumber());

                //linking curacao id with the existing customer.
                $this->customer = $this->customerRepository->save($customerData, $hashPassword);
            } catch (NoSuchEntityException $exception) {
                //Confirmed customer is a guest; so let's create a new customer account for the user.
                $this->customerData->setEmail($curacaoInfo->getEmailAddress())
                    ->setFirstname($curacaoInfo->getFirstName())
                    ->setLastname($curacaoInfo->getLastName())
                    ->setStoreId($store->getStoreId())
                    ->setWebsiteId($this->storeManager->getStore()->getWebsiteId())
                    ->setCustomAttribute($this->curacaoIdAttribute, $curacaoInfo->getAccountNumber());

                //linking curacao id with the newly created customer.
                $this->customer = $this->customerRepository->save($this->customerData, $hashPassword);
            }
        }

        return $this;
    }

    /**
     * Fail response.
     *
     * @return \Magento\Framework\Controller\ResultInterface
     */
    protected function verificationFailedResponse()
    {
        $result = $this->_resultFactory->create(ResultFactory::TYPE_JSON);
        $result->setData([
            'message' => 'Curacao account verification failed. Please try later.',
            'type'    => 'error',
        ]);

        return $result;
    }

    /**
     * Success response.
     *
     * @return \Magento\Framework\Controller\ResultInterface
     */
    protected function successResponse()
    {
        $output = $this->prepareResponse();
        $result = $this->_resultFactory->create(ResultFactory::TYPE_JSON);
        $result->setData([
            'data' => $output,
            'type' => 'success',
        ]);
        return $result;
    }

    /**
     * Response data.
     *
     * @return array
     */
    protected function prepareResponse()
    {
        return [
            'email' => $this->customer->getEmail(),
        ];
    }
}
