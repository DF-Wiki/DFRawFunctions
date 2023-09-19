<?php
/*
 * DFRawFunctions extension by Quietust
 * Dwarf Fortress Raw parser functions
 */

if (!defined('MEDIAWIKI'))
{
	echo "This file is an extension of the MediaWiki software and cannot be used standalone\n";
	die(1);
}

/*
 * Configuration
 * These may be overridden in LocalSettings.php
 */

// Whether or not to allow loading raws from disk
$wgDFRawEnableDisk = false;

// The directory which contains the raw folders and files
$wgDFRawPath = dirname(__FILE__) .'/raws';

/*
 * Extension Logic - do not change anything below this line
 */

$wgExtensionCredits['parserhook'][] = array(
	'path'           => __FILE__,
	'name'           => 'DFRawFunctions',
	'author'         => 'Quietust',
	'url'            => 'http://dwarffortresswiki.org/index.php/User:Quietust',
	'description'    => 'Dwarf Fortress Raw parser functions',
	'version'        => '1.7.3',
);

$wgAutoloadClasses['DFRawFunctions'] = dirname(__FILE__) . '/DFRawFunctions.body.php';

$wgHooks['ParserFirstCallInit'][] = 'efDFRawFunctions_Initialize';

$wgExtensionMessagesFiles['DFRawFunctions'] = dirname(__FILE__) . '/DFRawFunctions.i18n.magic.php';

function efDFRawFunctions_Initialize (&$parser)
{
	$parser->setFunctionHook('df_raw',		'DFRawFunctions::raw');
	$parser->setFunctionHook('df_tag',		'DFRawFunctions::tag');
	$parser->setFunctionHook('df_tagentry',		'DFRawFunctions::tagentry');
	$parser->setFunctionHook('df_tagvalue',		'DFRawFunctions::tagvalue');
	$parser->setFunctionHook('df_foreachtag',	'DFRawFunctions::foreachtag');
	$parser->setFunctionHook('df_foreachtoken',	'DFRawFunctions::foreachtoken');
	$parser->setFunctionHook('df_makelist',		'DFRawFunctions::makelist');
	$parser->setFunctionHook('df_statedesc',	'DFRawFunctions::statedesc');
	$parser->setFunctionHook('df_cvariation',	'DFRawFunctions::cvariation');
	$parser->setFunctionHook('mreplace',		'DFRawFunctions::mreplace');
	$parser->setFunctionHook('delay',		'DFRawFunctions::delay');
	$parser->setFunctionHook('eval',		'DFRawFunctions::evaluate');
	return true;
}
