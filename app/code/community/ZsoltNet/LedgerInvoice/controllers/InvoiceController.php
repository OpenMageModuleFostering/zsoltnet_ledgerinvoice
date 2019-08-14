<?php
require_once('HttpClient.class.php');
require_once('simple_html_dom.php');
require_once('db_pg.php');
require_once('tcpdf/concat_pdf.php');
require_once('tcpdf/tcpdf.php');
require_once('tcpdf/tcpdi.php');


class ZsoltNet_LedgerInvoice_InvoiceController extends Mage_Adminhtml_Controller_Action
{
    private $hostname;
    private $context;
    private $username;
    private $dbuser;
    private $dbpass;
    private $dbdb;
    private $dbhost;
    private $dbport;
    private $debug;
    private $tmpdir;
    private $convert;
    private $texts;

    /**
     * code by Magento
     */
    protected function _getItemQtys()
    {
        $data = $this->getRequest()->getParam('invoice');
        if (isset($data['items'])) {
            $qtys = $data['items'];
            //$this->_getSession()->setInvoiceItemQtys($qtys);
        }
        /*elseif ($this->_getSession()->getInvoiceItemQtys()) {
            $qtys = $this->_getSession()->getInvoiceItemQtys();
        }*/
        else {
            $qtys = array();
        }
        return $qtys;
    }

    /**
     * Initialize invoice model instance
     *
     * code by Magento
     * @return Mage_Sales_Model_Order_Invoice
     */
    protected function _initInvoice($orderId, $update = false)
    {
        $this->_title($this->__('Sales'))->_title($this->__('Invoices'));

        $invoice = false;
        $itemsToInvoice = 0;

        $order      = Mage::getModel('sales/order')->load($orderId);
        /**
         * Check order existing
         */
        if (!$order->getId()) {
            $this->_getSession()->addError($this->__('Order not longer exist'));
            return false;
        }
        /**
         * Check invoice create availability
         */
        if (!$order->canInvoice()) {
            $this->_getSession()->addError($this->__('Order does not allow to create an invoice.'));
            return false;
        }

        $convertor  = Mage::getModel('sales/convert_order');
        $invoice    = $convertor->toInvoice($order);

        $savedQtys = $this->_getItemQtys();
        /* @var $orderItem Mage_Sales_Model_Order_Item */
        foreach ($order->getAllItems() as $orderItem) {

            if (!$orderItem->isDummy() && !$orderItem->getQtyToInvoice() && $orderItem->getLockedDoInvoice()) {
                continue;
            }

            if ($order->getForcedDoShipmentWithInvoice() && $orderItem->getLockedDoShip()) {
                continue;
            }

            if (!$update && $orderItem->isDummy() && !empty($savedQtys) && !$this->_needToAddDummy($orderItem, $savedQtys)) {
                continue;
            }
            $item = $convertor->itemToInvoiceItem($orderItem);

            if (isset($savedQtys[$orderItem->getId()])) {
                $qty = $savedQtys[$orderItem->getId()];
            }
            else {
                if ($orderItem->isDummy()) {
                    $qty = 1;
                } else {
                    $qty = $orderItem->getQtyToInvoice();
                }
            }
            $itemsToInvoice += floatval($qty);
            $item->setQty($qty);
            $invoice->addItem($item);
        }

        if ($itemsToInvoice <= 0){
            Mage::throwException($this->__('Invoice without products could not be created.'));
            return false;
        }

        $invoice->collectTotals();

        return $invoice;
    }

    /**
     * Decides if we need to create dummy invoice item or not
     * for eaxample we don't need create dummy parent if all
     * children are not in process
     *
     * @param Mage_Sales_Model_Order_Item $item
     * @param array $qtys
     * @return bool
     */
    protected function _needToAddDummy($item, $qtys) {
        if ($item->getHasChildren()) {
            foreach ($item->getChildrenItems() as $child) {
                if (isset($qtys[$child->getId()]) && $qtys[$child->getId()] > 0) {
                    return true;
                }
            }
            return false;
        } else if($item->getParentItem()) {
            if (isset($qtys[$item->getParentItem()->getId()]) && $qtys[$item->getParentItem()->getId()] > 0) {
                return true;
            }
            return false;
        }
    }

