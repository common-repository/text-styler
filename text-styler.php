<?php
/*
 * Plugin Name: Text Styler (Classic Editor Add-on)
 * Description: This plugin will allow a user to style text/phrase of a post or page. He can set background color, text color, and padding, border, etc. Easily adds styles to all tags such as header,paragraph, list, list item, span, strong, etc.
 * Author: EdesaC
 * Version: 1.1.1
**/

include_once('TXST/MCEForm.php');
include_once('TXST/MCEForm/StyleOptions.php');

TXST_MCEForm::_init();

class TextStyler {
    protected $_plugin_url;
    protected $_plugin_folder;

    public function __construct($file) {
        if (!$file) {
            throw new Error('Missing 1 argument!');
        }

        $this->_plugin_url = plugin_dir_url($file);
        $this->_plugin_folder = basename(dirname($file));

        add_action('admin_head', array($this, 'initTinymceData'));        
        add_action('init', array($this, 'tinymce'));
        add_action('admin_enqueue_scripts', array($this, 'load_admin_scripts_css'));

        add_action('wp_ajax_get_styles', array($this, 'ajaxGetStyles'));
        add_action('wp_ajax_save_styles', array($this, 'ajaxSaveStyles'));
        add_action('wp_ajax_init_styles', array($this, 'ajaxInitStyles'));

        add_action('admin_footer', array($this, 'TS_Modal'));

        add_filter('the_content', array($this, 'filterContent'));
        add_action('wp_footer', array($this, 'load_website_scripts_css')); 
    }

    /**
     * save the customs styles of a post to the database
     */
    public function ajaxSaveStyles() {
        if (!wp_verify_nonce(sanitize_text_field($_POST['nonce']), 'get-text-styles') || !current_user_can('edit_posts')) {
            return ;
        }
                
        $data = $_POST;
        $ret['success'] = false;
        $post_id = sanitize_text_field($data['post_id']);

        if (sanitize_text_field($_POST['type']) == 'post'){            
            $css_id = sanitize_text_field($data['id']);
            $post_styles = get_post_meta($post_id, 'text_styles', true);

            if (!$post_styles || !is_array($post_styles)) {
                $post_styles = array();
            }

            $post_styles[$css_id] = sanitize_meta('text_styles', $data['data'], 'array');
            update_post_meta($post_id, 'text_styles', $post_styles);       
        }    

        
        $new_styles = $this->getPostStyles($post_id);
        $ret['post_id'] = $post_id;
        $ret['success'] = true;

        print_r( json_encode($ret));
        exit;
    }
    
    /**
     * return the customs of a given post
     */
    public function ajaxGetStyles() {
        if (!wp_verify_nonce(sanitize_text_field($_POST['nonce']), 'get-text-styles') || !current_user_can('edit_posts')) {
            return ;
        }

        $data = $_POST;
        $post_id = sanitize_text_field($data['post_id']);
        $css_id = sanitize_text_field($data['id']);
        $post_styles = null;
        $defaults = array('color' => '', 'background-color' => '', 'padding' => '');
        
        if (sanitize_text_field($_POST['type']) == 'post') {
            $post_styles = get_post_meta($post_id, 'text_styles', true);
            $ret['post_id'] = $post_id;
        }


        if (is_array($post_styles) && isset($post_styles[$css_id])) {
            $final_styles = array_merge($defaults, $post_styles[$css_id]);
        }
        else {
            $final_styles = $defaults;
        }

        $ret['styles'] = $final_styles;        
        $ret['success'] = true;

        print_r( json_encode($ret));    
        exit;
    }
    
    public function ajaxInitStyles() {

        if (!wp_verify_nonce(sanitize_text_field($_POST['nonce']), 'get-text-styles') || !current_user_can('edit_posts')) {
            return ;
        }

        if (sanitize_text_field($_POST['type']) == 'post') {
            $ret['styles'] = esc_html__($this->getPostStyles(sanitize_text_field($_POST['post_id'])));
        }

        $ret['success'] = true;

        print_r( json_encode($ret));    
        exit;        
    }

    /**
     * Get the custom styles of the post specified
     * @param type $post_id the id of the post
     * @return string e.g. #id1 {color: red} #id2 {background: green}
     */
    public static function __getStyles($styles_data) {        
        $allowed_styles = array('padding', 'margin', 'color', 'background-color', 'font-family', 'font-size', 'font-weight', 'font-style', 'line-height', 'border-style', 'border-width', 'border-color', 'border-radius', 'list-style-position', 'list-style-type', 'font-style', 'font-weight');
        $s = '';
        if (is_array($styles_data)) {
            $s = '.ts ul, .ts ol {padding: 0; margin: 0;}';
            foreach ($styles_data as $id => $styles) {            
                if (strpos($id, 'st-') === 0) {
                    if (is_array($styles)) {
                        $s .= "#" . $id . " { ";
                        foreach ($styles as $css => $value) {
                            if (in_array($css, $allowed_styles)) {
                                $s .= $css . ': ' . $value . ';';
                            }
                        }
                        $s .= " } ";
                    }

                    if (strpos($id, 'st-UL-') === 0 || strpos($id, 'st-OL-') === 0) {
                        $s .= "#" . $id . " > li{ ";
                        $s .= 'list-style-type: inherit;';
                        $s .= 'list-style-position: inherit;';
                        $s .= " } ";
                    }                
                }
            }
        }

        return $s;
    }
    
