<?php
/*
 * URL finder class
 */
require BFF_PATH . '/includes/bff-target.php';

abstract class BFFFinder extends BFFTarget {
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
    protected $usual_places = array(
        'rss.xml',
        'feed.rss',
        'feed/',
        'rss.atom',
        'feed.json',
    );
    protected $feed_types = array(
        'application/atom+xml' => 'Atom',
        'application/rss+xml' => 'RSS',
        'application/json' => 'JSON',
    );

    // process the URL provided by the user
    public function process_url($entered_url) {
        // make sure we're not a vector for XSS
        $url = sanitize_text_field($entered_url);
        $this->log('entered url: '.$entered_url.', sanitised: '.$url);
        // now test the URL...
        $this->test_url($url);
        $response = $this->response; // just for convenience...
        $this->log('### test_url response: '. print_r($response, true));
        // if we got a valid URL...
        if ($response['valid_url']) {
                if ($response['code'] == '302' || $response['code'] == '301') {
                //if ($path != '') { $redirect .= $path; }
                $url = $response['redirect'];
            } else {
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
                $this->log('looking for a feed in the page content, or if that fails, in the "usual places."');
                // we've got a valid URL, so let's look for a feed...
                if ($this->find_feed_in_page($url)) {
                    $this->log('found a feed reference in the page content!');
                    // look for a feed referenced in the page
                    return true;
                } else if ($this->find_feed_in_usual_places($url)) {
                    // look for feeds in the normal places
                    $this->log('looking for a feed in the usual places');
                    return true;
                } else {
                    $this->log('failed to find a feed at: '. $url);
                    $this->set_response(true, $response['orig_url'], $response['path'],
                        'No feed found', $response['redirect'],
                        'We weren\'t able to find a feed anywhere near '.$url.'... it\'s possible the site just doesn\'t have one.', 'problem');
                }
            }
            // we've successfully processed the URL...
            //return true;
        }
        return false;
    }

