<?php

abstract class TXST_MCEForm {
    // version 1.0.3 - FINAL
    const _TYPE_ALHANUMERIC = 1; // letters and numbers
    const _TYPE_DIGIT       = 2; // numbers only
    const _TYPE_ALPHABET    = 3; // letters only
    const _TYPE_FLOAT       = 4; // set the number of digits after the decimal point
    const _TYPE_GENDER      = 5; // m or f <- value in the database
    const _FIELD_REQUIRED   = 6; // field cannot be empty
    
    protected $_validators = array();    
    
    protected $_id; //plugin id
    protected $_classes = array(); //plugin class
    protected $_prefix;
    protected $_data;
    protected $_dbid;
    protected $_values = array();
    protected $_slug;
    protected $_plugin_folder;

    
    protected $_errors = array();
    
    protected $_error_messages = array('1' => 'Accepts letters and numbers only.', '2' => 'Accepts numbers only.', '3' => 'Accepts letters only.');
    protected $_conditions = array();

    public function __construct($id, $options = array()) {
        $this->_plugin_folder = basename(dirname(dirname(__FILE__)));
        $this->_id    = $id;
        $type         = (isset($options['type'])) ? $options['type'] :'';
        
        $this->_slug = $options['slug'];
        $library_folder = basename(dirname(__FILE__));
        $this->_prefix = 'form-' . strtolower($library_folder);
        
        $this->_classes []= $this->_prefix;
        $this->_classes []= (isset($options['class'])) ? $options['class'] : 'frm-' . uniqid();

        if ($type == 'wp') {
            add_action('wp_footer', array($this, 'addScript'));
        }
        else if($type == 'admin'){
            add_action('admin_footer', array($this, 'addScript'));        
        }
    }
    
    public static function _init() {
        $class = get_class();
        $library_folder = basename(dirname(__FILE__));
        $prefix = 'form-' . strtolower($library_folder);
        
        add_action('wp_ajax_' . $prefix, array($class, 'ajaxCallSaveForm'));
        add_action('wp_ajax_nopriv_' . $prefix, array($class, 'ajaxCallSaveForm'));        

        add_action('wp_ajax_' . $prefix . '_related_fields', array($class, 'ajaxCallRelatedFields'));
        add_action('wp_ajax_nopriv_' . $prefix . '_related_fields', array($class, 'ajaxCallRelatedFields'));        
        
        add_action('wp_footer' . $prefix, array($class, 'setFormStyles'));    
        add_action('admin_head' . $prefix, array($class, 'setFormStyles'));    
    }       
    
    abstract protected function showFormElements();
    
    public function getErrors() {
        return $this->_errors;
    }
    
    public function isValid($data) {
        foreach ($this->_validators as $fieldname => $validators) {
            foreach ($validators as $v) {
                $value = $data[$fieldname];
                if ($v == self::_FIELD_REQUIRED && !$value) {
                    $this->_errors [$fieldname] = 'This is a required field.';
                    continue;
                }
                else if ($value) {
                    $is_valid = $this->isValidField($v, $value);
                    if (!$is_valid) {
                        $this->_errors [$fieldname] = $this->_error_messages[$v];
                        continue;
                    }
                }
            }
        }

        if (count($this->_errors) > 0) {
            return false;
        }
        
        return true;
    }

    public function isValidField($type, $value, $args = array()) {
        switch($type) {
            case 1: // alphanumeric
                if (ctype_alnum($value)) {
                    return true;
                }
                break;
            case 2: // digit
                if (is_numeric($value)) {
                    return true;
                }
                break;
            case 3: // letters
                if (ctype_alpha($value)) {
                    return true;
                }
                break;
            case 4: // float
                break;
            case 5: // gender
                break;
            default :
                return true;
        }
        return false;
    }
    
    protected function _getValue($db_fieldname, $default = '') {
        $value = (isset($this->_data->$db_fieldname)) ? $this->_data->$db_fieldname : $default;
        return $value;
    }
    
    public function setValues($data) {
        $this->_data = $data;
    }

