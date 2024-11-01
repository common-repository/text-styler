<?php

class TXST_MCEForm_StyleOptions extends TXST_MCEForm {

    public function __construct() {
        $slug = 'text-styler';
        $id = 'text-styler';
        $this->_option_name = $id;
        $this->_validators = array('name' => array(parent::_TYPE_ALPHABET), 'html' => array(parent::_TYPE_ALPHABET));
        $this->_conditions = array(
//                                'font-style' => array(
//                                                    'border-style' => array('operator' => '=', 'value' => 'solid', 'action' => 'show')
//                                                )
                             );
        
        $options = array('type' => 'tinymce', 'slug' => $slug);
        parent::__construct($id, $options);
    }

    protected function showFormElements() {
    ?>
        <div class="tinymce-tabs">
            <ul class="tab-links">
                <li class="first"><a href="#tab-step1">Basic</a></li>
                <li ><a href="#tab-step2">Advance</a></li>
            </ul>
            <div class="tab-content">
                <div id="tab-step1" class="tab">
                    <?php
                    $this->_showColorPicker('color', array('label' => 'Text Color'));
                    
                    $ff = array('Arial', 'Comic Sans MS', 'FontAwesome', 'Georgia', 'Helvetica', 'Lucida Console', "Lucida Grande", "Segoe UI", 'Tahoma', 'Times New Roman', "Trebuchet MS", "Verdana", "monospace");
                    $font_families = array('inherit' => 'Default');

                    foreach ($ff as $font) {
                        $font_families [strtolower($font)] = $font;
                    }

                    $border_styles = array('none' => 'None', 'solid' => 'Solid', 'dashed' => 'Dashed', 'dotted' => 'Dotted', 'double' => 'Double');
                    $list_styles = array('inside' => 'Inside', 'outside' => 'Outside');
                    $list_types = array('disc' => 'Disc', 'circle' => 'Circle', 'decimal' => 'Decimal', 'square' => 'Square', 'upper-alpha' => 'Upper Alpha', 'upper-roman' => 'Upper Roman', 'lower-alpha' => 'Lower Alpha', 'lower-roman' => 'Lower Roman', 'none' => 'None');
        

                    $this->_showStandardDropDownField('font-family', array('label' => 'Font Family', 'options' => $font_families));
                    $this->_showStandardTextField('font-size', array('label' => 'Font Size', 'placeholder' => 'e.g. 14px', 'note1' => 'E.g. 14px'));
                    $this->_showStandardRadioButtonField('font-style', array('label' => 'Font Style', 'options' => array('normal' => 'Normal', 'italic' => 'Italic')));
                    $this->_showStandardRadioButtonField('font-weight', array('label' => 'Font Weight', 'options' => array('normal' => 'Normal', 'bold' => 'Bold')));
                    $this->_showColorPicker('background-color', array('label' => 'Background Color'));
                    $this->_showStandardTextField('margin', array('label' => 'Margin', 'placeholder' => 'e.g. 1px', 'note1' => 'E.g. 1px'));
                    $this->_showStandardTextField('padding', array('label' => 'Padding', 'placeholder' => 'e.g. 1px', 'note1' => 'E.g. 1px'));                    ?>
                </div>
                <div id="tab-step2" class="tab">
<?php
                    $this->_showStandardDropDownField('border-style', array('label' => 'Border Style', 'options' => $border_styles));
                    $this->_showStandardTextField('border-width', array('label' => 'Border Width', 'placeholder' => 'e.g. 1px', 'note1' => 'E.g. 1px'));
                    $this->_showColorPicker('border-color', array('label' => 'Border Color'));
                    $this->_showStandardTextField('border-radius', array('label' => 'Border Radius', 'placeholder' => 'e.g. 1px', 'note1' => 'E.g. 1px'));

?>
                <div id="css-list">
                    <?php
                    $this->_showStandardDropDownField('list-style-type', array('label' => 'List Style Type', 'options' => $list_types));
                    $this->_showStandardDropDownField('list-style-position', array('label' => 'List Style Position', 'options' => $list_styles));
                    ?>
                </div>                    
                </div>
            </div>
        </div>

        <div class="frm-buttons" style="margin-top: 10px;">
            <input type="button" value="Cancel" id="cancel" class="button" />
            <input class="button-primary" type="submit" value="Save" />                
        </div>    
<?php
    }
}