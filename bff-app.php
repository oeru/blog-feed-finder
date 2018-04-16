<?php

class BFF_Widget extends WP_Widget {

        public function __construct() {
            // widget actual processes
            parent::__construct('BFF_Widget', 'Timer Widget', array('description' => __('A Timer Plugin Widget', 'text_domain')));
        }

        public function form() {
            if (!$_POST)
                $bff_arr = unserialize(get_option('bff_ID_' . $this->number));
            else {
                if ($_POST['tp-hidd'] == 'true') {
                    $bff_arr['tp-title'] = $_POST['tp-title'];
                    $bff_arr['tp-date'] = $_POST['tp-date'];
                    $bff_arr['tp-hour'] = $_POST['tp-hour-val'];
                    $bff_arr['tp-minute'] = $_POST['tp-minute-val'];
                    update_option('bff_ID_' . $this->number, serialize($bff_arr));
                }
            }
            // outputs the options form on admin
            ?>
            <label>Title:</label><br/>
            <input type="text" name="tp-title" class="tp-title" value="<?php echo $bff_arr['tp-title']; ?>" /><br/><br/>
            <a class="tp-time-edit" href="javascript:void(0);">[ Edit ]</a><br/><br/>
            <label>Select a date:</label><br/>
            <input type="text" name="tp-date" class="tp-date" readonly="true" value="<?php echo $bff_arr['tp-date']; ?>" />
            <p><div class="tp-time"><label>Hours</label>
                <input name="tp-hour-val" class="tp-hour-val" value="<?php echo $bff_arr['tp-hour']; ?>" readonly="true" /><div class="tp-hour"></div>
            </p>
            <p><label>Minutes</label>
            <input name="tp-minute-val" class="tp-minute-val" readonly="true" value="<?php echo $bff_arr['tp-minute']; ?>"><div class="tp-minute"></div></div></p>
            <input type="hidden" name="tp-hidd" value="true" />
            <?php
        }

        public function update($new_instance, $old_instance) {
            // processes widget options to be saved
            if ($_POST['tp-hidd'] == 'true') {
                $bff_Arr['tp-title'] = $_POST['tp-title'];
                $bff_Arr['tp-date'] = $_POST['tp-date'];
                $bff_Arr['tp-hour'] = $_POST['tp-hour-val'];
                $bff_Arr['tp-minute'] = $_POST['tp-minute-val'];

                $bff_Arr['tp-date'] = $_POST['tp-date'];
                update_option('bff_ID_' . $this->number, serialize($bff_Arr));
            }
        }

        public function widget($args, $instance) {
            $bff_arr = unserialize(get_option('bff_ID_' . $this->number));
            $temp_date = explode("/", $bff_arr['tp-date']);
            $bff_arr['tp-date'] = $temp_date[2] . "/" . $temp_date[0] . "/" . $temp_date[1];
            extract($args);
            $title = apply_filters('widget_title', $bff_arr['tp-title']);

            echo $before_widget;
            if (!empty($title))
                echo $before_title . $title . $after_title;
            ?>
            <div class="timer-main">
                <input type="hidden" class="tp-widget-date" value="<?php echo $bff_arr['tp-date']; ?>" />
                <input type="hidden" class="tp-widget-time" value="<?php echo $bff_arr['tp-hour'] . ":" . $bff_arr['tp-minute']; ?>:0" />
                <ul class="tp-head">
                    <ol>YY</ol>
                    <ol>DD</ol>
                    <ol>HH</ol>
                    <ol>MM</ol>
                    <ol>SS</ol>
                </ul>
                <ul>
                    <li>-</li>
                    <li>-</li>
                    <li>-</li>
                    <li>-</li>
                    <li>-</li>
                </ul>
            </div>
            <?php
            echo $after_widget;
            // outputs the content of the widget
        }

    }

    function bff_widgets_init() {
        register_widget("bff_Widget");
    }

    function bff_admin_register_scripts() {
        //register
        wp_register_script('tp-admin-script', plugins_url('admin-script.js', __FILE__));
        //enqueue
        wp_enqueue_script('jquery-ui-core');
        wp_enqueue_script('jquery-ui-datepicker');
        wp_enqueue_script('jquery-ui-slider');
        wp_enqueue_script('tp-admin-script');
    }

    function bff_admin_register_styles() {
        //register
        wp_register_style('jquery-ui-theme', plugins_url('jquery-ui.css', __FILE__));
        wp_register_style('tp-admin-style', plugins_url('admin-style.css', __FILE__));

        //enqueue
        wp_enqueue_style('jquery-ui-theme');
        wp_enqueue_style('tp-admin-style');
    }

    function bff_register_scripts() {
        //register
        wp_register_script('tp-script', plugins_url('script.js', __FILE__));

        //enqueue
        wp_enqueue_script('jquery');
        wp_enqueue_script('tp-script');
    }

    function bff_register_styles() {
        //register
        wp_register_style('tp-style', plugins_url('style.css', __FILE__));

        //enqueue
        wp_enqueue_style('tp-style');
    }

    function bff_save_postdata() {
        global $post;
        if ($_POST['tp-hidd'] == 'true') {
            $bff_arr['tp-date'] = $_POST['tp-date'];
            $bff_arr['tp-hour'] = $_POST['tp-hour-val'];
            $bff_arr['tp-minute'] = $_POST['tp-minute-val'];
            update_option('bff_pp_ID_' . $post->ID, serialize($bff_arr));
        }
    }

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
    }

    function bff_shortcode($atts,$content="") {
        global $post;

        $bff_arr = unserialize(get_option('bff_pp_ID_' . $post->ID));
        $temp_date = explode("/", $bff_arr['tp-date']);
            $bff_arr['tp-date'] = $temp_date[2] . "/" . $temp_date[0] . "/" . $temp_date[1];
        return '<div class="timer-content">
            <input type="hidden" class="tp-widget-date" value="'.$bff_arr["tp-date"].'" />
            <input type="hidden" class="tp-widget-time" value="'.$bff_arr["tp-hour"].':'.$bff_arr["tp-minute"].':0" />
                <ul class="tp-head">
                    <ol>YY</ol>
                    <ol>DD</ol>
                    <ol>HH</ol>
                    <ol>MM</ol>
                    <ol>SS</ol>
                </ul>
                <ul>
                    <li>-</li>
                    <li>-</li>
                    <li>-</li>
                    <li>-</li>
                    <li>-</li>
                </ul>
            </div>';
    }

    function bff_admin_init() {
        add_meta_box('bff_metaid', __('Timer Plugin Post Settings', 'bff_textdomain'), 'bff_inner_custom_box', 'post', 'side');
        add_meta_box('bff_metaid', __('Timer Plugin Page Settings', 'bff_textdomain'), 'bff_inner_custom_box', 'page', 'side');
    }

    add_action('widgets_init', 'bff_widgets_init');
    add_action('admin_print_scripts', 'bff_admin_register_scripts');
    add_action('admin_print_styles', 'bff_admin_register_styles');
    add_action('wp_print_scripts', 'bff_register_scripts');
    add_action('wp_print_styles', 'bff_register_styles');
    add_action('admin_init', 'bff_admin_init');
    add_action('save_post', 'bff_save_postdata');
    add_shortcode('tp-shortcode', 'bff_shortcode');


}
?>
