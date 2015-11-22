<?php
/**
 * @file
 */
if ( !defined( 'MEDIAWIKI' ) ) {
	die( 'This is not a valid entry point to MediaWiki.' );
}

/**
 * HTML template for Special:EditAccount
 *
 * @ingroup Templates
 */
class EditAccountCloseAccountTemplate extends QuickTemplate {
	function execute() {
		$status = $this->data['status'];
		$statusMsg = $this->data['statusMsg'];
		$user = $this->data['user'];
		$user_hsc = $this->data['user_hsc'];
?>
<!-- s:<?php echo __FILE__ ?> -->
<?php
// Display a warning if the user hasn't requested their account to be closed or
// something *and* they are not the current user performing the action
if ( !is_null( $status ) && $user !== $user_hsc ) { ?>
<fieldset>
	<legend><?php echo wfMessage( 'editaccount-status' )->plain() ?></legend>
	<?php
	if ( $status ) {
		echo Xml::element( 'span', array( 'style' => 'color: darkgreen; font-weight: bold;' ), $statusMsg );
	} else {
		echo Xml::element( 'span', array( 'style' => 'color: #fe0000; font-weight: bold;' ), $statusMsg );
	}
	?>
</fieldset>
<?php } ?>
<form method="post" id="editaccountSelectForm" action="">
	<fieldset>
		<legend><?php echo wfMessage( 'editaccount-frame-close', $user )->escaped() ?></legend>
		<p><?php echo wfMessage( 'editaccount-warning-close', $user )->parse() ?></p>
		<div>
			<label for="wpReason"><?php echo wfMessage( 'editaccount-label-reason' )->escaped() ?></label>
			<input id="wpReason" name="wpReason" type="text" />
		</div>
		<div>
			<input type="submit" value="<?php echo wfMessage( 'editaccount-submit-close' )->plain() ?>" />
		</div>
		<input type="hidden" name="wpUserName" value="<?php echo $user_hsc ?>" />
		<input type="hidden" name="wpAction" value="closeaccountconfirm" />
	</fieldset>
</form>
<!-- e:<?php echo __FILE__ ?> -->
<?php
	}
}