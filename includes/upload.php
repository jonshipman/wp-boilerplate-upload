<?php

// Creates an action for uploads.
function wp_boilerplate_upload_media_upload_action() {
	$token = WPGraphQL\JWT_Authentication\Auth::validate_token();
	if ( ! empty( $token ) && ! is_wp_error( $token ) ) {
		wp_set_current_user( $token->data->user->id );

		if ( current_user_can( 'upload_files' ) ) {
			if ( isset( $_FILES['file'] ) ) {
					$file = wp_unslash( $_FILES['file'] );
					$mime = mime_content_type( $file['tmp_name'] );

					do_action( 'wp_boilerplate_upload_media_upload', $file, $mime );
					die;
			}
		} else {
			header( 'HTTP/1.1 403 FORBIDDEN' );
			echo json_encode( array( 'message' => '403 Forbidden' ) );
		}
	} else {
		header( 'HTTP/1.1 403 FORBIDDEN' );
		echo json_encode( array( 'message' => '403 Forbidden' ) );
	}

	die;
}

add_action( 'wp_ajax_nopriv_media_upload', 'wp_boilerplate_upload_media_upload_action' );
add_action( 'wp_ajax_media_upload', 'wp_boilerplate_upload_media_upload_action' );

// Allows the wp-ajax to correctly pre-flight the action.
add_filter(
	'allowed_http_origins',
	function( $origins ) {
		$action = isset( $_REQUEST['action'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['action'] ) ) : '';
		if ( apply_filters( 'wp_boilerplate_upload_action_name', 'media_upload' ) === $action ) {
			header( 'Access-Control-Allow-Headers: Access-Control-Allow-Origin, Authorization' );
		}

		return $origins;
	}
);

// Used in ajax. Takes a file and puts it in the media gallery.
function wp_boilerplate_upload_media_library_upload( $file, $mime ) {
	if ( in_array( $mime, get_allowed_mime_types() ) ) {
		$upload   = wp_upload_dir();
		$new_file = sprintf( '%s/%s', $upload['path'], $file['name'] );

		if ( move_uploaded_file( $file['tmp_name'], $new_file ) ) {
			$id = wp_insert_attachment(
				array(
					'guid'           => $new_file,
					'post_mime_type' => $mime,
					'post_title'     => preg_replace( '/\.[^.]+$/', '', $file['name'] ),
					'post_content'   => '',
					'post_status'    => 'inherit',
				),
				$new_file
			);

			if ( $id && ! is_wp_error( $id ) ) {
				if ( ! function_exists( 'wp_crop_image' ) ) {
					include ABSPATH . 'wp-admin/includes/image.php';
				}

				wp_update_attachment_metadata( $id, wp_generate_attachment_metadata( $id, $new_file ) );

				echo $id;
				return;
			}
		}

		echo 0;
		return;
	}
}

add_action( 'wp_boilerplate_upload_media_upload', 'wp_boilerplate_upload_media_library_upload', 10, 2 );
