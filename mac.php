<?php
/**
 * mac.php
 * 
 * This is the "main" file for controlling access to WP media files.
 * 
 * It is intended to be called directly by the web server, and it
 * handles the logic of determining whether a file should be served
 * or not.
 * 
 * It is not intended to be called directly by a user.
 * 
 * Heavily inspired by https://gist.github.com/hakre/1552239
 */

require_once('mac-requires.php');

if ( ! isset( $_GET['file'] ) ) {
	error_log('mac.php called without file parameter');
	die;
}

prep_output_buffer();
$upload_dir_info = wp_upload_dir();
// TODO: $file_url = explode('?', $_SERVER['REQUEST_URI'])[0]
//  item [1] is the query string...should probably log that it's been ignored by this script
$file_url = $upload_dir_info['baseurl'] . '/' . $_GET['file'];
$file_info = get_file_info( $file_url, $upload_dir_info );

// Check 404
if ( ! is_file( $file_info['path'] ) ) {
	error_log( 'mac.php: 404 ' . $file_url . " sought at " . $file_info['path']);
	wp_redirect( mac\REDIRECT_404 );
}

// Check 401
if ( $file_info['wp_attachment'] ) {
	$parent_post = get_post_parent( $file_info['wp_id'] );

	if ( $parent_post ) {

		if ( is_controlled_type( $parent_post ) ) {

			if ( 'publish' === $parent_post->post_status ) {

				switch ( eval_password_control( $parent_post ) ) {
					case 'deny':

						// User login trumps password control; serve if logged in.
						if ( current_user_can( 'read_post', $parent_post ) ) {
							/* NB: Any user can 'read' a password protected parent post!
							* Not exactly a bug, but possibly unexpected.
							*/
							serve_controlled_file( $file_info['path'] );
							exit;
						} else {
							deny( get_permalink( $parent_post ), $file_url );
							exit;
						}

						break;
					case 'control':
						serve_controlled_file( $file_info['path'] );
						exit;
						break;
					case 'normal':
						serve_uncontrolled_file( $file_info['path'] );
						exit;
						break;
				}
			} else {

				if ( current_user_can( 'read_post', $parent_post ) ) {
					/* NB: Any user can 'read' a password protected parent post!
					* Not exactly a bug, but possibly unexpected.
					*/
					serve_controlled_file( $file_info['path'] );
					exit;
				} else {
					deny( get_permalink( $parent_post ), $file_url );
					exit;
				}

			}				

		} else {
			serve_uncontrolled_file( $file_info['path]'] );
			exit;
		}

	} else {
		if ( is_controlled_slug( $file_url ) ) {
			
			// Check if user is logged in; let any user view "unattached" media files (unattached attachments)
			if ( wp_get_current_user()->ID ) {
				serve_controlled_file( $file_info['path'] );
				exit;
			} else {
				// This is a substitute for having real pw control on the attachment
				if ( isset_password_cookie() ) {
					serve_controlled_file( $file_info['path'] );
					exit;
				} else {
					deny( get_permalink( $parent_post ), $file_url );
					exit;
				}
			}

		} else {
			serve_uncontrolled_file( $file_info['path'] );
			exit;
		}
	}

} else {
	serve_uncontrolled_file( $file_info['path'] );
	exit;
}