    /**
     * Common method
     */
    protected function _initAction() {
        $this->hostname = Mage::getStoreConfig('ledgerinvoicemodule/ledgerinvoice/hostname');
        $this->context  = Mage::getStoreConfig('ledgerinvoicemodule/ledgerinvoice/context');
        $this->username = Mage::getStoreConfig('ledgerinvoicemodule/ledgerinvoice/username');
        $this->dbuser   = Mage::getStoreConfig('ledgerinvoicemodule/ledgerinvoice/dbusername');
        $this->dbpass   = Mage::getStoreConfig('ledgerinvoicemodule/ledgerinvoice/dbpassword');
        $this->dbdb     = Mage::getStoreConfig('ledgerinvoicemodule/ledgerinvoice/dbdb');
        $this->dbhost   = Mage::getStoreConfig('ledgerinvoicemodule/ledgerinvoice/dbhost');
        $this->dbport   = Mage::getStoreConfig('ledgerinvoicemodule/ledgerinvoice/dbport');
        $this->tmpdir   = Mage::getStoreConfig('ledgerinvoicemodule/ledgerinvoice/tmpdir');
        $this->debug    = Mage::getStoreConfig('ledgerinvoicemodule/ledgerinvoice/debug');
        $this->convert  = (Mage::getStoreConfig('ledgerinvoicemodule/ledgerinvoice/charset')=="iso-8859-2") ? true : false;

        if (!is_dir($this->tmpdir)) mkdir($this->tmpdir);
        if ($this->debug) {
            $h = fopen($this->tmpdir."/ledgerinvoice_debug.log","a");
            fwrite($h,"\n********************** _initAction **************************\n");
            fwrite($h,"hostname: ".$this->hostname."\n");
            fwrite($h,"context: ".$this->context."\n");
            fwrite($h,"username: ".$this->username."\n");
            fwrite($h,"dbuser: ".$this->dbuser."\n");
            fwrite($h,"dbdb: ".$this->dbdb."\n");
            fwrite($h,"dbhost: ".$this->dbhost."\n");
            fwrite($h,"tmpdir: ".$this->tmpdir."\n");
            $convertStr = ($this->convert) ? "true" : "false";
            fwrite($h,"convert: ".$convertStr."\n");
            fclose($h);
        }
        $this->texts = array(
            "engedmeny"             => ($this->convert) ? mb_convert_encoding("engedmény", "ISO-8859-2","UTF-8") : "engedmény",
            "Belepes"               => ($this->convert) ? mb_convert_encoding("Belépés", "ISO-8859-2","UTF-8") : "Belépés",
            "Rogzites"              => ($this->convert) ? mb_convert_encoding("Rögzítés", "ISO-8859-2","UTF-8") : "Rögzítés",
            "Nyomtatas"             => ($this->convert) ? mb_convert_encoding("Nyomtatás", "ISO-8859-2","UTF-8") : "Nyomtatás",
            "Tovabb"                => ($this->convert) ? mb_convert_encoding("Tovább", "ISO-8859-2","UTF-8") : "Tovább",
            "bankkartyas fizetes"   => ($this->convert) ? mb_convert_encoding("bankkártyás fizetés", "ISO-8859-2","UTF-8") : "bankkártyás fizetés",
            "atutalas"              => ($this->convert) ? mb_convert_encoding("átutalás", "ISO-8859-2","UTF-8") : "átutalás",
            "utanvet"               => ($this->convert) ? mb_convert_encoding("utánvét", "ISO-8859-2","UTF-8") : "utánvét"
        );
        return $this;
    }

    private function getSpecialID($content, $name) {
        $html = str_get_html($content);
        // find all input
        foreach($html->find('input[name='.$name.']') as $e) {
           $id = $e->value;
        }
        return $id;
    }

    private function errorHandle($message, $error = NULL, $errorfile ) {
        $this->_getSession()->addError($this->__($message));
        if ($error!=NULL) {
            $h = fopen($this->tmpdir."/ledgerinvoice_debug.log","a");
            fwrite($h,"\n**********************".$errorfile."**************************\n");
            fwrite($h,$error);
            fclose($h);
        }
    }

    private function getClient() {
        $useragent  = Mage::getStoreConfig('ledgerinvoicemodule/ledgerinvoice/useragent');
        $client     = new HttpClient($this->hostname);
        $client->setUserAgent($useragent);
//        $client->setDebug(true);
        $cookie     = Mage::getSingleton('admin/session')->getLedgerCookie(); 
        $cookiets   = Mage::getSingleton('admin/session')->getLedgerCookieTs(); 
        $timeout    = Mage::getStoreConfig('ledgerinvoicemodule/ledgerinvoice/timeout');
        if ($this->debug) {
            $h = fopen($this->tmpdir."/ledgerinvoice_debug.log","a");
            fwrite($h,"\n********************** getClient() **************************\n");
            fwrite($h,"cookie: ".$cookie."\n");
            fwrite($h,"cookiets: ".$cookiets."\n");
            fwrite($h,"timeout: ".$timeout."\n");
            fclose($h);
        }
        if (isset($cookie)) {
            //we have a working Ledger-cookie, checking the timestamp
            if ($this->debug) {
                $h = fopen($this->tmpdir."/ledgerinvoice_debug.log","a");
                fwrite($h,"\n********************** getClient() 2 **************************\n");
                fwrite($h,"time(): ".time()."\n");
                fwrite($h,"cookiets+timeout: ".($cookiets + $timeout*60)."\n");
                fclose($h);
            }
            if (($cookiets + $timeout*60) > time()) {
                $client->setCookies(array('Ledger-'.$this->username=>"".$cookie));
            }
        }

        return $client;
    }

    private function login() {
        $client     = $this->getClient();
        $password   = Mage::getStoreConfig('ledgerinvoicemodule/ledgerinvoice/password');

        if ($this->debug) {
            $h = fopen($this->tmpdir."/ledgerinvoice_debug.log","a");
            fwrite($h,"\n********************** login() **************************\n");
            fclose($h);
        }
        if (!$client->post($this->context.'login.pl', array(
            'login'     => $this->username,
            'password'  => $password,
            'path'      => 'bin/mozilla',
            'js'        => '1',
            'cjs'       => 'on',
            'action'    => $this->texts["Belepes"]))) {
                $this->errorHandle('Hiba történt belépés közben!', $client->getError(), "");
                return false;
        }

        if ($this->debug) {
            $h = fopen($this->tmpdir."/ledgerinvoice_debug.log","a");
            fwrite($h,$client->getContent()."\n");
            fclose($h);
        }

        $cookies= $client->getHeader('set-cookie');
        $cookie = substr($cookies, strpos($cookies,"=")+1, 10);
        $client->setCookies(array('Ledger-'.$this->username=>"".$cookie));

        if ($this->debug) {
            $h = fopen($this->tmpdir."/ledgerinvoice_debug.log","a");
            fwrite($h,"cookie: ".$cookie."\n");
            fclose($h);
        }

        //storing cookie info in the session
        Mage::getSingleton('admin/session')->setLedgerCookie($cookie);
        Mage::getSingleton('admin/session')->setLedgerCookieTs(time());

        return true;
    }

