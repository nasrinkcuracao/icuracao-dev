<?php
/**
 * Mirasvit
 *
 * This source file is subject to the Mirasvit Software License, which is available at https://mirasvit.com/license/.
 * Do not edit or add to this file if you wish to upgrade the to newer versions in the future.
 * If you wish to customize this module for your needs.
 * Please refer to http://www.magentocommerce.com for more information.
 *
 * @category  Mirasvit
 * @package   mirasvit/module-rma
 * @version   2.0.25
 * @copyright Copyright (C) 2018 Mirasvit (https://mirasvit.com/)
 */



namespace Mirasvit\Rma\Controller\Rma;

class SaveTest extends \Magento\TestFramework\TestCase\AbstractController
{
    /** @var \Magento\Customer\Api\AccountManagementInterface */
    private $accountManagement;

    /**
     * setUp.
     */
    protected function setUp()
    {
        parent::setUp();
        $logger = $this->getMock('Psr\Log\LoggerInterface', [], [], '', false);
        $session = \Magento\TestFramework\Helper\Bootstrap::getObjectManager()->create(
            'Magento\Customer\Model\Session',
            [$logger]
        );
        $this->accountManagement = \Magento\TestFramework\Helper\Bootstrap::getObjectManager()->create(
            'Magento\Customer\Api\AccountManagementInterface'
        );
        $customer = $this->accountManagement->authenticate('customer@example.com', 'password');
        $session->setCustomerDataAsLoggedIn($customer);
    }


    /**
     * @magentoDataFixture Magento/Sales/_files/order_with_customer.php
     * @covers  Mirasvit\Rma\Controller\Rma\Save::execute
     */
    public function testSaveAction()
    {
        /** @var \Magento\Sales\Model\Order $order */
        $order = $this->_objectManager->create('Magento\Sales\Model\Order');
        $order->loadByIncrementId('100000001');
        $items = $order->getItems();
        $item = array_pop($items);

        $data = [
            'order_id' => $order->getId(),
            'items' => [
                $item->getProductId() => [
                            'is_return' => '1', 'qty_requested' => '1',
                            'reason_id' => '2', 'condition_id' => '1', 'resolution_id' => '1',
                        ],
                    ],
            'message' => 'Additional',
        ];
        $this->getRequest()->setParams($data);
        $this->dispatch('rma/rma/save');
        $this->assertNotEquals('noroute', $this->getRequest()->getControllerName());
        $this->assertTrue($this->getResponse()->isRedirect());
        $this->assertSessionMessages(
            $this->equalTo(['RMA was successfully created']),
            \Magento\Framework\Message\MessageInterface::TYPE_SUCCESS
        );
    }
}