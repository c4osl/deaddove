<?php
/**
 * Plugin Name: Dead Dove
 * Description: Content warning plugin that blurs content until the user accepts a disclaimer.
 * Version: 1.0
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

// Enqueue the JavaScript file for the frontend behavior if needed
function deaddove_enqueue_modal_script() {
    if (!is_single()) {
        return; // Stop if not viewing a single post
    }

    global $post;

    // Check if the post has a content warning tag
    $warning_tags = get_option('deaddove_warning_tags', []);
    $post_tags = wp_get_post_tags($post->ID, ['fields' => 'slugs']);
    $has_warning_tag = array_intersect($post_tags, $warning_tags);

    // Check if the post contains the content warning shortcode
    $has_shortcode = has_shortcode($post->post_content, 'content_warning');

    // Check if a content warning block is on the page
    $has_block = has_block('cw/content-warning', $post);

    // Enqueue the script if any trigger is found
    if ($has_warning_tag || $has_shortcode || $has_block) {
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
    if (!is_single()) return $content; // Only apply on single posts

    $post_tags = wp_get_post_tags(get_the_ID(), ['fields' => 'slugs']);
    $admin_tags = get_option('deaddove_warning_tags', []);
    $user_tags = get_user_meta(get_current_user_id(), 'deaddove_warning_tags', true) ?: $admin_tags;
    $warning_tags = array_intersect($admin_tags, $user_tags, $post_tags);

    if (empty($warning_tags)) return $content; // No matching warning tags

    $warnings = [];
    foreach ($warning_tags as $tag) {
        $term = get_term_by('slug', $tag, 'post_tag');
        if ($term) {
            $warnings[] = $term->description ?: 'This content requires your agreement to view.';
        }
    }

    $warning_text = implode('<br><br>', $warnings);

    return '
        <div class="deaddove-modal-wrapper">
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
            <div class="deaddove-blurred-content deaddove-blur">
                ' . $content . '
            </div>
        </div>';
}

// Add filter to apply the content warning to post content
add_filter('the_content', 'deaddove_filter_content');

// Enqueue block editor assets
function deaddove_enqueue_block_editor_assets() {
    wp_enqueue_script(
        'deaddove-block-script',
        plugin_dir_url(__FILE__) . 'js/deaddove-block.js',
        ['wp-blocks', 'wp-element', 'wp-block-editor', 'wp-components'],  // Ensure dependencies are correct
        null,
        true
    );
}
add_action('enqueue_block_editor_assets', 'deaddove_enqueue_block_editor_assets');

// Register the block
function deaddove_register_content_warning_block() {
    register_block_type('cw/content-warning', [
        'editor_script' => 'deaddove-block-script',
        'render_callback' => 'deaddove_render_content_warning_block',
        'attributes' => [
            'tags' => [
                'type' => 'array', // Changed to 'array' to allow multiple tags
                'default' => [],
            ],
        ],
    ]);
}
add_action('init', 'deaddove_register_content_warning_block');

// Render callback for the block
function deaddove_render_content_warning_block($attributes, $content) {
    $tag_ids = $attributes['tags'] ?? [];

    // Retrieve user tag preferences or default ones.
    $admin_warning_tags = get_option('deaddove_warning_tags', []);
    $user_tags = get_user_meta(get_current_user_id(), 'deaddove_user_warning_tags', true) ?: $admin_warning_tags;

    $warning_texts = [];
    foreach ($tag_ids as $tag_id) {
        $tag = get_term($tag_id, 'post_tag');
        if ($tag && in_array($tag->slug, $user_tags)) {
            $warning_text = $tag->description ?: 'This content requires your agreement to view.';
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
            <div class="deaddove-modal" style="display: none;">
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
                ' . $content . ' <!-- Render nested blocks here -->
            </div>
        </div>';
}

// Register the block with a render callback.
add_action('init', function () {
    register_block_type('cw/content-warning', [
        'render_callback' => 'deaddove_render_content_warning_block',
    ]);
});

// Shortcode for custom content warnings
function deaddove_content_warning_shortcode($atts, $content = null) {
    $atts = shortcode_atts(['tags' => ''], $atts);
    $tags = array_map('trim', explode(',', $atts['tags']));

    $admin_warning_tags = get_option('deaddove_warning_tags', []);
    $user_tags = get_user_meta(get_current_user_id(), 'deaddove_user_warning_tags', true) ?: $admin_warning_tags;

    $warning_texts = [];
    foreach ($tags as $tag_slug) {
        $tag = get_term_by('slug', $tag_slug, 'post_tag');
        if ($tag && in_array($tag_slug, $user_tags)) {
            $warning_text = $tag->description ?: 'This content requires your agreement to view.';
            $warning_texts[] = $warning_text;
        }
    }

    if (empty($warning_texts)) {
        return do_shortcode($content);
    }

    $all_warnings = implode('<br><br>', $warning_texts);

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

        // Save the selected tags
        $selected_tags = isset($_POST['deaddove_tags']) 
            ? array_map('sanitize_text_field', wp_unslash($_POST['deaddove_tags'])) 
            : [];
        update_option('deaddove_warning_tags', $selected_tags);

        // Reload the updated tags to reflect changes immediately
        $selected_tags = get_option('deaddove_warning_tags', []);
        echo '<div class="updated"><p>Settings saved!</p></div>';
    } else {
        // Load selected tags for the first time when the form is displayed
        $selected_tags = get_option('deaddove_warning_tags', []);
    }

    $all_tags = get_terms([
        'taxonomy' => 'post_tag',
        'hide_empty' => false, // Include unused tags
    ]);
    ?>
    <div class="wrap">
    <h1>Dead Dove Settings</h1>
    <form method="post" action="">
        <?php wp_nonce_field('deaddove_save_settings_nonce', 'deaddove_nonce'); ?>
        <label for="deaddove_tags">Select tags that require content warnings:</label>
        <div>
            <?php foreach ($all_tags as $tag) : ?>
                <label>
                    <input type="checkbox" name="deaddove_tags[]" value="<?php echo esc_attr($tag->slug); ?>"
                        <?php echo in_array($tag->slug, $selected_tags) ? 'checked' : ''; ?>>
                    <?php echo esc_html($tag->name); ?>
                </label><br>
            <?php endforeach; ?>
        </div>
        <p>Each tag's description will be used as the warning text.</p>
        <input type="submit" name="deaddove_save_settings" value="Save Settings">
    </form>
    </div>
    <?php
}

// User profile settings section
function deaddove_user_profile_settings($user) {
    $admin_tags = get_option('deaddove_warning_tags', []);
    $user_tags = get_user_meta($user->ID, 'deaddove_user_warning_tags', true);
    $user_tags = $user_tags !== '' ? $user_tags : $admin_tags;  // Default to admin tags if user tags are empty
    $all_tags = get_terms([
        'taxonomy' => 'post_tag',
        'hide_empty' => false,  // Include unused tags
    ]);
    ?>
    <h3 id="deaddove-warning-settings">Dead Dove Settings</h3>
    <table class="form-table">
        <tr>
            <th><label for="deaddove_user_tags">Select tags for which a content warning should be shown:</label></th>
            <td>
                <div>
                    <?php foreach ($all_tags as $tag) : ?>
                        <label>
                            <input type="checkbox" name="deaddove_user_tags[]" value="<?php echo esc_attr($tag->slug); ?>"
                                <?php echo in_array($tag->slug, $user_tags) ? 'checked' : ''; ?>>
                            <?php echo esc_html($tag->name); ?>
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

    // Save selected tags or delete if empty
    if (isset($_POST['deaddove_user_tags'])) {
        $selected_tags = array_map('sanitize_text_field', wp_unslash($_POST['deaddove_user_tags']));
        update_user_meta($user_id, 'deaddove_user_warning_tags', $selected_tags);
    } else {
        delete_user_meta($user_id, 'deaddove_user_warning_tags');  // Clear settings if no tags are selected
    }
}
add_action('personal_options_update', 'deaddove_save_user_profile_settings');
add_action('edit_user_profile_update', 'deaddove_save_user_profile_settings');