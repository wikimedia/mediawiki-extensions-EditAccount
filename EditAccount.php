<?php
/**
 * EditAccount
 *
 * This extension is used by privileged users to manage essential user account
 * information in the case of a lost password and/or invalid e-mail submitted
 * during registration.
 *
 * Jack Phoenix's notes: the @date comment below also indicates from which
 * revision on Wikia SVN this extension was forked from.
 * For ShoutWiki, I changed the code so that users can access the special page
 * Special:CloseAccount to *permanently shut down* their _own_ account.
 *
 * Some i18n changes were also done and the logging system was converted to use
 * the 1.19-style logging system.
 *
 * @file
 * @ingroup Extensions
 * @author Łukasz Garczewski (TOR) <tor@wikia-inc.com>
 * @date 22 March 2013 (https://github.com/Wikia/app/commit/bb114fdb72c3be76594ff8bc46384093733c62e4, save for the i18n changes)
 * @copyright Copyright © 2008 Łukasz Garczewski, Wikia Inc.
 * @license http://www.gnu.org/copyleft/gpl.html GNU General Public License 2.0 or later
 */

if ( !defined( 'MEDIAWIKI' ) ) {
	echo "This is a MediaWiki extension named EditAccount.\n";
	exit( 1 );
}

// Extension credits that will show up on Special:Version
$wgExtensionCredits['specialpage'][] = array(
	'path' => __FILE__,
	'name' => 'EditAccount',
	'version' => '1.3.2',
	'author' => array( 'Łukasz Garczewski', 'Jack Phoenix' ),
	'descriptionmsg' => 'editaccount-desc',
	'url' => 'https://www.mediawiki.org/wiki/Extension:EditAccount'
);

// New user right, required to use the extension.
$wgAvailableRights[] = 'editaccount';
$wgGroupPermissions['*']['editaccount'] = false;
$wgGroupPermissions['staff']['editaccount'] = true;

// Log definition
$wgLogTypes[] = 'editaccnt';
$wgLogActionsHandlers['editaccnt/*'] = 'LogFormatter';
$wgLogRestrictions['editaccnt'] = 'editaccount';

// Set up the new special pages
$wgMessagesDirs['EditAccount'] = __DIR__ . '/i18n';
$wgExtensionMessagesFiles['EditAccountAliases'] = __DIR__ . '/EditAccount.alias.php';

$wgAutoloadClasses['EditAccount'] = __DIR__ . '/SpecialEditAccount_body.php';
$wgSpecialPages['EditAccount'] = 'EditAccount';

$wgAutoloadClasses['CloseAccount'] = __DIR__ . '/SpecialCloseAccount.body.php';
$wgSpecialPages['CloseAccount'] = 'CloseAccount';
