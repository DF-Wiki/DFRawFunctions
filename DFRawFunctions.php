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
$wgDFRawEnableDisk = true;

// The directory which contains the raw folders and files
$wgDFRawPath = dirname(__FILE__) .'/raws';

/*
 * Extension Logic - do not change anything below this line
 */

$wgExtensionCredits['parserhook'][] = array(
	'path'           => __FILE__,
	'name'           => 'DFRawFunctions',
	'author'         => 'Quietust',
	'url'            => 'http://df.magmawiki.com/index.php/User:Quietust',
	'description'    => 'Dwarf Fortress Raw parser functions',
	'version'        => '1.5',
);

$wgAutoloadClasses['DFRawFunctions'] = dirname(__FILE__) . '/DFRawFunctions.body.php';


$wgHooks['ParserFirstCallInit'][] = 'efDFRawFunctions_Initialize';
$wgHooks['LanguageGetMagic'][] = 'efDFRawFunctions_RegisterMagicWords';

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
	$parser->setFunctionHook('df_type',		'DFRawFunctions::getType');
	$parser->setFunctionHook('df_keybind',		'DFRawFunxctions::getKeybind');
	$parser->setFunctionHook('df_building',		'DFRawFunctions::getBuilding');
	$parser->setFunctionHook('df_item',		'DFRawFunctions::getItem');
	$parser->setFunctionHook('df_tile',		'DFRawFunctions::colorTile');
	
	
	return true;
}

function efDFRawFunctions_RegisterMagicWords (&$magicWords, $langCode)
{
	$magicWords['df_raw']		= array(0, 'df_raw');
	$magicWords['df_tag']		= array(0, 'df_tag');
	$magicWords['df_tagentry']	= array(0, 'df_tagentry');
	$magicWords['df_tagvalue']	= array(0, 'df_tagvalue');
	$magicWords['df_foreachtag']	= array(0, 'df_foreachtag');
	$magicWords['df_foreachtoken']	= array(0, 'df_foreachtoken');
	$magicWords['df_makelist']	= array(0, 'df_makelist');
	$magicWords['df_statedesc']	= array(0, 'df_statedesc');
	$magicWords['df_cvariation']	= array(0, 'df_cvariation');
	$magicWords['mreplace']		= array(0, 'mreplace');
	$magicWords['delay']		= array(0, 'delay');
	$magicWords['eval']		= array(0, 'eval');
	$magicWords['df_type']		= array(0, 'df_type');
	$magicWords['df_keybind']		= array(0, 'df_keybind');
	$magicWords['df_building']		= array(0, 'df_building');
	$magicWords['df_item']		= array(0, 'df_item');
	$magicWords['df_tile']		= array(0, 'df_tile');
	return true;
}