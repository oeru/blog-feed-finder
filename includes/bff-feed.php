<?php
/*
 * Target "feed" object class for returning useful info to a user
 */
require BFF_PATH . '/includes/bff-response.php';

abstract class BFFFeed extends BFFResponse {
    protected static $feeds = array();

    // add a feed description
    public function add_feed($url, $type, $title = '') {
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
            $this->response['feed_found'] = true;
        } else {
            $this->response['feed_found'] = false;
        }
        // add the response object details
        $this->ajaxresponse();
        $this->log('in ajaxfeeds - response object: '.print_r($this->response, true));
        return $this->response;
    }
}
