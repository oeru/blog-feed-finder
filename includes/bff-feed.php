<?php
/*
 * Target "feed" object class for returning useful info to a user
 */
require BFF_PATH . '/includes/bff-response.php';

abstract class BFFFeed extends BFFResponse {
    protected static $feeds = array();
    // formal content type and label
    protected $feed_types = array(
        'application/atom+xml' => 'Atom',
        'application/rss+xml' => 'RSS',
        'application/json' => 'JSON',
    );
    protected $feed_classes = array(
        'application/atom+xml' => 'bff-atom',
        'application/rss+xml' => 'bff-rss',
        'application/json' => 'bff-rss',
        'application/xml' => 'bff-default'
    );

    // add a feed description
    public function add_feed($url, $type, $title = '') {
        //$this->log('added feed!');
        $this->feeds[] = array(
            'url' => $url,
            'type' => $type,
            'title' => $title,
        );
        return true;
    }

    // construct the array that gets sent back to the user
    public function ajaxfeeds() {
        if (count($this->feeds)) {
            $this->response['feeds'] = $this->feeds;
            $this->response['feed_types'] = $this->feed_types;
            $this->response['feed_classes'] = $this->feed_classes;
            //$this->response['feeds_msg'] = $this->list_feeds();
            if (count($this->feeds) == 1) {
                $this->response['feed_selected'] = true;
            }
            $this->response['feed_found'] = true;
        } else {
            $this->response['feed_found'] = false;
        }
        // add the response object details
        $this->ajaxresponse();
        //$this->log('in ajaxfeeds - response object: '.print_r($this->response, true));
        $this->log('done with ajaxfeeds...');
        return $this->response;
    }
}
