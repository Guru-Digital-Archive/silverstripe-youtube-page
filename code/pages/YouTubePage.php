<?php

class YouTubePage extends Page {

}

class YouTubePage_Controller extends Page_Controller {

    var $yt;

    /**
     * An array of actions that can be accessed via a request. Each array element should be an action name, and the
     * permissions or conditions required to allow the user to access it.
     *
     * <code>
     * array (
     *     'action', // anyone can access this action
     *     'action' => true, // same as above
     *     'action' => 'ADMIN', // you must have ADMIN permissions to access this action
     *     'action' => '->checkAction' // you can only access this action if $this->checkAction() returns true
     * );
     * </code>
     *
     * @var array
     */
    private static $allowed_actions = array(
        'ViewVideo'
    );

    public function init() {
        set_include_path(get_include_path() . PATH_SEPARATOR . BASE_PATH . "/vendor/zend/gdata/library/");
        $this->yt = new Zend_Gdata_YouTube();
        $this->yt->setMajorProtocolVersion(2);
        parent::init();
    }

    public function search($term, $type = "all", $startIndex = "", $maxResults = "") {
        $ytQuery = $this->yt->newVideoQuery();
        $ytQuery->setQuery($term);
        $ytQuery->setStartIndex($startIndex);
        $ytQuery->setMaxResults($maxResults);
        $ytQuery->setFormat(5);
        $ytQuery->SetSafeSearch('strict');

        $feed = array();
        /* check for one of the standard feeds, or list from 'all' videos */
        switch ($type) {
            case 'most_viewed':
                $ytQuery->setFeedType('most viewed');
                $ytQuery->setTime('this_week');
                $feed = $this->yt->getVideoFeed($ytQuery);
                break;
            case 'most_recent':
                $ytQuery->setFeedType('most recent');
                $feed = $this->yt->getVideoFeed($ytQuery);
                break;
            case 'recently_featured':
                $ytQuery->setFeedType('recently featured');
                $feed = $this->yt->getVideoFeed($ytQuery);
                break;
            case 'top_rated':
                $ytQuery->setFeedType('top rated');
                $ytQuery->setTime('this_week');
                $feed = $this->yt->getVideoFeed($ytQuery);
                break;
            case 'all':
                $feed = $this->yt->getVideoFeed($ytQuery);
                break;
            default:
                echo 'ERROR - unknown queryType - "' . $type . '"';
                break;
        }
        $result = $this->entryToVideo($feed);
        return $result;
    }

    /**
     * Returns a feed of top rated videos for the specified user
     *
     * @param  string $user The username
     * @return Zend_Gdata_YouTube_VideoFeed The feed of top rated videos
     */
    function getTopRatedVideosByUser($userUri) {
        $userVideosUrl = $userUri . '/uploads';
        $ytQuery       = $this->yt->newVideoQuery($userVideosUrl);
        $ytQuery->SetSafeSearch('strict');
        // order by the rating of the videos
        $ytQuery->setOrderBy('rating');
        // retrieve a maximum of 5 videos
        $ytQuery->setMaxResults(5);
        // retrieve only embeddable videos
        $ytQuery->setFormat(5);
        $result        = $this->entryToVideo($this->yt->getVideoFeed($ytQuery));
        return $result;
    }

    /**
     * Returns a feed of videos related to the specified video
     *
     * @param  string $videoId The video
     * @return Zend_Gdata_YouTube_VideoFeed The feed of related videos
     */
    function getRelatedVideos($videoId) {
        $ytQuery = $this->yt->newVideoQuery();
        // show videos related to the specified video
        $ytQuery->SetSafeSearch('strict');
        $ytQuery->setFeedType('related', $videoId);
        // order videos by rating
        $ytQuery->setOrderBy('rating');
        // retrieve a maximum of 5 videos
        $ytQuery->setMaxResults(5);
        // retrieve only embeddable videos
        $ytQuery->setFormat(5);
        $result  = $this->entryToVideo($this->yt->getVideoFeed($ytQuery));
        return $result;
    }

    public function getVideos() {
        $videos = $this->search($this->GetSearchTerm(), $this->GetSearchType());
        return $videos;
    }

    /**
     *
     * @param type $entry
     * @return \ArrayList|YTVideoEntry
     */
    public function entryToVideo($entry) {
        $result = new YTVideoEntry();

        if ($entry instanceof Zend_Gdata_YouTube_VideoFeed) {
            $result = new ArrayList();
            foreach ($entry as $singleEntry) {
                $result->add($this->entryToVideo($singleEntry));
            }
        } else if ($entry instanceof Zend_Gdata_YouTube_VideoEntry) {
            $result->ID          = $entry->getVideoId();
            $result->Thumb       = $entry->mediaGroup->thumbnail[0]->url;
            $result->Title       = htmlentities($entry->mediaGroup->title);
            $result->Description = htmlentities($entry->mediaGroup->description);
            $result->Author      = htmlentities($entry->author[0]->name);
            $result->URL         = $this->Link('ViewVideo/' . $result->ID);
            $result->AuthorUri   = $entry->author[0]->uri;
        }
        return $result;
    }

    public function ViewVideo(SS_HTTPRequest $request) {
        $videoId          = $request->param("ID");
        $entry            = $this->yt->getVideoEntry($videoId);
        $video            = $this->entryToVideo($entry);
        $relatedVideoFeed = $this->getRelatedVideos($video->ID);
        $topRatedFeed     = $this->getTopRatedVideosByUser($video->AuthorUri);
        return $this->customise(array("Video" => $video, "RelatedVideos" => $relatedVideoFeed, "TopRated" => $topRatedFeed))->renderWith(array('YouTubePage_view', 'Page'));
    }

    public function SearchForm() {
        $searchText = $this->GetSearchTerm() ? : "";

        $searchField          = new TextField('Search', "Search youtube for:", $searchText);
        $searchField->setAttribute('placeholder', "Type here");
        $fields               = new FieldList(
                new DropdownField('queryType', "Search type:", array('all' => 'All Videos', 'top_rated' => 'Top Rated Videos', 'most_viewed' => 'Most Viewed Videos', 'recently_featured' => 'Recently Featured Videos')), $searchField
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

    public function GetSearchTerm() {
        $result = false;
        if ($this->request && $this->request->getVar('Search')) {
            $result = $this->request->getVar('Search');
        }
        return $result;
    }

    public function GetSearchType() {
        $result = "recently_featured";
        if ($this->request && $this->request->getVar('queryType')) {
            $result = $this->request->getVar('queryType');
        }
        return $result;
    }

}