    public function getPostStyles($post_id) {
        $post_styles = get_post_meta($post_id, 'text_styles', true);        
        return TextStyler::__getStyles($post_styles);
    }    

    public function filterTinymcePlugin($plugins) {
        $plugins []= 'text_styler_plugin';
        return $plugins;
    }
    
    public function tinymce() {
        add_filter( 'mce_external_plugins', array($this, 'registerTinymcePlugin'));
        add_filter( 'mce_buttons', array($this, 'registerTinymceButton')); 
    }

    public function registerTinymcePlugin($plugin_array) {    
        $plugin_array['text_styler_plugin'] = $this->_plugin_url . 'scripts/tinymce-ts-init.js';
        return $plugin_array;
    }

    public function registerTinymceButton($button_array) {
        array_push($button_array, 'text_styler_button');
        return $button_array;
    }

    public function initTinymceData() {
        global $post;

        if (isset($_GET['post'])) {
            $post_id = $post->ID;
            $post_styles = $this->getPostStyles($post_id);
             ?>
             <script type='text/javascript'>
                var text_styler_data = {
                    'ajax_url': '<?php echo admin_url('admin-ajax.php') ?>',
                    'wp_url' : '<?php echo get_bloginfo('wpurl') ?>',
                    'plugin_url': '<?php echo $this->_plugin_url; ?>',
                    'post_id': <?php echo $post->ID ?>,
                    'styles': '<?php echo $post_styles ?>',                    
                    'type': 'post',
                    'nonce':  '<?php echo wp_create_nonce('get-text-styles') ?>'
                };
             </script>
             <?php
        }

    }
    
    public function filterContent($content){
        return '<div ciass="ts">' . $content . '</div>';
    }
    
    public function load_website_scripts_css() {
        wp_enqueue_style($this->_plugin_folder . '-wp-styles', $this->_plugin_url . '/styles/wp-styles.css');
        
        global $post;
        if ($post->ID) {
            $post_id = $post->ID;
            echo $post_id;
            $s = $this->getPostStyles($post_id);

            // load the custom styles for the current post
            $styles = "<style type=\"text/css\">" . $s . "</style>";
            echo $styles;
        }
    }

    public function load_admin_scripts_css() {
        wp_enqueue_media();
        wp_enqueue_script('jquery');

        wp_enqueue_script('jquery-ui-datepicker');
        wp_enqueue_script('jquery-ui-sortable');
        wp_enqueue_script('jquery-ui-tabs');

        wp_enqueue_script(
            'wp-color-picker',
            admin_url( 'js/color-picker.min.js'),
            array( 'iris' ),
            false,
            1
        );
                
        wp_enqueue_script('tinymce-ts-modal', plugins_url('/scripts/tinymce-ts-modal.js', __FILE__));

        wp_enqueue_style('wp-color-picker');

        wp_enqueue_style('css-tinymce-text-styler1', plugins_url('/styles/tinymce-color-picker.css', __FILE__));
        wp_enqueue_style('css-tinymce-text-styler2', plugins_url('/styles/tinymce-text-styler.css', __FILE__));

    }

    public function TS_Modal(){
    ?>
            <input type="hidden" id="textcolorpicker" />
            <div id="editor-ts-popup" style="display: none"></div>
            <div id="editor-ts-popup-wrap" class="wp-core-ui" style="display: none" role="dialog" aria-labelledby="link-modal-title">
            <!-- <form id="wp-link" tabindex="-1"> -->
            <?php wp_nonce_field( 'internal-linking', '_ajax_linking_nonce', false ); ?>
            <h1 id="link-modal-title"><?php _e( 'Text Styler' ); ?></h1>
            <button type="button" id="editor-ts-close"><span class="screen-reader-text"><?php _e( 'Close' ); ?></span></button>
            <div id="link-selector">
                <div id="link-options">
                    <div id="text_styler_dialog_wrapper">
                        <div class="help">Need help? Click <a href="http://wordpress.org/support/plugin/text-styler" target="_blacnk">here</a> to submit a ticket! Also check out this <a href="https://www.youtube.com/watch?v=Ev1JMmkp9wU&feature=youtu.be" target="_blank">video tutorial</a> on how to use Text Styler.</div>
                        <div class="form">
                            <div class="step1">
                                <div class="opt"></div>
                                <div class="frm-buttons">
                                    <input type="button" value="Continue" id="continue-step2" class="button-primary" />
                                </div>                     
                            </div>  
                            <div class="step2">
                                <?php
                                $form = new TXST_MCEForm_StyleOptions();
                                $form->showForm();
                                ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
    <?php

    }

} // end of class

new TextStyler(__FILE__);




/* Ajax Callback Hook */
add_action( 'wp_ajax_my_color_mce_css', 'my_color_mce_css_ajax_callback' );
add_action( 'wp_ajax_no_priv_my_color_mce_css', 'my_color_mce_css_ajax_callback' );
 
/**
 * Ajax Callback
 */
function my_color_mce_css_ajax_callback(){
 
    /* Check nonce for security */
    $nonce = isset( $_REQUEST['_nonce'] ) ? $_REQUEST['_nonce'] : '';
    if( ! wp_verify_nonce( $nonce, 'my-color-mce-nonce' ) ){
        die(); // don't print anything
    }
 
    /* Get Link Color */
    $color_link = get_theme_mod( 'color_link', '#ea7521' );
 
    /* Set File Type and Print the CSS Declaration */
    header( 'Content-type: text/css' );
    echo "a,a:hover,a:focus{color:{$color_link}}";
    die(); // end ajax process.
}