    public function setData($name, $value) {
        $v = array($name => $value);
        $this->setValues((object)$v);
    }
    
    public function setDBID($id) {
        $this->_dbid = $id;
    }     
    
    public function getConditions() {
        return $this->_conditions;
    }

    protected function _isConditionMeet($f, $details) {
        $db_fn = str_replace('-', '_', $f);
        $val = $this->_data->$db_fn;
        switch ($details['operator']) {
            case '=':
                if ($val == $details['value']) {
                    //echo "Ret true\n";
                    return true;
                }
                break;
        }
        
        return false;
    }

    public function isShowField($details = array()) {
        $has_condition = false;
        $conditions = $details;
        //print_r($details);
        $action = $details['action'];
        
        if (is_array($conditions)) {
            $condition_success = false;
            $has_condition = (count($conditions)) > 0 ? true : false;
            foreach ($conditions as $f => $details2) {
                $condition_success = $this->_isConditionMeet($f, $details2);
                $action = $details2['action'];
                if (!$condition_success) {
                    break;
                }
            }
        }
        
        if(!$has_condition || ($has_condition && $action == 'show' && $condition_success) || ($has_condition && $action == 'hide' && !$condition_success)) {
            return true;
        }        
        
        return false;
    }
    
    public function ajaxCallRelatedFields() {
        $opt = sanitize_text_field($_POST['opt']);
        $opt['form-id'] = sanitize_text_field($_POST['key_id']);
        $data['hide'] = array();
        $data['show'] = array();
        $class_name = get_class();
        $class = $class_name::unswapChars(sanitize_text_field($_POST['key_cn']));
        $obj = new $class();

        $conditions = $obj->getConditions();

        foreach ($conditions as $fieldname => $cond) {
            $db_fn = str_replace('-', '_', sanitize_text_field($_POST['fieldname']));
            $obj->setData($db_fn, sanitize_text_field($_POST['value']));
            $hide_class = (!$obj->isShowField($cond)) ?  true : false;
            if ($hide_class) {
                $data['hide'][] = $fieldname;
            }
            else {
                $data['show'][] = $fieldname;
            }
        }

        $data['success'] = true;
        print_r( json_encode($data));
        exit;        
    }

    public static function ajaxCallSaveForm() {
        $data1 = sanitize_text_field($_POST['data']);
        $db_id = sanitize_text_field($_POST['dbid']);

        if ($db_id) {
            $data1['ID'] = $db_id;
        }

        $opt = sanitize_text_field($_POST['opt']);
        $opt['form-id'] = sanitize_text_field($_POST['key_id']);

        $class_name = get_class();
        $class = $class_name::unswapChars(sanitize_text_field($_POST['key_cn']));
        $obj = new $class();
        $data = $obj->saveForm($data1, $opt);
        print_r( json_encode($data));
        exit;
    }
     
    public function showForm() {
    ?>
    <form class="<?php echo implode(' ', $this->_classes) ?>" id="<?php echo $this->_id ?>" name="form-<?php echo $this->_id ?>" method="post">
        <?php
        if ($this->_dbid) {
            echo '<input type="hidden" name="dbid" value="' . $this->_dbid . '" />';
        }
        ?>        
        <?php $this->showFormElements() ?>
    </form>
    <?php
    }
     
    public static function swapChars($text) {
        $chars1 = 'praywithoutceasing';
        $chars2 = '12345678907!@3#6$%';
         
        for ($i = 0; $i < strlen($text); $i++) {
            $pos = strpos($chars1, $text[$i]);
            if ($pos !== false) {
                $text[$i] = $chars2[$pos];
            }
        }

        return $text;
    }

    public static function unSwapChars($text) {
        $chars1 = '12345678907!@3#6$%';
        $chars2 = 'praywithoutceasing';

        for ($i = 0; $i < strlen($text); $i++) {
            $pos = strpos($chars1, $text[$i]);
            if ($pos !== false) {
                $text[$i] = $chars2[$pos];
            }
        }

        return $text;
    }

