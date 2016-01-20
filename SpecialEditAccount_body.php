<?php
/**
 * Main logic of the EditAccount extension
 *
 * @file
 * @ingroup Extensions
 * @author Łukasz Garczewski (TOR) <tor@wikia-inc.com>
 * @date 2008-09-17
 * @copyright Copyright © 2008 Łukasz Garczewski, Wikia Inc.
 * @license http://www.gnu.org/copyleft/gpl.html GNU General Public License 2.0 or later
 */

class EditAccount extends SpecialPage {
	public $mUser = null;
	public $mStatus = null;
	public $mStatusMsg;
	public $mStatusMsg2 = null;
	public $mTempUser = null;

	/**
	 * Constructor -- set up the new special page
	 */
	public function __construct() {
		parent::__construct( 'EditAccount', 'editaccount' );
	}

	public function doesWrites() {
		return true;
	}

	/**
	 * Group this special page under the correct header in Special:SpecialPages.
	 *
	 * @return string
	 */
	function getGroupName() {
		return 'users';
	}

	/**
	 * Special page description shown on Special:SpecialPages -- different for
	 * privileged users and mortals
	 *
	 * @return string Special page description
	 */
	function getDescription() {
		if ( $this->getUser()->isAllowed( 'editaccount' ) ) {
			return $this->msg( 'editaccount' )->plain();
		} else {
			return $this->msg( 'editaccount-general-description' )->plain();
		}
	}

