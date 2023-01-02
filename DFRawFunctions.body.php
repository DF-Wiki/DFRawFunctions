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

class DFRawFunctions
{

	public static function efDFRawFunctions_Initialize (Parser $parser)
	{
		$parser->setFunctionHook('df_raw',		[ self::class, 'raw']);
		$parser->setFunctionHook('df_tag',		[ self::class, 'tag']);
		$parser->setFunctionHook('df_tagentry',		[ self::class, 'tagentry']);
		$parser->setFunctionHook('df_tagvalue',		[ self::class, 'tagvalue']);
		$parser->setFunctionHook('df_foreachtag',	[ self::class, 'foreachtag']);
		$parser->setFunctionHook('df_foreachtoken',	[ self::class, 'foreachtoken']);
		$parser->setFunctionHook('df_makelist',		[ self::class, 'makelist']);
		$parser->setFunctionHook('df_statedesc',	[ self::class, 'statedesc']);
		$parser->setFunctionHook('df_cvariation',	[ self::class, 'cvariation']);
		$parser->setFunctionHook('mreplace',		[ self::class, 'mreplace']);
		$parser->setFunctionHook('delay',		[ self::class, 'delay']);
		$parser->setFunctionHook('eval',		[ self::class, 'evaluate']);
		return true;
	}
	// Takes some raws and returns a 2-dimensional token array
	// If 2nd parameter is specified, then only tags of the specified type will be returned
	// Optional 3rd parameter allows specifying an array which will be filled with indentation for each line
	// Optional 4th parameter allows specifying substitution parameters (for APPLY_CREATURE_VARIATION)
	private static function getTags ($data, $type = '', &$padding = array(), $parms = array())
	{
		$raws = array();
		$off = 0;
		$pad = '';
		while (1)
		{
			$start = strpos($data, '[', $off);
			if ($start === FALSE)
				break;
			$end = strpos($data, ']', $start);
			if ($end === FALSE)
				break;
			if ($off < $start)
			{
				$tmp = explode("\n", trim(substr($data, $off, $start - $off), "\r\n"));
				$pad = end($tmp);
			}
			$tags = explode(':', substr($data, $start + 1, $end - $start - 1));
			if ($parms)
			{
				foreach ($tags as &$tag)
				{
					if (substr($tag, 0, 4) == '!ARG')
					{
						$idx = substr($tag, 4) - 1;
						if ($idx >= 0 && $idx < count($parms))
							$tag = $parms[$idx];
					}
				}
			}
			if (($type == '') || ($tags[0] == $type))
			{
				$padding[] = $pad;
				$raws[] = $tags;
			}
			$off = $end + 1;
		}
		return $raws;
	}

	// Checks if the specified string is a valid namespace:filename
	// If it is, then load and return its contents; otherwise, just return the data as-is
	private static function loadFile ($data)
	{
		global $wgDFRawEnableDisk;
		if (!$wgDFRawEnableDisk)
			return $data;

		global $wgDFRawPath;
		if ($wgDFRawPath == "")
			$wgDFRawPath = __DIR__ . '/raws';
		if (!is_dir($wgDFRawPath))
			return $data;

		global $wgDFRawVersion;
		$version_name = explode(':', $data, 2);
		if ( count($version_name) == 2 and $version_name[0] != "") {
			$version_name = str_replace(array('/', '\\'), '', $version_name);
			$raw_version = $version_name[0];
			$file_name = $version_name[1];

			if ($raw_version == 'DF2012') $raw_version = 'v0.34'; // HACK to handle both DF2012 and v0.34 - once the /raw pages for 0.34 have been fixed, this can go away
		} else {
			if ( $wgDFRawVersion == "" )
				return $data;

			$raw_version = $wgDFRawVersion;
			$file_name = str_replace(array('/', '\\', ":"), '', $data);
		}

		$wantfile = $wgDFRawPath .'/'. $raw_version .'/'. $file_name;

		if (!is_file($wantfile))
			return $data;

		return file_get_contents($wantfile);
	}

