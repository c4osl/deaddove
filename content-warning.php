<?php
/**
 * Plugin Name: Dead Dove
 * Description: Content warning plugin that blurs content until the user accepts a disclaimer.
 * Version: 1.1
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Author: Jeremy Malcolm
 */

if (!defined('ABSPATH')) exit; // Exit if accessed directly

// Enqueue CSS and JS
function deaddove_enqueue_assets() {
    wp_enqueue_style('deaddove-style', plugin_dir_url(__FILE__) . 'css/deaddove-style.css');
    wp_enqueue_script('deaddove-script', plugin_dir_url(__FILE__) . 'js/deaddove-script.js', ['jquery'], null, true);
}
add_action('wp_enqueue_scripts', 'deaddove_enqueue_assets');

// Register the custom taxonomy
function deaddove_register_taxonomy() {
    register_taxonomy('content_warning', 'post', [
        'label' => 'Content Warnings',
        'public' => true,
        'show_in_rest' => true,
        'show_ui' => true,
        'rewrite' => ['slug' => 'content-warning'],
        'hierarchical' => false,
    ]);
}
add_action('init', 'deaddove_register_taxonomy');

// Enqueue the JavaScript file for the frontend behavior if needed
function deaddove_enqueue_modal_script() {
    if (!is_single()) {
        return; // Stop if not viewing a single post
    }

    global $post;

    // Check if the post has a content warning term
    $warning_terms = get_option('deaddove_warning_terms', []);
    $post_terms = wp_get_post_terms($post->ID, 'content_warning', ['fields' => 'slugs']);
    $has_warning_term = array_intersect($post_terms, $warning_terms);

    // Check if the post contains the content warning shortcode
    $has_shortcode = has_shortcode($post->post_content, 'content_warning');

    // Check if a content warning block is on the page
    $has_block = has_block('cw/content-warning', $post);

    // Enqueue the script if any trigger is found
    if ($has_warning_term || $has_shortcode || $has_block) {
        wp_enqueue_script(
            'deaddove-modal-script',
            plugin_dir_url(__FILE__) . 'js/deaddove-modal.js',
            ['jquery'],
            null,
            true
        );
    }
}
add_action('wp_enqueue_scripts', 'deaddove_enqueue_modal_script');

// Apply content warnings based on post tags
function deaddove_filter_content($content) {
    if (!is_single()) return $content;

    $post_terms = wp_get_object_terms(get_the_ID(), 'content_warning', ['fields' => 'slugs']);
    $admin_terms = get_option('deaddove_warning_terms', []);
    $user_terms = get_user_meta(get_current_user_id(), 'deaddove_warning_terms', true) ?: $admin_terms;
    $warning_terms = array_intersect($admin_terms, $user_terms, $post_terms);

    if (empty($warning_terms)) return $content;

    $warnings = [];
    foreach ($warning_terms as $term) {
        $term_obj = get_term_by('slug', $term, 'content_warning');
        if ($term_obj) {
            $warnings[] = $term_obj->description ?: 'This content requires your agreement to view.';
        }
    }

    $warning_text = implode('<br><br>', $warnings);

    return '<div class="deaddove-modal-wrapper">
                <div class="deaddove-modal" style="display:none;">
                    <div class="deaddove-modal-content">
                        <p>' . $warning_text . '</p>
                        <div class="modal-buttons">
                            <button class="deaddove-show-content-btn">Show this content</button>
                            <button class="deaddove-hide-content-btn">Keep it hidden</button>
                        </div>
                        <small><a href="#deaddove-warning-settings">Modify your content warning settings</a></small>
                    </div>
                </div>
                <div class="deaddove-blurred-content deaddove-blur">' . $content . '</div>
            </div>';
}
add_filter('the_content', 'deaddove_filter_content');

// Enqueue block editor assets
function deaddove_enqueue_block_editor_assets() {
    wp_enqueue_script(
        'deaddove-block-script',
        plugin_dir_url(__FILE__) . 'js/deaddove-block.js',
        ['wp-blocks', 'wp-element', 'wp-block-editor', 'wp-components', 'wp-api-fetch'],
        filemtime(plugin_dir_path(__FILE__) . 'js/deaddove-block.js'),
        true
    );
}
add_action('enqueue_block_editor_assets', 'deaddove_enqueue_block_editor_assets');

