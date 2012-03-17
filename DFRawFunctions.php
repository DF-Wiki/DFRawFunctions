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

$wgExtensionCredits['parserhook'][] = array(
	'path'           => __FILE__,
	'name'           => 'DFRawFunctions',
	'author'         => 'Quietust',
	'url'            => 'http://df.magmawiki.com/index.php/User:Quietust',
	'version'        => '1.0',
	'description'    => 'Dwarf Fortress Raw parser functions',
);

$wgHooks['ParserFirstCallInit'][]	= 'efDFRawFunctions_Setup';
$wgHooks['LanguageGetMagic'][]		= 'efDFRawFunctions_Magic';

function efDFRawFunctions_Setup (&$parser)
{
	$parser->setFunctionHook('df_raw',		'DFRawFunctions::raw');
	$parser->setFunctionHook('df_tag',		'DFRawFunctions::tag');
	$parser->setFunctionHook('df_tagentry',		'DFRawFunctions::tagentry');
	$parser->setFunctionHook('df_tagvalue',		'DFRawFunctions::tagvalue');
	$parser->setFunctionHook('df_foreachtag',	'DFRawFunctions::foreachtag');
	$parser->setFunctionHook('df_foreachtoken',	'DFRawFunctions::foreachtoken');
	$parser->setFunctionHook('df_makelist',		'DFRawFunctions::makelist');
	$parser->setFunctionHook('df_statedesc',	'DFRawFunctions::statedesc');
	$parser->setFunctionHook('mreplace',		'DFRawFunctions::mreplace');
	$parser->setFunctionHook('delay',		'DFRawFunctions::delay');
	$parser->setFunctionHook('eval',		'DFRawFunctions::evaluate');
	return true;
}

function efDFRawFunctions_Magic (&$magicWords, $langCode)
{
	$magicWords['df_raw']		= array(0, 'df_raw');
	$magicWords['df_tag']		= array(0, 'df_tag');
	$magicWords['df_tagentry']	= array(0, 'df_tagentry');
	$magicWords['df_tagvalue']	= array(0, 'df_tagvalue');
	$magicWords['df_foreachtag']	= array(0, 'df_foreachtag');
	$magicWords['df_foreachtoken']	= array(0, 'df_foreachtoken');
	$magicWords['df_makelist']	= array(0, 'df_makelist');
	$magicWords['df_statedesc']	= array(0, 'df_statedesc');
	$magicWords['mreplace']		= array(0, 'mreplace');
	$magicWords['delay']		= array(0, 'delay');
	$magicWords['eval']		= array(0, 'eval');
	return true;
}

class DFRawFunctions
{
	// Takes some raws and returns a 2-dimensional token array
	// If 2nd parameter is specified, then only tags of the specified type will be returned
	private static function getTags ($data, $type = '')
	{
		$raws = array();
		$off = 0;
		while (1)
		{
			$start = strpos($data, '[', $off);
			if ($start === FALSE)
				break;
			$end = strpos($data, ']', $start);
			if ($end === FALSE)
				break;
			$off = $end + 1;
			$tag = explode(':', substr($data, $start + 1, $end - $start - 1));
			if (($type == '') || ($tag[0] == $type))
				$raws[] = $tag;
		}
		return $raws;
	}

	// Take an entire raw file and extract one entity
	public static function raw (&$parser, $data = '', $object = '', $id = '', $notfound = '')
	{
		$start = strpos($data, '['. $object .':'. $id .']');
		if ($start === FALSE)
			return $notfound;
		$end = strpos($data, '['. $object .':', $start + 1);
		if ($end === FALSE)
			return substr($data, $start);
		return substr($data, $start, $end - $start);
	}

	// Checks if a tag is present, optionally with a particular token at a specific offset
	public static function tag (&$parser, $data = '', $type = '', $offset = 0, $entry = '')
	{
		if ($entry == '')
			$entry = $type;
		$tags = self::getTags($data, $type);
		foreach ($tags as $tag)
		{
			if ($offset >= count($tag))
				continue;
			if ($tag[$offset] == $entry)
				return TRUE;
		}
		return FALSE;
	}