    private function importUser($order) {
        $client         = $this->getClient();
        $customerId     = $order->getCustomerId();
        $orderId        = $order->getId();
        $phone          = "";
        $fax            = "";
        $email          = "";

        if ($customerId=="") {
            //vendegrol van szo
            $customerId = Mage::getStoreConfig('ledgerinvoicemodule/ledgerinvoice/guestcustomeridprenumber') + $orderId;
        } else {
            $customerId = Mage::getStoreConfig('ledgerinvoicemodule/ledgerinvoice/regcustomeridprenumber') + $customerId;
        }
        $name           = $order->getBillingAddress()->getName();
        $company        = $order->getBillingAddress()->getCompany();
        $zipcode        = $order->getBillingAddress()->getPostcode();
        $city           = $order->getBillingAddress()->getCity();
        $street         = $order->getBillingAddress()->getStreet(1);
        if (Mage::getStoreConfig('ledgerinvoicemodule/ledgerinvoice/importPhone')) {
            $phone      = $order->getBillingAddress()->getTelephone();
            $fax        = $order->getBillingAddress()->getFax();
        }
        if (Mage::getStoreConfig('ledgerinvoicemodule/ledgerinvoice/importEmail')) {
            $email      = $order->getCustomerEmail();
        }
        $taxvat         = $order->getCustomerTaxvat();
        $country        = $order->getBillingAddress()->getCountryModel()->getName();
        if ($order->getBillingAddress()->getCountryId()=="HU") {
            $country    = "";
        }
        if(trim($company)!="") {
            $name = $company;
        }

        $shiptoname     = $order->getShippingAddress()->getName();
        $shiptocompany  = $order->getShippingAddress()->getCompany();
        $shiptozipcode  = $order->getShippingAddress()->getPostcode();
        $shiptocity     = $order->getShippingAddress()->getCity();
        $shiptostreet   = $order->getShippingAddress()->getStreet(1);
        $shiptophone    = $order->getShippingAddress()->getTelephone();
        $shiptofax      = $order->getShippingAddress()->getFax();
        $shiptocountry  = $order->getShippingAddress()->getCountryModel()->getName();
        if ($order->getShippingAddress()->getCountryId()=="HU") {
            $shiptocountry= "";
        }
        if(trim($shiptocompany)!="") {
            $shiptoname = $shiptocompany." (".$shiptoname.")";
        }

        if ($this->debug) {
            $h = fopen($this->tmpdir."/ledgerinvoice_debug.log","a");
            fwrite($h,"\n********************** importUser: userdata **************************\n");
            fwrite($h,$orderId."\n");
            fwrite($h,$customerId."\n");
            fwrite($h,$order->getCustomerId()."\n");
            fwrite($h,$name."\n");
            fwrite($h,$zipcode."\n");
            fwrite($h,$country."\n");
            fwrite($h,$city."\n");
            fwrite($h,$street."\n");
            fwrite($h,$phone."\n");
            fwrite($h,$fax."\n");
            fwrite($h,$email."\n");
            fclose($h);
        }

        $convert   = $this->convert;
        $sessionid = Mage::getSingleton('admin/session')->getLedgerCookie(); 
        $userArray = array(
            'login' => $this->username,
            'sessionid' => $sessionid,
            'path' => 'bin/mozilla',
            'db' => 'customer',
            'action' => 'save',
            'customernumber' => $customerId,
            'name' => ($convert) ? mb_convert_encoding($name, "ISO-8859-2", "UTF-8") : $name,
            'zipcode' => $zipcode,
            'country' => ($convert) ? mb_convert_encoding($country, "ISO-8859-2", "UTF-8") : $country,
            'city' => ($convert) ? mb_convert_encoding($city, "ISO-8859-2", "UTF-8") : $city,
            'address1' => ($convert) ? mb_convert_encoding($street, "ISO-8859-2", "UTF-8") : $street,
            'shiptoname' => ($convert) ? mb_convert_encoding($shiptoname, "ISO-8859-2", "UTF-8") : $shiptoname,
            'shiptozipcode' => $shiptozipcode,
            'shiptocity' => ($convert) ? mb_convert_encoding($shiptocity, "ISO-8859-2", "UTF-8") : $shiptocity,
            'shiptoaddress1' => ($convert) ? mb_convert_encoding($shiptostreet, "ISO-8859-2", "UTF-8") : $shiptostreet,
            'shiptophone' => $shiptophone,
            'shiptofax' => $shiptofax,
            'shiptocountry' => ($convert) ? mb_convert_encoding($shiptocountry, "ISO-8859-2", "UTF-8") : $shiptocountry,
            'terms' => 0,
            'duebase' => 'transdate',
            'shipvia' => $this->texts["utanvet"],
            'currency' => 'HUF',
            'phone' => $phone,
            'fax' => $fax,
            'email' => $email,
            'taxnumber' => $taxvat,
            'js'=>''
        );
        $taximport  = explode(",",Mage::getStoreConfig('ledgerinvoicemodule/ledgerinvoice/taximport'));
        $taxaccounts= '';
        foreach ($taximport as $tax) {
            if ($tax!='') { 
                $userArray['tax_'.Mage::getStoreConfig('ledgerinvoicemodule/ledgerinvoice/tax_'.$tax)] = '1';
                $taxaccounts .= Mage::getStoreConfig('ledgerinvoicemodule/ledgerinvoice/tax_'.$tax).' ';
            }
        }
        $taxaccounts = trim($taxaccounts);
        $userArray['taxaccounts'] = $taxaccounts;

        //checking if we have a customer_id in db
        if (!$dbConnID=db_connect($this->dbhost,$this->dbport,$this->dbuser,$this->dbpass,$this->dbdb)) { 
            $this->errorHandle('Hiba az adatbázishoz kapcsolódáskor!');
            return false;
        }
        $qText      = "SELECT id, shipvia, terms, notes from customer where customernumber='$customerId'";
        $qResult    = db_query($qText, $dbConnID);
        $paramsID   = "";
        if (($qResult) && (db_fetch_assoc($qResult,$qRow))){
            if ($qRow['id']!="" ) {
                //var mar ilyen, update
                $userArray['id']        = $qRow['id'];
                $userArray['shipvia']   = $qRow['shipvia'];
                $userArray['terms']     = $qRow['terms'];
                $userArray['notes']     = $qRow['notes'];
            }//if
        }//if

        //creating/updating user in db
        if (!$client->post($this->context.'ct.pl', $userArray)) {
            $this->errorHandle('Hiba történt a felhasználó importálása közben!', $client->getError(), $order->getRealOrderId());
            return false;
        }

        $content = $client->getContent();
        if (substr_count($content,"ERROR")>0 || substr_count($content,"Be kell megint jelentkezni")>0) {
            $this->errorHandle('Hiba történt a felhasználó importálása közben!', $content, $order->getRealOrderId());
            return false;
        }

        if ($this->debug) {
            $h = fopen($this->tmpdir."/ledgerinvoice_debug.log","a");
            fwrite($h,"\n********************** importUser: result **************************\n");
            fwrite($h,$content."\n");
            fclose($h);
        }

        return true;

    }

