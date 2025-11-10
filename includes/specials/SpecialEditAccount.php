<?php
/**
 * Main logic of the EditAccount extension
 *
 * @file
 * @ingroup Extensions
 * @author Łukasz Garczewski (TOR) <tor@wikia-inc.com>
 * @date 2008-09-17
 * @copyright Copyright © 2008 Łukasz Garczewski, Wikia Inc.
 * @license GPL-2.0-or-later
 */

use MediaWiki\Linker\LinkRenderer;
use MediaWiki\User\UserFactory;
use MediaWiki\User\UserIdentityLookup;
use MediaWiki\User\UserNameUtils;
use MediaWiki\User\UserOptionsManager;
use Wikimedia\Rdbms\ILoadBalancer;

class EditAccount extends SpecialPage {

	/** @var User|null */
	public ?User $mUser = null;
	/** @var bool|null */
	public ?bool $mStatus = null;
	/** @var string|null */
	public ?string $mStatusMsg = null;
	/** @var string|null */
	public ?string $mStatusMsg2 = null;
	/** @var User|null */
	public ?User $mTempUser = null;

	private ILoadBalancer $lb;
	private LinkRenderer $linkRenderer;
	private PasswordFactory $passwordFactory;
	private UserFactory $userFactory;
	private UserIdentityLookup $userIdentityLookup;
	private UserNameUtils $userNameUtils;
	private UserOptionsManager $userOptionsManager;

	public function __construct(
		ILoadBalancer $lb,
		LinkRenderer $linkRenderer,
		PasswordFactory $passwordFactory,
		UserFactory $userFactory,
		UserIdentityLookup $userIdentityLookup,
		UserNameUtils $userNameUtils,
		UserOptionsManager $userOptionsManager
	) {
		parent::__construct( 'EditAccount', 'editaccount' );
		$this->lb = $lb;
		$this->linkRenderer = $linkRenderer;
		$this->passwordFactory = $passwordFactory;
		$this->userFactory = $userFactory;
		$this->userIdentityLookup = $userIdentityLookup;
		$this->userNameUtils = $userNameUtils;
		$this->userOptionsManager = $userOptionsManager;
	}

	public function doesWrites(): bool {
		return true;
	}

	/**
	 * Group this special page under the correct header in Special:SpecialPages.
	 *
	 * @return string
	 */
	public function getGroupName(): string {
		return 'users';
	}

	/**
	 * Special page description shown on Special:SpecialPages -- different for
	 * privileged users and mortals
	 *
	 * @return Message Special page description
	 */
	public function getDescription(): Message {
		if ( $this->getUser()->isAllowed( 'editaccount' ) ) {
			return $this->msg( 'editaccount' );
		} else {
			return $this->msg( 'editaccount-general-description' );
		}
	}

