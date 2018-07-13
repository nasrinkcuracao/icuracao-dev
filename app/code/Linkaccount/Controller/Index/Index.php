<?php


namespace Dyode\Linkaccount\Controller\Index;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\View\Result\PageFactory;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\View\Element\Messages;

class Index extends Action
{

    protected $resultPageFactory;
    protected $customer;
    protected $storeManager;
    protected $_coreSession;
    protected $helper;
    protected $resultFactory;
    protected $customerSession;
     /**
     * @var \Magento\Framework\Message\ManagerInterface
     */
    protected $_messageManager;
    /**
     * Constructor
     *
     * @param \Magento\Framework\App\Action\Context  $context
     * @param \Magento\Framework\View\Result\PageFactory $resultPageFactory
     */
    public function __construct(
        Context $context,
        PageFactory $resultPageFactory,
        \Magento\Customer\Model\Customer $customer,
        \Magento\Customer\Model\Session $customerSession,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\Framework\Session\SessionManagerInterface $coreSession,
        \Dyode\ARWebservice\Helper\Data $helper,
        \Magento\Framework\Message\ManagerInterface $messageManager,
        ResultFactory $resultFactory
    ) {
        parent::__construct($context);
        $this->resultPageFactory = $resultPageFactory;
        $this->customer = $customer;
        $this->storeManager = $storeManager;
        $this->_coreSession = $coreSession;
        $this->helper = $helper;
        $this->_messageManager = $messageManager;
        $this->resultFactory = $resultFactory;
        $this->customerSession = $customerSession;
    }

    /**
     * Execute view action
     *
     * @return \Magento\Framework\Controller\ResultInterface
     */
    public function execute()
    {      
        echo "here";
        exit;   
        if($this->customerSession->isLoggedIn()){
            //Redirect to Credit linking if already logged in
            $resultRedirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);
            $resultRedirect->setPath('linkaccount/credit/index');
            return $resultRedirect;
        } else {    
            $postVariables = (array) $this->getRequest()->getPost();
            $resultRedirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);
            if(!empty($postVariables)){
                $postVariables['curacao_account'] = '51333768';
                $postVariables['email'] = "cservice@icuracao.com";
                $postVariables['password'] = "kavi@123";
                $alreadyExist = 0;
                $result = '';
                if(count($postVariables)> 0){
                    $accountNumber = $postVariables['curacao_account'];
                    $email = $postVariables['email'];
                    $password = $postVariables['password'];
                    $accountInfo = false;
                    
                    //check already existing in Magento
                    $websiteId = $this->getCurrentWebsiteId();
                    $customer = $this->customerExists($email,$websiteId);
                
                    if($customer){
                        //Check customer already updated the curacao 
                        $curaAccId = $customer->getCuracaocustid();
                        if($curaAccId != '' && $curaAccId == $accountNumber){
                            //Already linked with the Magento Account
                            $this->_messageManager->addError(__('We were unable to submit your request. Please try again!'));
                            return $this->_redirect('linkaccount/index');
                        }
                        //Verify Credit Account Infm
                        $accountInfo   =  $this->helper->getARCustomerInfoAction($accountNumber);
                       
                        if($accountInfo !== false){
                            
                            $resultRedirect->setPath('linkaccount/verify/index');
                            return $resultRedirect;
                        }

                    } else{
                        //Redirect to Registration
                        $resultRedirect->setPath('customer/account/create');
                        return $resultRedirect;
                    }

                //  $this->getCoreSession()->setAttemptId(1);
                }
            }
        }
        return $this->resultPageFactory->create();    
    }

    /**
     * @param string     $email
     * @param null $websiteId
     *
     * @return bool|\Magento\Customer\Model\Customer
     */
    public function customerExists($email, $websiteId = null)
    {
        $customer = $this->customer;
        if ($websiteId) {
            $customer->setWebsiteId($websiteId);
        }
        $customer->loadByEmail($email);
        if ($customer->getId()) {
            return $customer;
        }

        return false;
     }
    /*== Returns the current website Id ==*/
    public function getCurrentWebsiteId(){
        return $this->storeManager->getStore()->getWebsiteId();
    }

    public function getCoreSession() 
    {
        return $this->_coreSession;
    }

   /* public function setValue(){
        $this->_coreSession->start();
        $this->_coreSession->setMessage('The Core session');
    }
    
    public function getValue(){
        $this->_coreSession->start();
        return $this->_coreSession->getMessage();
    }
    
    public function unSetValue(){
        $this->_coreSession->start();
        return $this->_coreSession->unsMessage();
    }*/

    /**
     * @param string $email
     * @param int  $curacao_account_number
     *
     * @return bool|\Magento\Customer\Model\Customer
    */

}

