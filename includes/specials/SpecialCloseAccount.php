<?php
/**
 * A special page to allow mortals to close their accounts.
 * Originally used to be a part of the main EditAccount special page, but a
 * rather essential bug prevented this feature from ever working as intended.
 * It's easier to have that feature implemented as a special page than fixing
 * the broken-by-design logic.
 *
 * @file
 * @date 27 February 2015
 * @see https://bugzilla.shoutwiki.com/show_bug.cgi?id=294
 */

use MediaWiki\MediaWikiServices;
use MediaWiki\User\UserGroupManager;
use MediaWiki\User\UserNameUtils;

// @note Extends EditAccount so that we don't have to duplicate closeAccount() etc.
class CloseAccount extends EditAccount {

	/**
	 * @var null|User User object for the account that is to be disabled
	 */
	public ?User $mUser;

	/**
	 * @var UserGroupManager
	 */
	private UserGroupManager $userGroupManager;

	/** @var UserNameUtils */
	private UserNameUtils $userNameUtils;

	/**
	 * Constructor -- set up the new special page
	 *
	 * @param UserGroupManager $userGroupManager
	 * @param UserNameUtils $userNameUtils
	 */
	public function __construct(
		UserGroupManager $userGroupManager,
		UserNameUtils $userNameUtils
	) {
		SpecialPage::__construct( 'CloseAccount' );
		$this->userGroupManager = $userGroupManager;
		$this->userNameUtils = $userNameUtils;
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
	 * Special page description shown on Special:SpecialPages (for mortals)
	 *
	 * @return string Special page description
	 */
	public function getDescription(): string {
		return $this->msg( 'editaccount-general-description' )->plain();
	}

	/**
	 * Show this special page on Special:SpecialPages only for registered users
	 * who are not staff members
	 *
	 * @return bool
	 */
	public function isListed(): bool {
		$user = $this->getUser();
		$effectiveGroups = $this->userGroupManager->getUserEffectiveGroups( $user );
		$isStaff = in_array( 'staff', $effectiveGroups );
		return $user->isRegistered() && !$isStaff;
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

		// Anons should not be allowed to access this special page
		if ( !$user->isRegistered() ) {
			throw new PermissionsError( 'editaccount' );
		}

		// Show a message if the database is in read-only mode
		$this->checkReadOnly();

		// If user is blocked, s/he doesn't need to access this page
		if ( $user->getBlock() ) {
			// @phan-suppress-next-line PhanTypeMismatchArgumentNullable
			throw new UserBlockedError( $user->getBlock() );
		}

		// Redirect staff members to Special:EditAccount instead
		$effectiveGroups = $this->userGroupManager->getUserEffectiveGroups( $user );
		if ( in_array( 'staff', $effectiveGroups ) ) {
			$out->redirect( SpecialPage::getTitleFor( 'EditAccount' )->getFullURL() );
		}

		// Set page title and other stuff
		$this->setHeaders();

		// Special:EditAccount is a fairly stupid page title
		$out->setPageTitle( $this->getDescription() );

		// Mortals can only close their own account
		$userName = $user->getName();
		// Clean up the user name
		$userName = str_replace( '_', ' ', trim( $userName ) );
		// User names begin with a capital letter
		$userName = $this->getLanguage()->ucfirst( $userName );

		// Check if user name is an existing user
		if ( $this->userNameUtils->isValid( $userName ) ) {
			$this->mUser = MediaWikiServices::getInstance()->getUserFactory()->newFromName( $userName );
		}

		$changeReason = $request->getVal( 'wpReason' );

		if ( $request->wasPosted() ) {
			$this->mStatus = $this->closeAccount( $changeReason );
			if ( $this->mStatus ) {
				$color = 'darkgreen';
			} else {
				$color = '#fe0000';
			}

			$out->addHTML(
				"<fieldset>\n<legend>" . $this->msg( 'editaccount-status' )->escaped() .
				'</legend>' .
				Xml::element( 'span', [ 'style' => "color: $color; font-weight: bold;" ], $this->mStatusMsg ) .
				'</fieldset>'
			);
		} else {
			// Load the correct template file and initiate a new template object
			include __DIR__ . '/../../templates/closeaccount.tmpl.php';
			$tmpl = new EditAccountCloseAccountTemplate;

			$templateVariables = [
				// the value of this is irrelevant, it just needs to be defined
				// for the template because we're reusing EditAccount's UI template
				// and otherwise we'll get "undefined index" notices
				'status' => '',
				'statusMsg' => '',
				// likewise
				'user' => $userName,
				'user_hsc' => htmlspecialchars( $userName )
			];
			foreach ( $templateVariables as $templateVariable => $variableValue ) {
				$tmpl->set( $templateVariable, $variableValue );
			}

			// Output everything!
			// @phan-suppress-next-line PhanTypeMismatchArgument
			$out->addTemplate( $tmpl );
		}
	}
}