	// Locates a tag matching certain criteria and returns the tag at the specified offset
	// Match condition parameters are formatted CHECKOFFSET:CHECKVALUE
	// If offset is of format MIN:MAX, then all tokens within the range will be returned, colon-separated
	public static function tagentry (&$parser, $data = '', $type = '', $offset = 0, $notfound = 'not found')
	{
		$numcaps = func_num_args() - 5;
		$tags = self::getTags($data, $type);
		foreach ($tags as $tag)
		{
			if ($offset >= count($tag))
				continue;
			$match = true;
			for ($i = 0; $i < $numcaps; $i++)
			{
				$parm = func_get_arg($i + 5);
				list($checkoffset, $checkval) = explode(':', $parm);
				if (($checkoffset >= count($tag)) || ($tag[$checkoffset] != $checkval))
				{
					$match = false;
					break;
				}
			}
			if ($match)
			{
				$range = explode(':', $offset);
				if (count($range) == 1)
					return $tag[$offset];
				else
				{
					$out = array();
					for ($i = $range[0]; $i <= $range[1]; $i++)
						$out[] = $tag[$i];
					return implode(':', $out);
				}
			}
		}
		return $notfound;
	}

	// Locates a tag and returns all of its tokens as a colon-separated string
	public static function tagvalue (&$parser, $data = '', $type = '', $notfound = 'not found')
	{
		$tags = self::getTags($data, $type);
		if (count($tags) == 0)
			return $notfound;
		$tag = $tags[0];
		array_shift($tag);
		return implode(':', $tag);
	}

	// Iterates across all matching tags and produces the string for each one, substituting \1, \2, etc. for the tokens
	// Probably won't work with more than 9 parameters
	public static function foreachtag (&$parser, $data = '', $type = '', $string = '')
	{
		$tags = self::getTags($data, $type);
		$out = '';
		foreach ($tags as $tag)
		{
			$rep_in = array();
			for ($i = 0; $i < count($tag); $i++)
				$rep_in[$i] = '\\'. ($i + 1);
			$out .= str_replace($rep_in, $tag, $string);
		}
		return $out;
	}

	// Iterates across all tokens within a specific tag in groups and produces the string for each group, substituting \1, \2, etc.
	// Input data is expected to come from tagvalue()
	public static function foreachtoken (&$parser, $data = '', $offset = 0, $group = 1, $string = '')
	{
		$tag = explode(':', $data);
		$out = '';
		$rep_in = array();
		for ($i = 0; $i < $group; $i++)
			$rep_in[] = '\\'. ($i + 1);
		for ($i = $offset; $i < count($tag); $i += $group)
		{
			$rep_out = array();
			for ($j = 0; $j < $group; $j++)
				$rep_out[] = $tag[$i + $j];
			$out .= str_replace($rep_in, $rep_out, $string);
		}
		return $out;
	}

	// Iterates across all objects in the specified raw file and extracts specific tokens
	// Token extraction parameters are formatted TYPE:OFFSET:CHECKOFFSET:CHECKVALUE
	// If CHECKOFFSET is -1, then CHECKVALUE is ignored
	// If TYPE is "STATE" and OFFSET is "NAME" or "ADJ", then OFFSET and CHECKOFFSET will be fed into statedesc() to return the material's state descriptor
	// Objects which fail to match *any* of the checks will be skipped
	public static function makelist (&$parser, $data = '', $object = '', $string = '')
	{
		$numcaps = func_num_args() - 4;
		$rep_in = array();
		for ($i = 0; $i < $numcaps; $i++)
			$rep_in[$i] = '\\'. ($i + 1);
		$out = '';
		$off = 0;
		while (1)
		{
			$start = strpos($data, '['. $object .':', $off);
			if ($start === FALSE)
				break;
			$end = strpos($data, '['. $object .':', $start + 1);
			if ($end === FALSE)
				$end = strlen($data);
			$off = $end;
			$tags = self::getTags(substr($data, $start, $end - $start));
			$rep_out = array();
			for ($i = 0; $i < $numcaps; $i++)
			{
				$parm = func_get_arg($i + 4);
				@list($gettype, $getoffset, $checkoffset, $checkval) = explode(':', $parm);
				// permit fetching material state descriptors from here
				if (($gettype == 'STATE') && (in_array($getoffset, array('NAME', 'ADJ'))))
				{
					$val = self::statedesc($parser, substr($data, $start, $end - $start), $getoffset, $checkoffset);
					if (strlen($val))
						$rep_out[$i] = $val;
					continue;
				} 
				foreach ($tags as $tag)
				{
					if (($tag[0] != $gettype) || ($getoffset >= count($tag)))
						continue;
					if (($checkoffset < 0) || (($checkoffset < count($tag)) && ($tag[$checkoffset] == $checkval)))  
					{
						$rep_out[$i] = $tag[$getoffset];
						break;
					}
				}
			}
			if (count($rep_in) == count($rep_out))
				$out .= str_replace($rep_in, $rep_out, $string);
		}
		return $out;
	}