	// Take an entire raw file and extract one entity
	// If 'object' is not specified, returns the entire file
	public static function raw (&$parser, $data = '', $object = '', $id = '', $notfound = '')
	{
		$data = self::loadFile($data);
		if (!$object)
			return $data;
		$start = strpos($data, '['. $object .':'. $id .']');
		if ($start === FALSE)
			return $notfound;
		$end = strpos($data, '['. $object .':', $start + 1);
		if ($end === FALSE)
			$end = strlen($data);

		// include any plaintext before the beginning
		$tmp = self::rstrpos($data, ']', $start);
		if ($tmp !== FALSE)
			$start = $tmp;
		// and remove any plaintext after the end
		$tmp = self::rstrpos($data, ']', $end);
		if ($tmp !== FALSE)
			$end = $tmp;

		return trim(substr($data, $start, $end - $start));
	}

	// Same as raw(), but allows specifying multiple files and uses the first one it finds
	public static function raw_mult (&$parser, $datas = array(), $object = '', $id = '', $notfound = '')
	{
		foreach ($datas as $data)
		{
			$data = self::loadFile($data);
			$start = strpos($data, '['. $object .':'. $id .']');
			if ($start === FALSE)
				continue;
			$end = strpos($data, '['. $object .':', $start + 1);
			if ($end === FALSE)
				$end = strlen($data);

			// include any plaintext before the beginning
			$tmp = self::rstrpos($data, ']', $start);
			if ($tmp !== FALSE)
				$start = $tmp;
			// and remove any plaintext after the end
			$tmp = self::rstrpos($data, ']', $end);
			if ($tmp !== FALSE)
				$end = $tmp;

			return trim(substr($data, $start, $end - $start));
		}
		return $notfound;
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
	// Num indicates which instance of the tag should be returned - a negative value counts from the end
	// Match condition parameters are formatted CHECKOFFSET:CHECKVALUE
	// If offset is of format MIN:MAX, then all tokens within the range will be returned, colon-separated
	public static function tagentry (&$parser, $data = '', $type = '', $num = 0, $offset = 0, $notfound = 'not found'/*, ...*/)
	{
		$numcaps = func_num_args() - 6;
		$tags = self::getTags($data, $type);
		if (count($tags) == 0)
			return $notfound;
		if ($num < 0)
			$num += count($tags);
		if (($num < 0) || ($num >= count($tags)))
			return $notfound;
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
			if (!$match)
				continue;
			if ($num)
			{
				$num--;
				continue;
			}
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
		return $notfound;
	}

	// Locates a tag and returns all of its tokens as a colon-separated string
	public static function tagvalue (&$parser, $data = '', $type = '', $num = 0, $notfound = 'not found')
	{
		$tags = self::getTags($data, $type);
		if (count($tags) == 0)
			return $notfound;
		if ($num < 0)
			$num += count($tags);
		if (($num < 0) || ($num >= count($tags)))
			return $notfound;

		$tag = $tags[$num];
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
	// If CHECKOFFSET is -1, then CHECKVALUE is ignored; -2 permits the token to be missing altogether
	// If TYPE is "STATE" and OFFSET is "NAME" or "ADJ", then OFFSET and CHECKOFFSET will be fed into statedesc() to return the material's state descriptor
	// Objects which fail to match *any* of the checks will be skipped
	public static function makelist (&$parser, $data = '', $object = '', $string = ''/*, ...*/)
	{
		$data = self::loadFile($data);

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
				if (($checkoffset == -2) && !isset($rep_out[$i]))
					$rep_out[$i] = '';
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
				if (in_array($tag[1], array('ALL', 'ALL_SOLID', 'SOLID')))
					$names['NAME']['SOLID'] = $tag[2];
				if (in_array($tag[1], array('ALL', 'ALL_SOLID', 'SOLID_POWDER', 'POWDER')))
					$names['NAME']['POWDER'] = $tag[2];
				if (in_array($tag[1], array('ALL', 'ALL_SOLID', 'SOLID_PASTE', 'PASTE')))
					$names['NAME']['PASTE'] = $tag[2];
				if (in_array($tag[1], array('ALL', 'ALL_SOLID', 'SOLID_PRESSED', 'PRESSED')))
					$names['NAME']['PRESSED'] = $tag[2];
				if (in_array($tag[1], array('ALL', 'LIQUID')))
					$names['NAME']['LIQUID'] = $tag[2];
				if (in_array($tag[1], array('ALL', 'GAS')))
					$names['NAME']['GAS'] = $tag[2];
			}
			if (in_array($tag[0], array('STATE_ADJ', 'STATE_NAME_ADJ')))
			{
				if (in_array($tag[1], array('ALL', 'ALL_SOLID', 'SOLID')))
					$names['ADJ']['SOLID'] = $tag[2];
				if (in_array($tag[1], array('ALL', 'ALL_SOLID', 'SOLID_POWDER', 'POWDER')))
					$names['ADJ']['POWDER'] = $tag[2];
				if (in_array($tag[1], array('ALL', 'ALL_SOLID', 'SOLID_PASTE', 'PASTE')))
					$names['ADJ']['PASTE'] = $tag[2];
				if (in_array($tag[1], array('ALL', 'ALL_SOLID', 'SOLID_PRESSED', 'PRESSED')))
					$names['ADJ']['PRESSED'] = $tag[2];
				if (in_array($tag[1], array('ALL', 'LIQUID')))
					$names['ADJ']['LIQUID'] = $tag[2];
				if (in_array($tag[1], array('ALL', 'GAS')))
					$names['ADJ']['GAS'] = $tag[2];
			}
		}
		if (!isset($names[$type]))
			return '';
		if (!isset($names[$type][$state]))
			return '';
		return $names[$type][$state];
	}