// Register the block
function deaddove_register_content_warning_block() {
    register_block_type('cw/content-warning', [
        'render_callback' => 'deaddove_render_content_warning_block',
        'attributes' => [
            'terms' => [
                'type' => 'array',
                'default' => [],
            ],
        ],
    ]);
}
add_action('init', 'deaddove_register_content_warning_block');

// Render callback for the block
function deaddove_render_content_warning_block($attributes, $content) {

    $term_ids = $attributes['terms'] ?? [];
    // Retrieve user term preferences or default ones.
    $admin_warning_terms = get_option('deaddove_warning_terms', []);
    $user_terms = get_user_meta(get_current_user_id(), 'deaddove_user_warning_terms', true);
    if (empty($user_terms)) {
        $user_terms = $admin_warning_terms;
    }

    $warning_texts = [];
    foreach ($term_ids as $term_id) {
        $term = get_term($term_id, 'content_warning');
        if ($term && in_array($term->slug, $user_terms)) {
            $warning_text = $term->description ?: 'This content requires your agreement to view.';
            $warning_texts[] = $warning_text;
        }
    }

    // If there are no warnings, show content directly.
    if (empty($warning_texts)) {
        return '<div class="deaddove-block-content">' . $content . '</div>';
    }

    // Create the warning modal with all warnings displayed.
    $all_warnings = implode('<br><br>', $warning_texts);

    return '
        <div class="deaddove-modal-wrapper">
            <div class="deaddove-modal" style="display: block;">
                <div class="deaddove-modal-content">
                    <p>' . $all_warnings . '</p>
                    <div class="modal-buttons">
                        <button class="deaddove-show-content-btn">Show this content</button>
                        <button class="deaddove-hide-content-btn">Keep it hidden</button>
                    </div>
                    <small><a href="#deaddove-warning-settings" class="deaddove-settings-link">Modify your content warning settings</a></small>
                </div>
            </div>
            <div class="deaddove-blurred-content deaddove-blur" style="filter: blur(10px);">
                ' . $content . '
            </div>
        </div>';
}

// Shortcode for custom content warnings
function deaddove_content_warning_shortcode($atts, $content = null) {

    $atts = shortcode_atts(['tags' => ''], $atts);
    $tags = array_map('trim', explode(',', $atts['tags']));
    $admin_warning_tags = get_option('deaddove_warning_terms', []);
    $user_tags = get_user_meta(get_current_user_id(), 'deaddove_user_warning_terms', true) ?: $admin_warning_tags;

    $warning_texts = [];
    foreach ($tags as $tag_slug) {
        $tag = get_term_by('slug', $tag_slug, 'content_warning');
        if ($tag && in_array($tag_slug, $user_tags)) {
            $warning_text = $tag->description ?: 'This content requires your agreement to view.';
            $warning_texts[] = $warning_text;
        }
    }

    if (empty($warning_texts)) {
        return do_shortcode($content);
    }


    $all_warnings = implode('<br><br>', $warning_texts);

    if (strpos($_SERVER['REQUEST_URI'], '/add-new-post') !== false) {
        return '<p class="deaddove-block-description" tags="'.$atts['tags'].'">' . $content . '</p><br>';
    }
    return '
        <div class="deaddove-modal-wrapper">
            <div class="deaddove-modal" style="display:none;">
                <div class="deaddove-modal-content">
                    <p>' . $all_warnings . '</p>
                    <div class="modal-buttons">
                        <button class="deaddove-show-content-btn">Show this content</button>
                        <button class="deaddove-hide-content-btn">Keep it hidden</button>
                    </div>
                    <small><a href="#deaddove-warning-settings" class="deaddove-settings-link">Modify your content warning settings</a></small>
                </div>
            </div>
            <div class="deaddove-blurred-content deaddove-blur">
                ' . do_shortcode($content) . '
            </div>
        </div>';
}
add_shortcode('content_warning', 'deaddove_content_warning_shortcode');  

// Admin settings page
function deaddove_settings_page() {
    add_options_page(
        'Dead Dove Settings', 
        'Content Warning', 
        'manage_options', 
        'content-warning-settings', 
        'deaddove_settings_page_html'
    );
}
add_action('admin_menu', 'deaddove_settings_page');

