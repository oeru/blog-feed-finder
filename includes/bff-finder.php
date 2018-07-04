<?php
/*
 * URL finder class
 */
require BFF_PATH . '/includes/bff-feed.php';

abstract class BFFFinder extends BFFFeed {
    // domains that aren't going to be blog sites that people might add
    // accidentally
    protected $bad_hosts = array(
        DOMAIN_CURRENT_SITE,
        'accounts.google.com',
        'course.oeru.org',
        'facebook.com',
        'google.com',
        'saylor.org',
        'wikieducator.org',
        'www.facebook.com',
        'www.google.com',
        'www.saylor.org',
    );
    // common path/file.suffix for feeds on blogs
    protected $usual_places = array(
        'rss.xml',
        'feed.rss',
        'feed/',
        'rss.atom',
        'feed.json',
    );

    public function process_feed_selection($selected, $feeds) {
        //$this->log('processing feed selection, button: '.$selected.' and feeds: '.print_r($feeds, true));
        // the array index is the final segment of the CSS id, e.g. bff-select-3...
        $feed_id = explode("-",$selected)[2];
        $this->log('selected feed['.$feed_id.']: '. print_r($feeds[$feed_id], true));
    }

    // process the URL provided by the user
    public function process_url($entered_url) {
        // make sure we're not a vector for XSS
        $url = sanitize_text_field($entered_url);
        $this->log('entered url: '.$entered_url.', sanitised: '.$url);
        // now test the URL...
        $succeeded = false;
        // set this to ensure that the various URL fetches, e.g. get_headers, time out quickly...
        ini_set('default_socket_timeout', 5);
        $this->test_url($url);
        $response = $this->response; // just for convenience...
        $this->log('### test_url response: '. print_r($response, true));
        // if we got a valid URL...
        if ($response['valid_url']) {
            if ($response['code'] == '302' || $response['code'] == '301') {
                $this->log('setting url to redirect value');
                //if ($path != '') { $redirect .= $path; }
                $url = $response['redirect'];
            } else {
                $this->log('setting url to orig_url value');
                $url = $response['orig_url'];
            }
            $this->log('new url: '. $url);
            if ($response['message'] != '') {
                $this->log('message for this URL: '. $response['message']);
            }
            // check if this URL, though valid, falls into a common
            // mistaken pattern, e.g. it's just the course's URL
            // if so, continue on to the next URL
            if ($this->valid_blog_url($url)) {
                $this->log('looking for a feed in the page content of '.$url.', or if that fails, in the "usual places."');
                // we've got a valid URL, so let's look for a feed...
                $this->log('**** response content_type = '.$this->response['content_type']);
                if ($this->find_feed_in_page($url)) {
                    $this->log('found a feed reference in the page content of '.$url);
                    // look for a feed referenced in the page
                    $this->response['valid_url'] = $url;
                    $succeeded = true;
                } else if ($this->find_feed_in_usual_places($url)) {
                    // look for feeds in the normal places
                    $this->log('looking for a feed in the usual places');
                    $this->response['valid_url'] = $url;
                    $succeeded = true;
                } else {
                    $this->log('failed to find a feed at: '. $url);
                    $this->set_response(true, $response['orig_url'], $response['path'],
                        'No feed found', $response['redirect'],
                        'We weren\'t able to find a feed on this site.', 'Perhaps there is no feed at the site you specified, '.$url, 'problem');
                }
            }
        }
        // we need to make sure this is returned to its normal value
        ini_set('default_socket_timeout', 60);
        if ($succeeded) {
            return true;
        }
        return false;
    }