    public function _showColorPicker($fieldname, $args = array()) {
        $options = array_merge(array('label' => $fieldname, 'placeholder' => '', 'note1' => ''), $args);

        $db_fieldname = str_replace('_showColorPicker-', '_', $fieldname);
        $value = (isset($this->_data->$db_fieldname)) ? $this->_data->$db_fieldname : (isset($args['default']) ? $args['default'] : '');

        $html = '<div class="std-form-line field-' . $fieldname . '">
                    <label>' . $options['label'] . '</label>
                    <div class="grp">
                        <input class="wp-color-picker std-input ' . (isset($options['classes']) ? $options['classes'] : '') . '" type="text" ' . (($options['placeholder']) ? 'placeholder="' . $options['placeholder'] . '"' : '') . ' id="' . ((isset($args['id']) ? $args['id'] : $fieldname)) . '" name="data[' . $fieldname . ']" value="' . $value . '" />
                    ' . (($options['note1']) ? '<span class="important-note">' . $options['note1'] . '</span>' : '') . '
                        <span class="error"></span>
                        <span class="error-msg"></span>
                    </div>
                </div>';

        echo $html;        
    }    
    
    protected function _showStandardDropDownField($fieldname, $args = array()) {
        $options = array_merge(array('label' => $fieldname), $args);
        $db_fieldname = str_replace('-', '_', $fieldname);
        $value = (isset($this->_data->$db_fieldname)) ? $this->_data->$db_fieldname : (isset($args['default']) ? $args['default'] : '');
        
        $opt = '<select name="data[' . $fieldname . ']" data-name="' . $fieldname . '">';
        foreach ($options['options'] as $idx => $o) {
            $is_selected = ($idx == $value) ? 'selected="selected"': '';
            $opt .= '<option value="' . $idx . '" ' . $is_selected . '>' . $o . '</option>';
        }
        $opt .= '</select>';

        $html = '<div class="std-form-line field-' . $fieldname . '">
                    <label>' . $options['label'] . '</label>
                    <div class="grp">
                        ' . $opt . '
                        <span class="error"></span>
                        <span class="error-msg"></span>                                
                    </div>
                </div>';

        echo $html;
    }

    protected function _showStandardRadioButtonField($fieldname, $args = array()) {
        $options = array_merge(array('label' => $fieldname), $args);
        $cond = (isset($this->_conditions[$fieldname])) ? $this->_conditions[$fieldname] : false;
        $hide_class = ($cond && !$this->isShowField($this->_conditions[$fieldname])) ?  true : false;       
        
        $db_fieldname = str_replace('-', '_', $fieldname);
        $value = (isset($this->_data->$db_fieldname)) ? $this->_data->$db_fieldname : (isset($args['default']) ? $args['default'] : '');
        $opt = '';

        foreach ($options['options'] as $idx => $o) {
            $is_selected = ($idx == $value) ? 'checked="checked"': '';
            $opt .= '<input type="radio" name="data[' . $fieldname . ']" data-name="' . $fieldname . '" value="' . $idx . '" ' . $is_selected . '>' . $o;
        }

        $html = '<div class="std-form-line field-' . $fieldname . '" ' . (($hide_class) ? 'style="display: none"' : '') . '>
                    <label>' . $options['label'] . '</label>
                    <div class="grp">
                        ' . $opt . '
                        <span class="error"></span>
                        <span class="error-msg"></span>                                
                    </div>
                </div>';

        echo $html;

    }

    protected function _showGenderRadioButtonField($fieldname, $args = array()) {
        $options = array_merge(array('label' => $fieldname), $args);
        $this->_fields[$fieldname] = array('validator' => $options['validator']);
        
        $db_fieldname = str_replace('-', '_', $fieldname);
        $value = ($this->_data->$db_fieldname) ? $this->_data->$db_fieldname : (isset($args['default']) ? $args['default'] : '');

        $html = '<div class="std-form-line field-' . $fieldname . '">
                    <label>' . $options['label'] . '</label>
                    <div class="grp">
                        <input type="radio" ' . (($value == 'f') ? 'checked="checked"' : '') . ' name="data[' . $fieldname . ']" value="f"> Female
                        <input type="radio" ' . (($value == 'm') ? 'checked="checked"' : '') . ' name="data[' . $fieldname . ']" value="m"> Male
                        <span class="error"></span>
                        <span class="error-msg"></span>                            
                    </div>
                </div>';

        echo $html;
    }