// Settings page HTML
function deaddove_settings_page_html() {
    if (isset($_POST['deaddove_save_settings'])) {
        if (isset($_POST['deaddove_nonce'])) {
            $nonce = sanitize_text_field(wp_unslash($_POST['deaddove_nonce']));
            if (!wp_verify_nonce($nonce, 'deaddove_save_settings_nonce')) {
                wp_die('Nonce verification failed.');
            }
        } else {
            wp_die('Nonce not found.');
        }

        // Save the selected terms
        $selected_terms = isset($_POST['deaddove_terms']) 
            ? array_map('sanitize_text_field', wp_unslash($_POST['deaddove_terms'])) 
            : [];
        update_option('deaddove_warning_terms', $selected_terms);

        // Reload the updated terms to reflect changes immediately
        $selected_terms = get_option('deaddove_warning_terms', []);
        echo '<div class="updated"><p>Settings saved!</p></div>';
    } else {
        // Load selected terms for the first time when the form is displayed
        $selected_terms = get_option('deaddove_warning_terms', []);
    }

    $all_terms = get_terms([
        'taxonomy' => 'content_warning',
        'hide_empty' => false, // Include unused terms
    ]);
    ?>
    <div class="wrap">
    <h1>Dead Dove Settings</h1>
    <form method="post" action="">
        <?php wp_nonce_field('deaddove_save_settings_nonce', 'deaddove_nonce'); ?>
        <label for="deaddove_terms">Select terms that require content warnings:</label>
        <div>
            <?php foreach ($all_terms as $term) : ?>
                <label>
                    <input type="checkbox" name="deaddove_terms[]" value="<?php echo esc_attr($term->slug); ?>"
                        <?php echo in_array($term->slug, $selected_terms) ? 'checked' : ''; ?>>
                    <?php echo esc_html($term->name); ?>
                </label><br>
            <?php endforeach; ?>
        </div>
        <p>Each term's description will be used as the warning text.</p>
        <input type="submit" name="deaddove_save_settings" value="Save Settings">
    </form>
    </div>
    <?php
}

// User profile settings section
function deaddove_user_profile_settings($user) {
    $admin_terms = get_option('deaddove_warning_terms', []);
    $user_terms = get_user_meta($user->ID, 'deaddove_user_warning_terms', true);
    $user_terms = $user_terms !== '' ? $user_terms : $admin_terms;  // Default to admin terms if user terms are empty
    $all_terms = get_terms([
        'taxonomy' => 'content_warning',
        'hide_empty' => false,  // Include unused terms
    ]);
    ?>
    <h3 id="deaddove-warning-settings">Dead Dove Settings</h3>
    <table class="form-table">
        <tr>
            <th><label for="deaddove_user_terms">Select terms for which a content warning should be shown:</label></th>
            <td>
                <div>
                    <?php foreach ($all_terms as $term) : ?>
                        <label>
                            <input type="checkbox" name="deaddove_user_terms[]" value="<?php echo esc_attr($term->slug); ?>"
                                <?php echo in_array($term->slug, $user_terms) ? 'checked' : ''; ?>>
                            <?php echo esc_html($term->name); ?>
                        </label><br>
                    <?php endforeach; ?>
                </div>
                <p class="description">These settings override the default warnings.</p>
            </td>
        </tr>
    </table>
    <?php wp_nonce_field('deaddove_user_profile_nonce', 'deaddove_user_nonce'); ?>
    <?php
}
add_action('show_user_profile', 'deaddove_user_profile_settings');
add_action('edit_user_profile', 'deaddove_user_profile_settings');

// Save user settings
function deaddove_save_user_profile_settings($user_id) {
    // Verify nonce
    if (!isset($_POST['deaddove_user_nonce']) || 
        !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['deaddove_user_nonce'])), 'deaddove_user_profile_nonce')) {
        return;  // Exit if nonce verification fails.
    }

    // Save selected terms or delete if empty
    if (isset($_POST['deaddove_user_terms'])) {
        $selected_terms = array_map('sanitize_text_field', wp_unslash($_POST['deaddove_user_terms']));
        update_user_meta($user_id, 'deaddove_user_warning_terms', $selected_terms);
    } else {
        delete_user_meta($user_id, 'deaddove_user_warning_terms');  // Clear settings if no terms are selected
    }
}
add_action('personal_options_update', 'deaddove_save_user_profile_settings');
add_action('edit_user_profile_update', 'deaddove_save_user_profile_settings');

