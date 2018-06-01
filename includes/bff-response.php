<?php
/*
 * Response object class for returning useful info to a user
 */
require BFF_PATH . '/includes/bff-base.php';

abstract class BFFResponse extends BFFBase {
    protected static $response = array();
    protected $response_types = array(
        'neutral' => '<span class="bff-emoji bff-neutral">&nbsp;</span>',
        'problem' => '<span class="bff-emoji bff-problem">&nbsp;</span>',
        'good' => '<span class="bff-emoji bff-good">&nbsp;</span>',
    );

    // create a default response object, adding a message if given
    public function set_response($valid = false, $orig = '', $path = '', $code = '',
        $redirect = '', $message = '', $type = 'neutral') {
        $this->response['valid_url'] = $valid;
        $this->response['orig_url'] = $orig;
        $this->response['path'] = $path;
        $this->response['code'] = $code;
        $this->response['redirect'] = $redirect;
        if ($message != '') {
            $this->add_message($message, $type);
        }
    }

    // add another message to the response object
    public function add_message($msg, $type = 'neutral') {
        $this->response['messages'][] = array(
            'message' => $msg,
            'type' => $type,
        );
    }

    // set the content type on the response object
    public function set_content_type($headers) {
        if ($content_type = $this->get_content_type($headers)) {
            $this->response['content_type'] = $content_type;
        }
    }

    // extract and return the content type
    public function get_content_type($headers) {
        foreach ($headers as $header) {
            $line = explode(': ', $header);
            if ($line[0] == 'Content-Type') {
                $content_string = explode(';', $line[1]);
                $content_type = $content_string[0];
                $content_charset = $content_string[1]; // we're ignoring this for the time being
                $this->log('content type: '.$content_type.', charset: '.$content_charset);
                return $content_type;
            }
        }
        return false;
    }

    public function get_redirect($headers) {
        foreach ($headers as $header) {
            $line = explode(': ', $header);
            if ($line[0] == 'Location') {
                return $line[1];
            }
        }
        return false;
    }

    // return the last message added, or false if there are none
    public function get_last_message() {
        if ($cnt = count($this->response['messages'])) {
            return $this->response['messages'][$cnt-1];
        }
        return false;
    }

    // create a sutiable oject
    public function ajaxresponse() {
        $this->response['success'] = true;
        $this->response['types'] = $this->response_types;
        return $this->response;
    }
}

?>
