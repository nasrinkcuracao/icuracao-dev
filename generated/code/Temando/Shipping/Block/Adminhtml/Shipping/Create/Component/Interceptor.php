<?php
namespace Temando\Shipping\Block\Adminhtml\Shipping\Create\Component;

/**
 * Interceptor class for @see \Temando\Shipping\Block\Adminhtml\Shipping\Create\Component
 */
class Interceptor extends \Temando\Shipping\Block\Adminhtml\Shipping\Create\Component implements \Magento\Framework\Interception\InterceptorInterface
{
    use \Magento\Framework\Interception\Interceptor;

    public function __construct(\Magento\Backend\Block\Widget\Context $context, \Temando\Shipping\Webservice\Config\WsConfigInterface $config, \Magento\Backend\Model\Auth\StorageInterface $session, \Temando\Shipping\Rest\AuthenticationInterface $auth, \Magento\Integration\Model\Oauth\Token $token, \Magento\Framework\Stdlib\DateTime\DateTime $dateTime, \Magento\Framework\HTTP\PhpEnvironment\RemoteAddress $remoteAddress, \Temando\Shipping\Model\ResourceModel\Repository\OrderRepositoryInterface $orderRepository, \Temando\Shipping\Model\Shipment\ShipmentProviderInterface $shipmentProvider, \Magento\Security\Model\Config $securityConfig, array $data = array())
    {
        $this->___init();
        parent::__construct($context, $config, $session, $auth, $token, $dateTime, $remoteAddress, $orderRepository, $shipmentProvider, $securityConfig, $data);
    }

    /**
     * {@inheritdoc}
     */
    public function canRender(\Magento\Backend\Block\Widget\Button\Item $item)
    {
        $pluginInfo = $this->pluginList->getNext($this->subjectType, 'canRender');
        if (!$pluginInfo) {
            return parent::canRender($item);
        } else {
            return $this->___callPlugins('canRender', func_get_args(), $pluginInfo);
        }
    }
}
