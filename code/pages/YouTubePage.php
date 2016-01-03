<?php

class YouTubePage extends Page
{
}

class YouTubePage_Controller extends Page_Controller
{

    /**
     *
     * @var Google_Service_YouTube
     */
    private $yt;
    private $conf;
    private $videos;
    private $NextPageToken;
    private $PrevPageToken;
    private $TotalResults;
    private $ResultsPerPage;
    private $defaults               = array();
    private static $allowed_actions = array(
        'ViewVideo'
    );
    protected $validDefaults        = array(
        'safeSearch' => array("moderate", "none", "strict")
    );

    public function init()
    {
        $this->yt     = new Google_Service_YouTube($this->getGoogleClient());
        $this->setDefaults();
        $this->videos = $this->search($this->GetSearchTerm(), $this->GetSearchType());
        parent::init();
    }

    protected function getGoogleClient()
    {
        $client       = new Google_Client();
        $this->conf   = $this->config()->forClass("YouTubePage");
        $appName      = $this->conf->get("AppName");
        $auth         = $this->conf->get("Auth");
        $developerKey = isset($auth['DeveloperKey']) ? $auth['DeveloperKey'] : false;
        if (empty($appName)) {
            throw new Exception("No AppName in YouTubePage Config");
        } elseif (empty($auth)) {
            throw new Exception("No Auth in YouTubePage Config");
        }
        $client->setApplicationName($appName);
        if (!empty($developerKey)) {
            $client->setDeveloperKey($developerKey);
        } else {
            throw new Exception("No Auth DeveloperKey set in YouTubePage Config");
        }
        return $client;
    }

    protected function setDefaults()
    {
        $defaults = $this->conf->get("Defaults");
        if ($defaults) {
            foreach ($defaults as $key => $value) {
                if (isset($this->validDefaults[$key]) && in_array($value, $this->validDefaults[$key])) {
                    $this->defaults[$key] = $value;
                }
            }
        }
    }

    public function getPagerLink($token)
    {
        $result = false;
        if ($token) {
            $requestVars              = $this->request->getVars();
            unset($requestVars['url']);
            $requestVars["pageToken"] = $token;
            $result                   = htmlentities($this->Link("?" . http_build_query($requestVars)));
        }
        return $result;
    }

    public function getNextPageLink()
    {
        return $this->getPagerLink($this->NextPageToken);
    }

    public function getPrevPageLink()
    {
        return $this->getPagerLink($this->PrevPageToken);
    }

    public function search($term, $type = "all", $maxResults = 9)
    {
        $result = new ArrayList();

        $options = array(
            'q'               => $term,
            'maxResults'      => $maxResults,
            'type'            => "video",
            'videoEmbeddable' => 'true',
            'order'           => $type,
        );
        if ($this->GetPageToken()) {
            $options['pageToken'] = $this->GetPageToken();
        }
        if ($this->GetCategory()) {
            $options['videoCategoryId'] = $this->GetCategory();
        }
        if (is_array($this->defaults)) {
            $options = array_merge($this->defaults, $options);
        }
        $searchResponse = $this->yt->search->listSearch("snippet", $options);
        foreach ($searchResponse['items'] as $searchResult) {
            $result->add(YTVideoEntry::fromYouTubeSearchResultSnippet($searchResult));
        }
        $this->NextPageToken  = $searchResponse["nextPageToken"];
        $this->PrevPageToken  = $searchResponse["prevPageToken"];
        $this->TotalResults   = $searchResponse["pageInfo"]["totalResults"];
        $this->ResultsPerPage = $searchResponse["pageInfo"]["resultsPerPage"];

        return $result;
    }

    public function getVideos()
    {
        return $this->videos;
    }

    public function ViewVideo(SS_HTTPRequest $request)
    {
        $relatedVideoFeed = new ArrayList();

        $videoId         = $request->param("ID");
        $options         = ['maxResults' => 1, 'id' => $videoId];
        $searchResponse  = $this->yt->videos->listVideos("snippet", $options);
        $relatedResponse = $this->yt->search->listSearch("snippet", ['maxResults' => 16, 'type' => 'video', 'relatedToVideoId' => $videoId]);
        foreach ($relatedResponse['items'] as $searchResult) {
            $relatedVideoFeed->add(YTVideoEntry::fromYouTubeSearchResultSnippet($searchResult));
        }
        return $this->customise(array(
                    "Video"         => YTVideoEntry::fromYouTubeSearchResultSnippet($searchResponse[0]),
                    "RelatedVideos" => $relatedVideoFeed
                        )
                )->renderWith(array('YouTubePage_view', 'Page'));
    }

    public function SearchForm()
    {
        $searchText  = $this->GetSearchTerm() ? : "";
        $queryType   = $this->GetSearchType() ? : "";
        $category    = $this->GetCategory() ? : "";
        $searchField = new TextField('Search', "Search youtube for:", $searchText);
        $searchField->setAttribute('placeholder', "Type here");

        $fields               = new FieldList(
                new DropdownField('queryType', "Search type:", array(
            'relevance' => 'All Videos',
            'viewcount' => 'Most Viewed Videos',
            'date'      => 'Most recent',
            'rating'    => 'Top rated',
                ), $queryType), new DropdownField('category', "Category:", $this->GetCategories(), $category), $searchField
        );
        $action               = new FormAction('YouTubeSearchResults', _t('SearchForm.GO', 'Go'));
        $action->addExtraClass('btn btn-default btn-search');
        $action->useButtonTag = true;
        $action->setButtonContent('<i class="fa fa-search"></i><span class="text-hide">Search</span>');
        $actions              = new FieldList(
                $action
        );
        $form                 = new Form($this, 'YouTubeSearchForm', $fields, $actions);
        $form->setFormMethod('GET');
        $form->setFormAction($this->Link() . "");
        $form->disableSecurityToken();
        return $form;
    }

    public function GetSearchTerm()
    {
        $result = false;
        if ($this->request && $this->request->getVar('Search')) {
            $result = $this->request->getVar('Search');
        }
        return $result;
    }

    public function GetSearchType()
    {
        $result = "relevance";
        if ($this->request && $this->request->getVar('queryType')) {
            $result = $this->request->getVar('queryType');
        }
        return $result;
    }

    public function GetCategory()
    {
        $result = "";
        if ($this->request && $this->request->getVar('category')) {
            $result = $this->request->getVar('category');
        }
        return $result;
    }

    public function GetPageToken()
    {
        $result = false;
        if ($this->request && $this->request->getVar('pageToken')) {
            $result = $this->request->getVar('pageToken');
        }
        return $result;
    }

    public function GetCategories()
    {
        $localeEx   = explode("_", Member::currentUser()->Locale);
        $regionCode = isset($localeEx[1]) ? $localeEx[1] : "NZ";
        $categories = $this->yt->videoCategories->listVideoCategories('snippet', ['regionCode' => $regionCode]);
        $result     = array("" => "Any");
        foreach ($categories as $category) {
            $result[$category->getId()] = $category['snippet']['title'];
        }
        return $result;
    }
}
