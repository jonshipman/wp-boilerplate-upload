<?php

/**
 * Provides a way to access files behind a post by way of a jwt.
 * Save the content path to a post_meta and load the contents
 * via the wp_boilerplate_upload_asset_token_content action.
 */

// Generate content asset tokens.
if ( ! function_exists( 'wp_boilerplate_upload_jwt_generate_asset_token' ) ) {
	function wp_boilerplate_upload_jwt_generate_asset_token( $asset_id, $type, $extra_meta = 0 ) {
		$user = wp_get_current_user();

		$token = array(
			'iss'  => get_bloginfo( 'url' ),
			'iat'  => time(),
			'nbf'  => time() - 10,
			'exp'  => apply_filters( 'wp_boilerplate_upload_asset_token_expiration', time() + 3600 ),
			'data' => array(
				'asset' => array(
					'asset_id'   => $asset_id,
					'type'       => $type,
					'extra_meta' => $extra_meta,
					'user_id'    => $user ? $user->ID : 0,
				),
			),
		);

		Firebase\JWT\JWT::$leeway = 60;

		$token = Firebase\JWT\JWT::encode( $token, WPGraphQL\JWT_Authentication\Auth::get_secret_key() );

		return $token;
	}
}

// Decode the asset token.
if ( ! function_exists( 'wp_boilerplate_upload_jwt_decode_asset_token' ) ) {
	function wp_boilerplate_upload_jwt_decode_asset_token( $token ) {
		Firebase\JWT\JWT::$leeway = 60;

		$secret = WPGraphQL\JWT_Authentication\Auth::get_secret_key();

		try {
			$token = ! empty( $token ) ? Firebase\JWT\JWT::decode( $token, $secret, array( 'HS256' ) ) : null;
		} catch ( \Exception $exception ) {
			$token = new \WP_Error( 'invalid-secret-key', $exception->getMessage() );
		}

		if ( ! empty( $token ) && ! is_wp_error( $token ) ) {
			if ( get_bloginfo( 'url' ) === $token->iss ) {
				return $token->data->asset;
			}
		}

		return null;
	}
}

// Template redirect wrapper for decoding asset jwts.
if ( ! function_exists( 'wp_boilerplate_upload_template_redirect' ) ) {
	function wp_boilerplate_upload_template_redirect() {
		global $wp_query;

		$url_path = trim( parse_url( add_query_arg( array() ), PHP_URL_PATH ), '/' );

		if ( 0 === stripos( $url_path, 'asset/' ) ) {
			$parts        = explode( '/', rtrim( $url_path, '/' ) );
			$token_string = end( $parts );

			// Decode the token passed.
			$token = apply_filters( 'wp_boilerplate_upload_asset_token', array(), $token_string );

			if ( ! empty( $token ) ) {
				list( $asset_id, $type, $extra_meta ) = $token;

				// Token is not blocked. Proceed to run asset actions.
				do_action( 'wp_boilerplate_upload_asset_token_content', $asset_id, $type, $token_string, $extra_meta );
			} else {
				$wp_query->is_404 = false;
				header( 'HTTP/1.1 403 FORBIDDEN' );
			}

			die;
		}
	}
}

add_action( 'template_redirect', 'wp_boilerplate_upload_template_redirect' );

// Filter to decode the token, set headers, and set the current user.
if ( ! function_exists( 'wp_boilerplate_upload_asset_token_function' ) ) {
	function wp_boilerplate_upload_asset_token_function( $token, $token_string ) {
		if ( $token_string ) {
			$token = wp_boilerplate_upload_jwt_decode_asset_token( $token_string );

			if ( $token ) {
				$asset_id   = $token->asset_id;
				$type       = $token->type;
				$extra_meta = $token->extra_meta;
				$user_id    = $token->user_id;

				$blocked_tokens = get_user_meta( $user_id, 'blocked_asset_token' );
				if ( ! in_array( $token_string, $blocked_tokens, true ) ) {

					// To remain secure, one should only use this filter in reading data.
					wp_set_current_user( $user_id );

					// Add in expiration headers.
					if ( apply_filters( 'wp_boilerplate_upload_asset_expiry_headers', true ) ) {
						header( 'Expires: ' . gmdate( 'D, d M Y H:i:s \G\M\T', time() + 3600 ) );
						header( 'Cache-Control: private' );
					}

					return array(
						$asset_id,
						$type,
						$extra_meta,
						$user_id,
					);
				}
			}
		}

		return $token;
	}
}

add_filter( 'wp_boilerplate_upload_asset_token', 'wp_boilerplate_upload_asset_token_function', 10, 2 );
