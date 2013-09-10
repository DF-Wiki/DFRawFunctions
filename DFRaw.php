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
	'name'           => 'DFRaw',
	'author'         => 'Quietust, Asva',
	'url'            => 'http://df.magmawiki.com/index.php/User:Quietust',
	'description'    => 'Dwarf Fortress Raw parser',
	'version'        => '1.6',
);

$wgAutoloadClasses['DFRaw'] = dirname(__FILE__) . '/DFRaw.body.php';


$wgHooks['ParserFirstCallInit'][] = 'efDFRaw_Initialize';
$wgHooks['LanguageGetMagic'][] = 'efDFRaw_RegisterMagicWords';

function efDFRaw_Initialize (&$parser)
{
	$parser->setFunctionHook('df_raw',		'DFRaw::raw');
	return true;
}

function efDFRaw_RegisterMagicWords (&$magicWords, $langCode)
{
	$magicWords['df_raw']		= array(0, 'df_raw');
	return true;
}