function register_custom_user_widget() {
    register_widget('DeadDove_Widget');
}
add_action('widgets_init', 'register_custom_user_widget');  

/* 
Ajax method to update the description
*/
function save_user_description() {
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'User not logged in']);
    }
    $user_id = $_POST['user_id'];
    $post_id = $_POST['post_id'];

    $selected_text = sanitize_text_field($_POST['selected_text'] ?? '');
    $selected_tags = $_POST['tags'] ?? [];

    $post = get_post($post_id);
    if(!$post_id){
        wp_send_json_error(['message' => 'post  is null']);
    }
    if (!$post || $post->post_author != $user_id) {
        wp_send_json_error(['message' => 'Unauthorized action']);
    }

    if (empty($selected_text)) {
        wp_send_json_error(['message' => 'No text selected']);
    }
    $post_content = $post->post_content;
    if (strpos($post_content, $selected_text) !== false) {
        $tags_string = implode(',', array_map('sanitize_text_field', $selected_tags)); 
        $wrapped_text = '[content_warning tags="' . esc_attr($tags_string) . '"]' . $selected_text . '[/content_warning]';

        $updated_content = str_replace($selected_text, $wrapped_text, $post_content);
        wp_update_post([
            'ID' => $post_id,
            'post_content' => $updated_content,
        ]);
        ob_clean();
        wp_send_json_success(['message' => 'Description updated successfully']);
    } else {
        ob_clean();
        wp_send_json_error(['message' => 'Selected text not found in post content']);
    }


    wp_die();
}
add_action('wp_ajax_save_user_description', 'save_user_description');
add_action('wp_ajax_nopriv_save_user_description', 'save_user_description');


/* 

Get User  Dead dove to show the dead dove tag
*/
function get_user_used_tags($user_id) {
    $args = [
        'author' => $user_id,
        'posts_per_page' => -1,
        'fields' => 'ids'
    ];

    $posts = get_posts($args);

    if (!$posts) return [];

    $tags = wp_get_object_terms($posts, 'content_warning', ['fields' => 'id=>name']);

    if (!$tags || is_wp_error($tags)) return [];

    $tag_data = [];

    foreach ($tags as $tag_id => $tag_name) {
        $tag_obj = get_term($tag_id, 'content_warning');  

        $tag_data[] = [
            'name' => $tag_name,
            'slug' => $tag_obj->slug  
        ];
    }

    return $tag_data;
}
function custom_user_widget_shortcode() {
    ob_start();
    the_widget('Custom_User_Widget');
    return ob_get_clean();
}
add_shortcode('custom_user_widget', 'custom_user_widget_shortcode');



add_action('wp', function() {


    if (is_page()) {
        $page_id = get_queried_object_id();
        $page_slug = get_post_field('post_name', $page_id);
        $page_url = get_permalink($page_id);

    }
});

// Hook into BuddyBoss navigation
function deaddove_add_buddyboss_profile_tab() {
    global $bp;

    // Add to Settings menu
    bp_core_new_subnav_item(array(
        'name' => __('Content Warning Settings', 'textdomain'),
        'slug' => 'content-warning-settings',
        'parent_url' => trailingslashit(bp_displayed_user_domain() . bp_get_settings_slug()),
        'parent_slug' => bp_get_settings_slug(),
        'position' => 30,
        'screen_function' => 'deaddove_buddyboss_settings_page',
        'user_has_access' => bp_core_can_edit_settings()
    ));
}
add_action('bp_setup_nav', 'deaddove_add_buddyboss_profile_tab');

function deaddove_buddyboss_settings_page() {
    add_action('bp_template_content', 'deaddove_display_settings_form');
    bp_core_load_template('members/single/plugins');
}