	/**
	 * Show the special page
	 *
	 * @param string|null $subPage Parameter (user name) passed to the page or null
	 */
	public function execute( $subPage ) {
		$out = $this->getOutput();
		$request = $this->getRequest();
		$user = $this->getUser();

		// Redirect mortals to Special:CloseAccount
		if ( !$user->isAllowed( 'editaccount' ) ) {
			// throw new PermissionsError( 'editaccount' );
			$out->redirect( SpecialPage::getTitleFor( 'CloseAccount' )->getFullURL() );
		}

		// Show a message if the database is in read-only mode
		$this->checkReadOnly();

		// If user is blocked, s/he doesn't need to access this page
		if ( $user->getBlock() ) {
			// @phan-suppress-next-line PhanTypeMismatchArgumentNullable
			throw new UserBlockedError( $user->getBlock() );
		}

		// Set page title and other stuff
		$this->setHeaders();

		// Special:EditAccount is a fairly stupid page title
		$out->setPageTitleMsg( $this->getDescription() );

		// Get name to work on. Subpage is supported, but form submit name trumps
		$userName = $request->getVal( 'wpUserName', $subPage );
		$action = $request->getVal( 'wpAction' );

		if ( $userName !== null ) {
			// Got a name, clean it up
			$userName = str_replace( '_', ' ', trim( $userName ) );
			// User names begin with a capital letter
			$userName = $this->getLanguage()->ucfirst( $userName );

			// Check if user name is an existing user
			if ( $this->userNameUtils->isValid( $userName ) ) {
				$this->mUser = $this->userFactory->newFromName( $userName );
				$actor = $this->userIdentityLookup->getUserIdentityByName( $userName );
				$id = $actor ? $actor->getId() : null;

				if ( !$action ) {
					$action = 'displayuser';
				}

				if ( !$id ) {
					// Wikia stuff...
					if ( class_exists( 'TempUser' ) ) {
						$this->mTempUser = TempUser::getTempUserFromName( $userName );
					}

					if ( $this->mTempUser ) {
						$id = $this->mTempUser->getId();
						$this->mUser = $this->userFactory->newFromId( $id );
					} else {
						$this->mStatus = false;
						$this->mStatusMsg = $this->msg( 'editaccount-nouser', $userName )->text();
						$action = '';
					}
				}
			}
		}

		// FB:23860
		if ( !$this->mUser ) {
			$action = '';
		}

		$changeReason = $request->getVal( 'wpReason' );

		// What to do, what to show? Hmm...
		switch ( $action ) {
			case 'setemail':
				$newEmail = $request->getVal( 'wpNewEmail' );
				$this->mStatus = $this->setEmail( $newEmail, $changeReason );
				$template = 'DisplayUser';
				break;
			case 'setpass':
				$newPass = $request->getVal( 'wpNewPass' );
				$this->mStatus = $this->setPassword( $newPass, $changeReason );
				$template = 'DisplayUser';
				break;
			case 'setrealname':
				$newRealName = $request->getVal( 'wpNewRealName' );
				$this->mStatus = $this->setRealName( $newRealName, $changeReason );
				$template = 'DisplayUser';
				break;
			case 'closeaccount':
				$template = 'CloseAccount';
				$this->mStatus = (bool)$this->userOptionsManager->getOption( $this->mUser, 'requested-closure', 0 );
				if ( $this->mStatus ) {
					$this->mStatusMsg = $this->msg( 'editaccount-requested' )->text();
				} else {
					$this->mStatusMsg = $this->msg( 'editaccount-not-requested' )->text();
				}
				break;
			case 'closeaccountconfirm':
				$this->mStatus = $this->closeAccount( $changeReason );
				$template = $this->mStatus ? 'SelectUser' : 'DisplayUser';
				break;
			case 'clearunsub':
				$this->mStatus = $this->clearUnsubscribe();
				$template = 'DisplayUser';
				break;
			case 'cleardisable':
				$this->mStatus = $this->clearDisable();
				$template = 'DisplayUser';
				break;
			case 'toggleadopter':
				$this->mStatus = $this->toggleAdopterStatus();
				$template = 'DisplayUser';
				break;
			case 'displayuser':
				$template = 'DisplayUser';
				break;
			default:
				$template = 'SelectUser';
		}

		// Load the correct template file, build the class name and initiate a
		// new template object (so that we can set variables later on)
		include __DIR__ . '/../../templates/' . strtolower( $template ) . '.tmpl.php';
		$templateClassName = 'EditAccount' . $template . 'Template';
		$tmpl = new $templateClassName;

		$templateVariables = [
			'status' => $this->mStatus,
			'statusMsg' => $this->mStatusMsg,
			'statusMsg2' => $this->mStatusMsg2,
			'user' => $userName,
			'userEmail' => null,
			'userRealName' => null,
			'userEncoded' => urlencode( $userName ),
			'user_hsc' => htmlspecialchars( $userName ),
			'userId' => null,
			'userReg' => null,
			'isUnsub' => null,
			'isDisabled' => null,
			'isAdopter' => null,
			'returnURL' => $this->getFullTitle()->getFullURL(),
			'logLink' => $this->linkRenderer->makeLink(
				SpecialPage::getTitleFor( 'Log', 'editaccnt' ),
				$this->msg( 'log-name-editaccnt' )->text()
			),
			'userStatus' => null,
			'emailStatus' => null,
			'disabled' => null,
			'changeEmailRequested' => null,
		];
		foreach ( $templateVariables as $templateVariable => $variableValue ) {
			$tmpl->set( $templateVariable, $variableValue );
		}

		if ( $this->mUser ) {
			if ( $this->mTempUser ) {
				$this->mUser = $this->mTempUser->mapTempUserToUser( false );
				$userStatus = $this->msg( 'editaccount-status-tempuser' )->plain();
				$tmpl->set( 'disabled', 'disabled="disabled"' );
			} else {
				$userStatus = $this->msg( 'editaccount-status-realuser' )->plain();
			}
			$this->mUser->load();

			// get new e-mail (unconfirmed)
			$optionNewEmail = $this->userOptionsManager->getOption( $this->mUser, 'new_email' );
			if ( !$optionNewEmail ) {
				$changeEmailRequested = '';
			} else {
				$changeEmailRequested = $this->msg( 'editaccount-email-change-requested', $optionNewEmail )->parse();
			}

			// emailStatus is the status of the e-mail in the "Set new email address" field
			if ( $this->mUser->isEmailConfirmed() ) {
				$emailStatus = $this->msg( 'editaccount-status-confirmed' )->plain();
			} else {
				$emailStatus = $this->msg( 'editaccount-status-unconfirmed' )->plain();
			}

			$templateVariables2 = [
				'userEmail' => $this->mUser->getEmail(),
				'userRealName' => $this->mUser->getRealName(),
				'userId' => $this->mUser->getId(),
				'userReg' => date( 'r', strtotime( $this->mUser->getRegistration() ) ),
				'isUnsub' => $this->userOptionsManager->getOption( $this->mUser, 'unsubscribed' ),
				'isDisabled' => $this->userOptionsManager->getOption( $this->mUser, 'disabled' ),
				'isAdopter' => $this->userOptionsManager->getOption( $this->mUser, 'AllowAdoption', 1 ),
				'userStatus' => $userStatus,
				'emailStatus' => $emailStatus,
				'changeEmailRequested' => $changeEmailRequested,
			];
			// This will overwrite the previous variables which are null
			foreach ( $templateVariables2 as $templateVariable2 => $variableValue2 ) {
				$tmpl->set( $templateVariable2, $variableValue2 );
			}
		}

		// HTML output
		$out->addTemplate( $tmpl );
	}

