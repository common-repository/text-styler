(function($) {
    $plugin_url = text_styler_data.plugin_url;
    $post_id = text_styler_data.post_id;
    $styles = text_styler_data.styles;
    $type = text_styler_data.type;
    $ajax_url = text_styler_data.ajax_url;
    $wp_url = text_styler_data.wp_url;
    $nonce = text_styler_data.nonce;

    tinymce.create('tinymce.plugins.text_styler_plugin', {
        init: function(editor, url) {      
            title = 'Text Styler';

            // loads the custom styles of the current post to the tinymce editor
            if ($type == 'post') {
                $params = '&type=' + $type + '&post_id=' + $post_id;
            }

            $.ajax({
                url: $ajax_url, //AJAX file path – admin_url("admin-ajax.php")
                type: "POST",
                data: 'action=init_styles' + $params + '&nonce=' + $nonce,
                dataType: "json",
                success: function($data){      
                    if ($data.success) {   
                        // $(editor.dom.select('head')).append('<style type="text/css" id="custom-text-styles">' + $data.styles + '</style>');
                        $('head').append('<style type="text/css" id="custom-text-styles">' + $data.styles + '</style>');
                    }
                }
            });

            is_style_added = true;            
                        
            editor.addButton('text_styler_button', {
                title: title,
                image: $plugin_url + '/images/ts-mce-button.png',
                cmd: 'text_styler_command',
                onPostRender: function() {
                    var _this = this;   // reference to the button itself
                    editor.on('NodeChange', function(e) {
                        i=tinyMCE.activeEditor;
                        l=i.dom;
                        j=i.selection;
                        list = l.getParent(j.getNode(),"button");
                        node = i.selection.getNode();
                        id = node.id;
                        id_split = id.split('-');   
                        
                        parent = l.getParent(j.getNode(), 'SPAN');
                        if(parent) {
                            parent_id = parent.id;
                            parent_id_split = parent_id.split('-');  
                            is_styled_text = (parent_id_split[0] == 'st') ? true : false;
                        }
                        else {
                            is_styled_text = false;
                        }
                
                        if(id_split[0] == 'st' || is_styled_text) {
                            _this.active(true);
                        }
                        else {
                            _this.active(false);
                        }
                        

                        
                    });
                }
            });

            editor.addCommand('text_styler_command', function() {
                i=tinyMCE.activeEditor;
                l=i.dom;
                j=i.selection;
                node = i.selection.getNode();
                id = node.id;
                id_split = id.split('-');   

                $p = l.getParents(j.getNode());         


                $opt = '<div><input type="radio" class="new-el" checked="checked" name="element" value="NEW_EL"> New Element (SPAN)';
                $opt += '<div class="el-option"><textarea style="width: 100%; height: 30px; font-size: 13px;" name="text">enter text here</textarea></div>';

                for($x in $p) {
                    $node_name = $p[$x].nodeName;
                    if (!$p[$x].id) {
                        $p[$x].id = 'st-' + $node_name + '-' + Date.now();
                    }
                    if ($p[$x].nodeName != 'BODY' && $p[$x].nodeName != 'HTML') {
                        $opt += '<br /><input class="' + $p[$x].nodeName + '" type="radio" name="element" value="' + $p[$x].id + '"> ' + $p[$x].nodeName;
                        $opt += '<div class="' + $p[$x].id + ' el-option">' + $($p[$x]).html() + '</div>';
                    }
                }

                $opt += '</div>';

                if(id_split[0] == 'st') {
                    $value = $(node).html();
                    textStyler.open(i, editor, $value, null, $opt, $post_id, url);
                }
                else {
                    $value = editor.selection.getContent();
                    textStyler.open(i, editor, $value, null, $opt, $post_id, url);
                }

            });
        }
    });

    tinymce.PluginManager.add('text_styler_plugin', tinymce.plugins.text_styler_plugin);

})(jQuery);