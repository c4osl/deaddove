<?php
/**
 * The parent theme functions are located at /buddyboss-theme/inc/theme/functions.php.
 * Add your own functions at the bottom of this file.
 *
 * @package BuddyBoss Child
 */

/****************************** THEME SETUP ******************************/

/**
 * Sets up theme for translation
 *
 * @since BuddyBoss Child 1.0.0
 */
function buddyboss_theme_child_languages() {
    /**
     * Makes child theme available for translation.
     * Translations can be added into the /languages/ directory.
     */

    // Translate text from the PARENT theme.
    load_theme_textdomain( 'buddyboss-theme', get_stylesheet_directory() . '/languages' );
}
add_action( 'after_setup_theme', 'buddyboss_theme_child_languages' );

/**
 * Enqueues scripts and styles for child theme front-end.
 *
 * @since Boss Child Theme  1.0.0
 */
function buddyboss_theme_child_scripts_styles() {
    /**
     * Scripts and Styles loaded by the parent theme can be unloaded if needed
     * using wp_deregister_script or wp_deregister_style.
     *
     * See the WordPress Codex for more information about those functions:
     * http://codex.wordpress.org/Function_Reference/wp_deregister_script
     * http://codex.wordpress.org/Function_Reference/wp_deregister_style
     */

    // Styles.
    wp_enqueue_style( 'buddyboss-child-css', get_stylesheet_directory_uri() . '/assets/css/custom.css', '', '1.0.0' );

    // Javascript.
    wp_enqueue_script( 'buddyboss-child-js', get_stylesheet_directory_uri() . '/assets/js/custom.js', '', '1.0.0', true );
}
add_action( 'wp_enqueue_scripts', 'buddyboss_theme_child_scripts_styles', 9999 );


/****************************** CUSTOM FUNCTIONS ******************************/

/**
 * Gets the content warning description for an activity.
 *
 * @param int $activity_id Activity ID.
 * @return string Warning descriptions joined with a pipe, or an empty string when none apply.
 */
function get_activity_meta_description( $activity_id ) {
    $content_warning_tag = bp_activity_get_meta( intval( $activity_id ), 'content_warning_tags', true );
    $description         = '';
    if ( $content_warning_tag ) {
        $tag_ids          = explode( ',', $content_warning_tag );
        $tag_descriptions = array();
        foreach ( $tag_ids as $tag_id ) {
            $tag = get_term( intval( $tag_id ), 'content_warning' );
            if ( $tag && ! is_wp_error( $tag ) ) {
                $tag_descriptions[] = $tag->description;
            }
        }
        if ( ! empty( $tag_descriptions ) ) {
            $description = implode( ' | ', $tag_descriptions );
        }
    } else {
            $activity = new BP_Activity_Activity( $activity_id );
        if ( ! empty( $activity ) ) {

            $post_id = $activity->item_id;
            if ( $post_id ) {
                $tag_ids = get_post_meta( $post_id, 'forum_content_warning_tags', true );
                if ( empty( $tag_ids ) ) {
                    return $description;
                }
                if ( is_string( $tag_ids ) ) {
                    $tag_ids = explode( ',', $tag_ids );
                }
                if ( is_array( $tag_ids ) ) {
                    $post_descriptions = array();
                    foreach ( $tag_ids as $tag_id ) {
                        $tag_id = intval( $tag_id );
                        if ( $tag_id && term_exists( $tag_id, 'content_warning' ) ) {
                            $term = get_term( $tag_id, 'content_warning' );
                            if ( ! is_wp_error( $term ) && ! empty( $term->description ) ) {
                                $post_descriptions[] = $term->description;
                            }
                        }
                    }
                    if ( ! empty( $post_descriptions ) ) {
                        $description = implode( ' | ', $post_descriptions );
                    }
                }
            }
        }
    }
    return $description;
}