    private function checkProduct($sku, $name) {
        $client = $this->getClient();
        $name   = ($this->convert) ? mb_convert_encoding($name, "ISO-8859-2","UTF-8") : $name;

        if ($this->debug) {
            $h = fopen($this->tmpdir."/ledgerinvoice_debug.log","a");
            fwrite($h,"\n********************** checkProduct **************************\n");
            fwrite($h,$sku."\n");
            fclose($h);
        }

        if (!$dbConnID=db_connect($this->dbhost,$this->dbport,$this->dbuser,$this->dbpass,$this->dbdb)) { 
            $this->errorHandle('Hiba az adatbázishoz kapcsolódáskor!');
            return false;
        }
        $qText      = "SELECT id from parts where partnumber='$sku'";
        $qResult    = db_query($qText, $dbConnID);
        if (($qResult) && (db_fetch_assoc($qResult,$qRow))){
            if ($qRow['id']!="" ) {
                //van ilyen termek, nem kell importalni
                return;
            }//if
        }//if

        $sessionid  = Mage::getSingleton('admin/session')->getLedgerCookie(); 
        $vtsz       = Mage::getStoreConfig('ledgerinvoicemodule/ledgerinvoice/vtsz');

        $productArray = array(
            'login' => $this->username,
            'sessionid' => $sessionid,
            'path' => 'bin/mozilla',
            'action' => 'save',
            'item' => 'service',
            'taxaccounts' => $taxaccounts,
            'orphaned' =>'1',
            'partnumber' => $sku,
            'description' => $name,
            'unit' => 'db',
            'bin' => $vtsz,
            'IC_tax_'.$tax => '1'
        );

        $tax        = Mage::getStoreConfig('ledgerinvoicemodule/ledgerinvoice/taximport4service');
        $productArray['IC_tax_'.Mage::getStoreConfig('ledgerinvoicemodule/ledgerinvoice/tax_'.$tax)] = '1';
        $productArray['taxaccounts'] = Mage::getStoreConfig('ledgerinvoicemodule/ledgerinvoice/tax_'.$tax);

        if (!$client->post($this->context.'ic.pl', $productArray)) {
            $this->errorHandle('Hiba történt a termék ellenőrzése közben!', $client->getError(), $sku);
            return false;
        }

        $content = $client->getContent();
        if (substr_count($content,"ERROR")>0 || substr_count($content,"Be kell megint jelentkezni")>0) {
            $this->errorHandle('Hiba történt a termék importálása közben!', $content, $sku);
            return false;
        }

        if ($this->debug) {
            $h = fopen($this->tmpdir."/ledgerinvoice_debug.log","a");
            fwrite($h,"\n********************** checkProduct **************************\n");
            fwrite($h,$content."\n");
            fclose($h);
        }

        return true;
    }

