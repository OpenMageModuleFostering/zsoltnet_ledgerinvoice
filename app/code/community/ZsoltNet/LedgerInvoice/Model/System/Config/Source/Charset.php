<?php
/**
 *
 */
class Zsoltnet_Ledgerinvoice_Model_System_Config_Source_Charset
{

    /**
     * Options getter
     *
     * @return array
     */
    public function toOptionArray()
    {
        return array(
            array('value' => 'iso-8859-2', 'label'=>Mage::helper('adminhtml')->__('ISO-8859-2')),
            array('value' => 'utf-8', 'label'=>Mage::helper('adminhtml')->__('UTF-8')),
        );
    }

}
