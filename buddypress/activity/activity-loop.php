<?php
/**
 * The template for activity loop
 *
 * This template can be overridden by copying it to yourtheme/buddypress/activity/activity-loop.php.
 *
 * @since   BuddyBoss 1.0.0
 * @version 1.0.0
 */

bp_nouveau_before_loop();

if ( bp_has_activities( bp_ajax_querystring( 'activity' ) ) ) :

	if ( empty( $_POST['page'] ) || 1 === (int) $_POST['page'] ) :
		?>
		<ul class="activity-list item-list bp-list customTheme">
		<?php
	endif;

	while ( bp_activities() ) :
		bp_the_activity();
		bp_get_template_part( 'activity/entry' );
	endwhile;

	if ( bp_activity_has_more_items() ) :
		?>
		<li class="load-more">
			<a class="button outline" href="<?php bp_activity_load_more_link(); ?>"><?php esc_html_e( 'Load More', 'buddyboss' ); ?></a>
		</li>
		<?php
	endif;
	?>

	<li class="activity activity_update activity-item activity-popup"></li>

	<?php if ( empty( $_POST['page'] ) || 1 === (int) $_POST['page'] ) :
		?>
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
						<small><a href="<?php echo $url ?>" class="deaddove-settings-link">Modify your content warning settings</a></small>
					</div>
				</div> 
			</div>
		</ul>
		<?php
	endif;

else :
	bp_nouveau_user_feedback( 'activity-loop-none' );
endif;

bp_nouveau_after_loop();