    /**
     * a szamla elokeszitese, azaz Ledgerben a szamla inicializalasa
     */
    private function preInvoice($customer_id, $order) {
        $client     = $this->getClient();
        $sessionid  = Mage::getSingleton('admin/session')->getLedgerCookie(); 

        $postArray  = array(
            'login'         => $this->username,
            'sessionid'     => $sessionid,
            'path'          => 'bin/mozilla',
            'type'          => 'invoice',
            'action'        => 'add',
            'customer_id'   => $customer_id,
            'oddordnumber'  => $order->getRealOrderId()
        );

        //creating pre invoice
        if (!$client->post($this->context.'is.pl', $postArray )) {
            $this->errorHandle('Hiba történt a számla előkészítése közben!', $client->getError(), $order->getRealOrderId());
            return NULL;
        }

        $content = $client->getContent();

        if (substr_count($content,"ERROR")>0) {
            $this->errorHandle('Hiba történt a számla előkészítése közben!', $content, $order->getRealOrderId());
            return NULL;
        }

        if ($this->debug) {
            $h = fopen($this->tmpdir."/ledgerinvoice_debug.log","a");
            fwrite($h,"\n********************** preInvoice **************************\n");
            fwrite($h,$content."\n");
            fclose($h);
        }

        return $content;
    }

    private function parseInvoice($content, $postArray) {
        $html = str_get_html($content);

        // find all input
        foreach($html->find('input') as $e) {
            $postArray[$e->name] = $e->value;
        }
        // find all textarea
        foreach($html->find('textarea') as $e) {
            if ($e->value!="") {
                $postArray[$e->name] = $e->value;
            } else {
                foreach($e->find('text') as $t) {
                    $postArray[$e->name] = $t->__toString();
                }
            }
        }
        //find all select
        foreach($html->find('select') as $select) {
            $selected       = 0;
            $wasSelected    = 0;
            $firstValue     = null;
            foreach($select->find('option') as $option) {
                if ($firstValue == null) {
                    if ($option->value!='') {
                        $firstValue = $option->value;
                    } else {
                        foreach($select->find('text') as $t) {
                            $firstValue = $t->__toString();
                            break;
                        }
                    }
                }
                if ($option->value!='') {
                    if ($option->selected) {
                        //proper <option value="">
                        $wasSelected = 1;
                        $postArray[$select->name] = $option->value;
                        continue;
                    }
                } else {
                    //<option>
                    if ($option->selected) {
                        $o = 0;
                        foreach($select->find('text') as $t) {
                            if ($o==$selected) {
                                $wasSelected = 1;
                                $postArray[$select->name] = chop($t->__toString());
                                break;
                            }
                            $o++;
                        }
                    }
                }
                $selected++;
            }//foreach
            if (!$wasSelected) {
                $postArray[$select->name] = chop($firstValue);
            }
        }
        return $postArray;
    }
 
    /**
     * az egyes tetelek hozzaadasa a szamlahoz
     */
    private function subInvoice($order, $content, $sku, $qty, $price, $i, $description = NULL) {
        $client     = $this->getClient();
        $postArray  = $this->parseInvoice($content, array());
        $postArray['partnumber_'.$i]    = $sku;
        $postArray['qty_'.$i]           = $qty;
        $postArray['sellprice_'.$i]     = $price;
        $postArray['action']            = $this->texts["Tovabb"];

        if ($description != NULL) {
            $postArray['description_'.$i]     = $description;
        }

        //refreshing
        if (!$client->post($this->context.'is.pl', $postArray )) {
            $this->errorHandle('Hiba történt a számla készítése közben!', $client->getError(), $order->getRealOrderId());
            return NULL;
        }

        $content    = $client->getContent();
        if ($this->debug) {
            $h = fopen($this->tmpdir."/ledgerinvoice_debug.log","a");
            fwrite($h,"\n********************** subInvoice **************************\n");
            fwrite($h,$content."\n");
            fclose($h);
        }

        return $content;
    }

    private function getCodfee($order) {
        $_totalData = $order->getData();
        $cod        = 0;
        if(in_array('cod_fee', array_keys($_totalData))) {
            $cod    = $_totalData['cod_fee'];
            $cod    = str_replace(".",",",$cod);
        }
        return $cod;
    }

    private function getBpiofee($order) {
        $_totalData = $order->getData();
        $bpio       = 0;
        if(in_array('bpio_fee', array_keys($_totalData))) {
            $bpio   = $_totalData['bpio_fee'];
            $bpio   = str_replace(".",",",$bpio);
        }
        return $bpio;
    }

