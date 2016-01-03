<?php

class YTVideoEntry extends ViewableData
{

    public $ID;
    public $Thumb;
    public $Title;
    public $Description;
    public $Author;
    public $AuthorUri;
    public $URL;

    public function __construct()
    {
    }

    public function fortemplate()
    {
        return $this->renderWith(__CLASS__);
    }

    /**
     * 
     * @param Google_Service_YouTube_SearchResultSnippet $snippit
     * @return \YTVideoEntry
     */
    public static function fromYouTubeSearchResultSnippet(Google_Service_YouTube_SearchResultSnippet $snippit)
    {
        $result = new YTVideoEntry();
        if ($snippit instanceof Google_Service_YouTube_Video) {
            $result->ID = $snippit['id'];
        } else {
            $result->ID = $snippit['id']['videoId'];
        }
        $result->Thumb       = $snippit['snippet']['thumbnails']['high']['url'];
        $result->Title       = $snippit['snippet']['title'];
        $result->Description = $snippit['snippet']['description'];
        $result->URL         = Controller::curr()->Link('ViewVideo/' . $result->ID);
        return $result;
    }
}
