<?php

class BFF_Widget extends WP_Widget {
/*    protected static $instance = NULL; // this instance

    // returns an instance of this class if called, instantiating if necessary
    public static function get_instance() {
        NULL === self::$instance and self::$instance = new self();
        return self::$instance;
    }*/

    public function __construct() {
        $this->log('in constructor');
        // widget actual processes
        parent::__construct('BFF_Widget', 'Blog Feed Finder Widget',
            array('description' => __('A Widget to help users specify a valid URL for their blog feed',
            'text_domain')));
        $this->init();
    }

    public function init() {
        $this->log('in init');
        // set up the widget
        //register_widget("BFF_Widget");
        $this->register_scripts();
        $this->register_styles();
        // register actions
        add_action('admin_init', array($this, 'admini_init'));

    }

    public function admin_init() {
        $this->log('in admin_init');
        /*add_meta_box('bff_metaid', __('Timer Plugin Post Settings', 'bff_textdomain'), 'bff_inner_custom_box', 'post', 'side');
        add_meta_box('bff_metaid', __('Timer Plugin Page Settings', 'bff_textdomain'), 'bff_inner_custom_box', 'page', 'side');*/
        $this->admin_register_scripts();
        $this->admin_register_styles();
    }

    protected function admin_register_scripts() {
        $this->log('in admin_register_scripts');
        //register
        wp_register_script('bff-admin-script', plugins_url('admin-script.js', __FILE__));
        //enqueue
        wp_enqueue_script('jquery-ui-core');
        wp_enqueue_script('jquery-ui-datepicker');
        wp_enqueue_script('jquery-ui-slider');
        wp_enqueue_script('bff-admin-script');
    }

    protected function admin_register_styles() {
        $this->log('in admin_register_styles');
        //register
        wp_register_style('jquery-ui-theme', plugins_url('jquery-ui.css', __FILE__));
        wp_register_style('bff-admin-style', plugins_url('admin-style.css', __FILE__));
        //enqueue
        wp_enqueue_style('jquery-ui-theme');
        wp_enqueue_style('bff-admin-style');
    }

    protected function register_scripts() {
        $this->log('in register_scripts');
        //register
        wp_register_script('bff-script', plugins_url('script.js', __FILE__));
        //enqueue
        wp_enqueue_script('jquery');
        wp_enqueue_script('bff-script');
    }

    protected function register_styles() {
        $this->log('in register_styles');
        //register
        wp_register_style('bff-style', plugins_url('style.css', __FILE__));
        //enqueue
        wp_enqueue_style('bff-style');
    }

    public function shortcode($atts,$content="") {
        global $post;

        $this->log('in shortcode');

        $bff_arr = unserialize(get_option('bff_pp_ID_' . $post->ID));
        $form = '
        <div class="blog-feed-finder-main">
             <label>The URL to your blog</label>
             <input type="text" name="bff-path" class="bff-url" value="'.$bff_arr['bff-url'].'" />
             <a class="bff-submit" href="javascript:void(0);">Submit</a>
             <input type="hidden" name="bff-hidd" value="true" />
        </div>';
        return $form;
    }

    // for the administrative functionality
    public function form($instance) {
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
        <label>The path to your '<?php echo __('Blog Feed Finder Widget'); ?>'</label><br/>
        <input type="text" name="bff-path" class="bff-path" value="<?php echo $bff_arr['bff-path']; ?>" />
        <a class="bff-submit" href="javascript:void(0);">Submit</a>
        <input type="hidden" name="bff-hidd" value="true" />
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

    public function widget($args, $instance) {
        $this->log('in widget');
        $bff_arr = unserialize(get_option('bff_ID_' . $this->number));
        extract($args);

        echo $before_widget;
        ?>
        <div class="blog-feed-finder-main">
            <label>Enter your personal blog's web address:</label>
            <input type="text" name="bff-url" class="bff-url" value="<?php echo $bff_arr['bff-url']; ?>" />
            <a class="bff-submit" href="javascript:void(0);">Submit</a>
            <input type="hidden" name="bff-hidd" value="true" />
        </div>
        <?php
        echo $after_widget;
        // outputs the content of the widget
    }

    // Debugging related //////////////////////////
    //
    // log things to the web server log
    public function log($message) {
        if (BFF_DEBUG) {
            error_log('+++++ DEBUG('.$this->get_caller_info().'): '.$message);
        }
    }

    function get_caller_info() {
        $c = '';
        $file = '';
        $func = '';
        $class = '';
        $trace = debug_backtrace();
        if (isset($trace[2])) {
            $file = $trace[1]['file'];
            $line = $trace[1]['line'];
            $func = $trace[2]['function'];
            if ((substr($func, 0, 7) == 'include') || (substr($func, 0, 7) == 'require')) {
                $func = '';
            }
        } else if (isset($trace[1])) {
            $file = $trace[1]['file'];
            $func = '';
        }
        if (isset($trace[3]['class'])) {
            $class = $trace[3]['class'];
            $func = $trace[3]['function'];
            $file = $trace[2]['file'];
            $line = $trace[2]['line'];
        } else if (isset($trace[2]['class'])) {
            $class = $trace[2]['class'];
            $func = $trace[2]['function'];
            $file = $trace[1]['file'];
            $line = $trace[1]['line'];
        }
        if ($file != '') $file = basename($file);
        $c = $file . "(".$line."): ";
        $c .= ($class != '') ? " " . $class . "->" : "";
        $c .= ($func != '') ? $func . "(): " : "";
        return($c);
    }
}

/*
function bff_inner_custom_box() {
    global $post;

    // Use nonce for verification
    wp_nonce_field(plugin_basename(__FILE__), 'cuschost_noncename');

    $bff_arr = unserialize(get_option('bff_pp_ID_' . $post->ID));
    ?>
    <a class="tp-time-edit" href="javascript:void(0);">[ Edit ]</a><br/><br/>
    <label>Select a date:</label><br/>
    <input type="text" name="tp-date" class="tp-date" readonly="true" value="<?php echo $bff_arr['tp-date']; ?>" />
    <p><div class="tp-time"><label>Hours</label>
        <input name="tp-hour-val" class="tp-hour-val" value="<?php echo $bff_arr['tp-hour']; ?>" readonly="true" /><div class="tp-hour"></div>
    </p>
    <p><label>Minutes</label>
    <input name="tp-minute-val" class="tp-minute-val" readonly="true" value="<?php echo $bff_arr['tp-minute']; ?>"><div class="tp-minute"></div></div></p>
    <input type="hidden" name="tp-hidd" value="true" />
    <input type="button" class="tp-insert-shortcode button-primary" id="publish" value="Insert Short-Code" />
    <?php
}*/


?>