    protected function _showStandardTextField($fieldname, $args = array()) {
        $options = array_merge(array('label' => $fieldname), $args);

        $db_fieldname = str_replace('-', '_', $fieldname);
        $value = $this->_getValue($db_fieldname, (isset($args['default']) ? $args['default'] : ''));
        
        $html = '<div class="std-form-line field-' . $fieldname . '">
                    <label>' . $options['label'] . '</label>
                    <div class="grp">
                        <input class="std-input ' . (isset($options['classes']) ? $options['classes'] : '') . '" type="text" ' . (($options['placeholder']) ? 'placeholder="' . $options['placeholder'] . '"' : '') . ' id="' . ((isset($args['id']) ? $args['id'] : $fieldname)) . '" name="data[' . $fieldname . ']" value="' . $value . '" />
                        ' . (($options['note1']) ? '<span class="important-note">' . $options['note1'] . '</span>' : '') . '
                        <span class="error"></span>
                        <span class="error-msg"></span>
                    </div>
                </div>';

        echo $html;
    }
    
    protected function _showStandardPasswordField($fieldname, $args = array()) {
        $options = array_merge(array('label' => $fieldname), $args);

        $db_fieldname = str_replace('-', '_', $fieldname);

        $value = $this->_getValue($db_fieldname, (isset($args['default']) ? $args['default'] : ''));
        $html = '<div class="std-form-line field-' . $fieldname . '">
                    <label>' . $options['label'] . '</label>
                    <div class="grp">
                        <input class="std-input ' . (isset($options['classes']) ? $options['classes'] : '') . '" type="password" ' . (($options['placeholder']) ? 'placeholder="' . $options['placeholder'] . '"' : '') . ' id="' . ((isset($args['id']) ? $args['id'] : $fieldname)) . '" name="data[' . $fieldname . ']" style="height: ' . $options['height'] . '" value="' . $value . '" />
                        ' . (($options['note1']) ? '<span class="important-note">' . $options['note1'] . '</span>' : '') . '
                        <span class="error"></span>
                        <span class="error-msg"></span>
                    </div>
                </div>';

        echo $html;
    }    
    
    protected function _showStandardDatePicker($fieldname, $args = array()) {
        $options = array_merge(array(), $args);

        $db_fieldname = str_replace('-', '_', $fieldname);
        $value = ($this->_data->$db_fieldname) ? $this->_data->$db_fieldname : (($args['default']) ? $args['default'] : '');
        
        $html = '<div class="std-form-line field-' . $fieldname . '">
                    <label>' . $options['label'] . '</label>
                    <div class="grp">
                        <input class="date-picker" type="text" ' . (($options['placeholder']) ? 'placeholder="' . $options['placeholder'] . '"' : '') . ' name="data[' . $fieldname . ']" style="height: ' . $options['height'] . '" value="' . $value . '" />
                        <div>' . (($options['note1']) ? '<span class="important-note">' . $options['note1'] . '</span>' : '') . '</div>
                        <span class="error"></span>
                        <span class="error-msg"></span>                                 
                    </div>
                </div>';
        echo $html;
    }    
    
    protected function _showStandardCheckboxField($fieldname, $args = array()) {
        $options = array_merge(array(), $args);
        
        $db_fieldname = str_replace('-', '_', $fieldname);
        $value = $this->_getValue($db_fieldname, (isset($options['default']) ? $options['default'] : ''));
        
        $html = '<div class="std-form-line field-' . $fieldname . '">
                    <label>' . $options['label'] . '</label>
                    <div class="grp">
                        <input type="checkbox" name="data[' . $fieldname . ']" style="height: ' . $options['height'] . '" ' . (($value == 'on') ? 'checked="checked"' : '') . ' />
                        <div>' . (($options['note1']) ? '<span class="important-note">' . $options['note1'] . '</span>' : '') . '</div>
                        <span class="error"></span>
                        <span class="error-msg"></span>                            
                    </div>
                </div>';
        echo $html;
    }    
    
