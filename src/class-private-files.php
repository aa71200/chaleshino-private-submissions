<?php

namespace Chaleshino\CallReview;

use finfo;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Adapted from Chaleshino 1.0.0 build 20261028.1, Chaleshino_Media.
 * GPL-2.0-or-later. Files are owned by entry rows and never registered as attachments.
 */
final class Private_Files {
	const MIMES = array(
		'jpg|jpeg|jpe' => 'image/jpeg',
		'png'          => 'image/png',
		'pdf'          => 'application/pdf',
	);

	public static function limit(): int {
		return min( 10 * MB_IN_BYTES, wp_max_upload_size() );
	}

	private static function normalized( string $path ): string {
		$path = untrailingslashit( wp_normalize_path( $path ) );
		return '\\' === DIRECTORY_SEPARATOR ? strtolower( $path ) : $path;
	}

	private static function contains( string $root, string $path ): bool {
		return str_starts_with( self::normalized( $path ) . '/', self::normalized( $root ) . '/' );
	}

	private static function absolute( string $path ): bool {
		$path = wp_normalize_path( $path );
		return '\\' === DIRECTORY_SEPARATOR ? (bool) preg_match( '#^[a-zA-Z]:/#', $path ) : str_starts_with( $path, '/' );
	}

	public static function directory( bool $create = false ): string|WP_Error {
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Server configuration, not HTTP input. Preserve path bytes for canonical resolution.
		$server_root   = isset( $_SERVER['DOCUMENT_ROOT'] ) ? wp_unslash( $_SERVER['DOCUMENT_ROOT'] ) : '';
		$document_root = self::absolute( $server_root ) ? realpath( $server_root ) : false;
		$wordpress     = realpath( ABSPATH );
		$id            = get_option( 'chlr_storage_id' );
		$error         = new WP_Error( 'chlr_storage', __( 'فضای نوشتنی فایل‌های خصوصی خارج از مسیر عمومی سایت آماده نیست.', 'chaleshino-call-review' ), array( 'status' => 503 ) );
		if ( ! $document_root || ! $wordpress || ! is_string( $id ) || ! wp_is_uuid( $id ) || ! class_exists( finfo::class ) ) {
			return $error;
		}
		$base = defined( 'CHLR_PRIVATE_DIR' ) ? CHLR_PRIVATE_DIR : dirname( $document_root ) . '/chaleshino-call-review-private';
		if ( ! is_string( $base ) || ! self::absolute( $base ) ) {
			return $error;
		}

		// Resolve the parent before creating anything, including for a custom path.
		$base   = untrailingslashit( $base );
		$parent = realpath( dirname( $base ) );
		if ( ! $parent ) {
			return $error;
		}
		$expected_base = trailingslashit( $parent ) . basename( $base );
		if ( self::contains( $document_root, $expected_base ) || self::contains( $wordpress, $expected_base ) ) {
			return $error;
		}
		if ( $create && ! is_dir( $expected_base ) && ! wp_mkdir_p( $expected_base ) ) {
			return $error;
		}
		$actual_base = realpath( $expected_base );
		if ( ! $actual_base || self::normalized( $actual_base ) !== self::normalized( $expected_base ) ) {
			return $error;
		}
		$site = trailingslashit( $actual_base ) . 'site-' . $id;
		if ( $create && ! is_dir( $site ) && ! wp_mkdir_p( $site ) ) {
			return $error;
		}
		$directory = realpath( $site );
		if ( ! $directory || self::normalized( $directory ) !== self::normalized( $site ) || ! is_dir( $directory ) || ( $create && ! wp_is_writable( $directory ) ) ) {
			return $error;
		}
		return $directory;
	}

	public static function receive( array $upload ): array|WP_Error {
		$error = new WP_Error( 'chlr_file_type', __( 'یک فایل سالم PDF، JPG یا PNG در محدودهٔ حجم مجاز انتخاب کنید.', 'chaleshino-call-review' ), array( 'status' => 400 ) );
		$tmp   = $upload['tmp_name'] ?? null;
		$name  = $upload['name'] ?? null;
		if ( UPLOAD_ERR_OK !== ( $upload['error'] ?? null ) || ! is_string( $name ) || ! is_string( $tmp ) || ! is_uploaded_file( $tmp ) ) {
			return $error;
		}
		$size = filesize( $tmp );
		if ( ! $size || $size > self::limit() ) {
			return $error;
		}
		$directory = self::directory( true );
		if ( is_wp_error( $directory ) ) {
			return $directory;
		}
		$checked = wp_check_filetype_and_ext( $tmp, $name, self::MIMES );
		$mime    = ( new finfo( FILEINFO_MIME_TYPE ) )->file( $tmp );
		if ( ! in_array( $mime, self::MIMES, true ) || $checked['type'] !== $mime ) {
			return $error;
		}
		$key  = bin2hex( random_bytes( 24 ) ) . '.' . sanitize_key( $checked['ext'] );
		$path = trailingslashit( $directory ) . $key;
		// Direct move avoids a transient file in the public uploads directory.
		if ( ! move_uploaded_file( $tmp, $path ) ) { // phpcs:ignore Generic.PHP.ForbiddenFunctions.Found -- Move a verified HTTP upload directly into private storage.
			return new WP_Error( 'chlr_file_write', __( 'فایل خصوصی ذخیره نشد. دوباره تلاش کنید.', 'chaleshino-call-review' ), array( 'status' => 503 ) );
		}
		if ( '\\' !== DIRECTORY_SEPARATOR ) {
			chmod( $path, 0640 ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_chmod -- Restrict the verified canonical path without an FTP fallback.
		}
		return array(
			'file_key'   => $key,
			'file_mime'  => $mime,
			'file_bytes' => $size,
		);
	}

	public static function path( string $key ): string|false {
		$directory = self::directory();
		if ( is_wp_error( $directory ) || ! preg_match( '/\A[a-f0-9]{48}\.(?:jpg|jpeg|jpe|png|pdf)\z/', $key ) ) {
			return false;
		}
		$expected = trailingslashit( $directory ) . $key;
		$path     = realpath( $expected );
		return $path && self::normalized( $expected ) === self::normalized( $path ) && is_file( $path ) ? $path : false;
	}

	public static function discard( string $key ): void {
		$path = self::path( $key );
		if ( $path ) {
			wp_delete_file( $path );
		}
	}
}
