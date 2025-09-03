<?php
/**
 * The template for displaying bbPress pages
 *
 * @link https://developer.wordpress.org/themes/basics/template-hierarchy/
 *
 * @package BuddyBoss_Theme
 */

get_header();

$is_buddyboss_bbpress = function_exists( 'buddyboss_bbpress' );

if ( ! $is_buddyboss_bbpress && ! bbp_is_single_user() ) {
	get_template_part( 'template-parts/bbpress-banner' );
} ?>

<?php
$sidebar_position = buddyboss_theme_get_option( 'forums' );
if ( ! function_exists( 'buddyboss_bbpress' ) && 'left' == $sidebar_position ) {
	get_sidebar( 'bbpress' );
}
?>

<div id="primary" class="content-area">
	<?php
	$bbpress_banner = buddyboss_theme_get_option( 'bbpress_banner_switch' );

	if ( bbp_is_forum_archive() && ! $bbpress_banner ) {
		?>
		<header class="entry-header">
			<h1 class="entry-title"><?php echo esc_html( get_the_title() ); ?></h1>

			<?php if ( bbp_allow_search() ) : ?>
				<div id="forums-dir-search" role="search" class="bs-dir-search bs-forums-search">
					<form class="bs-search-form search-form-has-reset" role="search" method="get" id="bbp-search-form" action="<?php bbp_search_url(); ?>">
						<input type="hidden" name="action" value="bbp-search-request"/>
						<input tabindex="<?php bbp_tab_index(); ?>" type="text" value="<?php echo esc_attr( bbp_get_search_terms() ); ?>" name="bbp_search" id="bbp_search" placeholder="<?php esc_attr_e( 'Search forums...', 'buddyboss-theme' ); ?>"/>
						<input tabindex="<?php bbp_tab_index(); ?>" class="button hide search-form_submit" type="submit" id="bbp_search_submit" value="<?php esc_attr_e( 'Search', 'buddyboss-theme' ); ?>"/>
						<button type="reset" class="search-form_reset">
							<span class="bb-icon-rf bb-icon-times" aria-hidden="true"></span>
							<span class="bp-screen-reader-text"><?php esc_html_e( 'Reset', 'buddyboss-theme' ); ?></span>
						</button>
					</form>
				</div>
			<?php endif; ?>

		</header>
		<?php
	}
	?>
	<main id="main" class="site-main site-custom-plugin-main">
		<?php 
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
		?>
	<div class="deaddove-forums-modal-wrapper">
				<div class="deaddove-modal" style="display:none;">
					<div class="deaddove-modal-content">
						<p class="description-text">Data not avalaible</p>
						<div class="modal-buttons">
							<button class="deaddove-show-content-btn">Show this content</button>
							<button class="deaddove-hide-content-btn">Keep it hidden</button>
						</div>
						<small><a href="<?php echo esc_url( $url ); ?>" class="deaddove-settings-link">Modify your content warning settings</a></small>
					</div>
				</div> 
	</div>
		<?php 
		if ( have_posts() ) : ?>
			<?php
			/* Start the Loop */
			while ( have_posts() ) :
				the_post();
				// echo "hello this pulgin bbpress oriride ";

				/*
				 * Include the Post-Format-specific template for the content.
				 * If you want to override this in a child theme, then include a file
				 * called content-___.php (where ___ is the Post Format name) and that will be used instead.
				 */
				// get_template_part( 'template-parts/content', 'bbpress' );
				dd_get_template_part( 'template-parts/content', 'bbpress' );
				 


			endwhile;
			?>

			<?php
			// buddyboss_pagination();

		else :
			get_template_part( 'template-parts/content', 'none' );
			?>

		<?php endif; ?>

	</main><!-- #main -->
</div><!-- #primary -->

<?php
if ( ! function_exists( 'buddyboss_bbpress' ) && 'right' == $sidebar_position ) {
	get_sidebar( 'bbpress' );
}
?>

<?php
get_footer();
