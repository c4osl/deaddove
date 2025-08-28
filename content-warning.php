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
// Hook to store the user ID and role when a new term is created or edited
function store_user_id_and_role_on_term_creation($term_id) {
    $current_user_id = get_current_user_id();
    $user = get_user_by('id', $current_user_id);
    $user_role = isset($user->roles[0]) ? $user->roles[0] : '';
    update_term_meta($term_id, 'author_user_id', $current_user_id);
    update_term_meta($term_id, 'author_user_role', $user_role);
}

add_action('created_term', 'store_user_id_and_role_on_term_creation');
add_action('edited_term', 'store_user_id_and_role_on_term_creation');
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

    $post_terms = wp_get_post_terms(get_the_ID(), 'content_warning', ['fields' => 'slugs']);
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
                        <small><a href="#deaddove-warning-settings1">Modify your content warning settings</a></small>
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
            'terms' => [
                'type' => 'array', // Changed to 'array' to allow multiple tags
                'default' => [],
            ],
        ],
    ]);
}
add_action('init', 'deaddove_register_content_warning_block');

// Render callback for the block
function deaddove_render_content_warning_block($attributes, $content) {
    
    $term_ids = $attributes['tags'] ?? [];
    // echo '<pre>';
    // print_r($attributes);
    // echo '</pre>';
    // Retrieve user term preferences or default ones.
    $admin_warning_terms = get_option('deaddove_warning_terms', []);
    $user_terms = get_user_meta(get_current_user_id(), 'deaddove_user_warning_terms', true) ?: $admin_warning_terms;

    $warning_texts = [];
    foreach ($term_ids as $term_id) {
        $term = get_term($term_id, 'content_warning');
        if ($term && in_array($term->slug, $user_terms)) {
            $warning_text = $term->description ?: 'This content requires your agreement to view.';
            $warning_texts[] = $warning_text;
        }
    }
    // print_r($warning_texts);


    // If there are no warnings, show content directly.
    if (empty($warning_texts)) {
        // return '<div class="deaddove-block-content">' . $content . '</div>';
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
                    <small><a href="#deaddove-warning-settings2" class="deaddove-settings-link">Modify your content warning settings</a></small>
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
                    <small><a href="#deaddove-warning-settings3" class="deaddove-settings-link">Modify your content warning settings</a></small>
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


function deaddove_display_meta_box($post) {
    // Retrieve values or set default for new posts
    $boolean_field_1 = get_post_meta($post->ID, '_blured_featured_image', true);
    if ($boolean_field_1 === '' && $post->post_status == 'auto-draft') $boolean_field_1 = 0;
    ?>
    <p>
        <label>
            <input type="checkbox" name="_blured_featured_image" value="1" <?php checked($boolean_field_1, 1); ?>>
            Blured Featured Image
        </label>
    </p>
  
    <?php
}
 
 /* 
    save to custom field image blured  
*/
function deaddove_save_custom_fields($post_id) {
    $user = wp_get_current_user();
    $allowed_roles = ['administrator', 'editor', 'author', 'vendor', 'customer', 'member', 'subscriber'];
    $boolean_field_1 = isset($_POST['_blured_featured_image']) ? 1 : 0;
    update_post_meta($post_id, '_blured_featured_image', $boolean_field_1);
}
add_action('save_post', 'deaddove_save_custom_fields');


/* 

Featured Image blured

*/

class Blur_Featured_Image_Widget extends WP_Widget {
    // Corrected constructor
    public function __construct() {
        parent::__construct(
            'blur_featured_image_widget', // Widget ID
            __('Blur Featured Image', 'text_domain'), // Widget name
            array('description' => __('Enable/Disable blur effect for the current post.', 'text_domain'))
        );
    }

    // Display the widget form in the admin panel
    public function form($instance) {
        echo "<p>This widget allows users to blur the featured image for individual posts.</p>";
    }

    // Display the checkbox in the frontend
    public function widget($args, $instance) {
        if (is_user_logged_in()) {
            // global $post;
            // $post_id = isset($_GET['post']) ? intval($_GET['post']) : 0;
            // if (!$post_id && isset($post)) {
            //     $post_id = $post->ID;
            // }   
            $post_author_id = get_post_field('post_author', $post_id);
            $current_user_id = get_current_user_id();
            if ($current_user_id == $post_author_id) {
                if (is_single()) {  
                    $post_id = get_the_ID();
                    $blur_enabled = get_post_meta($post_id, '_blured_featured_image', true);
                    echo $args['before_widget'];
                    ?>
                    <form id="blur-featured-image-form">
                        <input type="hidden" name="post_id" value="<?php echo esc_attr($post_id); ?>">
                        
                        <p>
                            <input type="checkbox" id="blur_featured_image_widget" name="_blured_featured_image" value="1" 
                                <?php checked($blur_enabled, 1); ?>>
                            <label for="blur_featured_image_widget">Content warning on Post Featured Image</label>
                        </p>
                        <button type="submit">Save</button>
                        <span id="blur-featured-image-message" style="color: green; display: none;">Saved!</span>
                    </form>
                    <script>
                        jQuery(document).ready(function($) {
                        $("#blur-featured-image-form").on("submit", function(e) {
                            e.preventDefault();
                            var postData = {
                                action: "save_blur_featured_image",
                                post_id: $("input[name='post_id']").val(),
                                _blured_featured_image: $("#blur_featured_image_widget").is(":checked") ? 1 : 0
                            };

                        $.post("<?php echo admin_url('admin-ajax.php'); ?>", postData, function(response) {
                            if (response.success) {
                                $("#blur-featured-image-message").show();
                                setTimeout(function() {
                                    location.reload();  
                                }, 1000);
                            } else {
                                alert("Error: " + response.data);
                            }
                        }).fail(function(xhr, status, error) {
                            console.error("AJAX Error:", status, error);
                            alert("Failed to save. Check the console for details.");
                        });
                        });
                        });
                    </script>

                    <?php
                    echo $args['after_widget'];
                }
                }
    }
}
}


function register_blur_featured_image_widget() {
    register_widget('Blur_Featured_Image_Widget');
}
add_action('widgets_init', 'register_blur_featured_image_widget');


/* 
Apply blur effect if enabled

*/
add_filter('post_thumbnail_html', 'apply_blur_if_enabled', 10, 3);
function apply_blur_if_enabled($attr, $attachment, $size) {
   
    if (is_single()) {
        $post_id = get_the_ID();
        $blur_enabled = get_post_meta($post_id, '_blured_featured_image', true);
        
        $tags = get_the_tags($post_id);
 
        $warning_texts = [];
    
        if ($tags) {
            foreach ($tags as $tag) {
                if (!empty($tag->description)) {
                     
                    $warning_texts[] = esc_html($tag->description);
                } else {
              
                    $warning_texts[] = 'This content requires your agreement to view.';
                }
            }
        }else{
            $warning_texts[] = 'This content requires your agreement to view.';
        }
    
        $all_warnings = implode('<br><br>', $warning_texts);   
        if ($blur_enabled) {
            return '
               <div class="deaddove-modal-wrapper">
                <div class="deaddove-modal" style="display:none;">
                    <div class="deaddove-modal-content">
                        <p>'.$all_warnings.'</p>
                        <div class="modal-buttons">
                            <button class="deaddove-show-content-btn">Show this content</button>
                            <button class="deaddove-hide-content-btn">Keep it hidden</button>
                        </div>
                        <small><a href="#deaddove-warning-settings4" class="deaddove-settings-link">Modify your content warning settings</a></small>
                    </div>
                </div> 
                <div class="deaddove-blurred-content deaddove-blur">' . $attr    . '</div>
            </div>  
                ';
           
        }
        
    }
    return $attr;
}

// Disable block widgets (if necessary)
function disable_block_widgets() {
    remove_theme_support('widgets-block-editor');
}
add_action('after_setup_theme', 'disable_block_widgets');


 
function save_blur_featured_image() {
    if (!isset($_POST['post_id'])) {
        wp_send_json_error("Post ID missing");
    }

    $post_id = intval($_POST['post_id']);

    if (isset($_POST['_blured_featured_image']) && $_POST['_blured_featured_image'] == "1") {
        
        update_post_meta($post_id, '_blured_featured_image', $_POST['_blured_featured_image']);
    } else {
        update_post_meta($post_id, '_blured_featured_image', 0);
       
    }

    wp_send_json_success("Updated successfully");
}

// Register AJAX actions
add_action('wp_ajax_save_blur_featured_image', 'save_blur_featured_image');
add_action('wp_ajax_nopriv_save_blur_featured_image', 'save_blur_featured_image'); // For non-logged-in users



 

 /* 
 ************** Update Description Widget ******************* it should remove 
 */
class Custom_User_Widget extends WP_Widget {

    public function __construct() {
        parent::__construct(
            'custom_user_widget', 
            __('Custom User Widget', 'text_domain'), 
            ['description' => __('Add the content warning for description', 'text_domain')]
        );
    }
    public function form($instance) {
        echo "<p>This widget allows users to blur the featured image for individual posts.</p>";
    }
    public function widget($args, $instance) {
      
        if (is_user_logged_in()) {
            $user_id = get_current_user_id();
          
            if (strpos($_SERVER['REQUEST_URI'], '/add-new-post') === false) {
                return;
            }
       
            $admin_warning_tags = get_option('content_warning', []);
            $user_tags = get_user_meta($user_id, 'content_warning', true) ?: $admin_warning_tags;
          
            $post_author_id = get_post_field('post_author', $post_id);
             
            // if ($user_id == $post_author_id) {
                $post_description = isset($post) ? esc_textarea($post->post_content) : '';
                
                global $post;
                $post_description = isset($post) ? esc_textarea($post->post_content) : '';
                echo $args['before_widget'];
                ?>
                <form method="POST" action="" id="description-form">
                    <!-- <?php wp_nonce_field('update_user_tags', 'user_tags_nonce'); ?> -->
                    <input type="hidden" name="post_id" value="<?php echo esc_attr($post_id); ?>">
                    <input type="hidden" name="user_id" value="<?php echo esc_attr($user_id); ?>">
                    <p><strong>Content Warnings:</strong></p>
                    <?php if (!empty($user_tags)): ?>
                        <?php foreach ($user_tags as $tag): ?>
                            <label>
                                <input type="checkbox" name="tags[]" value="<?php echo esc_attr($tag); ?>">
                                <?php echo esc_html($tag); ?>
                            </label>
                            <br>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p>No tags found.</p>
                    <?php endif; ?>
                    <span id="tagErrorMessage" style="color: red; display: none;">Please select at least one Tags</span>
                    <!-- <p><strong>Selected Text:</strong></p> -->
                    <textarea id="selected_text" name="selected_text"></textarea>
                    <span id="description_not_select_errorMessage" style="color: red; display: none;">Please Select Description</span>
                    <br><br>
                    <button type="submit" id="submit_description" value="Save Description">Submit</button>
                    <span id="blur-featured-image-message1" style="color: green; display: none;">Saved!</span>
                </form>
                <script>    
                    document.addEventListener("DOMContentLoaded", function () {
                        jQuery(document).ready(function($) {
                            var selectedTextArea = document.getElementById("selected_text");
                            var widget = document.querySelector(".widget.widget_custom_user_widget");
                            let selectContainer = document.querySelector('.sap-editable-area');
                            console.log("checking select area", selectContainer);
                            selectContainer.addEventListener("mouseup", function () {
                                var selectedText = window.getSelection().toString();
                                if (selectedText.length > 0) {
                                    selectedTextArea.value = selectedText;
                                    widget.style.display = "block";
                                }
                            });
                            $("#description-form").on("submit", function(e) {
                                e.preventDefault();  
                                var selectedText = selectedTextArea.value.trim();
                                let tagsChecked = $("input[name='tags[]']:checked").length > 0;
                                if (selectedText === "" || !tagsChecked) {
                                    if (!tagsChecked) {
                                        $("#tagErrorMessage").css("display", "block");
                                    } else {
                                        $("#tagErrorMessage").css("display", "none");
                                    }
                                    if (selectedText === "") {
                                        $("#description_not_select_errorMessage").show();
                                    } else {
                                        $("#description_not_select_errorMessage").hide();
                                    }
                                    return;
                                } else {
                                    var checkedTags = [];
                                    $("input[name='tags[]']:checked").each(function() {
                                        checkedTags.push($(this).val());
                                    });
                                    var tagsAttribute = checkedTags.join(", ");
                                    var editableArea = document.querySelector('.sap-editable-area');
                                    if (editableArea) {
                                        
                                        var pTags = editableArea.querySelectorAll('p');  
                                                pTags.forEach(function(pTag) {
                                                    var existingText = pTag.textContent.trim();
                                                    console.log("existing test::", existingText);
                                                    if (existingText.includes(selectedText)) {
                                                        console.log("Selected text found! Replacing text...");
                                                
                                                        var updatedHTML = existingText.replace(selectedText, `<p class="deaddove-block-description" tags="${tagsAttribute   }">${selectedText}</p><p></p>`);
                                                        pTag.innerHTML = updatedHTML; 
                                                    }
                                                });

                                            var formData = {
                                                'post_id': $("input[name='post_id']").val(),
                                                'user_id': $("input[name='user_id']").val(),
                                                'selected_text': selectedText,
                                                'tags': tagsAttribute
                                            };
                                            $("#blur-featured-image-message1").show(); 
                                            widget.style.display = "none";
                                    }
                                    $("#blur-featured-image-message1").show();
                                }
                            });
                        });
                    
                    });
                
                </script>
                <?php
                echo $args['after_widget'];
            // }
        }
    }
  
  
}
function register_custom_user_widget() {
    register_widget('Custom_User_Widget');
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

    $tags = wp_get_object_terms($posts, 'post_tag', ['fields' => 'id=>name']);

    if (!$tags || is_wp_error($tags)) return [];

    $tag_data = [];

    foreach ($tags as $tag_id => $tag_name) {
        $tag_obj = get_tag($tag_id);  

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

/*
Content warning tab navigation settings 
*/
add_action('bp_setup_nav', 'deaddove_add_buddyboss_profile_tab');

function deaddove_add_buddyboss_profile_tab() {
    global $bp;
    $parent_slug = 'settings';
    $parent_url = bp_loggedin_user_domain() . $parent_slug . '/';
    bp_core_new_subnav_item([
        'name'            => __('Content Warning Settings', 'textdomain'),
        'slug'            => 'content-warning-settings',
        'parent_slug'     => $parent_slug,
        'parent_url'      => $parent_url,
        'position'        => 60,
        'show_for_displayed_user' => false,
        'screen_function' => 'deaddove_buddyboss_settings_page',
        'default_subnav_slug' => 'content-warning-settings',
        'user_has_access' => bp_is_my_profile() || is_super_admin(), // Allow admins to see for debugging
    ]);
}
add_action('bbapp_profile_tab_icon', 'deaddove_profile_tab_icon_callback', 10, 3);

function deaddove_buddyboss_settings_page() {
    add_action('bp_template_content', 'deaddove_display_settings_form');
    bp_core_load_template('members/single/plugins');
    
}

/*
########Form of Content warning setting tab
######## It's used where content warning listed
*/
function deaddove_display_settings_form() {
    if (!is_user_logged_in()) {
        echo "<p>You need to log in to access this page.</p>";
        return;
    }
    $user_id = get_current_user_id();
    $userSelectedTags = get_user_meta($user_id, 'deaddove_user_warning_tags', true);
    // $adminTags = get_user_meta
    $userSelectedTags = $userSelectedTags !== '' ? $userSelectedTags : []; 
     
    // Getting all content warning tags
    $all_tags = get_terms([
        'taxonomy' => 'content_warning',
        'hide_empty' => false,
    ]);
    
    if (empty($userSelectedTags)) {
        $adminTags = [];
        foreach ($all_tags as $tag) {
            // $author_user_id = get_term_meta($tag->term_id, 'author_user_id', true);
            $author_user_role = get_term_meta($tag->term_id, 'author_user_role', true);

            if ($author_user_role === 'administrator') {
                $adminTags[] = $tag->slug;
            }
        }
        $userSelectedTags = $adminTags;
    }
    
     

    ?>
    <h3 id="deaddove-warning-settings">Content Warning Settings</h3>
    
    <form id="deaddove-settings-form">
        <?php wp_nonce_field('deaddove_user_profile_nonce', 'deaddove_user_nonce'); ?>

        <label><strong>Select tags for which a content warning should be shown:</strong></label>
        <div style="margin-top: 10px;">
            <ul class="terms-list">
            <?php foreach ($all_tags as $tag) : 
               
                ?>
                <li class="term-item">
                <label style="display: block; margin-bottom: 5px;">
                    <input type="checkbox" name="deaddove_user_tags[]" value="<?php echo esc_attr($tag->slug); ?>"
                    <?php echo in_array($tag->slug, $userSelectedTags) ? 'checked' : ''; ?>>
                    <?php echo esc_html($tag->name); ?>
                </label>
            <?php endforeach; ?>
            </li>
            </ul>
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
            // console.log("checking form dataa",formData)
            $.ajax({
                type: 'POST',
                url: '<?php echo admin_url("admin-ajax.php"); ?>',
                data: formData + '&action=deaddove_save_user_settings',
                beforeSend: function() {
                    $('#deaddove-save-message').hide();  
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

    // Save selected tags or delete if empty
    if (isset($_POST['deaddove_user_tags'])) {
        $selected_tags = array_map('sanitize_text_field', wp_unslash($_POST['deaddove_user_tags']));
        update_user_meta($user_id, 'deaddove_user_warning_tags', $selected_tags);
    } else {
        delete_user_meta($user_id, 'deaddove_user_warning_tags');
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
    $admin_warning_tags = get_option('deaddove_warning_tags', []);
    $user_tags = get_user_meta($post_author_id, 'deaddove_user_warning_tags', true) ?: $admin_warning_tags;
    $post_tags = wp_get_post_tags(get_the_ID(), ['fields' => 'slugs']);
     
    if (!empty(array_intersect($post_tags, $user_tags))) {
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
        $current_user = wp_get_current_user();
   
        $url = '#';
       
        if ($current_user->exists() && function_exists('bp_core_get_user_domain')) {
             
            $url = trailingslashit(bp_core_get_user_domain($current_user->ID)) . 'settings/content-warning-settings/';
        }
        else {
            $redirect_to = home_url('/members/me/settings/content-warning-settings/');  
            if (function_exists('bp_core_get_user_domain') && $current_user->exists()) {
                $redirect_to = trailingslashit(bp_core_get_user_domain($current_user->ID)) . 'settings/content-warning-settings/';
            }
            $url = wp_login_url($redirect_to);  
        }
        // $url =  function_exists('bp_core_get_user_domain') ? bp_core_get_user_domain($current_user->ID)+'settings/content-warning-settings/' : '#';
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
                    <small><a href=<?php echo $url;?> class="deaddove-settings-link">Modify your content warning settings</a></small>
                </div>
            </div>
        </div>
        <?php
        
    }
}

 
function deaddove_register_widget() {
    register_widget('DeadDove_Widget');
}
add_action('widgets_init', 'deaddove_register_widget');

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
            $admin_warning_tags = get_option('content_warning', []);
            $user_tags = get_user_meta($post_author_id, 'deaddove_user_warning_tags', true) ?: $admin_warning_tags;
            $post_tags = wp_get_post_tags($post_id, ['fields' => 'slugs']);  
            $matching_tags = array_intersect($post_tags, $user_tags);
            $tagDescriptions = [];
            if (!empty($matching_tags)) {
                foreach($matching_tags as $tag_slug){
                    $tag = get_term_by('slug', $tag_slug, 'post_tag'); // Slug se tag details lo
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
    // die;
    $user_id = get_current_user_id();
    
    $admin_warning_terms = get_option('content_warning', []);
    $userTerms = get_user_meta($user_id, 'deaddove_user_warning_tags', true) ?: $admin_warning_terms; 
    if (!is_array($userTerms)) {
        $userTerms = [];
    }
    $terms = get_terms([
        'taxonomy' => 'content_warning',
        'hide_empty' => false,
    ]);
    // print_r($terms)
    ?>
    <div class="custom-activity-field" style="margin-top: 10px;">
       <div class="accordion-header1" style="cursor: pointer; font-weight: bold; background: #f1f1f1; padding: 10px; border: 1px solid #ddd;">
          Content Warnings <span style="float: right;">&#9660;</span>
        </div>
        <div class="accordion-content1" style="display: none; padding: 10px; border: 1px solid #ddd; height:200px; overflow-y:auto">
        <?php foreach ($terms as $term) :
            // if (in_array($term->slug, $userTerms)) :
        ?>
            <label style="display: block; margin-bottom: 5px;">
                <input type="checkbox" name="content_warning_tags[]" value="<?php echo esc_attr($term->term_id); ?>">
                <?php echo esc_html($term->name); ?>
                </label>
                <?php 
            // endif; 
        endforeach; ?>
    </div>
    <script>
        jQuery(document).ready(function($) {
                $(".accordion-header1").click(function () {
                console.log("hello checking why it's not working as expect")
                 
                $(".accordion-content1").slideToggle(300); 
                const icon = $(this).find("span");
             
                icon.text(icon.text() === "▼" ? "▲" : "▼"); 
            });
        }
        )
    </script>
    </div>
        <?php

}
add_action('bp_activity_post_form_options', 'bboss_add_custom_field_to_activity_form');

function bboss_save_custom_activity_field($content, $user_id, $activity_id) {
    if (!isset($_POST['content_warning_tags']) || empty($_POST['content_warning_tags'])) {
        return;
    }

    $tags = $_POST['content_warning_tags'];

    
    if (!is_array($tags)) {
        $tags = array($tags);
    }

    $selected_tags = array_map('intval', $tags);
    $tags_string = implode(',', $selected_tags);

    bp_activity_update_meta($activity_id, 'content_warning_tags', $tags_string);
    // bp_activity_update_meta($activity_id, 'content_warning_tags', $tags_string);
}
add_action('bp_activity_posted_update', 'bboss_save_custom_activity_field', 10, 3);

function bboss_add_custom_field_to_forum_form() {
    $user_id = get_current_user_id();

    $admin_warning_terms = get_option('content_warning', []);
    $userTerms = get_user_meta($user_id, 'deaddove_user_warning_tags', true) ?: $admin_warning_terms;
    if (!is_array($userTerms)) {
        $userTerms = [];
    }

    $terms = get_terms([
        'taxonomy' => 'content_warning',
        'hide_empty' => false,
    ]);

    ?>
    <div class="custom-forum-field" style="margin-top: 10px;">
        <div class="accordion-header1" style="cursor: pointer; font-weight: bold; background: #f1f1f1; padding: 10px; border: 1px solid #ddd;">
            Content Warnings <span style="float: right;">&#9660;</span>
        </div>
        <div class="accordion-content1" style="display: none; padding: 10px; border: 1px solid #ddd; height:200px; overflow-y:auto">
            <?php foreach ($terms as $term) : ?>
                <label style="display: block; margin-bottom: 5px;">
                    <input type="checkbox" name="forum_content_warning_tags[]" value="<?php echo esc_attr($term->term_id); ?>">
                    <?php echo esc_html($term->name); ?>
                </label>
            <?php endforeach; ?>
        </div>
    </div>

    <script>
        jQuery(document).ready(function($) {
            
            $(".accordion-header1").click(function () {
                $(".accordion-content1").slideToggle(300);
                const icon = $(this).find("span");
                icon.text(icon.text() === "▼" ? "▲" : "▼");
            });
    //          $(document).on('change', 'input[name="forum_content_warning_tags[]"]', function () {
    //     let selectedTags = [];

    //     $('input[name="forum_content_warning_tags[]"]:checked').each(function () {
    //         selectedTags.push($(this).val());
    //     });

    //     // Show in console
    //     // console.log("Selected Content Warning Tags:", selectedTags);
 
    // });
        });
          
    </script>
    <?php
}
// add_action('bbp_theme_after_topic_form_content', 'bboss_add_custom_field_to_forum_form');
add_action('bbp_theme_after_topic_form_content', 'bboss_add_custom_field_to_forum_form');
add_action('bbp_theme_after_reply_form_content', 'bboss_add_custom_field_to_forum_form');
 


function save_forum_custom_field($topic_id) {
    if (isset($_POST['forum_content_warning_tags'])) {
        $tags = array_map('intval', $_POST['forum_content_warning_tags']);
        update_post_meta($topic_id, 'forum_content_warning_tags', $tags);
    }
}
add_action('bbp_new_topic', 'save_forum_custom_field');

function save_reply_custom_field($reply_id) {
    if (isset($_POST['forum_content_warning_tags'])) {
        $tags = array_map('intval', $_POST['forum_content_warning_tags']);
        update_post_meta($reply_id, 'forum_content_warning_tags', $tags);
    }
}
add_action('bbp_new_reply', 'save_reply_custom_field');

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
    $warningType = $_POST['type'];
    // print_r($warningType);
    $args = array(
        'per_page' => 100,  
        'page' => 1, 
    );
    if(isset($_POST['activities']) && !empty($_POST['activities'])){
        // print_r($_POST['activities']);
        $activities = $_POST['activities'];
    }
    else{
        $activities = bp_activity_get($args);
    }
    // echo "<pre>";
    // print_r($activities);
    // echo "</pre>";
    // if (empty($activities['activities'])) {
    //     wp_send_json_error(array('message' => 'No activities found.'));
    // }

    $activity_data = [];
    foreach ($activities as $activity) {
        // $activity_id = $activity->id;
        // echo $activity;
        if($warningType=='acticityWarning'){
            $content_warning_tag = bp_activity_get_meta(intval($activity), 'content_warning_tags', true);
        }
        else{
            $content_warning_tag = get_post_meta(intval($activity), 'content_warning_tags', true);
        }
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
                    'activity_id' => intval($activity),
                    'content_warning_tag' => implode(', ', $tag_names), 
                    'content_warning_description'=>implode(' | ', $tag_descriptions), 
                ];
            }
        }
    }
    if (empty($activity_data)) {
        wp_send_json_error(array('message' => 'No activities with content warning Terms.'));
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
    // $admin_warning_terms = get_option('content_warning', []);
    $all_tags = get_terms([
        'taxonomy' => 'content_warning',
        'hide_empty' => false,
    ]);
    // $all_tags = get_user_meta($user_id, 'deaddove_user_warning_tags', true) ?: $admin_warning_terms;

    if (empty($all_tags)) {
        wp_send_json_error(array(
            'message' => 'No warning tags available',  
        ));
    } 
    wp_send_json_success(array(
        'user_content_warning_tag' =>$all_tags,   
    ));
}

add_action('wp_ajax_get_custom_widget', 'get_custom_widget_callback');   
add_action('wp_ajax_nopriv_get_custom_widget', 'get_custom_widget_callback'); 


/*

Fetching current user for js link 
*/

function deaddove_current_user_ajax_handler() {
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'deaddove_nonce')) {
        wp_send_json_error(array('message' => 'Invalid nonce'));
    }

    // Current user ko nikalna
    $current_user = wp_get_current_user();

    // Check if user is logged in
    if (!$current_user->exists()) {
        wp_send_json_error(array('message' => 'User not logged in'));
    }
    // User ki details collect karna
    $user_data = array(
        'user_id' => $current_user->ID,
        'username' => $current_user->user_login,
        'display_name' => $current_user->display_name,
        'email' => $current_user->user_email,
        'profile_url' => get_author_posts_url($current_user->ID), // WordPress author profile link
        'registered_date' => $current_user->user_registered,
        // Agar BuddyPress use kar rahe ho, to BuddyPress profile link
        'bp_profile_url' => function_exists('bp_core_get_user_domain') ? bp_core_get_user_domain($current_user->ID) : '',
        // Aur bhi fields add kar sakte ho, jaise first_name, last_name, etc.
        'first_name' => get_user_meta($current_user->ID, 'first_name', true),
        'last_name' => get_user_meta($current_user->ID, 'last_name', true),
    );

    // Success response with user data
    wp_send_json_success($user_data);
}
add_action('wp_ajax_deaddove_current_user', 'deaddove_current_user_ajax_handler');  
add_action('wp_ajax_nopriv_deaddove_current_user', 'deaddove_current_user_ajax_handler');
 
add_filter( 'bbp_template_include', 'myplugin_bbp_template_override' );

function myplugin_bbp_template_override( $template ) {
    
    $active_theme = wp_get_theme();

   
    $is_buddyboss = ( $active_theme->get( 'Name' ) === 'BuddyBoss Theme' || $active_theme->get( 'Template' ) === 'buddyboss-theme' );

    if ( ! $is_buddyboss ) {
        return $template;  
    }

    $plugin_template_dir = plugin_dir_path( __FILE__ ) . 'templates/bbpress/';
    $template_filename   = basename( $template );
    $custom_template     = $plugin_template_dir . $template_filename;

    if ( file_exists( $custom_template ) ) {
        echo '<!-- Loading plugin template: ' . $custom_template . ' -->';
        return $custom_template;
    }

    return $template;
}



function dd_get_template_part( $slug, $name = null ) {
    $plugin_path = plugin_dir_path( __FILE__ ) . 'templates/';
    $template = '';
    
    if ( $name ) {
        $file = $slug . '-' . $name . '.php';
        // print_r($file);
        if ( file_exists( $plugin_path . $file ) ) {
            
            $template = $plugin_path . $file;
        }
    }
    
    // If no named file, check for base slug
    if ( empty( $template ) ) {
        $file = $slug . '.php';
        if ( file_exists( $plugin_path . $file ) ) {
            $template = $plugin_path . $file;
        }
    }
    

    // Load the file if found
    
    if ( ! empty( $template ) ) {
        echo '<!-- Loading plugin template: ' . $template . ' -->';
        load_template( $template, false );
        return;
    }

    // Fallback to theme
    get_template_part( $slug, $name );
}
 
function dd_is_topic_blurred( $topic_id ) {
    $blurred = false;
 
    if ( ! $topic_id ) {
        return $blurred;  
    }
  
    $tag_ids = get_post_meta( $topic_id, 'forum_content_warning_tags', true );
 
    if ( ! empty( $tag_ids ) ) {
        // $tag_ids = explode( ',', $content_warning_tag );
        foreach ( $tag_ids as $tag_id ) {
            if ( term_exists( intval( $tag_id ), 'content_warning' ) ) {
                $blurred = true;
                break;
            }
            // $blurred = true;
            // break;
        }
    }
    
    return $blurred;
}
// File: wp-content/plugins/my-custom-plugin/my-custom-plugin.php

function dd_custom_bbp_reply_callback( $reply, $args, $depth ) {
    $GLOBALS['post'] = $reply;
    setup_postdata( $reply );
 
    ?>
    <li id="post-<?php bbp_reply_id(); ?>" <?php bbp_reply_class(); ?>>
        <?php dd_get_template_part( 'template-parts/loop', 'single-reply' ); ?>
    </li>
    <?php
}
function dd_blurTopic_description( $topic_id ) {
    $blurredDescription = '';

    if ( ! $topic_id ) {
        return $blurredDescription;  
    }

    $tag_ids = get_post_meta( $topic_id, 'forum_content_warning_tags', true );

    if ( empty( $tag_ids ) ) {
        return $blurredDescription;
    }

    if ( is_string( $tag_ids ) ) {
        $tag_ids = explode( ',', $tag_ids );
    }

    if ( is_array( $tag_ids ) ) {
        $descriptions = [];

        foreach ( $tag_ids as $tag_id ) {
            $tag_id = intval( $tag_id );
            if ( $tag_id && term_exists( $tag_id, 'content_warning' ) ) {
                $term = get_term( $tag_id, 'content_warning' );
                if ( ! is_wp_error( $term ) && ! empty( $term->description ) ) {
                    $descriptions[] = $term->description;
                }
            }
        }

        if ( ! empty( $descriptions ) ) {
            $blurredDescription = implode( ' | ', $descriptions );
        }
    }

    return $blurredDescription;
}
add_action( 'bbp_ajax_reply_posted', 'dd_custom_ajax_reply_output', 20, 5 );

// function dd_custom_ajax_reply_output( $reply_id, $topic_id, $forum_id, $anonymous_data, $reply_author ) {
//     ob_start();
    
//     // Load your custom reply template (same as loop-single-reply.php)
//     bbp_set_query_name( 'bbp_single_reply' ); // Important
//     bbp_get_template_part( 'loop', 'single-reply' );
    
//     $html = ob_get_clean();

//     // Send HTML back to browser
//     wp_send_json_success( array(
//         'reply_id' => $reply_id,
//         'html'     => $html,
//     ) );
// }
