<?php
require_once('tcpdf.php');
require_once('tcpdi.php');

class concat_pdf extends TCPDI{
    var $files = array(); 

    function setFiles($files) { 
        $this->files = $files; 
    } 

    function addFile($file) { 
        array_push($this->files, $file);
    } 

    function concat() { 
        foreach($this->files AS $file) { 
            $this->setPrintHeader(false);
            $this->setPrintFooter(false);
            $pagecount = $this->setSourceFile($file); 
            for ($i = 1; $i <= $pagecount; $i++) { 
                $tplidx = $this->ImportPage($i); 
                $s = $this->getTemplatesize($tplidx); 
                $this->AddPage('P', array($s['w'], $s['h'])); 
                $this->useTemplate($tplidx); 
            } 
        }
    } 
}