// Display User Settings Form
function deaddove_display_settings_form() {
    if (!is_user_logged_in()) {
        echo "<p>You need to log in to access this page.</p>";
        return;
    }

    $user_id = get_current_user_id();
    $admin_warning_terms = get_option('deaddove_warning_terms', []);
    $user_warning_terms = get_user_meta($user_id, 'deaddove_user_warning_terms', true);
    
    // Ensure we have an array, using admin settings as default if user has no settings
    $user_warning_terms = (!empty($user_warning_terms) && is_array($user_warning_terms)) ? $user_warning_terms : $admin_warning_terms;
    
    $all_terms = get_terms([
        'taxonomy' => 'content_warning',
        'hide_empty' => false,
    ]);

    ?>
    <h3 id="deaddove-warning-settings">Content Warning Settings</h3>

    <form id="deaddove-settings-form">
        <?php wp_nonce_field('deaddove_user_profile_nonce', 'deaddove_user_nonce'); ?>

        <label><strong>Select tags for which a content warning should be shown:</strong></label>
        <div style="margin-top: 10px;">
            <?php foreach ($all_terms as $term) : ?>
                <label style="display: block; margin-bottom: 5px;">
                    <input type="checkbox" name="deaddove_user_tags[]" value="<?php echo esc_attr($term->slug); ?>"
                        <?php echo in_array($term->slug, $user_warning_terms) ? 'checked' : ''; ?>>
                    <?php echo esc_html($term->name); ?>
                </label>
            <?php endforeach; ?>
        </div>

        <p class="description">These settings override the default warnings.</p>

        <button type="submit" class="button-primary" style="margin-top: 10px;">Save Settings</button>
        <span id="deaddove-save-message" style="display:none; color:green; font-weight:bold;">Settings saved!</span>
    </form>

    <script>
    jQuery(document).ready(function($) {
        $('#deaddove-settings-form').on('submit', function(e) {
            e.preventDefault(); // Prevent form submission

            var formData = $(this).serialize(); // Get form data

            $.ajax({
                type: 'POST',
                url: '<?php echo admin_url("admin-ajax.php"); ?>',
                data: formData + '&action=deaddove_save_user_settings',
                beforeSend: function() {
                    $('#deaddove-save-message').hide(); // Hide message before sending request
                },
                success: function(response) {
                    $('#deaddove-save-message').show().text(response.message);
                    location.reload();
                }
            });
        });
    });
    </script>
    <?php
}
add_action('wp_ajax_deaddove_save_user_settings', 'deaddove_save_user_settings');

function deaddove_save_user_settings() {
    // Check if user is logged in
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'You must be logged in to save settings.']);
    }

    $user_id = get_current_user_id();

    // Verify nonce
    if (!isset($_POST['deaddove_user_nonce']) || 
        !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['deaddove_user_nonce'])), 'deaddove_user_profile_nonce')) {
        wp_send_json_error(['message' => 'Security check failed.']);
    }

    // Get existing user settings or admin defaults
    $admin_warning_terms = get_option('deaddove_warning_terms', []);
    $current_settings = get_user_meta($user_id, 'deaddove_user_warning_terms', true);
    $current_settings = (!empty($current_settings) && is_array($current_settings)) ? $current_settings : $admin_warning_terms;

    // Process the new settings
    if (isset($_POST['deaddove_user_tags']) && is_array($_POST['deaddove_user_tags'])) {
        $selected_terms = array_map('sanitize_text_field', wp_unslash($_POST['deaddove_user_tags']));
        update_user_meta($user_id, 'deaddove_user_warning_terms', $selected_terms);
    } else {
        // If no tags are selected, store an empty array rather than deleting the meta
        update_user_meta($user_id, 'deaddove_user_warning_terms', []);
    }

    wp_send_json_success(['message' => 'Settings saved successfully!']);
}



/* 
adding content warning class
*/
function deaddove_custom_post_class($classes) {
    if (!is_home() && !is_archive() && !is_category()) {
        return $classes;  
    }    
    $post_author_id = get_post_field('post_author', get_the_ID());

    if (!$post_author_id) {
        return $classes;  
    }
    $admin_warning_terms = get_option('deaddove_warning_terms', []);
    $user_tags = get_user_meta($post_author_id, 'deaddove_user_warning_terms', true) ?: $admin_warning_terms;
    $post_terms = wp_get_object_terms(get_the_ID(), 'content_warning', ['fields' => 'slugs']);

    if (!empty(array_intersect($post_terms, $user_tags))) {
        $classes[] = 'deaddove-blog-warning';  
    }

    return $classes;
}
add_filter('post_class', 'deaddove_custom_post_class');

/* 
Widget for blur modal filter modal 
*/
class DeadDove_Widget extends WP_Widget {

