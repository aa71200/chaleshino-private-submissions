<?php
/** Selected download authorization methods from Chaleshino Call Review. */
namespace Chaleshino\CallReview;

defined( 'ABSPATH' ) || exit;

final class File_Access {
	public static function can_judge( object $entry, object $call ): bool {
		return is_user_logged_in()
			&& 'approved' === $entry->status
			&& (bool) $call->is_public
			&& get_current_user_id() !== (int) $entry->owner_id
			&& current_user_can( 'read' )
			&& null !== Store::assignment( (int) $entry->id, get_current_user_id() );
	}

	public static function can_download( object $entry, object $call ): bool {
		return is_user_logged_in() && (
			current_user_can( MANAGE ) || get_current_user_id() === (int) $entry->owner_id || self::can_judge( $entry, $call )
		);
	}
}