	// Determines a material's state descriptor by parsing its raws
	public static function statedesc (&$parser, $data = '', $type = '', $state = '')
	{
		$tags = self::getTags($data);
		$names = array('NAME' => array(), 'ADJ' => array());
		foreach ($tags as $tag)
		{
			if (in_array($tag[0], array('STATE_NAME', 'STATE_NAME_ADJ')))
			{
				if (in_array($tag[1], array('SOLID', 'ALL_SOLID')))
					$names['NAME']['SOLID'] = $tag[2];
				if (in_array($tag[1], array('SOLID_POWDER', 'POWDER', 'ALL_SOLID')))
					$names['NAME']['POWDER'] = $tag[2];
				if (in_array($tag[1], array('SOLID_PASTE', 'PASTE', 'ALL_SOLID')))
					$names['NAME']['PASTE'] = $tag[2];
				if (in_array($tag[1], array('SOLID_PRESSED', 'PRESSED', 'ALL_SOLID')))
					$names['NAME']['PRESSED'] = $tag[2];
				if ($tag[1] == 'LIQUID')
					$names['NAME']['LIQUID'] = $tag[2];
				if ($tag[1] == 'GAS')
					$names['NAME']['GAS'] = $tag[2];
			}
			if (in_array($tag[0], array('STATE_ADJ', 'STATE_NAME_ADJ')))
			{
				if (in_array($tag[1], array('SOLID', 'ALL_SOLID')))
					$names['ADJ']['SOLID'] = $tag[2];
				if (in_array($tag[1], array('SOLID_POWDER', 'POWDER', 'ALL_SOLID')))
					$names['ADJ']['POWDER'] = $tag[2];
				if (in_array($tag[1], array('SOLID_PASTE', 'PASTE', 'ALL_SOLID')))
					$names['ADJ']['PASTE'] = $tag[2];
				if (in_array($tag[1], array('SOLID_PRESSED', 'PRESSED', 'ALL_SOLID')))
					$names['ADJ']['PRESSED'] = $tag[2];
				if ($tag[1] == 'LIQUID')
					$names['ADJ']['LIQUID'] = $tag[2];
				if ($tag[1] == 'GAS')
					$names['ADJ']['GAS'] = $tag[2];
			}
		}
		if (!isset($names[$type]))
			return '';
		if (!isset($names[$type][$state]))
			return '';
		return $names[$type][$state];
	}

	// Performs multiple string replacements
	public static function mreplace (&$parser, $data = '')
	{
		$numargs = func_num_args() - 2;
		$rep_in = array();
		$rep_out = array();
		for ($i = 0; $i < $numargs; $i += 2)
		{
			$rep_in[] = func_get_arg($i + 2);
			if ($i == $numargs + 2)
				$rep_out[] = '';
			else	$rep_out[] = func_get_arg($i + 3);
		}
		return str_replace($rep_in, $rep_out, $data);
	}

	public static function delay (&$parser)
	{
		$args = func_get_args();
		array_shift($args);
		return '{{'. implode('|', $args) .'}}';
	}

	// Evaluates any templates within the specified data - best used with foreachtag
	public static function evaluate (&$parser, $data = '')
	{
		return $parser->replaceVariables($data);
	}
}