	/**
	 * Set a user's e-mail
	 *
	 * @param string $email E-mail address to set to the user
	 * @param string $changeReason Reason for change
	 * @return bool True on success, false on failure (i.e. if we were given an invalid email address)
	 */
	public function setEmail( string $email, string $changeReason = '' ): bool {
		if ( Sanitizer::validateEmail( $email ) || $email == '' ) {
			if ( $this->mTempUser ) {
				if ( $email == '' ) {
					$this->mStatusMsg = $this->msg( 'editaccount-error-tempuser-email' )->text();
					return false;
				} else {
					$this->mTempUser->setEmail( $email );
					$this->mUser = $this->mTempUser->activateUser( $this->mUser );

					// reset temp user after activating the user
					$this->mTempUser = null;
				}
			} else {
				$this->mUser->setEmail( $email );
				if ( $email != '' ) {
					$this->mUser->confirmEmail();
					$this->userOptionsManager->setOption( $this->mUser, 'new_email', null );
				} else {
					$this->mUser->invalidateEmail();
				}
				$this->mUser->saveSettings();
			}

			// Check if everything went through OK, just in case
			if ( $this->mUser->getEmail() == $email ) {
				// Log the change
				$logEntry = new ManualLogEntry( 'editaccnt', 'mailchange' );
				$logEntry->setPerformer( $this->getUser() );
				$logEntry->setTarget( $this->mUser->getUserPage() );
				// JP 13 April 2013: not sure if this is the correct one, CHECKME
				$logEntry->setComment( $changeReason );
				$logEntry->insert();

				if ( $email == '' ) {
					$this->mStatusMsg = $this->msg( 'editaccount-success-email-blank', $this->mUser->mName )->text();
				} else {
					$this->mStatusMsg = $this->msg( 'editaccount-success-email', $this->mUser->mName, $email )->text();
				}
				return true;
			} else {
				$this->mStatusMsg = $this->msg( 'editaccount-error-email', $this->mUser->mName )->text();
				return false;
			}
		} else {
			$this->mStatusMsg = $this->msg( 'editaccount-invalid-email', $email )->text();
			return false;
		}
	}