	// Internal function used by cvariation, inserts new tags into the list at a particular offset
	private static function cvariation_merge (&$output, &$out_pad, &$insert, &$insert_pad, $insert_offset)
	{
		if ($insert_offset == -1)
		{
			// splice can't actually append to the end of the array
			$output = array_merge($output, $insert);
			$out_pad = array_merge($out_pad, $insert_pad);
		}
		else
		{
			array_splice($output, $insert_offset, 0, $insert);
			array_splice($out_pad, $insert_offset, 0, $insert_pad);
		}
		$insert = array();
		$insert_pad = array();
	}

	// Parses a creature variation to produce composite raws
	public static function cvariation (&$parser, $data = '', $base = ''/*, ...*/)
	{
		$variations = array();
		for ($i = 3; $i < func_num_args(); $i++)
			$variations[] = func_get_arg($i);

		$insert_offset = -1;
		$insert_pad = array();
		$insert = array();

		$var_pad = array();
		$vardata = array();

		$out_pad = array();
		$output = array();

		$in_pad = array();
		$input = self::getTags($data, '', $in_pad);

		// remove object header tag so new tags don't get inserted in front of it
		$start = array_shift($input);
		$start_pad = array_shift($in_pad);

		foreach ($input as $x => $tag)
		{
			$padding = $in_pad[$x];
			switch ($tag[0])
			{
			case 'COPY_TAGS_FROM':
				$base_pad = array();
				// get the base creature, making sure to apply variations as well
				$basedata = self::getTags(call_user_func_array(array('DFRawFunctions', 'cvariation'), array_merge(array(&$parser, self::raw($parser, $base, 'CREATURE', $tag[1]), $base), $variations)), '', $base_pad);
				// discard the object definition
				array_shift($basedata);
				array_shift($base_pad);
				$output = array_merge($output, $basedata);
				$out_pad = array_merge($out_pad, $base_pad);
				break;
			case 'APPLY_CREATURE_VARIATION':
				// if any CV_* tags were entered already, append this to them
				$vardata = array_merge($vardata, self::getTags(self::raw_mult($parser, $variations, 'CREATURE_VARIATION', $tag[1]), '', $var_pad, array_slice($tag, 2)));
			case 'APPLY_CURRENT_CREATURE_VARIATION':
				// parse the creature variation and apply it to the output so far
				foreach ($vardata as $y => $vartag)
				{
					$varpad = $var_pad[$y];
					$cv_tag = array_shift($vartag);
					$varlen = count($vartag);
					switch ($cv_tag)
					{
					case 'CV_NEW_TAG':
					case 'CV_ADD_TAG':
						$insert[] = $vartag;
						$insert_pad[] = $varpad;
						break;
					case 'CV_REMOVE_TAG':
						$adjust = 0;
						foreach ($output as $z => $outtag)
						{
							if (array_slice($outtag, 0, $varlen) == $vartag)
							{
								if ($z < $insert_offset)
									$adjust++;
								unset($output[$z]);
								unset($out_pad[$z]);
							}
						}
						// reset indices
						$output = array_merge($output);
						$out_pad = array_merge($out_pad);
						$insert_offset -= $adjust;
						break;
					case 'CV_CONVERT_TAG':
						$conv = array();
						break;
					case 'CVCT_MASTER':
						foreach ($output as $z => $outtag)
						{
							if ($outtag[0] == $vartag[0])
							{
								$conv[] = $z;
								break;
							}
						}
						break;
					case 'CVCT_TARGET':
						$conv_from = ':'. implode(':', $vartag) .':';
						break;
					case 'CVCT_REPLACEMENT':
						$conv_to = ':'. implode(':', $vartag) .':';
						foreach ($conv as $z)
						{
							$conv_data = str_replace($conv_from, $conv_to, implode(':', $output[$z]) .':');
							$output[$z] = explode(':', trim($conv_data, ':'));
						}
						break;
					}
				}
				self::cvariation_merge($output, $out_pad, $insert, $insert_pad, $insert_offset);
				// then clear the variation buffer
				$var_pad = array();
				$vardata = array();
				// reset to inserting at the end
				$insert_offset = -1;
				break;
			case 'GO_TO_START':
				self::cvariation_merge($output, $out_pad, $insert, $insert_pad, $insert_offset);
				$insert_offset = 0;
				break;
			case 'GO_TO_END':
				self::cvariation_merge($output, $out_pad, $insert, $insert_pad, $insert_offset);
				$insert_offset = -1;
				break;
			case 'GO_TO_TAG':
				self::cvariation_merge($output, $out_pad, $insert, $insert_pad, $insert_offset);
				// if we don't actually find the tag, then insert at the end
				$insert_offset = -1;
				$taglen = count($tag) - 1;
				foreach ($output as $z => $outtag)
				{
					if ($outtag == array_slice($tag, 1, $taglen))
					{
						$insert_offset = $z;
						break;
					}
				}
				break;
			case 'CV_NEW_TAG':
			case 'CV_ADD_TAG':
			case 'CV_REMOVE_TAG':
			case 'CV_CONVERT_TAG':
			case 'CVCT_MASTER':
			case 'CVCT_TARGET':
			case 'CVCT_REPLACEMENT':
				$vardata[] = $tag;
				$var_pad[] = $padding;
				break;
			default:
				$insert[] = $tag;
				$insert_pad[] = $padding;
				break;
			}
		}
		// Merge any remaining tags
		self::cvariation_merge($output, $out_pad, $insert, $insert_pad, $insert_offset);

		// prepend object header tag
		array_unshift($output, $start);
		array_unshift($out_pad, $start_pad);

		foreach ($output as $x => &$data)
			$data = $out_pad[$x] .'['. implode(':', $data) .']';
		return implode("\n", $output);
	}

	// Performs multiple string replacements
	public static function mreplace (&$parser, $data = ''/*, ...*/)
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

	// Takes parameters and encodes them as an unevaluated template transclusion
	// Best used with 'evaluate' below
	public static function delay (&$parser/*, ...*/)
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

	// equivalent of lastIndexOf, search backwards for needle in haystack and return its position
	private static function rstrpos ($haystack, $needle, $offset)
	{
		$size = strlen($haystack);
		$pos = strpos(strrev($haystack), $needle, $size - $offset);
		if ($pos === false)
			return false;
		return $size - $pos;
	}
}
