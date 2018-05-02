<?php
/*
 * base class for all BFF classes with utility functions
 */
require BFF_PATH . '/includes/bff-base.php';

abstract class BFFFinder extends BFFBase {

    protected static $response;
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

    // return the error object
    public function response() {
        return $this->response;
    }

    // process the URL provided by the user
    public function process_url($entered_url) {
        // make sure we're not a vector for XSS
        $url = sanitize_text_field($entered_url);
        $this->log('entered url: '.$entered_url.', sanitised: '.$url);
        // now test the URL...
        $response = $this->test_url($url);
        $this->log('### test_url response: '. print_r($response, true));
        // if we got a valid URL...
        if ($response['valid']) {
                if ($response['code'] == '302' || $response['code'] == '301') {
                //if ($path != '') { $redirect .= $path; }
                $url = $response['redirect'];
            } else {
                $url = $response['orig_url'];
            }
            $this->log('new url: '. $url);
            if ($response['comment'] != '') {
                $this->log('Comment for this URL: '. $response['comment']);
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

                } else if ($this->find_feed_in_usual_places($url)) {
                    // look for feeds in the normal places
                    $this->log('looking for a feed in the usual places');
                } else {
                    $this->log('failed to find a feed at: '. $url);
                    $this->set_error(true, $response['orig_url'], $response['path'],
                        'No feed found', $response['redirect'],
                        'We weren\'t able to find a feed anywhere near '.$url.'... it\'s possible the site just doesn\'t have one.');
                }

            }
            // we've successfully processed the URL...
            return true;
        }
        return false;
    }

    // check and see if there are any references to feeds in the content of the page
    public function find_feed_in_page($url) {
        $this->log('checking the page of '.$url.' to look for feed references.');
        $content = file_get_contents($url, FALSE, NULL, 0, BFF_MAX_FILE_READ_CHAR);

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


    private function is_valid_xml($url, $content) {
        $this->log('check if the content found at '.$url.' is valid XML.');
        // first check if the suspected feed URL points to a valid XML feed
        try {
            $xml = new SimpleXmlElement($content);
        } catch (Exception $e){
            $this->log('the content at '.$url.' is not valid XML');
            return false;
        }
        $type = 'none';
        if ($xml->channel->item && $xml->channel->item->count() > 0) {
            $type = 'rss';
        } elseif ($xml->entry) {
            $type = 'atom';
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
            $type = 'json';
            return $type;
        }
        $this->log('hmm, this content isn\'t valid JSON.');
        return false;
    }

    // convenience function to return a suitably structure array
    private function set_error($valid = false, $orig = '', $path = '', $code = '',
        $redirect = '', $comment = '') {
        $this->log('returning response: valid: '.$valid.
            ', orig url: '.$orig.
            ', path: '.$path.
            ', code: '.$code.
            ', redirect: '.$redirect.
            ', comment: '.$comment
        );
        $this->response =  array(
            'valid' => $valid,
            'orig_url' => $orig,
            'path' => $path,
            'code' => $code,
            'redirect' => $redirect,
            'comment' => $comment
        );
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
                    $parts['scheme'] = 'http';
                }
                // reconstruct the URL
                $url = $parts['scheme'].'://'.$parts['host'].$parts['path'];
                $this->log('updated url: '. $url);
            } else {
                $this->log('unable to parse URL: '.$url);
                $this->set_error(false, $orig, $path, '404', '', 'This URL is not valid!');
                return $this->response;
            }
            // now query the URL and work out the response
            $this->log('testing for the existence of '.$url);
            $headers = @get_headers($url);
            if ($headers){
                $this->log('looks like we found something! Returns: '.
                    print_r($headers, true));
                switch ($headers[0]) {
                    case 'HTTP/1.0 200 OK':
                    case 'HTTP/1.1 200 OK':
                        $this->log('Yay! Returning valid url: '.$orig);
                        $this->set_error(true, $orig, $path, '200', '', $orig.' is a valid site!');
                    break;
                    case 'HTTP/1.0 301 Moved Permanently':
                    case 'HTTP/1.1 301 Moved Permanently':
                        foreach ($headers as $header) {
                            $line = explode(': ', $header);
                            if ($line[0] == 'Location') {
                                $this->set_error(true, $orig, $path, '301', $line[1], $orig.' redirected to '.$line[1]);
                                break;
                            }
                        }
                    break;
                    case 'HTTP/1.0 302 Moved Temporarily':
                    case 'HTTP/1.1 302 Moved Temporarily':
                        foreach ($headers as $header) {
                            $line = explode(': ', $header);
                            if ($line[0] == 'Location') {
                                $this->set_error(true, $orig, $path, '302', $line[1], $orig.' redirected to '.$line[1]);
                                break;
                            }
                        }
                    break;
                    case 'HTTP/1.0 302 Found':
                    case 'HTTP/1.1 302 Found':
                        foreach ($headers as $header) {
                            $line = explode(': ', $header);
                            if ($line[0] == 'Location') {
                                $this->set_error(false, '302', $orig, $path, $line[1], $orig.' redirected to '.$line[1]);
                                break;
                            }
                        }
                    break;
                    case 'HTTP/1.0 404 Not Found':
                    case 'HTTP/1.1 404 Not Found':
                        $this->set_error(false, $orig, $path, '404', '', $orig.' was not found The URL might be wrong, or the site currently unavailable for some reason.');
                    break;
                    case 'HTTP/1.0 410 Gone':
                    case 'HTTP/1.1 410 Gone':
                        $this->set_error(false, $orig, $path, '410', '', $orig.' was valid, but its host is now listing it as "Gone"');
                    break;
                    default:
                        $this->log('got unknown result: '. $headers[0]);
                        $this->set_error(false, $orig, $path, '', 'unknown', $orig.' got an unexpected result: '.$headers[0]);
                    break;
                }
            } else {
                $this->log('no headers returned');
                $this->set_error(false, $url, '', 'unknown', '', 'The URL entered, "'.$url.'", isn\'t found. The internet doesn\'t think it exists - perhaps a spelling error?');
            }
        } else {
            $this->log('empty URL');
            $this->set_error(false, '', '', 'unknown', '', 'No URL entered!');
        }
        return $this->response;
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
                $this->response['valid'] = false;
                $this->response['comment'] = 'Oops, this isn\'t a valid blog, but it\'s one people commonly enter by accident. Have a look at our support site\'s "<a href=\'https://course.oeru.org/support/studying-courses/course-blog/\'>blog</a>" section...';
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
