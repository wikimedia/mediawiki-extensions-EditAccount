<?php
/**
 * Migration script for setting the "account disabled" flag in the global_preferences
 * database table (and faking the associated timestamp, too) for users whose
 * real name is "Account Disabled".
 *
 * Requires the GlobalPreferences extension (just like the ShoutWiki patches to EditAccount do, too).
 *
 * See FlagClosedAccounts.php.
 *
 * @file
 * @ingroup Maintenance
 * @author Jack Phoenix <jack@shoutwiki.com>
 * @date 11 April 2014
 */

/**
 * Set the correct include path for PHP so that we can run this script from
 * $IP/extensions/EditAccount and we don't need to move this file to
 * $IP/maintenance/.
 */
ini_set( 'include_path', __DIR__ . '/../../../maintenance' );

require_once 'Maintenance.php';

class AddEntriesForAllDisabledUsers extends Maintenance {
	public function __construct() {
		parent::__construct();
		$this->mDescription = 'Updates global preferences for accounts that have their real name set to "Account Disabled".';
		$this->addOption( 'doit', 'Actually perform the database updates, too, instead of doing a dry run?' );

		$this->requireExtension( 'EditAccount' );
	}

	public function execute() {
		$dbw = wfGetDB( DB_PRIMARY );
		$res = $dbw->select(
			'user',
			[ 'user_id', 'user_name' ],
			[ 'user_real_name' => 'Account Disabled' ],
			__METHOD__
		);

		if ( $res->numRows() === 0 ) {
			// @phan-suppress-next-line PhanTypeMismatchArgument
			$this->error( 'Nothing to do...', true );
		} else {
			$this->output( 'Got ' . $res->numRows() . " accounts to mark as disabled\n" );

			$dbw = GlobalPreferences::getPrefsDB( DB_PRIMARY );
			foreach ( $res as $row ) {
				// Are we in it for real?
				if ( $this->getOption( 'doit' ) ) {
					$res = $dbw->update(
						'global_preferences',
						[
							'gp_property' => 'disabled',
							'gp_value' => 1
						],
						[
							'gp_user' => $row->user_id
						],
						__METHOD__
					);

					$res = $dbw->update(
						'global_preferences',
						[
							'gp_property' => 'disabled_date',
							'gp_value' => wfTimestamp( TS_DB )
						],
						[
							'gp_user' => $row->user_id
						],
						__METHOD__
					);
				}

				$this->output( "Marked {$row->user_name} (UID: {$row->user_id}) as disabled\n" );
			}
		}

		$this->output( "All done!\n" );
	}
}

$maintClass = 'AddEntriesForAllDisabledUsers';
require_once RUN_MAINTENANCE_IF_MAIN;
