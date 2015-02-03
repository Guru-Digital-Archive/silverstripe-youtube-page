<?php

class YTVideoEntry extends ViewableData {

    public $ID;
    public $Thumb;
    public $Title;
    public $Description;
    public $Author;
    public $AuthorUri;
    public $URL;

    function __construct() {

    }

    public function fortemplate() {
        return $this->renderWith(__CLASS__);
    }

//    public function GetCurrentController(){
//        return Controller::curr();
//    }
//    


}