    public function _showStandardTextAreaField($fieldname, $args = array()) {
        $options = array_merge(array(
            'height' => 'auto',
            'note1' => '',
            ), $args);

        $db_fieldname = str_replace('-', '_', $fieldname);
        $value = $this->_getValue($db_fieldname, (isset($options['default']) ? $options['default'] : ''));
        
        $html = '<div class="std-form-line field-' . $fieldname . '">
            <label>' . $options['label'] . '</label>
            <div class="grp">
                <textarea name="data[' . $fieldname . ']" style="height: ' . $options['height'] . '">' . $value . '</textarea>
                <div>' . (($options['note1']) ? '<span class="important-note">' . $options['note1'] . '</span>' : '') . '</div>
                <span class="error"></span>
                <span class="error-msg"></span>                    
            </div>
        </div> ';
        echo $html;
    }
    

//    public function _showWPEditorField($label, $fieldname, $args = array()) {
//        $options = array_merge(array(
//            'height' => 'auto',
//            'note1' => '',
//            ), $args);
//
//        $db_fieldname = str_replace('-', '_', $fieldname);
//        $value = ($this->_data->$db_fieldname) ? $this->_data->$db_fieldname : (($args['default']) ? $args['default'] : '');
//        
//        $html1 = '<div class="std-form-line">
//            <label>' . $label . '</label>
//            <div class="grp">';
//        echo $html1;
//        wp_editor($value, $fieldname, array('teeny' => true, 'editor_height' => '50px', 'media_buttons' => true));
//        $html2 = '<div>' . (($options['note1']) ? '<span class="important-note">' . $options['note1'] . '</span>' : '') . '</div>
//            </div>
//        </div> ';
//        echo $html2;
//    }    
    
    protected function _showMediaUploaderField($fieldname, $args = array()) {
        $options = array_merge(array(
            'height' => 'auto',
            'note1' => '',
            ), $args);

        $this->_fields[$fieldname] = array('validator' => $options['validator']);
        $db_fieldname = str_replace('-', '_', $fieldname);
        $value = ($this->_data->$db_fieldname) ? $this->_data->$db_fieldname : (($args['default']) ? $args['default'] : '');

        $html = '<div class="std-form-line field-' . $fieldname . '">
            <label>' . $options['label'] . '</label>
            <div class="grp">
                <div class="uploader">
                    <input id="' . $fieldname . '" name="data[' . $fieldname . ']" type="text" value="' . $value . '" />
                    <input class="button media-uploader" rel="' .  $fieldname . '" type="button" value="Upload" />
                </div>
                <div>' . (($options['note1']) ? '<span class="important-note">' . $options['note1'] . '</span>' : '') . '</div>
                <span class="error"></span>
                <span class="error-msg"></span>                    
                </div>
            </div>';

        echo $html;
    }         

