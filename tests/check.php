<?php
/** Standalone fixture for the extracted download policy. */

declare(strict_types=1);

namespace Chaleshino\CallReview;

if ( PHP_SAPI !== 'cli' ) {
	exit( 1 );
}
define( 'ABSPATH', __DIR__ );
const MANAGE = 'chlr_manage_calls';

final class Store {
	public static array $assignments = array();

	public static function assignment( int $entry, int $user ): ?object {
		return self::$assignments[ $entry ][ $user ] ?? null;
	}
}

function is_user_logged_in(): bool {
	return get_current_user_id() > 0;
}

function get_current_user_id(): int {
	return $GLOBALS['fixture_actor']['id'] ?? 0;
}

function current_user_can( string $capability ): bool {
	return in_array( $capability, $GLOBALS['fixture_actor']['caps'] ?? array(), true );
}

require __DIR__ . '/../src/class-file-access.php';

$entry              = (object) array(
	'id'       => 42,
	'owner_id' => 7,
	'status'   => 'approved',
);
$call               = (object) array( 'is_public' => 1 );
Store::$assignments = array( 42 => array( 11 => (object) array( 'judge_id' => 11 ) ) );
$cases              = array(
	array( 'Participant', 7, array( 'read' ), true, 'Own submission' ),
	array( 'Assigned judge', 11, array( 'read' ), true, 'Active assignment' ),
	array( 'Other user', 28, array( 'read' ), false, 'A different account' ),
	array( 'Guest', 0, array(), false, 'No signed-in account' ),
	array( 'Call manager', 18, array( MANAGE ), true, 'Manage submissions' ),
);
$results            = array();
foreach ( $cases as [ $role, $id, $caps, $expected, $reason ] ) {
	$GLOBALS['fixture_actor'] = array(
		'id'   => $id,
		'caps' => $caps,
	);
	$allowed                  = File_Access::can_download( $entry, $call );
	if ( $expected !== $allowed ) {
		throw new \RuntimeException( $role . ' access changed.' );
	}
	$results[] = array( $role, $reason, $allowed );
}
$GLOBALS['fixture_actor'] = array(
	'id'   => 11,
	'caps' => array( 'read' ),
);
unset( Store::$assignments[42][11] );
if ( File_Access::can_download( $entry, $call ) ) {
	throw new \RuntimeException( 'Removing the assignment must end access.' );
}
$results[] = array( 'Former judge', 'Assignment removed', false );

if ( ! in_array( '--html', $argv, true ) ) {
	foreach ( $results as [ $role, $reason, $allowed ] ) {
		echo 'PASS: ' . $role . ' / ' . ( $allowed ? 'allowed' : 'denied' ) . PHP_EOL;
	}
	return;
}
require __DIR__ . '/../examples/access-preview.php';
