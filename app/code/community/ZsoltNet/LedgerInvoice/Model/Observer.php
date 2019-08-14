<?php

/**
 * Event observer model
 *
 *
 */
class ZsoltNet_LedgerInvoice_Model_Observer
{
    public function addMassActionAddLedgerImport(Varien_Event_Observer $observer)
    {
        $block  = $observer->getEvent()->getBlock();

        if(($block instanceof Mage_Adminhtml_Block_Widget_Grid_Massaction)
            && $block->getRequest()->getControllerName() == 'sales_order')
        {
            $block->addItem('pdfinvoices_order', array(
                'label' => Mage::helper('sales')->__('Print Invoices'),
                'url'   => Mage::app()->getStore()->getUrl('ledgerinvoice/invoice/massprint'),
            ));
        }

        if(($block instanceof Mage_Adminhtml_Block_Sales_Order_View)
            && $block->getRequest()->getControllerName() == 'sales_order')
        {
            $request    = $block->getRequest();
            $params     = $request->getParams();
            $orderId    = $params['order_id'];
            $order      = Mage::getModel('sales/order')->load($orderId);

            if ($this->isImportable($order)) {
                $block->addButton('order_import', array(
                    'label'     => Mage::helper('sales')->__('Ledger-import'),
                    'onclick'   => 'setLocation(\'' . Mage::helper("adminhtml")->getUrl("ledgerinvoice/invoice/import/",array("orderid"=>$orderId)) . '\')'
                ), 0, 45);
            }

            if ($order->canInvoice()) {
                $_label = $order->getForcedDoShipmentWithInvoice() ?
                    Mage::helper('sales')->__('Invoice and Ship') :
                    Mage::helper('sales')->__('Invoice');
                $block->addButton('order_invoice', array(
                    'label'     => $_label,
                    'onclick'   => 'window.open(\'' . Mage::helper("adminhtml")->getUrl("ledgerinvoice/invoice/index/",array("orderid"=>$orderId)) . '\')',
                    'class'     => 'go'
                ), 0, 50);
            }
        }

    }

    public function isImportable($order)
    {
        return (
            $order->canInvoice() && Mage::getStoreConfig('ledgerinvoicemodule/ledgerinvoice/enableimport') &&
            ( $order->getCustomerId()!="" || ( $order->getCustomerId()=="" && !(Mage::getStoreConfig('ledgerinvoicemodule/ledgerinvoice/importregistered')) ) )
        );
    }

}