	/**
	 * Set a user's password.
	 *
	 * @param mixed $pass Password to set to the user
	 * @param string $changeReason Reason for change
	 * @return bool True on success, false on failure
	 */
	public function setPassword( $pass, string $changeReason = '' ): bool {
		if ( $this->setPasswordForUser( $this->mUser, $pass ) ) {
			// Save the new settings
			if ( $this->mTempUser ) {
				$this->setPasswordForUser( $this->mTempUser, $pass );
				$this->mTempUser->updateData();
				$this->mTempUser->saveSettingsTempUserToUser( $this->mUser );
				$this->mUser->mName = $this->mTempUser->getName();
			} else {
				$this->mUser->saveSettings();
			}

			// Log what was done
			$logEntry = new ManualLogEntry( 'editaccnt', 'passchange' );
			$logEntry->setPerformer( $this->getUser() );
			$logEntry->setTarget( $this->mUser->getUserPage() );
			// JP 13 April 2013: not sure if this is the correct one, CHECKME
			$logEntry->setComment( $changeReason );
			$logEntry->insert();

			// And finally, inform the user that everything went as planned
			$this->mStatusMsg = $this->msg( 'editaccount-success-pass', $this->mUser->mName )->text();
			return true;
		} else {
			// We have errors, let's inform the user about those
			$this->mStatusMsg = $this->msg( 'editaccount-error-pass', $this->mUser->mName )->text();
			return false;
		}
	}

	/**
	 * Set the password on a user
	 *
	 * @param User $user
	 * @param string $password
	 * @return bool
	 */
	public function setPasswordForUser( User $user, string $password ): bool {
		if ( !$user->getId() ) {
			return false;
			// throw new MWException( "Passed User has not been added to the database yet!" );
		}

		$dbw = $this->lb->getMaintenanceConnectionRef( DB_PRIMARY );
		$row = $dbw->selectRow(
			'user',
			'user_id',
			[ 'user_id' => $user->getId() ],
			__METHOD__
		);
		if ( !$row ) {
			return false;
			// throw new MWException( "Passed User has an ID but is not in the database?" );
		}

		$passwordHash = $this->passwordFactory->newFromPlaintext( $password );
		$dbw->update(
			'user',
			[ 'user_password' => $passwordHash->toString() ],
			[ 'user_id' => $user->getId() ],
			__METHOD__
		);

		return true;
	}

	/**
	 * Set a user's real name.
	 *
	 * @param mixed $realName Real name to set to the user
	 * @param string $changeReason Reason for change
	 * @return bool True on success, false on failure
	 */
	public function setRealName( $realName, string $changeReason = '' ): bool {
		$this->mUser->setRealName( $realName );
		$this->mUser->saveSettings();

		// Was the change saved successfully? The setRealName function doesn't
		// return a boolean value...
		if ( $this->mUser->getRealName() == $realName ) {
			// Log what was done
			$logEntry = new ManualLogEntry( 'editaccnt', 'realnamechange' );
			$logEntry->setPerformer( $this->getUser() );
			$logEntry->setTarget( $this->mUser->getUserPage() );
			// JP 13 April 2013: not sure if this is the correct one, CHECKME
			$logEntry->setComment( $changeReason );
			$logEntry->insert();

			// And finally, inform the user that everything went as planned
			$this->mStatusMsg = $this->msg( 'editaccount-success-realname', $this->mUser->mName )->text();
			return true;
		} else {
			// We have errors, let's inform the user about those
			$this->mStatusMsg = $this->msg( 'editaccount-error-realname', $this->mUser->mName )->text();
			return false;
		}
	}

