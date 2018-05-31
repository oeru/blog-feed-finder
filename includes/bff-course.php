<?php
/*
 * Integration of OERu Course-related functionality
 */
require BFF_PATH . '/includes/bff-finder.php';

class BFFCourse extends BFFFinder {
    protected static $user; // the current user, looking at the content
    protected static $course_list = array(); // a list of courses to pass to the interface

    protected function get_current_user() {
        if (!is_object($this->user)) {
            $this->user = wp_get_current_user();
        }
        return $this->user;
    }

    // show the user info if they're not logged in...
    public function alert_anon_user() {
    ?>
    <div id="bff-auth-notice" class="bff-auth bff-alert-box">
        <p class="bff-notice">You are not currently logged in. As such, you will not be able to link any blog feeds you find to your profile.<br/>You can <a
            data-toggle="modal" title="Click to log in or register for OERu Courses" data-target="#userModal"><span
            class="glyphicon glyphicon-user"></span> log in or register</a> to link your feed(s) to any OERu courses for which you're registered.</p>
    </div>
    <?php
    }

    // show a list of the courses the current user is registered for
    public function list_courses() {
        $user = $this->get_current_user();
        $sites = get_blogs_of_user($user->ID);
        $this->log('listing courses for '. print_r($user->data->user_login, true));
        $this->log('courses: '. print_r($sites, true));
        $course = array();
        foreach($sites as $site) {
            if ($site->userblog_id == 1) {
                $this->log('skipping the default site...');
                continue;
            }
            $course['tag'] = $this->get_site_tag($site);
            $course['id'] = $site->userblog_id;
            $course['path'] = $site->path;
            $course['name'] = $site->blogname;
            $this>log('processing site: '.$site->blogname);
            if ($feed = $this->get_blog_url_for_user_for_site($user, $site)) {
                $this->log('identified feed: '.print_r($feed, true));
                $course['feed'] = $feed;
            }
            $this->course_list[] = $course;
        }
        $this->log('course_list: '. print_r($this->course_list, true));
        $this->response['courses'] = $this->course_list;
        $this->log('response in list_courses... '.print_r($this->response, true));
        return $this->response;
    }

    public function process_set_feed($user_id, $site_id, $url, $type) {
        $this->log('in process_set_feed');
        $this->log('setting the feed URL for '.$user_id.' in course '.$site_id.' to '.$url.' of type '.$type);
        if (update_user_meta($user_id, 'url_'.$site_id, $url)) {
            $this->log('successfully set user '.$user_id.' feed url for site '.
                $site_id.' to '.$url);
            if (update_user_meta($user_id, 'feedtype_'.$site_id, $type)) {
                $this->log('successfully set user '.$user_id.' feed type for site '.
                    $site_id.' to '.$type);
            }
            return true;
        } else {
            $this->log('update failed!');
        }
        return false;
    }

    // given a site id and user id, get any associated blog url or return false
    public function get_blog_url_for_user_for_site($user, $site) {
        $user_id = $user->ID;
        $site_id = $site->userblog_id;
        // get the blog URL set for a user_id and site_id combo
        if ($url = get_user_meta($user_id, 'url_'.$site_id, true)) {
            $this->log('found url '.$url.' for user '.$user_id.' and site '.$site_id);
            return $url;
        }
        $this->log('no url found for user '.$user_id.' and site '.$site_id);
        return false;
    }

    // given a site object, return the site's name
    public function get_site_tag($site) {
        return strtolower(substr($site->path,1,-1));
    }

}
