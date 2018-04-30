<?php

require BFF_PATH . '/includes/bff-finder.php';

class BFFForm extends BFFFinder {
    protected static $instance = NULL; // this instance
    private $errors = array();

    // returns an instance of this class if called, instantiating if necessary
    public static function get_instance() {
        NULL === self::$instance and self::$instance = new self();
        return self::$instance;
    }

/*    public function __construct() {
        $this->log('in constructor');
        // widget actual processes
        parent::__construct('BFFForm', 'Blog Feed Finder Widget',
            array('description' => __('A Widget to help users specify a valid URL for their blog feed',
            'text_domain')));
        $this->init();
    }*/

    public function init() {
        $this->log('in init');
        $this->register_scripts();
        $this->register_styles();
        // register actions
        /*add_action('admin_init', array($this, 'admin_init'));*/
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
        ));
        add_action('wp_ajax_bff_submit', array($this, 'ajax_submit'));
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
       }
       $this->log("processing form...");
       // generate the response
       header( "Content-Type: application/json" );
       $this->response(array('success' => $this->process()));
       // IMPORTANT: don't forget to "exit"
       exit;
    }

    // process the form. This to be replaced by an ajax form
    public function process() {
        $this->log('in process with '. print_r($_POST, true));
        if (isset($_POST['action']) && $_POST['action'] == 'bff_submit') {
            $url = $_POST['url'];
            $this->log('looking at URL = '. $url);
            $this->process_url($url);
            $this->log('returned error object: '. print_r($this->error, true));
            if (isset($this->error['orig_url'])) $response['orig_url'] = $this->error['orig_url'];
            if (isset($this->error['code'])) $response['code'] = $this->error['code'];
            if (isset($this->error['redirect'])) $response['redirect'] = $this->error['redirect'];
            if (isset($this->error['comment'])) $response['message'] = $this->error['comment'];
            if ($this->error['valid']) {
                $response['success'] = true;
            } else {
                $response['error'] = true;
            }
            $this->response($response);

            /*$this->response(array(
                'success' => true,
                'message' => 'Well done!'
            ));*/
            // call the validation
            /*$this->validate($_POST['bff-url']);

            if (is_array($this->errors)) {
                foreach ($this->errors as $error) {
                    echo '<div>';
                    echo '<strong>ERROR</strong>:';
                    echo $error . '<br/>';
                    echo '</div>';
                }
            }*/
            return true;
        } else {
            $this->log('no bff_submit found...');
        }
        //self::form();
        return false;
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

        if (!$_POST)
            $bff_arr = unserialize(get_option('bff_ID_' . $this->number));
        else {
            if ($_POST['bff-hidd'] == 'true') {
                $bff_arr['bff-url'] = $_POST['bff-url'];
                update_option('bff_ID_' . $this->number, serialize($bff_arr));
            }
        }
        // outputs the options form on admin
        ?>
        <div id="<?php echo BFF_ID; ?>" class="<?php echo BFF_CLASS; ?>">
            <label class="<?php echo BFF_CLASS; ?>"><?php echo __('Find your blog\'s feed address!'); ?></label><br/>
            <input id="bff-url" class="url" type="text" name="bff-url" value="<?php echo $bff_arr['bff-url']; ?>" />
            <a id="bff-submit" class="submit" href="javascript:void(0);">Submit</a>
            <input type="hidden" name="bff-hidd" value="true" /><br/>
            <div id="bff-feedback" class="feedback">
                <p>Feedback...</p>
            </div>
        </div>
        <?php
    }

    public function update($new_instance, $old_instance) {
        $this->log('in update');
        // processes widget options to be saved
        if ($_POST['bff-hidd'] == 'true') {
            $bff_arr['bff-url'] = $_POST['bff-url'];
            update_option('bff_ID_' . $this->number, serialize($bff_arr));
        }
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
        $post['post_content'] = "The Blog Feed Finder helps you work out the exact web address (\"URL\") for your blog's feed. [".BFF_SHORTCODE."] ";
        return $post;
    }
}

?>
