<?php
/**
 * Aliases for Special:EditAccount
 *
 * @file
 * @ingroup Extensions
 */

$specialPageAliases = [];

/** English */
$specialPageAliases['en'] = [
	'EditAccount' => [ 'EditAccount', 'DisableAccount' ],
	'CloseAccount' => [ 'CloseAccount' ],
];

/** Finnish (Suomi) */
$specialPageAliases['fi'] = [
	'EditAccount' => [ 'Muokkaa käyttäjätunnusta', 'Sulje käyttäjätunnus', 'Sulje tunnus', 'Poista tunnus', 'Poista käyttäjätunnus' ],
];

/** Chinese (中文) */
$specialPageAliases['zh'] = [
	'EditAccount' => [ 'EditAccount' ],
	'CloseAccount' => [ 'CloseAccount' ],
];

/** Simplified Chinese (中文（简体）) */
$specialPageAliases['zh-hans'] = [
	'EditAccount' => [ '编辑账号', '禁用账号' ],
	'CloseAccount' => [ '关闭账号' ],
];

/** Traditional Chinese (中文（繁體）) */
$specialPageAliases['zh-hant'] = [
	'EditAccount' => [ '編輯帳號', '停用帳號' ],
	'CloseAccount' => [ '關閉帳號' ],
];