    private function createInvoice($order) {
        $client     = $this->getClient();
        $convert    = $this->convert;

        $customerId = $order->getCustomerId();
        if ($customerId=="") {
            //vendegrol van szo
            $customerId = Mage::getStoreConfig('ledgerinvoicemodule/ledgerinvoice/guestcustomeridprenumber') + $order->getId();
        } else {
            $customerId = Mage::getStoreConfig('ledgerinvoicemodule/ledgerinvoice/regcustomeridprenumber') + $customerId;
        }

        //checking customer_id in db
        if (!$dbConnID=db_connect($this->dbhost,$this->dbport,$this->dbuser,$this->dbpass,$this->dbdb)) { 
            $this->errorHandle('Hiba az adatbázishoz kapcsolódáskor', "", $order->getRealOrderId());
            return NULL;
        }
        $qText      = "SELECT id from customer where customernumber='".$customerId."'";
        $qResult    = db_query($qText, $dbConnID);
        $customer_id= 0;

        if (($qResult) && (db_fetch_assoc($qResult,$qRow))){
            if ($qRow['id']!="" ) {
                $customer_id = $qRow['id'];
            }
        }

        if ($this->debug) {
            $h = fopen($this->tmpdir."/ledgerinvoice_debug.log","a");
            fwrite($h,"\n********************** createInvoice: customer details **************************\n");
            fwrite($h,$order->getId()."\n");
            fwrite($h,$customerId."\n");
            fwrite($h,$order->getCustomerId()."\n");
            fwrite($h,$customer_id."\n");
            fwrite($h,$qText."\n");
            fclose($h);
        }

        if ($customer_id==0) {
            //ERROR
            $this->errorHandle('Hiba történt a számla ('.$order->getRealOrderId().') készítése közben!', "", $order->getRealOrderId());
            return NULL;
        }

        //preinvoice
        $content = $this->preInvoice($customer_id, $order);
        if ($content==NULL) {
            //hiba történt
            $this->errorHandle('Hiba történt a számla ('.$order->getRealOrderId().') készítése közben!', "", $order->getRealOrderId());
            return NULL;
        }

        //getting item parameters
        $i          = 1;
        $parentName = "";
        $longName   = "";//a bundle termek gyerekeinek "hosszabb" megnevezese: 'termek (bundle termek)' formatumban
        foreach ($order->getAllItems() as $item) {
            $this->checkProduct($item->getSku(), $item->getName());
            if ($item->canShip()) {
                $qty    = $item->getQtyToShip();
            } else {
                $qty    = $item->getQtyShipped();
                $qty    = substr($qty,0,strpos($qty,"."));
            }
            $sku        = $item->getSku();
            $name       = $item->getName();
            $price      = $item->getPrice();
            $price      = str_replace(".",",",$price);
            $type       = $item->getProductType();
            $options    = $item->getProductOptions();
            $info       = $options['info_buyRequest'];
            $realQty    = $info['qty'];

            if ($type=="bundle") {
                //bundle product, eltesszük a nevet
                $parentName = $name;
                //és nem kell szamlazni
                continue;
            }

            if ($qty==0) {
                //ha 0 db van valamiből, az egy bundle product "gyereke", ilyenkor a realQty mutatja a tényleges darabszamot
                $qty        = $realQty;
                $longName   = $name." (".$parentName.")";
            } else {
                $longName   = $name;
            }

            $content    = $this->subInvoice($order, $content, $sku, $qty, $price, $i);
            if ($content==NULL) return NULL;

            //ez egyelore kikapcsolva, mert atlathatatlanna teszi a szamlat
            //if ($name!=$longName) {
            //    //bundle termek gyereke speckó megnevezéssel, ezért módosítani kell a nevet
            //    $longName   = ($convert) ? mb_convert_encoding($longName, "ISO-8859-2", "UTF-8") : $longName;
            //    $content    = $this->subInvoice($order, $content, $sku, $qty, $price, $i, $longName);
            //    if ($content==NULL) return NULL;
            //}
            $i++;
        }//foreach

        //shipment details
        $_totalData = $order->getData();
        $ship       = $_totalData['shipping_amount'];
        $ship       = str_replace(".",",",$ship);
        $cod        = $this->getCodfee($order);
        $bpio       = $this->getBpiofee($order);

        if ($_totalData["shipping_method"] == Mage::getStoreConfig('ledgerinvoicemodule/ledgerinvoice/inpersonshippingmethod')) {
            //szemelyes atvetel
            $content    = $this->subInvoice($order, $content, Mage::getStoreConfig('ledgerinvoicemodule/ledgerinvoice/inpersoncode'), 1, $ship, $i++);
        } else {
            //futaros kiszallitas
            $content    = $this->subInvoice($order, $content, Mage::getStoreConfig('ledgerinvoicemodule/ledgerinvoice/shippingcode'), 1, $ship, $i++);
        }
        if ($content==NULL) return NULL;
        if ($cod>0) {
            $content= $this->subInvoice($order, $content, Mage::getStoreConfig('ledgerinvoicemodule/ledgerinvoice/codcode'), 1, $cod, $i++);
            if ($content==NULL) return NULL;
        }
        if ($bpio>0) {
            $content= $this->subInvoice($order, $content, Mage::getStoreConfig('ledgerinvoicemodule/ledgerinvoice/bpiocode'), 1, $bpio, $i++);
            if ($content==NULL) return NULL;
        }

        //getting discount
        $disc       = $_totalData['discount_amount'];
        $discTax    = $_totalData['hidden_tax_amount'];
        //ha konvertalas van a latin2-es Ledger miatt
        $discDesc   = ($convert) ? mb_convert_encoding($_totalData['discount_description'], "ISO-8859-2", "UTF-8") : $_totalData['discount_description'];
        $discDescPre= $this->texts["engedmeny"];
        $discDesc   = $discDescPre ." (" . $discDesc . ")";
        $disc       = str_replace(".",",",($disc+$discTax));
        if ($disc<0) {
            $content    = $this->subInvoice($order, $content, Mage::getStoreConfig('ledgerinvoicemodule/ledgerinvoice/discountcode') , 1, $disc, $i);
            if ($content==NULL) return NULL;
            $content    = $this->subInvoice($order, $content, Mage::getStoreConfig('ledgerinvoicemodule/ledgerinvoice/discountcode') , 1, $disc, $i, $discDesc);
            if ($content==NULL) return NULL;
        }

        if ($this->debug) {
            $h = fopen($this->tmpdir."/ledgerinvoice_debug.log","a");
            fwrite($h,"\n********************** createInvoice: shipment details **************************\n");
            fwrite($h,$content."\n");
            fclose($h);
        }

        if (substr_count($content,"A munkamenet ideje lej")>0) {
            $this->errorHandle('A munkamenet lejart! Ujra kell probalkozni!', $content, $order->getRealOrderId());
            return NULL;
        }

        return $content;
    }