	/**
	 * Scrambles the user's password, sets an empty e-mail and marks the
	 * account as disabled
	 *
	 * @param string $changeReason Reason for change
	 * @return bool True on success, false on failure
	 */
	public function closeAccount( string $changeReason = '' ): bool {
		// Set flag for Special:Contributions
		// NOTE: requires FlagClosedAccounts.php to be included separately
		if ( defined( 'CLOSED_ACCOUNT_FLAG' ) ) {
			// @phan-suppress-next-line PhanTypeMismatchArgumentNullable
			$this->mUser->setRealName( CLOSED_ACCOUNT_FLAG );
		} else {
			// magic value not found, so let's at least blank it
			$this->mUser->setRealName( '' );
		}

		if ( class_exists( 'Masthead' ) ) {
			// Wikia's avatar extension
			$avatar = Masthead::newFromUser( $this->mUser );
			if ( !$avatar->isDefault() ) {
				if ( !$avatar->removeFile( false ) ) {
					// don't quit here, since the avatar is a non-critical part
					// of closing, but flag for later
					$this->mStatusMsg2 = $this->msg( 'editaccount-remove-avatar-fail' )->plain();
				}
			}
		}

		// Remove e-mail address and password
		$this->mUser->setEmail( '' );
		$newPass = $this->generateRandomScrambledPassword();
		$this->setPasswordForUser( $this->mUser, $newPass );

		// Save the new settings
		$this->mUser->saveSettings();

		$id = $this->mUser->getId();

		// Reload user
		$this->mUser = $this->userFactory->newFromId( $id );

		if ( $this->mUser->getEmail() == '' ) {
			// ShoutWiki patch begin
			$this->setDisabled();
			// ShoutWiki patch end
			// Mark as disabled in a more real way, that doesn't depend on the real_name text
			$this->userOptionsManager->setOption( $this->mUser, 'disabled', 1 );
			$this->userOptionsManager->setOption( $this->mUser, 'disabled_date', wfTimestamp( TS_DB ) );
			// BugId:18085 - setting a new token causes the user to be logged out.
			$this->mUser->setToken( md5( microtime() . mt_rand( 0, 0x7fffffff ) ) );

			// BugID:95369 This forces saveSettings() to commit the transaction
			// FIXME: this is a total hack, we should add a commit=true flag to saveSettings
			$this->getRequest()->setVal( 'action', 'ajax' );

			// Need to save these additional changes
			$this->mUser->saveSettings();

			// Log what was done
			$logEntry = new ManualLogEntry( 'editaccnt', 'closeaccnt' );
			$logEntry->setPerformer( $this->getUser() );
			$logEntry->setTarget( $this->mUser->getUserPage() );
			// JP 13 April 2013: not sure if this is the correct one, CHECKME
			$logEntry->setComment( $changeReason );
			$logEntry->insert();

			// All clear!
			$this->mStatusMsg = $this->msg( 'editaccount-success-close', $this->mUser->mName )->text();
			return true;
		} else {
			// There were errors...inform the user about those
			$this->mStatusMsg = $this->msg( 'editaccount-error-close', $this->mUser->mName )->text();
			return false;
		}
	}

	/**
	 * Clears the magic unsub bit
	 *
	 * @return bool Always true
	 */
	public function clearUnsubscribe(): bool {
		$this->userOptionsManager->setOption( $this->mUser, 'unsubscribed', null );
		$this->userOptionsManager->saveOptions( $this->mUser );

		$this->mStatusMsg = $this->msg( 'editaccount-success-unsub', $this->mUser->mName )->text();

		return true;
	}

