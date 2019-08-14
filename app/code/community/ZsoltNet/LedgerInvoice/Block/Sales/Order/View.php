<?php
/**
 * Magento
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@magentocommerce.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade Magento to newer
 * versions in the future. If you wish to customize Magento for your
 * needs please refer to http://www.magentocommerce.com for more information.
 *
 * @category    Mage
 * @package     Mage_Adminhtml
 * @copyright   Copyright (c) 2012 Magento Inc. (http://www.magentocommerce.com)
 * @license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Adminhtml sales order view
 *
 * @category    Mage
 * @package     Mage_Adminhtml
 * @author      Magento Core Team <core@magentocommerce.com>
 */
class ZsoltNet_LedgerInvoice_Block_Sales_Order_View extends Mage_Adminhtml_Block_Sales_Order_View
{

    public function __construct()
    {
        parent::__construct();

        $order = $this->getOrder();
        $this->_removeButton('order_invoice');

        //Ledgerimport
        if ($this->isImportable($order)) {
            $this->_addButton('order_import', array(
                'label'     => Mage::helper('sales')->__('Ledger-import'),
                'onclick'   => 'setLocation(\'' . $this->getLedgerimportUrl() . '\')'
            ), 0, 45);
        }

        if ($this->_isAllowedAction('invoice') && $order->canInvoice()) {
            $_label = $order->getForcedDoShipmentWithInvoice() ?
                Mage::helper('sales')->__('Invoice and Ship') :
                Mage::helper('sales')->__('Invoice');
            $this->_addButton('order_invoice', array(
                'label'     => $_label,
                'onclick'   => 'window.open(\'' . $this->getInvoiceUrl() . '\')',
                'class'     => 'go'
            ), 0, 50);
        }

    }

    public function getInvoiceUrl()
    {
        return Mage::helper("adminhtml")->getUrl("ledgerinvoice/invoice/index/",array("orderid"=>$this->getOrderId()));
    }

    public function getLedgerimportUrl()
    {
        return Mage::helper("adminhtml")->getUrl("ledgerinvoice/invoice/import/",array("orderid"=>$this->getOrderId()));
    }

    public function isImportable($order)
    {
        return (
            $this->_isAllowedAction('invoice') && $order->canInvoice() && Mage::getStoreConfig('ledgerinvoicemodule/ledgerinvoice/enableimport') &&
            ( $order->getCustomerId()!="" || ( $order->getCustomerId()=="" && !(Mage::getStoreConfig('ledgerinvoicemodule/ledgerinvoice/importregistered')) ) )
        );
    }

}