    // check and see if there are any references to feeds in the content of the page
    public function find_feed_in_page($url) {
        $this->log('checking the returned content type '.$url.' to see if it\'s a feed.');
        // 1. Check if the page is, itself, a feed, by checking the Content-Type header
        if (array_key_exists($this->response['content_type'], $this->feed_types)) {
            $content_type = $this->feed_types[$this->response['content_type']];
            $this->log('***** bingo! We\'ve got a valid feed type '.$content_type);
            $this->add_message('Yay! we found a valid feed!', 'The address '.$url.
                ' points to a valid "'.$content_type.'" feed!', 'good');
            $this->add_feed($url, $this->response['content_type']);
            return true;
        } else {
            $this->log('***** the content type is '.$this->response['content_type']);
        }
        //
        // 2. failing that, get the actual HTML...
        $this->log('checking the content of '.$url.' to look for feed references.');
        $content = file_get_contents($url, FALSE, NULL, 0, BFF_MAX_FILE_READ_CHAR);
        // 2.a check if the page has any references to feeds...
        // create an object that knows how to read an HTML string and convert it into a DOM
        $dom = new DOMDocument('1.0', 'utf-8');
        libxml_use_internal_errors(true);
        $dom->loadHTML($content, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        $links = $dom->getElementsByTagName('link');
        $found = 0;
        if (count($links)) {
            foreach($links as $link) {
                if ($link->getAttribute('rel') == 'alternate') {
                    // if the type is one of the feed types we recognise...
                    //
                    $feed = $link->getAttribute('href');
                    // work out if the feed url scheme is http, but should be https
                    $url_parts = parse_url($url);
                    $feed_parts = parse_url($feed);
                    if ($url_parts['scheme'] == 'https' && $feed_parts['scheme'] == 'http') {
                        $this->log('Uh oh!!! Feed URL uses http, but main URL accepts https!');
                        $feed = 'https://'.$feed_parts['host'].$feed_parts['path'];
                        if (isset($feed_parts['query'])) {
                            $this->log('query = '.$feed_parts['query']);
                            $feed .= '?'.$feed_parts['query'];
                        }
                        $this->log('new feed url = '.$feed);
                    }
                    $type = $link->getAttribute('type');
                    $title = false;
                    if ($link->getAttribute('title') != '') {
                        $title = $link->getAttribute('title');
                    }
                    if (array_key_exists($type, $this->feed_types)) {
                        $this->log('link: '.$feed.', title: '.$title.' type: '.$type);
                        $type_name = $this->feed_types[$type];
                        if ($title) {
                            // allow feeds that don't have "Comments Feed" in the title...
                            if (strpos($title, 'Comments Feed') == false) {
                                $msg = ' Feed "'.$title.'" found.';
                                $detail= ' "'.$type_name.'" feed "'
                                    .$title.'" found at '.$feed.'...';
                                // add this feed...
                                $this->add_feed($feed, $type, $title);
                                $type = 'good';
                            } else {
                                // don't add this feed
                                $msg = ' Comment feed "'.$title.
                                    '" found - <em>probably not what you want</em>.';
                                $detail = ' "'.$type_name.'" feed "'
                                    .$title.'" found at '
                                    .$feed.'. This is probably not what we want, as it\'s specific to comments to your posts...';
                                $type = 'neutral';
                            }
                        } else {
                            $msg = 'Feed found.';
                            $detail = 'Untitled "'.$type_name.'" feed found at address '
                                .$feed.'!';
                            $this->add_feed($feed, $type);
                            $type = 'good';
                        }
                        $this->add_message($msg, $detail, $type);
                        $found++;
                        $this->log('msg: '.$msg);
                        $this->log('href = '.$feed.', type = '.$type);
                    }
                }
            }
        }
        if ($found > 0) {
            return true;
        }
        // 2b. now check if the content is valid XML, and if so, what type...
        if ($type = $this->is_valid_xml($content)) {
            // is it an type we're looking for?
            if (array_key_exists($type, $this->feed_types)) {
                $this->log('the content is of type "'.$this->feed_types[$type].'".');
                $this->response['content_type'] = $type;
                $this->add_message('Found a supported feed!', 'Found a feed in '.
                    $this->feed_types[$type].' format, which we support, yay!', 'good');
                $this->add_feed($url,$type);

                return true;
            }
            $this->log('the content is XML, but not of a sort we support as a feed type');
            $this->add_message('Found what <em>might</em> be a feed, but it\'s not one we support.', 'Found valid XML content, but not in a format we currently support...', 'neutral');
        } else {
            $this->log('the content isn\'t valid XML.');
        }
        // 2.c now check if the content is valid JSON...
        if ($type = $this->is_valid_json($content)) {
            $this->log('ok, found that it\'s in JSON format, so it\'s probably a feed.');
            $this->add_message('Found what is <em>probably</em> a feed!', 'Found JSON content, so this is likely to be a feed.', 'good');
            $this->add_feed($url, $type);
            return true;
        }
        $this->log('no feeds found in this document');
        return false;
    }

    // check and see if there are any references to feeds in the content of the page
    public function find_feed_in_usual_places($url) {
        $this->log('checking the page of '.$url.' to look for feed references.');
        foreach($this->usual_places as $place) {
            $this->log('testing '.$url.'/'.$place.' for a valid feed');
        }
        return false;
    }


    private function is_valid_xml($content) {
        // first check if the suspected feed URL points to a valid XML feed
        try {
            libxml_use_internal_errors(true);
            $xml = new SimpleXmlElement($content);
        } catch (Exception $e){
            return false;
        }
        $type = 'none';
        if ($xml->channel->item && $xml->channel->item->count() > 0) {
            $type = 'application/rss+xml';
        } elseif ($xml->entry) {
            $type = 'application/atom+xml';
        } else {
            $type = 'xml';
        }
        $this->log('found type = '. $type);
        return $type;
    }

    // check if a string is valid JSON
    private function is_valid_json($content) {
        $this->log('check if the content is valid JSON.');
        json_decode($content);
        // if not, check if it's a valid JSON feeds
        if (json_last_error() == JSON_ERROR_NONE) {
            $this->log('the content is, however, valid JSON.');
            $type = 'application/json';
            return $type;
        }
        $this->log('hmm, this content isn\'t valid JSON.');
        return false;
    }


    // check if a URL that is valid actually resolves. Returns false on 404
    public function test_url($url) {
        if ($url != '') {
            $orig = $url;
            $path = '';
            $parts = array();
            // split up the URL into its component parts and tidy it up
            if ($parts = parse_url(strtolower(trim($url)))) {
                $this->log('checking blog_url: '. print_r($parts, true));
                //$path = $parts['path'];
                // if no scheme was specified, default to http://
                // but also realise that the whole host + path are likely to be in
                // $parts['path']
                if (!isset($parts['scheme'])) {
                    $this->log('no scheme specified... adding http');
                    $this->add_message('We completed your address by adding http:// to the front.','We\'ve added an http:// \'scheme\' to your address - a valid scheme is a necessary part of a web address', 'neutral');
                    //$parts = parse_url('http://'.$parts['host'].$parts['path']);
                    $url = 'http://'.$orig;
                    $parts = parse_url(strtolower(trim($url)));
                    $this->log('re-checking blog_url: '. print_r($parts, true));
                } else {
                    // reconstruct the URL
                    $url = $parts['scheme'].'://'.$parts['host'].$parts['path'];
                }
                if (isset($parts['query'])) {
                    $url .= '?'.$parts['query'];
                }
                $this->log('updated url: '. $url);
                // if no host was provide... Houston, we have a problem.
                if (!isset($parts['host'])) {
                    $this->log('no host specified... bailing');
                    $this->set_response(false, $url);
                    $this->add_message('Unfortunately, that isn\'t a valid web address. Please try again. Not sure what we mean by "blog"? Check out our OERU <a href="'.BFF_SUPPORT_BLOG.'">Learner Support Site</a>.',
                        '"'.$url.'" isn\'t a valid web address - it requires at least a "top level domain", i.e. a ".", followed by a few letters, for example ".com". Best thing to do is to go to you blog and copy-and-paste the web address from your browser\'s address bar.', 'problem');
                    return false;
                }
            } else {
                $this->log('unable to parse URL: '.$url);
                $this->set_response(false, $orig, $path, '404', '', 'This isn\t a valid web address.',
                    'A valid web address needs text like <strong>domain.tld</strong> - tld = "Top Level Domain", like country-specific endings or .com, .org, .net, and others. It can also have an optional path, like "/blog/" in "mysite.org.nz/blog/"',
                    'problem');
            }
            // now query the URL and work out the response
            $this->log('testing for the existence of '.$url);
            $headers = @get_headers($url);
            if ($headers){
                $this->log('looks like we found something! Returns: '.
                    print_r($headers, true));
                $this->set_content_type($headers);
                switch ($headers[0]) {
                    case 'HTTP/1.0 200 OK':
                    case 'HTTP/1.1 200 OK':
                        $this->log('Yay! Returning valid url: '.$url);
                        $this->set_response(true, $orig, $path, '200', '', 'Ok, we\'ve verified the web address you entered points to a real site.',
                            '"'.$orig.'" found. It is a valid address!', 'good');
                    break;
                    case 'HTTP/1.0 301 Moved Permanently':
                    case 'HTTP/1.1 301 Moved Permanently':
                        if ($redirect = $this->get_redirect($headers)) {
                            $this->response['redirect'] = $redirect;
                            $this->set_response(true, $orig, $path, '301', $redirect, 'The web address you supplied redirects to another one - nothing wrong with that.',
                                '"'.$url.'" found. It redirects to '.$redirect.' via a "permanent redirect" (301)', 'good');
                        } else {
                            $this->set_response(false, $orig, $path, '301', '', 'Uh oh, your web address redirects to... a non-existent place.',
                                'Got redirect code (301), but no redirect destination! Either your link is incorrect, or whoever manages the website has made a configuration error somewhere.',
                                'bad');
                        }
                    break;
                    case 'HTTP/1.0 302 Moved Temporarily':
                    case 'HTTP/1.1 302 Moved Temporarily':
                        if ($redirect = $this->get_redirect($headers)) {
                            $this->response['redirect'] = $redirect;
                            $this->set_response(true, $orig, $path, '302', $redirect, 'Your web address redirects to another one, no problems.',
                                '"'.$url.'" found. It redirects to '.$redirect.' via a "temporary redirect" (302)', 'good');
                        } else {
                            $this->set_response(false, $orig, $path, '302', '', 'Uh oh, your web address redirects to... a non-existent place.',
                                'Got redirect code (302), but no redirect destination! Either your link is incorrect, or whoever manages the website has made a configuration error somewhere.',
                                'bad');
                        }
                    break;
                    case 'HTTP/1.0 302 Found':
                    case 'HTTP/1.1 302 Found':
                        if ($redirect = $this->get_redirect($headers)) {
                            $this->response['redirect'] = $redirect;
                            $this->set_response(true, $orig, $path, '302', $redirect,
                                'Your web address redirects to another one, no problems.',
                                '"'.$url.'" found. It redirects to '.$redirect.' via a "temporary redirect" (302)', 'neutral');
                        } else {
                            $this->set_response(false, $orig, $path, '302', '', 'Got redirect code (302), but no redirect destination!',
                                'Got redirect code (302), but no redirect destination! Either your link is incorrect, or whoever manages the website has made a configuration error somewhere.',
                                'bad');
                        }
                    break;
                    case 'HTTP/1.0 404 Not Found':
                    case 'HTTP/1.1 404 Not Found':
                        $this->set_response(false, $orig, $path, '404', '',
                            'Your web address wasn\'t found',
                            '"'.$url.'" was not found. The URL might be wrong, or the site currently unavailable for some reason.',
                            'problem');
                        $this->add_message('Make sure your URL\'s domain and the path (after the /) are spelled correctly! To avoid typos, go to your blog site\'s front page (not an admin or "edit" page) and copy-and-paste the URL from your address bar into the feed address field above.',
                            'neutral');
                    break;
                    case 'HTTP/1.0 410 Gone':
                    case 'HTTP/1.1 410 Gone':
                        $this->set_response(false, $orig, $path, '410', '',
                            'The web address you\'ve provided once existed, but it\'s now reported as "gone"...',
                            '"'.$orig.'" was valid, but its host is now listing it as "Gone".');
                    break;
                    default:
                        $this->log('got unknown result: '. $headers[0]);
                        $this->set_response(false, $orig, $path, '', 'unknown',
                            'Sorry, no website was found, but we have no idea why. Maybe a problem with our network? Try again in a little while...',
                            'Looking for "'.$orig.'" got an unexpected result: '.$headers[0],
                            'problem');
                    break;
                }
            } else {
                $this->log('no headers returned');
                $this->set_response(false, $url, '', 'unknown', '',
                    'Your web address isn\'t responding. Perhaps there\'s a typo? Otherwise, perhaps the site is down or there\'s a network problem between its server and our server.',
                    'The web address entered, "'.$url.'", isn\'t responding. Either the address is misspelled, the site is down, incredibly slow, or it doesn\'t exist...',
                    'problem');
            }
        } else {
            $this->log('empty URL');
            $this->set_response(false, '', '', 'unknown', '',
                'No web address entered!',
                'Perhaps you need to click your pointer in the text field, or you need to tap the field first (if you\'re on a touch device).',
                'problem');
        }
        return true;
    }

    // check if this *valid* URL falls into one of a few commonly seen
    // mistakes...
    public function valid_blog_url($url) {
        $this->log('Is this url a common mistake? '. $url);
        $parts = parse_url(strtolower(trim($url)));
        $this->log('parts of URL: '. print_r($parts, true));
        if (isset($parts['host'])) {
            if (in_array($parts['host'], $this->bad_hosts)) {
                $this->log('-----------------url '.$url.' has a bad host: '.$parts['host']);
                $this->response['valid_feed'] = false;
                $this->add_message('Oops, this isn\'t a valid blog, but it\'s one people commonly enter by accident - to learn more, see our support site\'s "<a href="'.BFF_SUPPORT_BLOG.'">blog</a>" section...', '', 'problem');
                return false;
            }
        } else {
            $this->log('no "host" detected...'. print_r($parts, true));
            return false;
        }
        $this->log('url '.$url.' could be a valid blog URL!!');
        return true;
    }

    // check if an actual RSS or Atom feed is returned for this URL
    // true or false
    // assumes a valid URL
    public function test_feed($url) {
        $content = file_get_contents($url);
        try {
            $xml = new SimpleXmlElement($content);
        } catch (Exception $e){
            $this->log('the content found at '.$url.' is not a valid feed.');
            return false;
        }
        $type = 'none';
        if ($feed->channel->item) {
            $type = 'rss';
        } elseif ($feed->entry) {
            $type = 'atom';
        } else {
            $type = 'xml';
        }
        $this->log('found type = '. $type);
        return $type;
    }
}
?>