    function __construct() {
        parent::__construct(
            'deaddove_widget',
            __('Dead Dove Content Warning', 'deaddove'),
            array('description' => __('Displays a content warning modal before showing the page content.', 'deaddove'))
        );
    }
    public function widget($args, $instance) {
        $warning_text = !empty($instance['warning_text']) ? $instance['warning_text'] : __('This content may not be suitable for all audiences.', 'deaddove');
        ?>
         <div class="deaddove-modal-wrapper-multiple-posts">
            <div class="deaddove-modal" style="display:none;">
                <div class="deaddove-modal-content">
                    <p class="description-text"><?php echo esc_html($warning_text); ?></p>
                    <div class="modal-buttons">
                        <button class="deaddove-show-content-btn">Show this content</button>
                        <button class="deaddove-hide-content-btn">Keep it hidden</button>
                    </div>
                    <small><a href="#deaddove-warning-settings" class="deaddove-settings-link">Modify your content warning settings</a></small>
                </div>
            </div>
        </div>
        <?php

    }
}




/* 
Get Description for modal 
*/
function deaddove_get_post_description() {

    check_ajax_referer('deaddove_nonce', 'security');

    if (isset($_POST['post_id'])) {
        $post_id = intval($_POST['post_id']); 
        $tags = $_POST['postTags']; 

        $post = get_post($post_id); 
        if ($post) {
            if(!empty($tags)){
                $tagsArray = explode(',', $tags);
                $tagDescriptions = [];
                if (!empty($tagsArray)) {
                    foreach($tagsArray as $tag_slug){
                        $tag = get_term_by('slug', $tag_slug, 'content_warning');  
                        if ($tag && !is_wp_error($tag) && !empty($tag->description)) {
                            $tagDescriptions[] = $tag->description;
                        }
                    }
                    }
                $tagDescriptionString =  !empty($tagDescriptions) ? implode(' | ', $tagDescriptions) : 'No matching tag descriptions';
                wp_send_json_success($tagDescriptionString);  
            }else{
            $post = get_post($post_id); 
            $post_author_id = get_post_field('post_author', $post_id); 
            $admin_warning_terms = get_option('deaddove_warning_terms', []);
            $user_tags = get_user_meta($post_author_id, 'deaddove_user_warning_terms', true) ?: $admin_warning_terms;
            $post_terms = wp_get_object_terms($post_id, 'content_warning', ['fields' => 'slugs']);  
            $matching_tags = array_intersect($post_terms, $user_tags);
            $tagDescriptions = [];
            if (!empty($matching_tags)) {
                foreach($matching_tags as $tag_slug){
                    $tag = get_term_by('slug', $tag_slug, 'content_warning'); // Slug se tag details lo
                    if ($tag && !is_wp_error($tag) && !empty($tag->description)) {
                        $tagDescriptions[] = $tag->description;
                    }
                }
                }
            $tagDescriptionString = !empty($tagDescriptions) ? implode(' | ', $tagDescriptions) : 'No matching tag descriptions';

            wp_send_json_success($tagDescriptionString);  
            }
        } else {
            wp_send_json_error("Post not found.");
        }
    } else {
        wp_send_json_error("Invalid request.");
    }
}
add_action('wp_ajax_deaddove_get_post_description', 'deaddove_get_post_description');  
add_action('wp_ajax_nopriv_deaddove_get_post_description', 'deaddove_get_post_description');  


function deaddove_enqueue_scripts() {
    wp_enqueue_script('deaddove-frontend-js', plugin_dir_url(__FILE__) . 'js/deaddove-script.js', array('jquery'), null, true);

    wp_localize_script('deaddove-frontend-js', 'deaddove_ajax', array(
        'ajaxurl' => admin_url('admin-ajax.php'),   
        'nonce'   => wp_create_nonce('deaddove_nonce'),  
    ));
}
add_action('wp_enqueue_scripts', 'deaddove_enqueue_scripts');

/* 
Adding content warning field in time line
*/
function bboss_add_custom_field_to_activity_form() {
    $terms = get_terms([
        'taxonomy' => 'content_warning',
        'hide_empty' => false,
    ]);
    ?>
    <div class="custom-activity-field" style="margin-top: 10px;">
        <label for="content_warning_tag">Content Warnings:</label>
        <div style="border: 1px solid #ddd; padding: 8px; height:200px; overflow:auto;">
            <?php foreach ($terms as $term) : ?>
                <label style="display: block; margin-bottom: 5px;">
                    <input type="checkbox" name="content_warning_tags[]" value="<?php echo esc_attr($term->term_id); ?>">
                    <?php echo esc_html($term->name); ?>
                </label>
            <?php endforeach; ?>
        </div>
    </div>
    <?php
}
add_action('bp_activity_post_form_options', 'bboss_add_custom_field_to_activity_form');


