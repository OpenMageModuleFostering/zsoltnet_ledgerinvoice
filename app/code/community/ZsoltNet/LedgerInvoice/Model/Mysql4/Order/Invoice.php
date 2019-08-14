<?php

class ZsoltNet_LedgerInvoice_Model_Mysql4_Order_Invoice extends Mage_Sales_Model_Mysql4_Order_Invoice
{

    /**
     * Perform actions before object save
     *
     * @param Varien_Object $object
     * @return Mage_Sales_Model_Mysql4_Abstract
     */
    protected function _beforeSave(Mage_Core_Model_Abstract $object)
    {
        $incrementId = Mage::getSingleton('admin/session')->getLedgerInvoiceNumber();
        if ($this->_useIncrementId && !$object->getIncrementId()) {
            $object->setIncrementId($incrementId);
        }
        parent::_beforeSave($object);
        return $this;
    }
}