	/**
	 * Show the special page
	 *
	 * @param string|null $par Parameter (user name) passed to the page or null
	 */
	public function execute( $par ) {
		$out = $this->getOutput();
		$request = $this->getRequest();
		$user = $this->getUser();

		// Redirect mortals to Special:CloseAccount
		if ( !$user->isAllowed( 'editaccount' ) ) {
			//throw new PermissionsError( 'editaccount' );
			$out->redirect( SpecialPage::getTitleFor( 'CloseAccount' )->getFullURL() );
		}

		// Show a message if the database is in read-only mode
		if ( wfReadOnly() ) {
			$out->readOnlyPage();
			return;
		}

		// If user is blocked, s/he doesn't need to access this page
		if ( $user->isBlocked() ) {
			throw new UserBlockedError( $user->mBlock );
		}

		// Set page title and other stuff
		$this->setHeaders();

		// Special:EditAccount is a fairly stupid page title
		$out->setPageTitle( $this->getDescription() );

		// Get name to work on. Subpage is supported, but form submit name trumps
		$userName = $request->getVal( 'wpUserName', $par );
		$action = $request->getVal( 'wpAction' );

		if( $userName !== null ) {
			// Got a name, clean it up
			$userName = str_replace( '_', ' ', trim( $userName ) );
			// User names begin with a capital letter
			$userName = $this->getLanguage()->ucfirst( $userName );

			// Check if user name is an existing user
			if ( User::isValidUserName( $userName ) ) {
				$this->mUser = User::newFromName( $userName );
				$id = $this->mUser->idFromName( $userName );

				if( empty( $action ) ) {
					$action = 'displayuser';
				}

				if ( empty( $id ) ) {
					// Wikia stuff...
					if ( class_exists( 'TempUser' ) ) {
						$this->mTempUser = TempUser::getTempUserFromName( $userName );
					}

					if ( $this->mTempUser ) {
						$id = $this->mTempUser->getId();
						$this->mUser = User::newFromId( $id );
					} else {
						$this->mStatus = false;
						$this->mStatusMsg = $this->msg( 'editaccount-nouser', $userName )->text();
						$action = '';
					}
				}
			}
		}

		// FB:23860
		if ( !( $this->mUser instanceof User ) ) {
			$action = '';
		}

		$changeReason = $request->getVal( 'wpReason' );

		// What to do, what to show? Hmm...
		switch( $action ) {
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
				$this->mStatus = (bool) $this->mUser->getOption( 'requested-closure', 0 );
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
		include( 'templates/' . strtolower( $template ) . '.tmpl.php' );
		$templateClassName = 'EditAccount' . $template . 'Template';
		$tmpl = new $templateClassName;

		$templateVariables = array(
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
			'returnURL' => $this->getTitle()->getFullURL(),
			'logLink' => Linker::linkKnown(
				SpecialPage::getTitleFor( 'Log', 'editaccnt' ),
				$this->msg( 'log-name-editaccnt' )->escaped()
			),
			'userStatus' => null,
			'emailStatus' => null,
			'disabled' => null,
			'changeEmailRequested' => null,
		);
		foreach ( $templateVariables as $templateVariable => $variableValue ) {
			$tmpl->set( $templateVariable, $variableValue );
		}

		if ( is_object( $this->mUser ) ) {
			if ( $this->mTempUser ) {
				$this->mUser = $this->mTempUser->mapTempUserToUser( false );
				$userStatus = $this->msg( 'editaccount-status-tempuser' )->plain();
				$tmpl->set( 'disabled', 'disabled="disabled"' );
			} else {
				$userStatus = $this->msg( 'editaccount-status-realuser' )->plain();
			}
			$this->mUser->load();

			// get new e-mail (unconfirmed)
			$optionNewEmail = $this->mUser->getOption( 'new_email' );
			if ( empty( $optionNewEmail ) ) {
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

			$templateVariables2 = array(
				'userEmail' => $this->mUser->getEmail(),
				'userRealName' => $this->mUser->getRealName(),
				'userId' => $this->mUser->getId(),
				'userReg' => date( 'r', strtotime( $this->mUser->getRegistration() ) ),
				'isUnsub' => $this->mUser->getOption( 'unsubscribed' ),
				'isDisabled' => $this->mUser->getOption( 'disabled' ),
				'isAdopter' => $this->mUser->getOption( 'AllowAdoption', 1 ),
				'userStatus' => $userStatus,
				'emailStatus' => $emailStatus,
				'changeEmailRequested' => $changeEmailRequested,
			);
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
	function setEmail( $email, $changeReason = '' ) {
		$oldEmail = $this->mUser->getEmail();
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
					$this->mUser->setOption( 'new_email', null );
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
				$logEntry->setComment( $changeReason ); // JP 13 April 2013: not sure if this is the correct one, CHECKME
				$logId = $logEntry->insert();

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
	function setPassword( $pass, $changeReason = '' ) {
		if ( $this->mUser->setPassword( $pass ) ) {
			// Save the new settings
			if ( $this->mTempUser ) {
				$this->mTempUser->setPassword( $this->mUser->mPassword );
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
			$logEntry->setComment( $changeReason ); // JP 13 April 2013: not sure if this is the correct one, CHECKME
			$logId = $logEntry->insert();

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
	 * Set a user's real name.
	 *
	 * @param mixed $pass Real name to set to the user
	 * @param string $changeReason Reason for change
	 * @return bool True on success, false on failure
	 */
	function setRealName( $realName, $changeReason = '' ) {
		$this->mUser->setRealName( $realName );
		$this->mUser->saveSettings();

		// Was the change saved successfully? The setRealName function doesn't
		// return a boolean value...
		if ( $this->mUser->getRealName() == $realName ) {
			// Log what was done
			$logEntry = new ManualLogEntry( 'editaccnt', 'realnamechange' );
			$logEntry->setPerformer( $this->getUser() );
			$logEntry->setTarget( $this->mUser->getUserPage() );
			$logEntry->setComment( $changeReason ); // JP 13 April 2013: not sure if this is the correct one, CHECKME
			$logId = $logEntry->insert();

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
	function closeAccount( $changeReason = '' ) {
		// Set flag for Special:Contributions
		// NOTE: requires FlagClosedAccounts.php to be included separately
		if ( defined( 'CLOSED_ACCOUNT_FLAG' ) ) {
			$this->mUser->setRealName( CLOSED_ACCOUNT_FLAG );
		} else {
			// magic value not found, so let's at least blank it
			$this->mUser->setRealName( '' );
		}

		// remove user's avatar
		if ( class_exists( 'wAvatar' ) ) { // SocialProfile
			// Commented out because as of 17 June 2013, ShoutWiki has only 8
			// wikis with SocialProfile enabled and this method is probably
			// *very* expensive since it does operations for everything in the
			// images directory...
			//$this->removeSocialProfileAvatars();
		} elseif ( class_exists( 'Masthead' ) ) { // Wikia's avatar extension
			$avatar = Masthead::newFromUser( $this->mUser );
			if ( !$avatar->isDefault() ) {
				if( !$avatar->removeFile( false ) ) {
					// don't quit here, since the avatar is a non-critical part
					// of closing, but flag for later
					$this->mStatusMsg2 = $this->msg( 'editaccount-remove-avatar-fail' )->plain();
				}
			}
		}

		// Remove e-mail address and password
		$this->mUser->setEmail( '' );
		$newPass = $this->generateRandomScrambledPassword();
		$this->mUser->setPassword( $newPass );

		// Save the new settings
		$this->mUser->saveSettings();

		$id = $this->mUser->getId();

		// Reload user
		$this->mUser = User::newFromId( $id );

		if ( $this->mUser->getEmail() == '' ) {
			// ShoutWiki patch begin
			$this->setDisabled();
			// ShoutWiki patch end
			// Mark as disabled in a more real way, that doesn't depend on the real_name text
			$this->mUser->setOption( 'disabled', 1 );
			$this->mUser->setOption( 'disabled_date', wfTimestamp( TS_DB ) );
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
			$logEntry->setComment( $changeReason ); // JP 13 April 2013: not sure if this is the correct one, CHECKME
			$logId = $logEntry->insert();

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
	function clearUnsubscribe() {
		$this->mUser->setOption( 'unsubscribed', null );
		$this->mUser->saveSettings();

		$this->mStatusMsg = $this->msg( 'editaccount-success-unsub', $this->mUser->mName )->text();

		return true;
	}

	/**
	 * Clears the magic disabled bit
	 *
	 * @return bool Always true
	 */
	function clearDisable() {
		$this->mUser->setOption( 'disabled', null );
		$this->mUser->setOption( 'disabled_date', null );
		$this->mUser->saveSettings();

		// ShoutWiki patch begin
		// We also need to clear GlobalPreferences data; otherwise it's possible
		// (though unlikely) that a staff member reactivates a disabled account
		// but the "this account has been disabled" notice on Special:Contributions
		// won't go away.
		if ( class_exists( 'GlobalPreferences' ) ) {
			$dbw = GlobalPreferences::getPrefsDB( DB_MASTER );

			$dbw->startAtomic( __METHOD__ );
			$dbw->delete(
				'global_preferences',
				array(
					'gp_property' => 'disabled',
					'gp_value' => 1,
					'gp_user' => $this->mUser->getId()
				),
				__METHOD__
			);
			$dbw->delete(
				'global_preferences',
				array(
					'gp_property' => 'disabled_date',
					'gp_user' => $this->mUser->getId()
				),
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
	function toggleAdopterStatus() {
		$this->mUser->setOption( 'AllowAdoption', (int) !$this->mUser->getOption( 'AllowAdoption', 1 ) );
		$this->mUser->saveSettings();

		$this->mStatusMsg = $this->msg( 'editaccount-success-toggleadopt', $this->mUser->mName )->text();

		return true;
	}

	/**
	 * Returns a random password which conforms to our password requirements
	 * and is not easily guessable.
	 *
	 * @return string
	 */
	function generateRandomScrambledPassword() {
		// Password requirements need a capital letter, a digit, and a lowercase letter.
		// wfGenerateToken() returns a 32 char hex string, which will almost
		// always satisfy the digit/letter but not always.
		// This suffix shouldn't reduce the entropy of the intentionally
		// scrambled password.
		$REQUIRED_CHARS = 'A1a';
		return ( self::generateToken() . $REQUIRED_CHARS );
	}

	/**
	 * Remove SocialProfile avatars from all wikis.
	 *
	 * @note The foreach loop is almost a verbatim copy-paste of the private method
	 * RemoveAvatar::deleteImage() from extensions/SocialProfile/UserProfile/SpecialRemoveAvatar.php
	 * That method should be made public and this should then be rewritten
	 * accordingly.
	 *
	 * @return bool Always true
	 */
	function removeSocialProfileAvatars() {
		global $IP, $wgUploadDirectory, $wgDBname, $wgMemc, $wgUploadAvatarInRecentChanges;

		// @see http://www.developerfusion.com/code/2058/determine-execution-time-in-php/
		$mtime = microtime();
		$mtime = explode( ' ', $mtime );
		$mtime = $mtime[1] + $mtime[0];
		$startTime = $mtime;

		// @todo FIXME: horribly ShoutWiki-specific
		$path = $IP . '/images/';
		$handle = opendir( $path );
		if ( $handle ) {
			$file = readdir( $handle );
			while ( $file !== false ) {
				// do something with the file
				// note that '.' and '..' is returned even
				if ( $file != '.' && $file != '..' ) {
					$fullpath = $path . $file;
					// If this is a directory...
					if ( is_dir( $fullpath ) ) {
						// change our current working directory to it, then!
						chdir( $fullpath );
						// Look for the avatars directory
						if ( is_dir( getcwd() . '/avatars' ) ) {
							// Split the current directory name from right to left
							// @see http://stackoverflow.com/questions/717328/how-to-explode-string-right-to-left
							$result = array_map( 'strrev', explode( '/', strrev( getcwd() ) ) );
							// $result[0] should now hold the image directory
							// name, from which we can construct the DB name
							// easily
							if ( isset( $result[0] ) && $result[0] ) {
								// Oh fuck, the directory name contains a
								// period (i.e. "fi.starwars")
								if ( strpos( $result[0], '.' ) !== false ) {
									$splitDirName = explode( '.', $result[0] );
									// for fi.starwars, this would look like "starwars_fiwiki"
									// which is the expected result.
									// Phew, finally!
									$dbName = $splitDirName[1] . '_' . $splitDirName[0] . '_wiki';
								} else {
									// Just append "_wiki" to the image dir
									// name to get the DB name.
									$dbName = $result[0] . '_wiki';
								}
								// Normalization...
								$dbName = str_replace( '-', '_', $dbName );

								// ACTUALLY REMOVE THE DAMN THINGS!
								foreach ( array( 's', 'm', 'ml', 'l' ) as $size ) {
									$avatar = new wAvatar( $this->mUser->getId(), $size );
									$files = glob(
										getcwd() . '/avatars/' . $dbName . '_' .
										$this->mUser->getId() .  '_' . $size . '*'
									);
									wfSuppressWarnings();
									$img = basename( $files[0] );
									wfRestoreWarnings();
									if ( $img && $img[0] ) {
										unlink( getcwd() . '/avatars/' . $img );
									}

									// clear cache
									$key = wfMemcKey( 'user', 'profile', 'avatar', $this->mUser->getId(), $size );
									$wgMemc->delete( $key );
								}

								// Ensure that the logs are placed into the correct DB
								$dbw = wfGetDB( DB_MASTER, array(), $dbName );
								// Log it!
								// Note: old-school logging style is
								// intentionally used here because it's what
								// SocialProfile uses, too.
								$log = new LogPage( 'avatar' );
								if ( !$wgUploadAvatarInRecentChanges ) {
									$log->updateRecentChanges = false;
								}

								$logMsg = $this->msg(
									'user-profile-picture-log-delete-entry',
									$this->mUser->getName()
								);
								// It should never be empty or disabled, but...
								if ( !$logMsg->isEmpty() || !$logMsg->isDisabled() ) {
									$log->addEntry(
										'avatar',
										$this->getUser()->getUserPage(),
										$logMsg->text()
									);
								}
							}
						} else {
							//error_log( getcwd() . '/avatars/ does not exist, skipping.' );
						}
					}
				}
			}
			closedir( $handle );
		}

		$mtime = microtime();
		$mtime = explode( ' ', $mtime );
		$mtime = $mtime[1] + $mtime[0];
		$endTime = $mtime;
		$totalTime = ( $endTime - $startTime );

		error_log(
			__METHOD__ . ': checking for (and possibly deleting) avatars took ' .
				$totalTime
		);

		return true;
	}

	/**
	 * Marks the account as disabled, the ShoutWiki way.
	 */
	function setDisabled() {
		if ( !class_exists( 'GlobalPreferences' ) ) {
			error_log( 'Cannot use the GlobalPreferences class in ' . __METHOD__ );
			return;
		}
		$dbw = GlobalPreferences::getPrefsDB( DB_MASTER );

		$dbw->startAtomic( __METHOD__ );
		$dbw->insert(
			'global_preferences',
			array(
				'gp_property' => 'disabled',
				'gp_value' => 1,
				'gp_user' => $this->mUser->getId()
			),
			__METHOD__
		);
		$dbw->insert(
			'global_preferences',
			array(
				'gp_property' => 'disabled_date',
				'gp_value' => wfTimestamp( TS_DB ),
				'gp_user' => $this->mUser->getId()
			),
			__METHOD__
		);
		$dbw->endAtomic( __METHOD__ );
	}

	/**
	 * Is the given user account disabled?
	 *
	 * @param $user User
	 * @return bool True if it is disabled, otherwise false
	 */
	public static function isAccountDisabled( $user ) {
		if ( !class_exists( 'GlobalPreferences' ) ) {
			error_log( 'Cannot use the GlobalPreferences class in ' . __METHOD__ );
			return;
		}
		$dbr = GlobalPreferences::getPrefsDB();
		$retVal = $dbr->selectField(
			'global_preferences',
			'gp_value',
			array(
				'gp_property' => 'disabled',
				'gp_user' => $user->getId()
			),
			__METHOD__
		);

		return (bool) $retVal;
	}

	/**
	 * Copypasta from pre-1.23 /includes/GlobalFunctions.php
	 * @see https://git.wikimedia.org/commitdiff/mediawiki%2Fcore/118567a4ba0ded669f43a58713733cab915afe39
	 */
	public static function generateToken( $salt = '' ) {
		$salt = serialize( $salt );
		return md5( mt_rand( 0, 0x7fffffff ) . $salt );
	}
}