function bboss_save_custom_activity_field($content, $user_id, $activity_id) {
    if (!isset($_POST['content_warning_tags']) || empty($_POST['content_warning_tags'])) {
        return;
    }
    // Sanitize and convert array to comma-separated string
    $selected_tags = array_map('intval', $_POST['content_warning_tags']);
    $tags_string = implode(',', $selected_tags);

    bp_activity_update_meta($activity_id, 'content_warning_tag', $tags_string);
}
add_action('bp_activity_posted_update', 'bboss_save_custom_activity_field', 10, 3);

/* 

Getting content warning media tag according to media id

*/
function deaddove_script_before_page_loading() {
    wp_enqueue_script('deaddove-modal-js', plugin_dir_url(__FILE__) . 'js/deaddove-content-warning.js', array('jquery'), null, true);
    wp_localize_script('deaddove-frontend-js', 'deaddove_ajax', array(
        'ajaxurl' => admin_url('admin-ajax.php'),   
        'nonce'   => wp_create_nonce('deaddove_nonce'),  
    ));
}
add_action('wp_enqueue_scripts', 'deaddove_script_before_page_loading');

function deaddove_content_warning_ajax_handler() {
    if( !isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'deaddove_nonce') ) {
        wp_send_json_error(array('message' => 'Invalid nonce'));
    }
    if( !isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'deaddove_nonce') ) {
        wp_send_json_error(array('message' => 'Invalid nonce'));
    }
    $args = array(
        'per_page' => 100,  
        'page' => 1, 
    );
    $activities = bp_activity_get($args);
    if (empty($activities['activities'])) {
        wp_send_json_error(array('message' => 'No activities found.'));
    }
    $activity_data = [];
    foreach ($activities['activities'] as $activity) {
        $activity_id = $activity->id;
        $content_warning_tag = bp_activity_get_meta($activity_id, 'content_warning_tag', true);
        if ($content_warning_tag) {

            $tag_ids = explode(',', $content_warning_tag); 
            $tag_names = [];
            $tag_descriptions = [];
            foreach ($tag_ids as $tag_id) {
                $tag = get_term(intval($tag_id), 'content_warning');
                if ($tag && !is_wp_error($tag)) {
                    $tag_names[] = $tag->name;  
                    $tag_descriptions[] = $tag->description;  
                }
            }
            if ($tag && !is_wp_error($tag)) {
                $activity_data[] = [
                    'activity_id' => $activity_id,
                    'content_warning_tag' => implode(', ', $tag_names), 
                    'content_warning_description'=>implode(' | ', $tag_descriptions), 
                ];
            }
        }
    }
    if (empty($activity_data)) {
        wp_send_json_error(array('message' => 'No activities with content warning tags.'));
    }
    wp_send_json_success(array('activities' => $activity_data));
}
add_action('wp_ajax_deaddove_content_warning', 'deaddove_content_warning_ajax_handler');  
add_action('wp_ajax_nopriv_deaddove_content_warning', 'deaddove_content_warning_ajax_handler');  


function get_custom_widget_callback() {
    if ( !isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'deaddove_nonce') ) {
        wp_die('Permission Denied');
    }
    if (!is_user_logged_in()) {
        wp_die('Permission Denied');
    }
    $user_id = get_current_user_id();
    $admin_warning_terms = get_option('deaddove_warning_terms', []);
    $user_tags = get_user_meta($user_id, 'deaddove_user_warning_terms', true) ?: $admin_warning_terms;  
    if (empty($user_tags)) {
        wp_send_json_error(array(
            'message' => 'No warning tags available',  
        ));
    } 

    wp_send_json_success(array(
        'user_content_warning_tag' =>$user_tags,   
    ));
}
add_action('wp_ajax_get_custom_widget', 'get_custom_widget_callback');   
add_action('wp_ajax_nopriv_get_custom_widget', 'get_custom_widget_callback');  



?>