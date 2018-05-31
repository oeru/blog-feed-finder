<?php

require BFF_PATH . '/includes/bff-course.php';

class BFFForm extends BFFCourse {
    protected static $instance = NULL; // this instance
    private $errors = array();

    // returns an instance of this class if called, instantiating if necessary
    public static function get_instance() {
        NULL === self::$instance and self::$instance = new self();
        return self::$instance;
    }

    // this starts everything...
    public function init() {
        $this->log('in init');
        $this->register_scripts();
        $this->register_styles();
        // register actions
        add_shortcode(BFF_SHORTCODE, array($this, 'shortcode'));
        // allows us to add a class to our post
        add_filter('body_class', array($this, 'add_post_class'));
        add_filter('post_class', array($this, 'add_post_class'));
        // and create the post to hold short code...
        $this->create_post(BFF_SLUG);
        $this->log('setting up scripts');
        // add the ajax handlers
        wp_enqueue_script('bff-script', BFF_URL.'js/script.js', array(
            'jquery', 'jquery-form'
        ));
        wp_localize_script('bff-script', 'bff_data', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce_submit' => wp_create_nonce('bff-submit-nonce'),
            'nonce_set' => wp_create_nonce('bff-set-nonce'),
        ));
        // this enables the feedfinder service for authenticated users...
        add_action('wp_ajax_bff_submit', array($this, 'ajax_submit'));
        // this allows users who aren't authenticated to use the feedfinder
        add_action('wp_ajax_nopriv_bff_submit', array($this, 'ajax_submit'));
        // this enables the setblogfeed service for authenticated users...
        add_action('wp_ajax_bff_set', array($this, 'ajax_set'));
        $this->log('finished setting up scripts');
    }

    protected function register_scripts() {
        $this->log('in register_scripts');
        //register
        wp_register_script('bff-script', plugins_url('js/script.js', __FILE__));
        //enqueue
        wp_enqueue_script('jquery');
        wp_enqueue_script('bff-script');
    }

    protected function register_styles() {
        $this->log('in register_styles');
        //register
        wp_register_style('bff-style', plugins_url('css/style.css', __FILE__));
        //enqueue
        wp_enqueue_style('bff-style');
    }

    // the function called after the bff-submit button is clicked in our form
    public function ajax_submit() {
       $this->log('in ajax_submit: '.print_r($_POST, true));
       // check if the submitted nonce matches the generated nonce created in the auth_init functionality
       if ( ! wp_verify_nonce( $_POST['nonce_submit'], 'bff-submit-nonce') ) {
           die ("Busted - someone's trying something funny in submit!");
       } else {
           $this->log('bff-submit-nonce all good.');
       }
       $this->log("processing submit form...");
       // generate the response
       header( "Content-Type: application/json" );
       $this->ajax_response(array('success' => $this->process()));
       // IMPORTANT: don't forget to "exit"
       //exit;
       $this->log('ajax_submit done, dying...');
       wp_die();
    }

    public function ajax_set() {
        $this->log('in ajax_set: '.print_r($_POST, true));
        // check if the submitted nonce matches the generated nonce created in the auth_init functionality
        if ( ! wp_verify_nonce( $_POST['nonce_set'], 'bff-set-nonce') ) {
            $this->log('bff-set-nonce ain\'t right!');
            die ("Busted - someone's trying something funny in set!");
        } else {
            $this->log('bff-set-nonce all good.');
        }
        $this->log("processing set form...");
        // generate the response
        header( "Content-Type: application/json" );
        $this->ajax_response(array('success' => $this->process()));
        // IMPORTANT: don't forget to "exit"
        //exit;
        $this->log('ajax_set done, dying...');
        wp_die();
    }

    // process the form. This to be replaced by an ajax form
    public function process() {
        $this->log('in process with _POST array: '. print_r($_POST, true));
        if (isset($_POST['action']) && $_POST['action'] == 'bff_submit') {
            $url = $_POST['url'];
            $this->log('looking at URL = '. $url);
            $this->process_url($url);
            // update the response object with the courses array...
            $this->response = $this->list_courses();
            // alert script.js that the user is not logged in!
            if (!is_user_logged_in()) {
                $this->response['authenticated'] = false;
            } else {
                $this->response['authenticated'] = true;
            }
            //$this->log('processing submit: returned response object: '. print_r($this->response, true));
            // sending the response object to be converted to JSON and returned to
            // the calling page
            $this->ajax_response($this->ajaxfeeds());
            //$this->log('back from ajaxfeeds...');
            //return true;
        } elseif (isset($_POST['action']) && $_POST['action'] == 'bff_set') {
            $user = $this->get_current_user();
            $uid = $user->ID;
            //$course = $this->get_course_details($_POST['course']);
            $feed = $_POST['feed'];
            $this->log('processing set request for feed = '. print_r($feed, true));
            $url = $feed['url'];
            $type = $feed['type'];
            $this->log('feed info: '.print_r($feed, true));
        //    $id = $course['id'];
        //    $tag = $course['tag'];
            $id = $_POST['course_id'];
            $tag = $_POST['course_tag'];
            $this->log('setting the feed URL for '.$uid.' in course '.$id.' ('.$tag.
                ') to '.$url.' of type '.$type);
            if ($this->process_set_feed($uid, $id, $url, $type)) {
                $this->log('processing set: returned response object: '. print_r($this->response, true));
                //return true;
            } else {
                $this->log('failed to process set...');
            }
        } else {
            $this->log('no POST action found...');
        }
        //self::form();
        //return false;
    }

    // define what happens when the shortcode - BFF_SHORTCODE - is fired...
    public function shortcode($atts,$content="") {
        $this->log('in shortcode');
        ob_start();
        //$this->process();
        $this->form();
        return ob_get_clean();
    }

    // for the administrative functionality
    public function form() {
        $this->log('in form');
        $course_list;
        $feed_list;

        // alert the user that they're not logged in
        if (!is_user_logged_in()) {
        //if (is_user_logged_in()) {
            $this->log('the current user ++isn\'t++ logged in!');
            $this->alert_anon_user();
        } else {
            $this->log('the current user *is* logged in!');

            // compile a list of courses the user's registered for
            //$this->list_courses();
        }
        // compile a list of valid feeds (ideally, just one)
        //$feed_list = $this->list_feeds();

        // outputs the options form on admin
        ?>
        <form id="<?php echo BFF_ID; ?>" class="<?php echo BFF_CLASS; ?>" target="#">
            <label class="<?php echo BFF_CLASS; ?>"><?php echo __('Find your blog\'s feed address!'); ?></label><br/>
            <input id="bff-url" class="url" type="text" name="bff-url" value="" />
            <span id="bff-submit" class="submit button" href="#">Submit</span><br/>
            <div id="bff-feedback" class="feedback">
                <p>Feedback...</p>
            </div>
            <div id="bff-feeds" class="feeds" hidden>
                <div id="bff-feed-list" class="bff-alert-box bff-info" hidden></div>
                <div id="bff-course-list" class="bff-alert-box bff-info" hidden></div>
            </div>
        </form>
        <?php
    }

    // create a default post to hold our form...
    public function create_post($slug) {
        $post_id = -1; // this is non-post right now...
        if (!($post_id = $this->slug_exists($slug))) {
            $this->log('Creating a post at '.$slug.'...');
            $post = $this->get_post($slug);
            // check to see if this page title is already used...
            $blog_page_check = get_page_by_title($post['post_title']);
            if (!isset($blog_page_check->ID)) {
                if (!($post_id = wp_insert_post($post))) {
                    $this->log('Inserting post at '.$slug.' failed!');
                    return false;
                }
            } else {
                $this->log('Already have a page with this title - id: '.$blog_page_check->ID);
                return false;
            }
        } else {
            $this->log('Not creating the content again.');
        }
        $this->log('returing post id '.$post_id);
        return $post_id;
    }

    public function add_post_class($classes) {
        global $post;
        $post_slug=$post->post_name;
        $this->log('running body_class filter - post_slug = '.$post_slug);
        if ($post_slug === BFF_SLUG) {
            $this->log('setting class on '.BFF_SLUG.' to '.BFF_CLASS);
            $classes[] = BFF_CLASS;
        }
        return $classes;
    }

    // check to see if a post with the given slug already exists...
    public function slug_exists($slug) {
        global $wpdb;
        $this->log('checking for a page at '.$slug);
        if ($wpdb->get_row("SELECT post_name FROM wp_posts WHERE post_name = '" . $slug . "'", 'ARRAY_A')) {
            return true;
        }
        return false;
    }

    private function get_post($slug) {
        $this->log('in get_post');
        // create the post array with boilerplate settings...
        $post = array(
            'comment_status' => 'closed',
            'ping_status' => 'closed',
            'post_status' => 'publish',
            'post_type' => 'page'
        );
        // Set the Author, Slug, title and content of the new post
        $post['post_author'] = 1;  // the default
        $post['post_name'] = $slug;
        $post['post_slug'] = $slug;
        $post['post_title']  = 'Blog Feed Finder';
        $post['post_content'] = "<p>The Blog Feed Finder helps you work out the exact web address (URL) for your blog's feed.</p>"
            ."<p>You can start by going to your blog in another browser tab or window...</p>"
            ."<p>Copy the web address - the text in your browser's 'address bar' which starts with '<strong>http://</strong>' or '<strong>https://</strong>' - "
            ."and pasting it into the text field below.</p>[".BFF_SHORTCODE."]";
        return $post;
    }
}

?>
