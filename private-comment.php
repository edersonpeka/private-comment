<?php
/*
Plugin Name: Private Comment
Plugin URI: https://ederson.ferreira.tec.br
Description: Allow commenters to choose restrict their comments exhibition only to site owners
Author: Ederson Peka
Version: 0.0.2
Author URI: https://profiles.wordpress.org/edersonpeka/
Text Domain: private-comment
*/

if ( !class_exists( 'private_comment' ) ) :

class private_comment {
    // Init
    public static function init() {
        // Internationalization
        load_plugin_textdomain( 'private-comment', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );

        // TODO: options para o administrador sobrescrever os textos padrão
        add_action( 'admin_init', array( __CLASS__, 'admin_init' ) );

        add_filter( 'comment_form_field_comment', array( __CLASS__, 'comment_form_field_comment' ) );
        add_action( 'comment_post', array( __CLASS__, 'comment_post' ) );
        add_filter( 'wp_update_comment_data', array( __CLASS__, 'wp_update_comment_data' ) );
        add_filter( 'get_comment', array( __CLASS__, 'get_comment' ) );
        add_filter( 'get_comment_text', array( __CLASS__, 'get_comment_text' ), 10, 2 );
        add_filter( 'the_comments', array( __CLASS__, 'the_comments' ) );
        add_action( 'wp_set_comment_status', array( __CLASS__, 'wp_set_comment_status' ), 10, 2 );
        add_filter( 'comment_row_actions', array( __CLASS__, 'comment_row_actions' ), 10, 2 );
        add_filter( 'edit_comment_misc_actions', array( __CLASS__, 'edit_comment_misc_actions' ), 10, 2 );
    }

    public static function admin_init() {
        // Creating a "new section" on "Options > Discussion" screen
        $section_title = apply_filters( 'private_comment_settings_title', __( 'Private Comment', 'private-comment' ) );
        add_settings_section( 'private_comment_settings', $section_title, array( __CLASS__, 'text' ), 'discussion' );
        // Creating a new "options group" attached to "Options > Discussion"
        //   screen. WordPress will automatically save them, after
        //   sanitizing their value through our callback function
        register_setting( 'discussion', 'private_comment_options', array( __CLASS__, 'options_sanitize' ) );
        // Adding checkbox field "Checked by default"
        $field_label = apply_filters( 'private_comment_default_checked_label', __( 'Checked by default', 'private-comment' ) );
        add_settings_field( 'private_comment_default_checked', $field_label, array( __CLASS__, 'default_checked_field' ), 'discussion', 'private_comment_settings' );
        // Adding text field "Label text"
        $field_label = apply_filters( 'private_comment_label_text_label', __( 'Label text', 'private-comment' ) );
        add_settings_field( 'private_comment_label_text', $field_label, array( __CLASS__, 'label_text_field' ), 'discussion', 'private_comment_settings' );
    }
    // Description of our "new section"
    public static function text() {
        // void
    }
    // Sanitize our options
    public static function options_sanitize( $ops ) {
        // sanitizing options array
        if ( !is_array( $ops ) ) $ops = array();
        // if we do not receive the expected format, we assume the zero value
        if ( !array_key_exists( 'private_comment_default_checked', $ops ) ) {
            $ops[ 'private_comment_default_checked' ] = apply_filters( 'private_comment_default_checked_default', 0 );
        }
        // if we do not receive the expected format, we assume the empty value
        if ( !array_key_exists( 'private_comment_label_text', $ops ) ) {
            $ops[ 'private_comment_label_text' ] = apply_filters( 'private_comment_label_text_default', '' );
        }
        return $ops;
    }
    public static function get_option() {
        // get saved options
        $options = get_option( 'private_comment_options' );

        $default_checked_default = apply_filters( 'private_comment_default_checked_default', 0 );
        $label_text_default = apply_filters( 'private_comment_label_text_default', '' );

        // nothing saved?
        if ( !$options ) :
            // preparing a zero value (under our expected structure)
            $options = array( 'private_comment_default_checked' => $default_checked_default );
            // preparing an empty value (under our expected structure)
            $options = array( 'private_comment_label_text' => $label_text_default );
        endif;

        // something saved, but not the "default_checked" field? (legacy)
        if ( !array_key_exists( 'private_comment_default_checked', $options ) ) :
            // preparing a zero value (under our expected structure)
            $options['private_comment_default_checked'] = $default_checked_default;
        endif;

        // something saved, but not the "label_text" field? (legacy)
        if ( !array_key_exists( 'private_comment_label_text', $options ) ) :
            // preparing an empty value (under our expected structure)
            $options['private_comment_label_text'] = $label_text_default;
        endif;

        return $options;
    }
    // Checkbox field markup
    public static function default_checked_field() {
        // get saved options
        $options = call_user_func( array( __CLASS__, 'get_option' ) );
        // create input[type="checkbox"]
        $checked = $options[ 'private_comment_default_checked' ] ? ' checked="checked" ' : '';
        echo apply_filters(
            'private_comment_default_checked_field',
            '<label for="private_comment_default_checked"><input name="private_comment_options[private_comment_default_checked]" type="checkbox" id="private_comment_default_checked" value="1" ' . $checked . ' />' . __( 'Show "private comment" option checked by default', 'private-comment' ) . '</label>'
        );
    }
    // Checkbox field markup
    public static function label_text_field() {
        // get saved options
        $options = call_user_func( array( __CLASS__, 'get_option' ) );
        // create input[type="text"]
        $text = trim( $options[ 'private_comment_label_text' ] );
        echo apply_filters(
            'private_comment_label_text_field',
            /* translators: %s: default label text */
            '<label for="private_comment_label_text"><input name="private_comment_options[private_comment_label_text]" type="text" id="private_comment_label_text" class="regular-text" value="' . esc_attr( $text ) . '" /><p class="description">' . sprintf( __( 'Default: <code>%s</code>', 'private-comment' ), __( 'Keep this comment private (visible to site owners only)', 'private-comment' ) ) . '</p></label>'
        );
    }

