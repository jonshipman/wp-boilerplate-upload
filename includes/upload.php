<?php

// Creates an action for uploads.
if ( ! function_exists( 'wp_boilerplate_upload_media_upload_action' ) ) {
	function wp_boilerplate_upload_media_upload_action() {
		do_action( 'wp_boilerplate_upload_preflight' );

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
}

// Allows us to preflight from the frontend with cors.
if ( ! function_exists( 'wp_boilerplate_upload_preflight' ) ) {
	function wp_boilerplate_upload_preflight() {
		$request_method = isset( $_SERVER['REQUEST_METHOD'] ) ? $_SERVER['REQUEST_METHOD'] : 'GET';

		$origin = get_http_origin();

		if ( is_allowed_http_origin( $origin ) ) {
			header( 'Access-Control-Allow-Origin: ' . $origin );
			header( 'Access-Control-Allow-Credentials: true' );
			header( 'Access-Control-Allow-Methods: GET, POST, OPTIONS, PUT, DELETE' );
			header( 'Access-Control-Allow-Headers: Access-Control-Allow-Origin, Access-Control-Allow-Credentials, Origin, Authorization, Credentials, Content-Type' );
			http_response_code(200);

			if ( 'OPTIONS' === $request_method ) {
				exit;
			}
		}
	}
}

add_action( 'wp_boilerplate_upload_preflight', 'wp_boilerplate_upload_preflight' );

// By wrapping the template redirect in this action, we can override without url sniffing.
if ( ! function_exists( 'wp_boilerplate_upload_media_upload_template_redirect' ) ) {
	function wp_boilerplate_upload_media_upload_template_redirect() {
		add_action( 'template_redirect', 'wp_boilerplate_upload_media_upload_action' );
	}
}

add_action( 'wp_boilerplate_upload_media_upload_template_redirect', 'wp_boilerplate_upload_media_upload_template_redirect' );

// Helper that will load the template redirect in case of another hook conflict.
if ( ! function_exists( 'wp_boilerplate_upload_action_redirect_helper' ) ) {
	function wp_boilerplate_upload_action_redirect_helper() {
		$url_path = trim( parse_url( add_query_arg( array() ), PHP_URL_PATH ), '/' );

		if ( 0 === stripos( $url_path, 'wp_boilerplate_upload_ajax' ) ) {
			remove_all_actions( 'template_redirect' );
			do_action( 'wp_boilerplate_upload_media_upload_template_redirect' );
		}
	}
}

add_action( 'init', 'wp_boilerplate_upload_action_redirect_helper' );

// Used in ajax. Takes a file and puts it in the media gallery.
if ( ! function_exists( 'wp_boilerplate_upload_media_library_upload' ) ) {
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

			header( 'HTTP/1.1 500 UPLOAD NOT MOVED' );
			echo 0;
			return;
		}

		header( 'HTTP/1.1 403 FORBIDDEN' );
		echo 0;
		return;
	}
}

add_action( 'wp_boilerplate_upload_media_upload', 'wp_boilerplate_upload_media_library_upload', 10, 2 );
