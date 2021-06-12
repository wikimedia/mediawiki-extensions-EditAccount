<?php
/**
 * FlagClosedAccounts
 *
 * This code displays a clear indication that an account has been disabled
 * on that user's Special:Contributions page
 *
 * @file
 * @ingroup Extensions
 * @author Łukasz Garczewski (TOR) <tor@wikia-inc.com>
 * @date 2008-01-29
 * @copyright Copyright © 2009 Łukasz Garczewski, Wikia Inc.
 * @license GPL-2.0-or-later
 */

if ( !defined( 'MEDIAWIKI' ) ) {
	echo "Not a valid entry point.\n";
	exit( 1 );
}

define( 'CLOSED_ACCOUNT_FLAG', 'Account Disabled' );

$wgHooks['SpecialContributionsBeforeMainOutput'][] = 'efFlagClosedAccounts';

$wgMessagesDirs['EditAccount'] = __DIR__ . '/i18n';

/**
 * @param int $id
 * @return bool
 */
function efFlagClosedAccounts( $id ) {
	$u = User::newFromId( $id );
	// Quick safeguard, *just* in case...probably not even needed.
	if ( !$u instanceof User ) {
		return true;
	}

	// ShoutWiki patch begin
	// Correctly show the "This account has been disabled" box on wikis other
	// than the central wiki (ShoutWiki Hub)
	// @date 27 October 2013
	// @author Jack Phoenix <jack@shoutwiki.com>
	$isDisabled = EditAccount::isAccountDisabled( $u );
	# $disOpt = $u->getOption( 'disabled' );

	if ( $isDisabled ) {
	/*if ( !empty( $disOpt ) ) {*/
	// ShoutWiki patch end
		global $wgOut;
		$wgOut->wrapWikiMsg(
			"<div class=\"errorbox account-disabled-box\" style=\"padding: 1em;\">\n$1\n</div>",
			'edit-account-closed-flag'
		);
		$wgOut->addHTML( '<br clear="both" />' );
	}

	return true;
}