    public static function comment_form_field_comment( $field ) {
        // get saved options
        $options = call_user_func( array( __CLASS__, 'get_option' ) );
        $checked = $options[ 'private_comment_default_checked' ] ? ' checked="checked" ' : '';
        $checked = apply_filters( 'private_comment_default_checked_attribute', $checked );
        
        $text = $options[ 'private_comment_label_text' ] ?: __( 'Keep this comment private (visible to site owners only)', 'private-comment' );
        $text = apply_filters( 'private_comment_label_text_value', $text );

        $field .= '<p class="comment-form-cookies-consent comment-form-private"><input id="wp-comment-private" name="wp-comment-private" type="checkbox" value="1" ' . $checked . ' /> <label for="wp-comment-private">' . $text . '</label></p>';
        return apply_filters( 'private_comment_input_field', $field );
    }
    public static function comment_post( $comment_ID ) {
        if ( array_key_exists( 'wp-comment-private', $_REQUEST ) && '1' == $_REQUEST['wp-comment-private'] ) {
            $commentarr = array( 'comment_ID' => $comment_ID, 'comment_approved' => 0 );
            wp_update_comment( $commentarr );
            update_comment_meta( $comment_ID, 'private', 1 );
        }
    }
    public static function is_private( $comment_ID ) {
        return 1 == get_comment_meta( $comment_ID, 'private', true );
    }
    public static function wp_update_comment_data( $data ) {
        $private = call_user_func( array( __CLASS__, 'is_private' ), $data['comment_ID'] );
        if ( 1 == $private ) {
            $data['comment_approved'] = 0;
        }
        return $data;
    }
    public static function get_comment( $comment ) {
        if ( ( !is_admin() ) && is_user_logged_in() && current_user_can( 'moderate_comments' ) ) {
            $private = call_user_func( array( __CLASS__, 'is_private' ), $comment->comment_ID );
            if ( $private ) {
                $comment->comment_approved = 1;
            }
        }
        return $comment;
    }
    public static function get_comment_text( $content, $comment ) {
        if ( ( !is_admin() ) && is_user_logged_in() && current_user_can( 'moderate_comments' ) ) {
            $private = call_user_func( array( __CLASS__, 'is_private' ), $comment->comment_ID );
            if ( $private ) {
                $content = apply_filters( 'private_comment_notice', '<p><em class="comment-awaiting-moderation comment-private">' . __( 'Private comment (visible to site owners only)', 'private-comment' ) . '</em></p>' . PHP_EOL . '<div class="comment-private-content">' . $content . '</div>' );
            }
        }
        return $content;

    }
    public static function the_comments( $comments ) {
        if ( ( !is_admin() ) && is_user_logged_in()  && current_user_can( 'moderate_comments' ) ) {
            foreach ( $comments as $ind => $comment ) {
                $private = call_user_func( array( __CLASS__, 'is_private' ), $comment->comment_ID );
                if ( $private ) {
                    $comments[$ind]->comment_approved = 1;
                }
            }
        }
        return $comments;
    }
    public static function wp_set_comment_status( $comment_ID, $comment_status ) {
        if ( 'approve' == $comment_status || '1' == $comment_status ) {
            $private = call_user_func( array( __CLASS__, 'is_private' ), $comment_ID );
            if ( $private ) {
                $err = new WP_Error(
                    'private_comment',
                    /* translators: %d: comment ID */
                    sprintf( __( 'Comment %d is private', 'private-comment' ), $comment_ID )
                );
                wp_set_comment_status( $comment_ID, 0 );
                if ( wp_doing_ajax() ) {
                    $x = new WP_Ajax_Response(
                        array(
                            'what' => 'comment',
                            'action' => 'approve',
                            'id' => $err,
                        )
                    );
                    $x->send();
                    wp_die();
                } else {
                    wp_die($err);
                }
            }
        }
    }
    public static function comment_row_actions( $actions, $comment ) {
        $private = call_user_func( array( __CLASS__, 'is_private' ), $comment->comment_ID );
        if ( $private ) {
            if ( array_key_exists( 'approve', $actions ) && $actions['approve'] ) {
                $actions['approve'] = apply_filters(
                    'private_comment_actions_approve',
                    '<del class="comment_row_action_approve_private" title="' . esc_attr( __( 'A private comment cannot be made public', 'private-comment' ) ) . '">' . __( 'Approve' ) . '</del>'
                );
            }
            if ( array_key_exists( 'reply', $actions ) && $actions['reply'] ) {
                $actions['reply'] = apply_filters(
                    'private_comment_actions_reply',
                    '<del class="comment_row_action_reply_private" title="' . esc_attr( __( 'A private comment cannot receive replies', 'private-comment' ) ) . '">' . __( 'Reply' ) . '</del>'
                );
            }
        }
        return $actions;
    }
    public static function edit_comment_misc_actions( $html, $comment ) {
        $private = call_user_func( array( __CLASS__, 'is_private' ), $comment->comment_ID );
        if ( $private ) {
            $approve_label_title = esc_attr( __( 'A private comment cannot be made public', 'private-comment' ) );
            $approve_label_title = apply_filters( 'private_comment_approve_label_title', $approve_label_title );
            $html .= <<<SCRIPT
<script>
    window.addEventListener( 'load', function () {
        let _approve = document.querySelector('input[name="comment_status"][value="1"]');
        if ( _approve ) {
            _approve.setAttribute( 'disabled', 'disabled' );
            let _approve_label = _approve.closest('label');
            if ( _approve_label ) {
                _approve_label.setAttribute( 'title', '$approve_label_title' );
            }
        }
    } );
</script>
SCRIPT;
        }
        return $html;
    }

}

add_action( 'init', array( 'private_comment', 'init' ) );

endif;