    // check and see if there are any references to feeds in the content of the page
    public function find_feed_in_page($url) {
        $this->log('checking the page of '.$url.' to look for feed references.');
        // 1. Check if the page is, itself, a feed, by checking the Content-Type header
        if (array_key_exists($this->response['content_type'], $this->feed_types)) {
            $content_type = $this->feed_types[$this->response['content_type']];
            $this->log('bingo! We\'ve got a valid feed of type '.$content_type);
            $this->add_message('Yay! '.$url.' points to a feed of type: "'.$content_type.'"!');
            return true;
        }
        //
        // 2. failing that, get the actual HTML...
        $content = file_get_contents($url, FALSE, NULL, 0, BFF_MAX_FILE_READ_CHAR);
        // 2a. now check if the content is valid XML, and if so, what type...
        if ($type = $this->is_valid_xml($content)) {
            // is it an type we're looking for?
            if (array_key_exists($type, $this->feed_types)) {
                $this->log('the content is of type "'.$this->feed_types[$type].'".');
                $this->response['content_type'] = $type;
                return true;
            }
            $this->log('the content is XML, but not of a sort we support as a feed type');
        } else {
            $this->log('the content isn\'t valid XML.');
        }
        // 2.b now check if the content is valid JSON...
        if ($this->is_valid_json($content)) {

        }
        // Failing that, check if the page has any references to feeds...
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
                    if (array_key_exists($link->getAttribute('type'), $this->feed_types)) {
                        $this->log('link title: '.$link->getAttribute('title').' type: '.$link->getAttribute('type'));
                        $type_name = $this->feed_types[$link->getAttribute('type')];
                        $this->add_message(' "'.$type_name.'" feed "'.
                        $link->getAttribute('title').'" found at address '.
                            $link->getAttribute('href').'!','good');
                        $found++;
                    }
                }
                //$this->log('link title: '.$link->getAttribute('title'));
            }
        } else {
            $this->log('no feeds found in this document');
        }
        if ($found > 0) return $true;
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
            $type = 'application/rss+atom';
        } else {
            $type = 'xml';
        }
        $this->log('found type = '. $type);
        return $type;
    }

    // check if a string is valid JSON
    private function is_valid_json($content) {
        $this->log('check if the content found at '.$url.' is valid JSON.');
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
                $path = $parts['path'];
                // if no scheme was specified, default to http://
                if (!isset($parts['scheme'])) {
                    $this->log('no scheme specified... adding http');
                    $this->add_message('We\'ve added an http:// \'scheme\' to your address - a valid scheme is a necessary part of a web address', 'neutral');
                    $parts = parse_url('http://'.$path);
                }
                // reconstruct the URL
                $url = $parts['scheme'].'://'.$parts['host'].$parts['path'];
                if (isset($parts['query'])) {
                    $url .= '?'.$parts['query'];
                }
                $this->log('updated url: '. $url);
                // if no host was provide... Houston, we have a problem.
                if (!isset($parts['host'])) {
                    $this->log('no host specified... bailing');
                    $this->set_response(false, $url);
                    $this->add_message('Unfortunately, "'.$url.'" isn\'t a valid web address - it requires at least a "top level domain", i.e. a ".", followed by a few letters (e.g. .com).', 'problem');
                    return false;
                }
            } else {
                $this->log('unable to parse URL: '.$url);
                $this->set_response(false, $orig, $path, '404', '', 'This URL is not valid!', 'problem');
            }
            // now query the URL and work out the response
            $this->log('testing for the existence of '.$url);
            // set this to ensure that the get_headers call times out quickly...
            ini_set('default_socket_timeout', 5);
            $headers = @get_headers($url);
            // return it to its normal value
            ini_set('default_socket_timeout', 60);
            if ($headers){
                $this->log('looks like we found something! Returns: '.
                    print_r($headers, true));
                $this->set_content_type($headers);
                switch ($headers[0]) {
                    case 'HTTP/1.0 200 OK':
                    case 'HTTP/1.1 200 OK':
                        $this->log('Yay! Returning valid url: '.$orig);
                        $this->set_response(true, $orig, $path, '200', '', '"'.$orig.'" found. It is a valid address!', 'good');
                    break;
                    case 'HTTP/1.0 301 Moved Permanently':
                    case 'HTTP/1.1 301 Moved Permanently':
                        if ($redirect = $this->get_redirect($headers)) {
                            $this->set_response(true, $orig, $path, '301', $redirect,
                                '"'.$orig.'" found. It redirects to '.$redirect.' via a "permanent redirect" (301)', 'good');
                        } else {
                            $this->set_response(false, $orig, $path, '301', '', 'Got redirect code (301), but no redirect destination!', 'bad');
                        }
                    break;
                    case 'HTTP/1.0 302 Moved Temporarily':
                    case 'HTTP/1.1 302 Moved Temporarily':
                        if ($redirect = $this->get_redirect($headers)) {
                            $this->set_response(true, $orig, $path, '302', $redirect,
                                '"'.$orig.'" found. It redirects to '.$redirect.' via a "temporary redirect" (302)', 'good');
                        } else {
                            $this->set_response(false, $orig, $path, '302', '', 'Got redirect code (302), but no redirect destination!', 'bad');
                        }
                    break;
                    case 'HTTP/1.0 302 Found':
                    case 'HTTP/1.1 302 Found':
                        if ($redirect = $this->get_redirect($headers)) {
                            $this->set_response(false, $orig, $path, '302', $redirect,
                                '"'.$orig.'" found. It redirects to '.$redirect.' via a "temporary redirect" (302)', 'neutral');
                        } else {
                            $this->set_response(false, $orig, $path, '302', '', 'Got redirect code (302), but no redirect destination!', 'bad');
                        }
                    break;
                    case 'HTTP/1.0 404 Not Found':
                    case 'HTTP/1.1 404 Not Found':
                        $this->set_response(false, $orig, $path, '404', '',
                            '"'.$orig.'" was not found. The URL might be wrong, or the site currently unavailable for some reason.', 'problem');
                        //if (!($path == '/' || $path == '')) {
                        $this->add('Make sure your URL\'s domain and the path (after the /) are spelled correctly! To avoid typos, go to your blog site (not edit page) and copy-paste the URL from your address bar.', 'neutral');
                        //}
                    break;
                    case 'HTTP/1.0 410 Gone':
                    case 'HTTP/1.1 410 Gone':
                        $this->set_response(false, $orig, $path, '410', '',
                            '"'.$orig.'" was valid, but its host is now listing it as "Gone".');
                    break;
                    default:
                        $this->log('got unknown result: '. $headers[0]);
                        $this->set_response(false, $orig, $path, '', 'unknown', 'Looking for "'.$orig.'" got an unexpected result: '.$headers[0], 'problem');
                    break;
                }
            } else {
                $this->log('no headers returned');
                $this->set_response(false, $url, '', 'unknown', '', 'The web address entered, "'.$url.'", isn\'t responding. Either the site is down, incredibly slow, or it doesn\'t exist - perhaps a spelling error?', 'problem');
            }
        } else {
            $this->log('empty URL');
            $this->set_response(false, '', '', 'unknown', '', 'No web address entered!', 'problem');
        }
        return true;
    }

    // check if this *valid* URL falls into one of a few commonly seen
    // mistakes...
    public function valid_blog_url($url) {
        $this->log('=================Is this url a common mistake? '. $url);
        $parts = parse_url(strtolower(trim($url)));
        $this->log('parts of URL: '. print_r($parts, true));
        if (isset($parts['host'])) {
            if (in_array($parts['host'], $this->bad_hosts)) {
                $this->log('-----------------url '.$url.' has a bad host: '.$parts['host']);
                $this->response['valid_feed'] = false;
                $this->add_message('Oops, this isn\'t a valid blog, but it\'s one people commonly enter by accident. Have a look at our support site\'s "<a href=\'https://course.oeru.org/support/studying-courses/course-blog/\'>blog</a>" section...', 'problem');
                return false;
            }
        } else {
            $this->log('no "host" detected...'. print_r($parts, true));
            return false;
        }
        $this->log('++++++++++++++++url '.$url.' could be a valid blog URL!!');
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
