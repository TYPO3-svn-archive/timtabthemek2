<?php
//
//	$Id$
//

if (!defined ('TYPO3_MODE')) 	die ('Access denied.');

//get EXT path
$PATH_timtab_theme_k2 = t3lib_extMgm::extPath('timtab_theme_k2');

if (TYPO3_MODE == 'FE')	{
	require_once($PATH_timtab_theme_k2.'class.tx_timtabthemek2_fe.php');
}

//registering for several hooks
$TYPO3_CONF_VARS['EXTCONF']['tt_news']['extraItemMarkerHook'][]      = 'tx_timtabthemek2_fe';
$TYPO3_CONF_VARS['EXTCONF']['ve_guestbook']['extraItemMarkerHook'][] = 'tx_timtabthemek2_fe';

?>