    public function addScript() {
    ?>
    <script type="text/javascript">
        var $jx = jQuery.noConflict();
        var ajaxurl = '<?php echo admin_url('admin-ajax.php'); ?>';

        $jx(function(){
            $jx('.<?php echo $this->_prefix?> .date-picker').datepicker();
            $jx('.<?php echo $this->_prefix?> .wp-tabs').tabs();
            
            $jx('.<?php echo $this->_prefix?> input, .<?php echo $this->_prefix?> select, .<?php echo $this->_prefix?> textarea').on('change', function(){
                $fieldname = $jx(this).data('name');
                $value = $jx(this).val();
                console.log($fieldname + ' ' + $value);
                
                $jx.ajax({
                    url: ajaxurl, //AJAX file path – admin_url("admin-ajax.php")
                    type: "POST",
                    data: 'action=<?php echo $this->_prefix ?>_related_fields&fieldname=' + $fieldname + '&value=' + $value + '&key_cn=<?php echo $this->swapChars(get_class($this)); ?>&key_id=<?php echo $this->_id ?>',
                    dataType: "json",
                    success: function($data){
                        if ($data.success) {
                            for($i in $data.show) {
                                console.log($data.show[$i]);
                                $jx('div.field-'+$data.show[$i]).slideDown('fast');
                            }
                            for($i in $data.hide) {
                                console.log($data.hide[$i]);
                                $jx('div.field-'+$data.hide[$i]).slideUp('fast');
                            }                            
                        }
                    },
                    error: function($data, $textStatus, $errorThrown){
                        // codes here
                    }
                });                
            });
        });
        
        document.success_form = [];
        
        $jx('form[name="<?php echo 'form-' . $this->_id ?>"]').live('submit', function(){   
            $form = $jx(this).serialize();
            $jx(this).find('input[type="submit"]').attr('disabled', 'disabled').addClass('sending-form');
            $jx('.msg').html('Processing ...');
            $jx('.error-msg').html('');
            $jx('.error').removeClass('check').removeClass('active');
            $jx.ajax({
                url: ajaxurl, //AJAX file path – admin_url("admin-ajax.php")
                type: "POST",
                data: 'action=<?php echo $this->_prefix ?>&' + $form + '&key_cn=<?php echo $this->swapChars(get_class($this)); ?>&key_id=<?php echo $this->_id ?>',
                dataType: "json",
                success: function($data){
                    if ($data.success) {                        
                        $jx('.msg').html($data.message);
                        //document.success_form['<?php echo $this->_id ?>']($data);
                        if ($data.redirect_to) {
                            window.location = $data.redirect_to;
                            //$jx('.sending-form').removeAttr('disabled').removeClass('sending-form');
                        }
                        else {
                            $jx('.sending-form').removeAttr('disabled').removeClass('sending-form');
                            $jx('.msg').html('Changes saved.');
                        }                        
                    }
                    else {
                        $jx('.sending-form').removeAttr('disabled').removeClass('sending-form');                        
                        for ($idx in $data.errors) {
                            $jx('.field-' + $idx).find('.error').addClass('active');
                            $jx('.field-' + $idx).find('.error-msg').html($data.errors[$idx]);
                        }
                        $jx('.msg').html('An error occured. Please try again.');                        
                    }
                },
                error: function($data, $textStatus, $errorThrown){
                    $jx('.sending-form').removeAttr('disabled').removeClass('sending-form');
                    $jx('.msg').html('An error occured. Please try again.');
                }
            });
 
            return false;
        }); 
    </script>
    <style type="text/css">
        #<?php echo $this->_id ?> .msg {
            font-size: 15px;
            font-style: italic;
            margin: 15px 0 0;
        }
        #<?php echo $this->_id ?> .std-form-line {
            padding: 7px 0;
        }
        #<?php echo $this->_id ?> .std-form-line label {
            display: inline-block;
            vertical-align: middle;
            width: 120px;
        }
        #<?php echo $this->_id ?> .grp {
            overflow: hidden;
            clear: both;
            display: inline-block;
            vertical-align: middle;            
        }
        #<?php echo $this->_id ?> .std-form-line .std-input {
            margin: 0;
            display: inline-block;
            vertical-align: middle;
        }
        #<?php echo $this->_id ?> .std-form-line .error {
                      
        }
        #<?php echo $this->_id ?> .std-form-line .error.active {
            border: 1px solid #dddddd;
            border-radius: 20px;
            display: inline-block;
            height: 20px;
            margin-left: 3px;
            vertical-align: middle;
            width: 20px;         
            background: #ddd url(<?php echo plugin_dir_path($this->_plugin_folder) . '/' . $this->_plugin_folder ?>/images/error.jpg) center no-repeat;
        }  
        #<?php echo $this->_id ?> .std-form-line .error.check {
            background: #ddd url(<?php echo plugin_dir_path($this->_plugin_folder) . '/' . $this->_plugin_folder ?>/images/check.jpg) center no-repeat;
        }        
        input.media-uploader {
            position: absolute;
        }
    </style>
    <?php
    }    
    
} //end of class