    private function saveLedgerInvoice($content, $method, $order) {
        $bankcard           = explode("\n",Mage::getStoreConfig('ledgerinvoicemodule/ledgerinvoice/bankcard'));
        $bankcardpayment    = false;
        $postArray          = $this->parseInvoice($content, array());
        $postArray['action']= $this->texts["Rogzites"];

        foreach ($bankcard as $option) {
            if ($method==$option) {
                $bankcardpayment = true;
                break;
            }
        }

        $trans = $postArray['transdate'];
        if ($method=='cashondelivery') {
            //utanvetel, ezert masok a datumok
            $postArray['shipvia']   = $this->texts["utanvet"];
            $postArray['transdate'] = date("Y-m-d",strtotime($trans)+Mage::getStoreConfig('ledgerinvoicemodule/ledgerinvoice/codtrans')*24*60*60);
            $postArray['duedate']   = date("Y-m-d",strtotime($trans)+Mage::getStoreConfig('ledgerinvoicemodule/ledgerinvoice/coddue')*24*60*60);
        } else  {
            $payment = $order->getPayment();
            if($payment->getLastTransId()) {
                //van tranzakcio
                $txid       = $payment->getLastTransId();
                $transaction= Mage::getModel('sales/order_payment_transaction');
                $transaction->setOrderPaymentObject($payment);
                $tx         = $transaction->loadByTxnId($txid);
                $gmtTZ      = new DateTimeZone('GMT');
                $localTZ    = new DateTimeZone('Europe/Budapest');
                $pd         = new DateTime($tx->getCreatedAt(), $gmtTZ);
                $offset     = $localTZ->getOffset($pd);
                $postArray['transdate'] = date('Y-m-d H:i', $pd->format('U') + $offset);
            }
            if ($bankcardpayment){
                $postArray['shipvia']   = $this->texts["bankkartyas fizetes"];
            } else {
                //banki atutalas vagy banki befizetes
                $postArray['shipvia']   = $this->texts["atutalas"];
            }
        }

        $client     = $this->getClient();
        $orderId    = $this->getSpecialID($content, "oddordnumber");

        //footer
        $footer              = Mage::getStoreConfig('ledgerinvoicemodule/ledgerinvoice/footer', $order->getStoreId());
        $postArray['footer'] = ($this->convert) ? mb_convert_encoding($footer, "ISO-8859-2","UTF-8") : $footer;

        //saving
        if (!$client->post($this->context.'is.pl', $postArray )) {
            $this->errorHandle('Hiba történt a számla ('.$orderId.') mentese kozben!', $client->getError(), $orderId);
            return NULL;
        } else {
            $content = $client->getContent();
        }

        if (substr_count($content,"ERROR")>0) {
            $this->errorHandle('Hiba történt a számla ('.$orderId.') mentese kozben!', $content, $orderId);
            return NULL;
        }

        if ($this->debug) {
            $h = fopen($this->tmpdir."/ledgerinvoice_debug.log","a");
            fwrite($h,"\n********************** saveLegerInvoice: invoice saved **************************\n");
            fwrite($h,$content."\n");
            fclose($h);
        }

        //parsing invoice number
        $invnumber = $this->getSpecialID($content, "invnumber");
        Mage::getSingleton('admin/session')->setLedgerInvoiceNumber($invnumber);
        Mage::getSingleton('admin/session')->setLedgerOrderID($orderId);//jelzi, hogy melyik orderhez tartozik az aktualis szamla

        //printing
        $postArray          = $this->parseInvoice($content, array());
        $postArray['action']= $this->texts["Nyomtatas"];
        $client             = $this->getClient();
        if (!$client->post($this->context.'is.pl', $postArray )) {
            $this->errorHandle('Hiba történt a számla ('.$invnumber.', '.$orderId.') nyomtatása közben, de a számla elkészült!', $client->getError(), $orderId);
            return NULL;
        } else {
            $content    = $client->getContent();
            if (substr_count($content,"error")>0) {
                $this->errorHandle('Hiba történt a számla ('.$invnumber.', '.$orderId.') nyomtatása közben, de a számla elkészült! Talán rossz karakter van a címben.', $content, $orderId);
                return NULL;
            }
            if ($this->debug) {
                $h = fopen($this->tmpdir."/ledgerinvoice_debug.log","a");
                fwrite($h,"\n********************** saveLedgerInvoice: printing **************************\n");
                fwrite($h,$content."\n");
                fclose($h);
            }
            $dir        = $this->tmpdir;
            if (!is_dir($dir)) mkdir($dir);
            $tempfile   = $dir."/".time().".pdf";
            $file       = fopen($tempfile,"w");
            fwrite($file,$content);
            fclose($file);

            $pdf        = new TCPDI(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
            $pdf->setPrintHeader(false);
            $pdf->setPrintFooter(false);
            $pagecount  = $pdf->setSourceFile($tempfile);
            $i          = 0;
            while($i<$pagecount) {
                $tplidx = $pdf->importPage(++$i);
                if (!$tplidx) {
                    break;
                }
                $pdf->addPage();
                $pdf->useTemplate($tplidx, 0, 0, 210);
            }

            unlink($tempfile);
            return $pdf;
        }

    }

    private function saveMageInvoice($orderId) {
        $order  = Mage::getModel('sales/order')->load($orderId);
        $realId = $order->getRealOrderId();
        if ($this->debug) {
            $h = fopen($this->tmpdir."/ledgerinvoice_debug.log","a");
            fwrite($h,"\n********************** saveMageInvoice started **************************\n");
            fwrite($h,"orderId:".$orderId."\n");
            fwrite($h,"realId:".$realId."\n");
            fclose($h);
        }
        if (Mage::getSingleton('admin/session')->getLedgerOrderID()!=$realId) {
//            if ($this->debug) {
//                $h = fopen($this->tmpdir."/ledgerinvoice_debug.log","a");
//                fwrite($h,"\n********************** saveMageInvoice **************************\n");
//                fwrite($h,"az elozo szamlakeszites nem sikerult, nem kell semmit menteni\n");
//                fclose($h);
//            }
            //az elozo szamlakeszites nem sikerult, nem kell semmit menteni
            return;
        }
        try {
            if ($invoice = $this->_initInvoice($orderId)) {
                $invoice->register();
                $invoice->getOrder()->setIsInProcess(true);
                $transactionSave = Mage::getModel('core/resource_transaction')
                    ->addObject($invoice)
                    ->addObject($invoice->getOrder());
                $transactionSave->save();
                $invoice->sendEmail(true, "");
            }
            if ($this->debug) {
                $h = fopen($this->tmpdir."/ledgerinvoice_debug.log","a");
                fwrite($h,"\n********************** saveMageInvoice finished **************************\n");
                fclose($h);
            }
            return;
        }
        catch (Mage_Core_Exception $e) {
            $this->_getSession()->addError($e->getMessage());
        }
        catch (Exception $e) {
            $this->_getSession()->addError($this->__('Failed to save invoice.'));
            Mage::logException($e);
        }
    }

    private function singleOrder($orderId) {
        $this->_initAction();
        $client = $this->getClient();
        if (!count($client->getCookies())) {
            if (!$this->login()) {
                return NULL;
            }
        }
        $order  = Mage::getModel('sales/order')->load($orderId);
        if (!$order->canInvoice()) {
            return NULL;
        }
        if (!$this->importUser($order)) {
            return NULL;
        }
        $cod    = $this->getCodfee($order);
        $bpio   = $this->getBpiofee($order);
        $content= $this->createInvoice($order);
        if ($content==NULL) {
            //hiba tortent
            return NULL;
        }

        $method = $order->getPayment()->getData('method');
        $pdf    = $this->saveLedgerInvoice($content, $method, $order);
        $this->saveMageInvoice($orderId);
        return $pdf;
    }

    public function indexAction() {
        $orderId    = $this->getRequest()->orderid;
        $pdf        = $this->singleOrder($orderId);
        if ($pdf==NULL) {
            return;
        }
        $invnumber  = Mage::getSingleton('admin/session')->getLedgerInvoiceNumber();
        $this->_getSession()->addSuccess($this->__('Invoice has been successfully created.'));
        $pdf->Output('invoice.'.$invnumber.'.pdf', 'D');
    }

    public function importAction() {
        $this->_initAction();
        $client = $this->getClient();
        if (!count($client->getCookies())) {
            if (!$this->login()) {
                return NULL;
            }
        }
        $orderId    = $this->getRequest()->orderid;
        $order      = Mage::getModel('sales/order')->load($orderId);
        if ($this->importUser($order)) {
            $this->_getSession()->addSuccess($this->__('Vásárló importálása sikerült.'));
        } else {
            $this->_getSession()->addError($this->__('Hiba importálás közben!'));
        }
        Mage::app()->getResponse()->setRedirect(Mage::helper('adminhtml')->getUrl("adminhtml/sales_order/view", array('order_id'=>$orderId)));
    }

    public function massprintAction() {
        $this->_initAction();
        $orderIds = $this->getRequest()->getPost('order_ids', array());
        $dir      = $this->tmpdir;
        $invoices = new concat_pdf();
        $error    = 0;
        foreach (array_reverse($orderIds, true) as $orderId) {
            $invoice  = $this->singleOrder($orderId);
            if ($invoice==NULL) {
                $error = 1;
                continue;
            }
            $invnumber= Mage::getSingleton('admin/session')->getLedgerInvoiceNumber();
            $invoice->Output($dir.'/invoice.'.$invnumber.'.pdf', 'F');
            $invoices->addFile($dir.'/invoice.'.$invnumber.'.pdf'); 
        }
        if ($error) {
            $this->_getSession()->addError($this->__('Néhány számla hibás!'));
        } else {
            $this->_getSession()->addSuccess($this->__('Számlák sikeresen elkészültek.'));
        }
        $invoices->concat();
        //torles
        $objects = scandir($dir);
        foreach ($objects as $object) {
            if ($object != "." && $object != ".." && $object != "ledgerinvoice_debug.log") unlink($dir."/".$object);
        }
        $invoices->Output('invoices.pdf', 'D');
    }
}

