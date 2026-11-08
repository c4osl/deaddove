<?php
/**
 * Tests for the plugin's AJAX endpoints and shortcode output.
 *
 * @package DeadDove
 */

/**
 * Covers the AJAX permission and nonce checks and the shortcode escaping.
 */
class AjaxEndpointsTest extends WP_Ajax_UnitTestCase {

    /**
     * Runs an AJAX action and returns the decoded response.
     *
     * @param string $action AJAX action name.
     * @return array Decoded response, with success set to false when the request died.
     */
    private function run_ajax( $action ) {
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- The tests build the request themselves.
        $_REQUEST             = $_POST;
        $this->_last_response = '';
        try {
            $this->_handleAjax( $action );
        } catch ( WPAjaxDieContinueException $e ) {
            return json_decode( $this->_last_response, true );
        } catch ( WPAjaxDieStopException $e ) {
            return array(
                'success' => false,
                'data'    => $e->getMessage(),
            );
        }
        return array( 'success' => false );
    }

    /**
     * Only users who can edit the post, or wrote it, may change the blur flag.
     */
    public function test_blur_featured_image_permissions() {
        $requests = array(
            'editor on another author post'   => array( 'editor', false, true ),
            'administrator on another post'   => array( 'administrator', false, true ),
            'subscriber on another post'      => array( 'subscriber', false, false ),
            'subscriber on a post they wrote' => array( 'subscriber', true, true ),
        );

        foreach ( $requests as $name => list( $role, $owns_post, $allowed ) ) {
            $user_id   = self::factory()->user->create( array( 'role' => $role ) );
            $author_id = $owns_post ? $user_id : self::factory()->user->create( array( 'role' => 'author' ) );
            $post_id   = self::factory()->post->create( array( 'post_author' => $author_id ) );
            wp_set_current_user( $user_id );

            $_POST    = array(
                'post_id'                => $post_id,
                '_blured_featured_image' => '1',
            );
            $response = $this->run_ajax( 'save_blur_featured_image' );
            $this->assertFalse( $response['success'], $name . ' without a nonce' );
            $this->assertSame( 0, (int) get_post_meta( $post_id, '_blured_featured_image', true ), $name );

            $_POST['nonce'] = wp_create_nonce( 'deaddove_nonce' );
            $response       = $this->run_ajax( 'save_blur_featured_image' );
            $this->assertSame( $allowed, $response['success'], $name );
            $this->assertSame( $allowed ? 1 : 0, (int) get_post_meta( $post_id, '_blured_featured_image', true ), $name );
        }
    }

    /**
     * No AJAX action is registered for logged-out visitors.
     */
    public function test_no_endpoint_is_open_to_logged_out_visitors() {
        $private_actions = array( 'save_blur_featured_image', 'get_custom_widget', 'deaddove_current_user' );
        foreach ( $private_actions as $action ) {
            $this->assertNotFalse( has_action( 'wp_ajax_' . $action ), $action );
            $this->assertFalse( has_action( 'wp_ajax_nopriv_' . $action ), $action );
        }
        $this->assertFalse( has_action( 'wp_ajax_save_user_description' ) );
        $this->assertFalse( has_action( 'wp_ajax_nopriv_save_user_description' ) );
    }

    /**
     * Each nonce-protected endpoint rejects a request without a nonce and accepts one with it.
     */
    public function test_nonce_is_required() {
        wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );

        $term_id = self::factory()->term->create( array( 'taxonomy' => 'content_warning' ) );

        foreach ( array( 'deaddove_content_warning', 'get_custom_widget', 'deaddove_current_user' ) as $action ) {
            $post_id = self::factory()->post->create();
            update_post_meta( $post_id, 'content_warning_tags', (string) $term_id );
            $_POST = array(
                'type'       => 'postWarning',
                'activities' => array( $post_id ),
            );
            $this->assertFalse( $this->run_ajax( $action )['success'], $action . ' without a nonce' );

            $_POST['nonce'] = wp_create_nonce( 'deaddove_nonce' );
            $this->assertTrue( $this->run_ajax( $action )['success'], $action . ' with a nonce' );
        }
    }

    /**
     * The tags attribute is escaped on the add-new-post page.
     */
    public function test_shortcode_escapes_tags_attribute() {
        self::factory()->term->create(
            array(
                'taxonomy' => 'content_warning',
                'name'     => 'Spoilers',
                'slug'     => 'spoilers',
            )
        );
        update_option( 'deaddove_warning_terms', array( 'spoilers' ) );
        $_SERVER['REQUEST_URI'] = '/add-new-post';

        $output = do_shortcode( '[content_warning tags=\'spoilers,x" onmouseover="alert(1)\']text[/content_warning]' );

        $this->assertStringContainsString( 'deaddove-block-description', $output );
        $this->assertStringNotContainsString( 'onmouseover="', $output );
    }
}