	/**
	 * Clears the magic disabled bit
	 *
	 * @return bool Always true
	 */
	public function clearDisable(): bool {
		$this->userOptionsManager->setOption( $this->mUser, 'disabled', null );
		$this->userOptionsManager->setOption( $this->mUser, 'disabled_date', null );
		$this->userOptionsManager->saveOptions( $this->mUser );

		// ShoutWiki patch begin
		// We also need to clear GlobalPreferences data; otherwise it's possible
		// (though unlikely) that a staff member reactivates a disabled account
		// but the "this account has been disabled" notice on Special:Contributions
		// won't go away.
		if ( class_exists( 'GlobalPreferences' ) ) {
			$dbw = GlobalPreferences::getPrefsDB( DB_PRIMARY );

			$dbw->startAtomic( __METHOD__ );
			$dbw->delete(
				'global_preferences',
				[
					'gp_property' => 'disabled',
					'gp_value' => 1,
					'gp_user' => $this->mUser->getId()
				],
				__METHOD__
			);
			$dbw->delete(
				'global_preferences',
				[
					'gp_property' => 'disabled_date',
					'gp_user' => $this->mUser->getId()
				],
				__METHOD__
			);
			$dbw->endAtomic( __METHOD__ );
		}
		// ShoutWiki patch end

		$this->mStatusMsg = $this->msg( 'editaccount-success-disable', $this->mUser->mName )->text();

		return true;
	}

	/**
	 * Set the adoption status (i.e. is the user who is being edited allowed to
	 * automatically adopt wikis or not).
	 *
	 * @return bool Always true
	 */
	public function toggleAdopterStatus(): bool {
		$this->userOptionsManager->setOption(
			$this->mUser,
			'AllowAdoption',
			(int)!$this->userOptionsManager->getOption( $this->mUser, 'AllowAdoption', 1 )
		);
		$this->userOptionsManager->saveOptions( $this->mUser );

		$this->mStatusMsg = $this->msg( 'editaccount-success-toggleadopt', $this->mUser->mName )->text();

		return true;
	}

	/**
	 * Returns a random password which conforms to our password requirements
	 * and is not easily guessable.
	 *
	 * @return string
	 */
	public function generateRandomScrambledPassword(): string {
		// Password requirements need a capital letter, a digit, and a lowercase letter.
		// wfGenerateToken() returns a 32 char hex string, which will almost
		// always satisfy the digit/letter but not always.
		// This suffix shouldn't reduce the entropy of the intentionally
		// scrambled password.
		$REQUIRED_CHARS = 'A1a';
		return ( self::generateToken() . $REQUIRED_CHARS );
	}

	/**
	 * Marks the account as disabled, the ShoutWiki way.
	 */
	public function setDisabled() {
		if ( !class_exists( 'GlobalPreferences' ) ) {
			error_log( 'Cannot use the GlobalPreferences class in ' . __METHOD__ );
			return;
		}
		$dbw = GlobalPreferences::getPrefsDB( DB_PRIMARY );

		$dbw->startAtomic( __METHOD__ );
		$dbw->insert(
			'global_preferences',
			[
				'gp_property' => 'disabled',
				'gp_value' => 1,
				'gp_user' => $this->mUser->getId()
			],
			__METHOD__
		);
		$dbw->insert(
			'global_preferences',
			[
				'gp_property' => 'disabled_date',
				'gp_value' => wfTimestamp( TS_DB ),
				'gp_user' => $this->mUser->getId()
			],
			__METHOD__
		);
		$dbw->endAtomic( __METHOD__ );
	}

	/**
	 * Is the given user account disabled?
	 *
	 * @param User $user
	 * @return bool|void True if it is disabled, otherwise false
	 */
	public static function isAccountDisabled( User $user ) {
		if ( !class_exists( 'GlobalPreferences' ) ) {
			error_log( 'Cannot use the GlobalPreferences class in ' . __METHOD__ );
			return;
		}
		$dbr = GlobalPreferences::getPrefsDB();
		$retVal = $dbr->selectField(
			'global_preferences',
			'gp_value',
			[
				'gp_property' => 'disabled',
				'gp_user' => $user->getId()
			],
			__METHOD__
		);

		return (bool)$retVal;
	}

	/**
	 * Copypasta from pre-1.23 /includes/GlobalFunctions.php
	 * @see https://phabricator.wikimedia.org/rMW118567a4ba0ded669f43a58713733cab915afe39
	 *
	 * @param string $salt
	 * @return string
	 */
	public static function generateToken( string $salt = '' ): string {
		$salt = serialize( $salt );
		return md5( mt_rand( 0, 0x7fffffff ) . $salt );
	}
}
