/**
 * @output wp-includes/js/textStyler.js
 */

 /* global textStyler */

( function( $, textStylerV110, wp ) {
	var editor, searchTimer, River, Query, correctedURL,
		inputs = {},
		isTouch = ( 'ontouchend' in document );

	window.textStyler = {
		modalOpen: false,

		init: function() {
			inputs.wrap = $('#editor-ts-popup-wrap');
			inputs.dialog = $( '#wp-link' );
			inputs.backdrop = $( '#editor-ts-popup' );
			inputs.submit = $( '#wp-link-submit' );
			inputs.close = $( '#editor-ts-close' );

			inputs.close.add( inputs.backdrop ).add( '#wp-link-cancel button' ).click( function( event ) {
				event.preventDefault();
				textStyler.close();
			});
		},

		open: function(editorId, editor, $value, $p, $opt, $post_id, url, $type) {
			var ed,
				$body = $(document.body);
			 
            $('.wp-color-picker').wpColorPicker();
            $('.wp-picker-clear').trigger('click');
            $('.date-picker').datepicker();

            $('.step1').css('display','block');
            $('.step2').css('display','none');       

            var $ajax_url = text_styler_data.ajax_url;
            var $wp_url = text_styler_data.wp_url;

            var $field_types = new Array();
            $field_types['color'] = 'color-picker';
            $field_types['border-color'] = 'color-picker';
            $field_types['background-color'] = 'color-picker';
            $field_types['font-family'] = 'dropdown';
            $field_types['border-style'] = 'dropdown';
            $field_types['font-size'] = 'text';
            $field_types['border-width'] = 'text';
            $field_types['margin'] = 'text';
            $field_types['padding'] = 'text';
            $field_types['border-radius'] = 'text';
            $field_types['font-style'] = 'radio';
            $field_types['font-weight'] = 'radio';
            
            // Show available elements

            $('#continue-step2').on('click', function(){
                $id = $('input[name="element"]:checked').val();

                if ($('input[name="element"]:checked').hasClass('UL') || 
                    $('input[name="element"]:checked').hasClass('OL')) {
                    $('#css-list').css('display', 'block');
                }

				if ($type == 'post') {
                    $params = '&type=' + $type + '&post_id=' + text_styler_data.post_id;
                }

                $.ajax({
                    url: $ajax_url, //AJAX file path – admin_url("admin-ajax.php")
                    type: "POST",
                    data: 'action=get_styles&id=' + $id + $params  + '&nonce=' + text_styler_data.nonce,
                    dataType: "json",
                    success: function($data){

                        if ($data.success) {   

                            for($i in $data.styles) {

                                switch ($field_types[$i]) {
                                    case 'color-picker':
                                        $('div.field-' + $i + ' .std-input').wpColorPicker('color', $data.styles[$i]);
                                        break;
                                    case 'text':
                                        $('div.field-' + $i + ' .std-input').val($data.styles[$i]);
                                        break;     
                                    case 'dropdown':
                                        if ($data.styles[$i]) {
                                            $('div.field-' + $i + ' select').val($data.styles[$i]);
                                        }
                                        break;                                                                                
                                    case 'checkbox':
                                        if ($data.styles[$i]) {
                                            $('div.field-' + $i + ' input').attr('checked', 'checked');
                                        }                                        
                                        break;
                                    case 'radio':
                                        $('div.field-' + $i + ' input').removeAttr('checked');
                                        if ($data.styles[$i]) {
                                            $('div.field-' + $i + ' input[value="' + $data.styles[$i] + '"]').attr('checked', 'checked');
                                        }                                        
                                        break;                                        
                                }
                            }
                            $('.step1').css('display','none');
                            $('.step2').css('display','block');                            
                        }
                    }
                });               
            });

            $("form[name='form-text-styler']").submit(function(event) {
                event.preventDefault();
				textStyler.update(editor, $value, $p, $opt, $post_id, url, text_styler_data.type);
				textStyler.close();
            });

            $('#cancel').on('click', function(){
				event.preventDefault();
				textStyler.close();
            });

            $('.step1 .opt').html($opt);


            $('.tinymce-tabs').each(function(){
                $href = $(this).find('li.first a').attr('href');
                $tab_id = $href.substr(1);

                $(this).find('li').removeClass('active');
                $(this).find('li.first').addClass('active');
                $('#'+$tab_id).css('display', 'block');
            });
            
            $('.tinymce-tabs .tab-links li a').on('click', function(){               
                $('.tinymce-tabs .tab-links li').removeClass('active');             
                $(this).parents('li').addClass('active');

                $('.tinymce-tabs .tab-content .tab').css('display', 'none');
                $href = $(this).attr('href');
                $tab_id = $href.substr(1);
                $('#'+$tab_id).css('display', 'block');

                return false;
            });

            
            $('#text_styler_dialog_wrapper').append('<link rel=\'stylesheet\' href=\'' + $wp_url  + '/wp-admin/load-styles.php?c=1&amp;dir=ltr&amp;load%5B%5D=wp-color-picker\' type=\'text/css\' media=\'all\' />');
                    
			$body.addClass('modal-open');
			textStyler.modalOpen = true;

			inputs.wrap.show();
			inputs.backdrop.show();

			// textStyler.refresh( url, text );

			$( document ).trigger( 'textStyler-open', inputs.wrap );
		},

		// buildHtml: function(attrs) {

		// },

		// setDefaultValues: function( selection ) {

		// },

		// refresh: function( url, text ) {

		// },		

		update: function(editor, $value, $p, $opt, $post_id, url, $type) {
            var input_text = $("textarea[name='text']").val();
            $form = $("form[name='form-text-styler']").serialize();

            $id = $('input[name="element"]:checked').val();

            if ($id == 'NEW_EL') {
                $id = $new_id = 'st-SPAN-' + Date.now();
                
                $text = '<span id="' + $id + '">' + (( input_text) ?  input_text : 'text') + '</span>';
                editor.selection.setContent($text);                    
            }
            
			if ($type == 'post') {
                $params = '&' + $form + '&type=' + $type + '&post_id=' + $post_id;
            }

            $.ajax({
                url: $ajax_url, //AJAX file path – admin_url("admin-ajax.php")
                type: "POST",
                data: 'action=save_styles&id=' + $id + $params + '&nonce=' + text_styler_data.nonce,
                dataType: "json",
                success: function($data){
					$params = '&type=' + $type + '&post_id=' + $post_id;

		            $.ajax({
		                url: $ajax_url, //AJAX file path – admin_url("admin-ajax.php")
		                type: "POST",
		                data: 'action=init_styles' + $params + '&nonce=' + text_styler_data.nonce,
		                dataType: "json",
		                success: function($data){      
		                    if ($data.success) {   
		                    	// $(editor.dom.select('style#custom-text-styles')).remove();
		                        // $(editor.dom.select('head')).append('<style type="text/css" id="custom-text-styles">' + $data.styles + '</style>');

                                $('head style#custom-text-styles').remove();
                                $('head').append('<style type="text/css" id="custom-text-styles">' + $data.styles + '</style>');

                                editor.windowManager.close();
		                    }
		                }
		            });
                    
                },
                error: function($data, $textStatus, $errorThrown){
                    
                }
            });  			
		},

		close: function( reset ) {
			$( document.body ).removeClass( 'modal-open' );
			textStyler.modalOpen = false;

			inputs.backdrop.hide();
			inputs.wrap.hide();
			
			$( document ).trigger( 'textStyler-close', inputs.wrap );
		},

	};

	$(document).ready(textStyler.init);

})(jQuery, window.textStylerV110, window.wp);
