<?php
/**
 * Functions for handling how comments are displayed and used on the site. This allows more
 * precise control over their display and makes more filter and action hooks available to developers
 * to use in their customizations.
 *
 * @package Hybrid
 * @subpackage Functions
 */

add_filter( 'comment_form_defaults', 'hybrid_comment_form_args' );

function hybrid_comment_form_args( $args ) {
	global $user_identity;

	$commenter = wp_get_current_commenter();

	$req = get_option( 'require_name_email' );
	$req = ( ( $req ) ? ' <span class="required">' . __( '*', 'hybrid' ) . '</span> ' : '' );

	$args = array(
		'fields' => apply_filters( 'comment_form_default_fields', array(
			'author' => '<p class="form-author"><label for="author">' . __( 'Name', 'hybrid' ) . $req . '</label> <input type="text" class="text-input" name="author" id="author" value="' . esc_attr( $commenter['comment_author'] ) . '" size="40" tabindex="1" /></p>',
			'email' => '<p class="form-email"><label for="email">' . __( 'Email', 'hybrid' ) . $req . '</label> <input type="text" class="text-input" name="email" id="email" value="' . esc_attr( $commenter['comment_author_email'] ) . '" size="40" tabindex="2" /></p>',
			'url' => '<p class="form-url"><label for="url">' . __( 'Website', 'hybrid' ) . '</label><input type="text" class="text-input" name="url" id="url" value="' . esc_attr( $commenter['comment_author_url'] ) . '" size="40" tabindex="3" /></p>'
		) ),
		'comment_field' => '<p class="form-textarea"><label for="comment">' . __( 'Comment', 'hybrid' ) . '</label><textarea name="comment" id="comment" cols="60" rows="10" tabindex="4"></textarea></p>',
		'must_log_in' => '<p class="alert">' . sprintf( __( 'You must be <a href="%1$s" title="Log in">logged in</a> to post a comment.', 'hybrid' ), wp_login_url( get_permalink() ) ) . '</p><!-- .alert -->',
		'logged_in_as' => '<p class="log-in-out">' . sprintf( __( 'Logged in as <a href="%1$s" title="%2$s">%2$s</a>.', 'hybrid' ), admin_url( 'profile.php' ), $user_identity ) . ' <a href="' . wp_logout_url( get_permalink() ) . '" title="' . __( 'Log out of this account', 'hybrid' ) . '">' . __( 'Log out &raquo;', 'hybrid' ) . '</a></p><!-- .log-in-out -->',
		'id_form' => 'commentform',
		'id_submit' => 'submit',
		'title_reply' => __( 'Leave a Reply', 'hybrid' ),
		'title_reply_to' => __( 'Leave a Reply to %s', 'hybrid' ),
		'cancel_reply_link' => __( 'Click here to cancel reply.', 'hybrid' ),
		'label_submit' => __( 'Submit', 'hybrid' ),
	);

	return $args;
}

/**
 * Arguments for the wp_list_comments_function() used in comments.php. Users can set up a 
 * custom comments callback function by changing $callback to the custom function.  Note that 
 * $style should remain 'ol' since this is hardcoded into the theme and is the semantically correct
 * element to use for listing comments.
 *
 * @since 0.7
 * @return array $args Arguments for listing comments.
 */
function hybrid_list_comments_args() {
	$args = array( 'style' => 'ol', 'type' => 'all', 'avatar_size' => 80, 'callback' => 'hybrid_comments_callback', 'end-callback' => 'hybrid_comments_end_callback' );
	return apply_atomic( 'list_comments_args', $args );
}

/**
 * Uses the $comment_type to determine which comment template should be used. Once the 
 * template is located, it is loaded for use. Child themes can create custom templates based off
 * the $comment_type. The comment template hierarchy is comment-$comment_type.php, 
 * comment.php.
 *
 * The templates are saved in $hybrid->templates[comment_template], so each comment template
 * is only located once if it is needed. Following comments will use the saved template.
 *
 * @since 0.2.3
 * @param $comment The comment variable
 * @param $args Array of arguments passed from wp_list_comments()
 * @param $depth What level the particular comment is
 */
function hybrid_comments_callback( $comment, $args, $depth ) {
	global $hybrid;

	$GLOBALS['comment'] = $comment;
	$GLOBALS['comment_depth'] = $depth;

	$comment_type = get_comment_type( $comment->comment_ID );

	if ( !is_array( $hybrid->templates['comment_template'] ) || !array_key_exists( $comment_type, $hybrid->templates['comment_template'] ) ) {

		$templates = array( "comment-{$comment_type}.php", 'comment.php' );

		$hybrid->templates['comment_template'][$comment_type] = locate_template( $templates );
	}

	require( $hybrid->templates['comment_template'][$comment_type] );
}

/**
 * Ends the display of individual comments. Uses the callback parameter for wp_list_comments(). 
 * Needs to be used in conjunction with hybrid_comments_callback(). Not needed but used just in 
 * case something is changed.
 *
 * @since 0.2.3
 */
function hybrid_comments_end_callback() {
	echo '</li><!-- .comment -->';
}

/**
 * Displays the avatar for the comment author and wraps it in the comment author's URL if it is
 * available.  Adds a call to THEME_IMAGES . "/{$comment_type}.png" for the default avatars for
 * trackbacks and pingbacks.
 *
 * @since 0.2
 * @global $comment The current comment's DB object.
 * @global $hybrid The global Hybrid object.
 */
function hybrid_avatar() {
	global $comment, $hybrid;

	if ( !get_option( 'show_avatars' ) )
		return false;

	/* Get/set some comment variables. */
	$comment_type = get_comment_type( $comment->comment_ID );
	$author = esc_html( get_comment_author( $comment->comment_ID ) );
	$url = esc_url( get_comment_author_url( $comment->comment_ID ) );

	if ( 'pingback' == $comment_type || 'trackback' == $comment_type ) 
		$default_avatar = THEME_IMAGES . "/{$comment_type}.png";

	$default_avatar = apply_filters( "{$hybrid->prefix}_{$comment_type}_avatar", $default_avatar );

	$comment_list_args = hybrid_list_comments_args();
	$size = ( ( $comment_list_args['avatar_size'] ) ? $comment_list_args['avatar_size'] : 80 );

	$avatar = get_avatar( get_comment_author_email( $comment->comment_ID ), absint( $size ), $default_avatar, $author );

	/* If URL input, wrap avatar in hyperlink. */
	if ( $url )
		$avatar = '<a href="' . $url . '" rel="external nofollow" title="' . $author . '">' . $avatar . '</a>';

	echo apply_filters( "{$hybrid->prefix}_avatar", $avatar );
}

/**
 * Function for displaying a comment's metadata.
 *
 * @since 0.7
 * @param string $metadata Custom metadata to use.
 * @global $comment The global comment object.
 * @global $post The global post object.
 */
function hybrid_comment_meta( $metadata = '' ) {
	global $comment, $post;

	if ( !$metadata )
		$metadata = '[comment-author] [comment-published] [comment-permalink before="| "] [comment-edit-link before="| "] [comment-reply-link before="| "]';

	$metadata = '<div class="comment-meta comment-meta-data">' . $metadata . '</div>';

	echo do_shortcode( apply_filters( hybrid_get_prefix() . '_comment_meta', $metadata ) );
}

?>