<?php

$cfg = require __DIR__ . '/../vendor/mediawiki/mediawiki-phan-config/src/config.php';

// Suppress EVERYTHING for now, we will fix it later
$cfg['suppress_issue_types'] = array_merge( $cfg['suppress_issue_types'], [
	'PhanTypeInvalidLeftOperandOfAdd',
	'PhanTypeInvalidRightOperandOfAdd',
	'PhanTypeMismatchArgumentNullableInternal',
	'PhanUndeclaredConstant',
	'PhanUndeclaredClassMethod',
	'PhanUndeclaredMethod',
	'PhanUndeclaredVariableDim',
] );

return $cfg;
