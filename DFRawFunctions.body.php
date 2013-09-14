<?php

ini_set('display_errors', 'On');
error_reporting(E_ALL);

class DFRawFunctions
{
	// Takes some raws and returns a 2-dimensional token array
	// If 2nd parameter is specified, then only tags of the specified type will be returned
	// Optional 3rd parameter allows specifying an array which will be filled with indentation for each line
	private static function getTags ($data, $type = '', &$padding = array())
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
			$tag = explode(':', substr($data, $start + 1, $end - $start - 1));
			if (($type == '') || ($tag[0] == $type))
			{
				$padding[] = $pad;
				$raws[] = $tag;
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
		if (!is_dir($wgDFRawPath))
			return $data;

		$filename = explode(':', $data, 2);
		if (count($filename) != 2)
			return $data;
		$filename = str_replace(array('/', '\\'), '', $filename);

		$wantfile = $wgDFRawPath .'/'. $filename[0] .'/'. $filename[1];

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
				$basedata = self::getTags(self::raw($parser, $base, 'CREATURE', $tag[1]), '', $base_pad);
				// discard the object definition
				array_shift($basedata);
				array_shift($base_pad);
				$output = array_merge($output, $basedata);
				$out_pad = array_merge($out_pad, $base_pad);
				break;
			case 'APPLY_CREATURE_VARIATION':
				// if any CV_* tags were entered already, append this to them
				$vardata = array_merge($vardata, self::getTags(self::raw_mult($parser, $variations, 'CREATURE_VARIATION', $tag[1]), '', $var_pad));
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
	
	/* 
	Input is: 1|2|3|4|5|6
	1) Data location:
			Masterwork:reaction_kobold.txt
	2) Object:
			"REACTION"
	3) Requirement: checks if those are present in Object
			"BUILDING:TANNER"
		or	"BUILDING"
	4) Type (inputs the following value if requirements are met):
			"NAME"
	inputs	"craft bone shovel"
	5) Number:
		1.	"-1"		returns the very last input with fulfilled requirements and Type
		2.	""			returns whole list of Types, numbered and comma separated
		3.	"N"			returns reaction number N, no formatting
		4.	"FORMAT" 	returns reaction number N, wiki table formatting and Description
		5.	"CHECK"		checks if Nth Type is the last one, returns error if it's not.
		6.  "ORDER"		compares first tags with Object, otherwise searches through every tag.
	6) Description: description for wiki, works only with "N:FORMAT"
	*/

	public static function getType (&$parser, $data = '', $object = '', $requirement = '', $l_type = '', $number = '',  $description = '')
	{
	
		// checks for errors and extracts from $number: number of reaction, options (ORDER, CHECK, FORMAT).
		$number=explode(":",$number);
		$Order = FALSE; $Check = FALSE; $Format = FALSE; $FirstOnly = FALSE; $Doubles = FALSE; $Number=''; 
		if ($number!='' and gettype($number)!="integer" and is_array($number))
		{
			foreach ($number as &$tmp)
			{	
				//echo "<br/>".$number;
				switch ($tmp)
				{
					case "ORDER":
						$Order = TRUE; break;
					case "CHECK":
						$Check = TRUE; break;
					case "FORMAT":
						$Format = TRUE; break;
					case "FIRST_ONLY":
						$FirstOnly = TRUE; break;
					case "DOUBLES":
						$Doubles = TRUE; break;
				}
				
				if (is_numeric($tmp))
					$Number=$tmp;
				if (!is_numeric($tmp) and $tmp!="ORDER" and $tmp!="CHECK" and $tmp!="FORMAT" and $tmp!='' and $tmp!="FIRST_ONLY" and $tmp!="DOUBLES")
					return '<span style="color:#ff0000">Error, check input values for getType</span>';
			}unset ($tmp);
		}
		if (is_numeric($number)){$Number=$number;}
		//echo "<br/>".$requirement.": ".$Number.' | '.$Order.' | '.$Check.' | '.$Format;
		$requirement=explode(":",$requirement); $l_type=explode(":",$l_type);
		$data = self::loadFile($data); $tags = self::getTags($data);
		
		if (!$object)
			return $data;
		$e=0; $i = 0; $obj_numb=0; $return_value = ''; $tmp=array(); $obj_num=0;
		
		while ($i<=(count($tags)-1))
		{
			if ($tags[$i][0]==$object)
			{ 			// Checks if left tag fits OBJECT.
				$obj_num=$obj_num+1; $affirmed_type=FALSE; $i_object=$i; 
			}
			if ($obj_num>0)
			{ 		// Made in case something's wrong with quotes.
				if  (array_diff($requirement, $tags[$i])==array() and $affirmed_type == FALSE) // Checks if TYPE:PARAMETER is present in the OBJECT. Puts flag and leaps back if yes.
				{$affirmed_type = TRUE; $i=$i_object;}
				
				if ($affirmed_type == TRUE)
				{
					$tmp_e=array();
					// advanced requirement check
					if ($Order)
					{
						if (array_intersect_assoc($l_type, $tags[$i])==$l_type)
						{
							$tmp_e=array_slice($tags[$i],count($l_type));
							$tmp[$e]=implode(":",$tmp_e); $e++;
						} 
					}
					else
					{
						$tmp_e=array_diff($tags[$i], $l_type);
						if (count($tmp_e) != count($tags[$i]))
						{
							$tmp[$e]=implode(":",$tmp_e); $e++;
						}
					}
				}
			}
			// Weird syntax, just extracts first value. 
			if ($FirstOnly === TRUE and $e !== 0){
			$tmp[$e-1]=(explode (':',$tmp[$e-1]));
			$tmp[$e-1]=$tmp[$e-1][0];}
			
			$i++; 
		}
		if ($Doubles === TRUE)
		{
			if	($tmp != array_unique($tmp)){
			return '<span style="color:#ff0000">Output contains doubles!</span>';}
			else {return '';}
		}
		
		$step='';
		if (($l_type[0]=="BUILDING" and isset($l_type[1]))or($l_type[0]=="BUILD_KEY")){
			foreach ($tmp as &$step)
			$step = self::getKeybind($parser, $step);
			}
			
		if ($Check and $Number === ''){return "''' There is ".count(array_unique($tmp))." ".implode(":",$l_type)."s in total.'''";}
		if ($Number === '') 
			return implode(", ",array_unique($tmp));
		if ($Number == -1)
			return "Last reaction of the TYPE is: '''". ($e-1) .". ". $tmp[$e-1] .".'''";
		if ($Number != ($e-1) and $Check)
			return "'''".'<span style="color:#ff0000">Error: Last '.implode(":",$l_type).' is '.($e-1)." and not ". $Number.".</span>'''";
		if ($Format)
			return "'''".($Number).". ". $tmp[$Number] ."''' || " .$description;
		//otherwise
		return $tmp[$Number];
					
	}			
	
	
	
	// Makes "Att+Ctrl+S" from "CUSTOM_SHIFT_ALT_CTRL_S".
	public static function getKeybind (&$parser, $custom=''){
		$custom=explode("_",$custom); $tmp=$custom[count($custom)-1]; $custom[count($custom)-1]='CUSTOM';
		if (array_diff($custom, array("CUSTOM", "ALT", "SHIFT", "CTRL", "NONE"))!=FALSE){return'<span style="color:#ff0000">Error, check input values for getKeybind</span>';}
		if (in_array("SHIFT",$custom)===FALSE)
			$tmp=(strtolower($tmp));
		if (in_array("ALT",$custom))
			$tmp="Alt+". $tmp;
		if (in_array("CTRL",$custom))
			$tmp="Ctrl+". $tmp;
		if (in_array("NONE",$custom))
			$tmp='';
		return $tmp;
	}
	
	
	
	/*###DF_BUILDING### Provides information about workshops and furnaces. 
	building - should be either workshop or furnace with syntax as follows:  "BUILDING_FURNACE:MAGMA_GENERATOR" or "NAME:Magma Generator (Dwarf)".
	options - DIM returns dimensions, TILE:N returns tiles as xHTML table */
	public static function getBuilding (&$parser, $data = '', $building = '', $options = '')
	{
		// Defining variables and input check
		$tags = array(); $dim = array(); $block = array(); $color = array(); $tile = array(); $j = 0; $i = 0; $type_check = 0; $single_tag=array(); $single_tag_counter=0; $item_counter=-1;
		$tags = self::getTags(self::loadFile($data));  $building=explode(":",$building); 
		
		// $options input check
		if ($options!="DIM")
		{
		$options=explode(":",$options);
		$building_stage = implode(':',array_intersect(array(0,1,2,3),$options)); if ($building_stage===''){$building_stage=3;}
		$options_err_check=array("TILE","COLOR","DIM",0,1,2,3,"WORK_LOCATION","BUILD_ITEM");
		if (array_diff($options, $options_err_check)!=FALSE 
			or count($building)!=2)
		return '<span style="color:#ff0000">Error, check input values!</span>';
		}
		
		// Extract arrays: dim (workshop dimensions), work_location, block, tile, color, item, single_tag from tags.
		while ($i<=(count($tags)-1)){
			if ($type_check == 0 and $tags[$i][0] == $building[0] and $tags[$i][1]==$building[1])
				$type_check = 1;
			
			if ($type_check == 1)
			{
				switch ($tags[$i][0]){
					case "DIM" :
						$dim=array_slice($tags[$i],1,3); 
						break;
					case "WORK_LOCATION": 
						$work_location=$tags[$i][1]."&#x2715".$tags[$i][2];
						break;
					case "BLOCK": 
						$block[$tags[$i][1]]=array_slice($tags[$i],2);
						break;
					case "TILE": 
						$tile[$tags[$i][1]][$tags[$i][2]]=array_slice($tags[$i],3);
						break;
					case "COLOR": 
						$color[$tags[$i][1]][$tags[$i][2]]=array_slice($tags[$i],3);
						break;
					case "BUILD_ITEM": 
						$item_counter=$item_counter+1; $single_tag_counter=0;
						$item[$item_counter]=array_slice($tags[$i],1);
						break;
				}
				if (count($tags[$i])==1 and $item_counter >= 0){
				$single_tag[$item_counter][$single_tag_counter].= $tags[$i][0]; 
				// echo ($item_counter." ".$single_tag_counter." ". $single_tag[$item_counter][$single_tag_counter]." ");
				$single_tag_counter++; }
			
				// Breaks per-tile extraction if next object
				if ($tags[$i][0]==$building[0] and $tags[$i][1]!=$building[1])
					break;
			}
			$i++;	
			
		}
		// ### Return dimensions
		if (in_array("DIM",$options))
			return implode("&#x2715;",$dim);
		
		// ### Return tile or colored tile
		if (in_array("TILE",$options) or in_array("COLOR",$options))
		{
			$tmp='';
			for ($j = 1; $j <= ($dim[1]-1); $j++) // Turn array into string.
			$tmp .= implode(":",$tile[$building_stage][$j])."<br/>";
			$tmp .= implode(":",$tile[$building_stage][$dim[1]]);
			$tile=$tmp;
		//echo '<br/>TILE='. $tile;
		//echo "<br/>Building stage =". $building_stage;
			if (in_array("COLOR",$options))
			{
				$tmp='';
				for ($j = 1; $j <= ($dim[1]-1); $j++) // Turn array into string.
				$tmp .= implode(":",$color[$building_stage][$j])."<br/>";
				$tmp .= implode(":",$color[$building_stage][$dim[1]]);
				$color=$tmp;
				$tile_color=self::colorTile($parser, $tile, $color);
				//echo '<br/>COLOR='. $color;
			}
			if (!in_array("COLOR",$options)){$tile_color=self::colorTile($parser, $tile);}
		}
		return $tile_color;
		
		// ### Return items
		if (in_array("ITEM",$options))
		{
			$tmp='';
			for ($j = 1; $j <= (count($item)-2); $j++) // Turn array into string.
			$tmp .= implode(":",$item[$j])."<br/>";
			$tmp .= implode(":",$item[count($item)-1]);
			$tile=$tmp;
		}
	}
	
	
	
	// ###### Returns item parameters based on whatever.
	public static function getItem (&$parser, $item = '', $options = '')
	{	
	
		$tags=array(); $i=0;
		$options=implode(':',$options);
		$tags = self::getTags(self::loadFile($data));
		while ($i<=(count($tags)-1)){
		$i++;
		}
	}
	
	
	
	// returns html values of tile and color taken meaningful strings from raws (without building stage and line)
	// meant to be used in par with
	public static function colorTile (&$parser, $tile='', $color='', $image='', $step='')
	{	
		if (($image !=='' and $step==='')or($image ==='' and $step!=='')){return '<span style="color:#ff0000">Either image or step are missing!</span>';}
		if (gettype($tile)!=="string" and $tile!==''){return '<span style="color:#ff0000">Tile for df_ile must be string!</span>';}
		if (gettype($color)!=="string" and $color!==''){
		return '<span style="color:#ff0000">Color for df_ile must be string!</span>';}
		//echo "<br/>TILE (in funct)=".$tile;
		
		// -----TILE ONLY-----
		if ($tile!=='')
		{
			$conv_unicode=explode(" "," &#x263A; &#x263B; &#x2665; &#x2666; &#x2663; &#x2660; &#x2022; &#x25D8; &#x25CB; &#x25D9; &#x2642; &#x2640; &#x266A; &#x266B; &#x263C; &#x25BA; &#x25C4; &#x2195; &#x203C; &#x00B6; &#x00A7; &#x25AC; &#x21A8; &#x2191; &#x2193; &#x2192; &#x2190; &#x221F; &#x2194; &#x25B2; &#x25BC; &nbsp; &#x0021; &#x0022; &#x0023; &#x0024; &#x0025; &#x0026; &#x0027; &#x0028; &#x0029; &#x002A; &#x002B; &#x002C; &#x002D; &#x002E; &#x002F; &#x0030; &#x0031; &#x0032; &#x0033; &#x0034; &#x0035; &#x0036; &#x0037; &#x0038; &#x0039; &#x003A; &#x003B; &#x003C; &#x003D; &#x003E; &#x003F; &#x0040; &#x0041; &#x0042; &#x0043; &#x0044; &#x0045; &#x0046; &#x0047; &#x0048; &#x0049; &#x004A; &#x004B; &#x004C; &#x004D; &#x004E; &#x004F; &#x0050; &#x0051; &#x0052; &#x0053; &#x0054; &#x0055; &#x0056; &#x0057; &#x0058; &#x0059; &#x005A; &#x005B; &#x005C; &#x005D; &#x005E; &#x005F; &#x0060; &#x0061; &#x0062; &#x0063; &#x0064; &#x0065; &#x0066; &#x0067; &#x0068; &#x0069; &#x006A; &#x006B; &#x006C; &#x006D; &#x006E; &#x006F; &#x0070; &#x0071; &#x0072; &#x0073; &#x0074; &#x0075; &#x0076; &#x0077; &#x0078; &#x0079; &#x007A; &#x007B; &#x007C; &#x007D; &#x007E; &#x2302; &#x00C7; &#x00FC; &#x00E9; &#x00E2; &#x00E4; &#x00E0; &#x00E5; &#x00E7; &#x00EA; &#x00EB; &#x00E8; &#x00EF; &#x00EE; &#x00EC; &#x00C4; &#x00C5; &#x00C9; &#x00E6; &#x00C6; &#x00F4; &#x00F6; &#x00F2; &#x00FB; &#x00F9; &#x00FF; &#x00D6; &#x00DC; &#x00A2; &#x00A3; &#x00A5; &#x20A7; &#x0192; &#x00E1; &#x00ED; &#x00F3; &#x00FA; &#x00F1; &#x00D1; &#x00AA; &#x00BA; &#x00BF; &#x2310; &#x00AC; &#x00BD; &#x00BC; &#x00A1; &#x00AB; &#x00BB; &#x2591; &#x2592; &#x2593; &#x2502; &#x2524; &#x2561; &#x2562; &#x2556; &#x2555; &#x2563; &#x2551; &#x2557; &#x255D; &#x255C; &#x255B; &#x2510; &#x2514; &#x2534; &#x252C; &#x251C; &#x2500; &#x253C; &#x255E; &#x255F; &#x255A; &#x2554; &#x2569; &#x2566; &#x2560; &#x2550; &#x256C; &#x2567; &#x2568; &#x2564; &#x2565; &#x2559; &#x2558; &#x2552; &#x2553; &#x256B; &#x256A; &#x2518; &#x250C; &#x2588; &#x2584; &#x258C; &#x2590; &#x2580; &#x03B1; &#x00DF; &#x0393; &#x03C0; &#x03A3; &#x03C3; &#x00B5; &#x03C4; &#x03A6; &#x0398; &#x03A9; &#x03B4; &#x221E; &#x03C6; &#x03B5; &#x2229; &#x2261; &#x00B1; &#x2265; &#x2264; &#x2320; &#x2321; &#x00F7; &#x2248; &#x00B0; &#x2219; &#x00B7; &#x221A; &#x207F; &#x00B2; &#x25A0;");
			$tmp='';
			$dim_1=substr_count($tile,"<br/>")+1;
			$tile=str_replace("<br/>",":",$tile);
			$tile=explode(":",$tile);
			if ($image!==''){$tile_reserve=$tile;}
			$dim_0=count($tile)/$dim_1; $dim_check=array($dim_0,$dim_1);
			for ($i = 1; $i <= $dim_1; $i++)
			{
				for ($j = 1; $j <= $dim_0; $j++)
				{
					$tmp .= $conv_unicode[$tile[($i-1)*$dim_0+$j-1]]." "; 
					if ($j==$dim_0) {$tmp .= "<br/>";}
				}
			}
			$tile=$tmp;
		}
		
		if ($color!=='')
		{
			$conv_color_foregr=array("0:0" => "#000000", "1:0" => "#000080", "2:0" => "#008000", "3:0" => "#008080", "4:0" => "#800000", "5:0" => "#800080", "6:0" => "#808000", "7:0" => "#C0C0C0", "0:1" => "#808080", "1:1" => "#0000FF", "2:1" => "#00FF00", "3:1" => "#00FFFF", "4:1" => "#FF0000", "5:1" => "#FF00FF", "6:1" => "#FFFF00", "7:1" => "#FFFFFF", "0:0:0" => "#000000", "1:0:0" => "#000080", "2:0:0" => "#008000", "3:0:0" => "#008080", "4:0:0" => "#800000", "5:0:0" => "#800080", "6:0:0" => "#808000", "7:0:0" => "#C0C0C0", "0:0:1" => "#808080", "1:0:1" => "#0000FF", "2:0:1" => "#00FF00", "3:0:1" => "#00FFFF", "4:0:1" => "#FF0000", "5:0:1" => "#FF00FF", "6:0:1" => "#FFFF00", "7:0:1" => "#FFFFFF", " 0:1:0" => "#000000", "1:1:0" => "#000080", "2:1:0" => "#008000", "3:1:0" => "#008080", "4:1:0" => "#800000", "5:1:0" => "#800080", "6:1:0" => "#808000", "7:1:0" => "#C0C0C0", "0:1:1" => "#808080", "1:1:1" => "#0000FF", "2:1:1" => "#00FF00", "3:1:1" => "#00FFFF", "4:1:1" => "#FF0000", "5:1:1" => "#FF00FF", "6:1:1" => "#FFFF00", "7:1:1" => "#FFFFFF", " 0:2:0" => "#000000", "1:2:0" => "#000080", "2:2:0" => "#008000", "3:2:0" => "#008080", "4:2:0" => "#800000", "5:2:0" => "#800080", "6:2:0" => "#808000", "7:2:0" => "#C0C0C0", "0:2:1" => "#808080", "1:2:1" => "#0000FF", "2:2:1" => "#00FF00", "3:2:1" => "#00FFFF", "4:2:1" => "#FF0000", "5:2:1" => "#FF00FF", "6:2:1" => "#FFFF00", "7:2:1" => "#FFFFFF", " 0:3:0" => "#000000", "1:3:0" => "#000080", "2:3:0" => "#008000", "3:3:0" => "#008080", "4:3:0" => "#800000", "5:3:0" => "#800080", "6:3:0" => "#808000", "7:3:0" => "#C0C0C0", "0:3:1" => "#808080", "1:3:1" => "#0000FF", "2:3:1" => "#00FF00", "3:3:1" => "#00FFFF", "4:3:1" => "#FF0000", "5:3:1" => "#FF00FF", "6:3:1" => "#FFFF00", "7:3:1" => "#FFFFFF", " 0:4:0" => "#000000", "1:4:0" => "#000080", "2:4:0" => "#008000", "3:4:0" => "#008080", "4:4:0" => "#800000", "5:4:0" => "#800080", "6:4:0" => "#808000", "7:4:0" => "#C0C0C0", "0:4:1" => "#808080", "1:4:1" => "#0000FF", "2:4:1" => "#00FF00", "3:4:1" => "#00FFFF", "4:4:1" => "#FF0000", "5:4:1" => "#FF00FF", "6:4:1" => "#FFFF00", "7:4:1" => "#FFFFFF", " 0:5:0" => "#000000", "1:5:0" => "#000080", "2:5:0" => "#008000", "3:5:0" => "#008080", "4:5:0" => "#800000", "5:5:0" => "#800080", "6:5:0" => "#808000", "7:5:0" => "#C0C0C0", "0:5:1" => "#808080", "1:5:1" => "#0000FF", "2:5:1" => "#00FF00", "3:5:1" => "#00FFFF", "4:5:1" => "#FF0000", "5:5:1" => "#FF00FF", "6:5:1" => "#FFFF00", "7:5:1" => "#FFFFFF", " 0:6:0" => "#000000", "1:6:0" => "#000080", "2:6:0" => "#008000", "3:6:0" => "#008080", "4:6:0" => "#800000", "5:6:0" => "#800080", "6:6:0" => "#808000", "7:6:0" => "#C0C0C0", "0:6:1" => "#808080", "1:6:1" => "#0000FF", "2:6:1" => "#00FF00", "3:6:1" => "#00FFFF", "4:6:1" => "#FF0000", "5:6:1" => "#FF00FF", "6:6:1" => "#FFFF00", "7:6:1" => "#FFFFFF", "0:7:0" => "#000000", "1:7:0" => "#000080", "2:7:0" => "#008000", "3:7:0" => "#008080", "4:7:0" => "#800000", "5:7:0" => "#800080", "6:7:0" => "#808000", "7:7:0" => "#C0C0C0", "0:7:1" => "#808080", "1:7:1" => "#0000FF", "2:7:1" => "#00FF00", "3:7:1" => "#00FFFF", "4:7:1" => "#FF0000", "5:7:1" => "#FF00FF", "6:7:1" => "#FFFF00", "7:7:1" => "#FFFFFF");
			
			$conv_color_backgr=array("0:0" => "#000000", "1:0" => "#000000", "2:0" => "#000000", "3:0" => "#000000", "4:0" => "#000000", "5:0" => "#000000", "6:0" => "#000000", "7:0" => "#000000", "0:1" => "#000000", "1:1" => "#000000", "2:1" => "#000000", "3:1" => "#000000", "4:1" => "#000000", "5:1" => "#000000", "6:1" => "#000000", "7:1" => "#000000", "0:0:0" => "#000000", "1:0:0" => "#000000", "2:0:0" => "#000000", "3:0:0" => "#000000", "4:0:0" => "#000000", "5:0:0" => "#000000", "6:0:0" => "#000000", "7:0:0" => "#000000", "0:0:1" => "#000000", "1:0:1" => "#000000", "2:0:1" => "#000000", "3:0:1" => "#000000", "4:0:1" => "#000000", "5:0:1" => "#000000", "6:0:1" => "#000000", "7:0:1" => "#000000", "0:1:0" => "#000080", "1:1:0" => "#000080", "2:1:0" => "#000080", "3:1:0" => "#000080", "4:1:0" => "#000080", "5:1:0" => "#000080", "6:1:0" => "#000080", "7:1:0" => "#000080", "0:1:1" => "#000080", "1:1:1" => "#000080", "2:1:1" => "#000080", "3:1:1" => "#000080", "4:1:1" => "#000080", "5:1:1" => "#000080", "6:1:1" => "#000080", "7:1:1" => "#000080", "0:2:0" => "#008000", "1:2:0" => "#008000", "2:2:0" => "#008000", "3:2:0" => "#008000", "4:2:0" => "#008000", "5:2:0" => "#008000", "6:2:0" => "#008000", "7:2:0" => "#008000", "0:2:1" => "#008000", "1:2:1" => "#008000", "2:2:1" => "#008000", "3:2:1" => "#008000", "4:2:1" => "#008000", "5:2:1" => "#008000", "6:2:1" => "#008000", "7:2:1" => "#008000", "0:3:0" => "#008080", "1:3:0" => "#008080", "2:3:0" => "#008080", "3:3:0" => "#008080", "4:3:0" => "#008080", "5:3:0" => "#008080", "6:3:0" => "#008080", "7:3:0" => "#008080", "0:3:1" => "#008080", "1:3:1" => "#008080", "2:3:1" => "#008080", "3:3:1" => "#008080", "4:3:1" => "#008080", "5:3:1" => "#008080", "6:3:1" => "#008080", "7:3:1" => "#008080", "0:4:0" => "#800000", "1:4:0" => "#800000", "2:4:0" => "#800000", "3:4:0" => "#800000", "4:4:0" => "#800000", "5:4:0" => "#800000", "6:4:0" => "#800000", "7:4:0" => "#800000", "0:4:1" => "#800000", "1:4:1" => "#800000", "2:4:1" => "#800000", "3:4:1" => "#800000", "4:4:1" => "#800000", "5:4:1" => "#800000", "6:4:1" => "#800000", "7:4:1" => "#800000", "0:5:0" => "#800080", "1:5:0" => "#800080", "2:5:0" => "#800080", "3:5:0" => "#800080", "4:5:0" => "#800080", "5:5:0" => "#800080", "6:5:0" => "#800080", "7:5:0" => "#800080", "0:5:1" => "#800080", "1:5:1" => "#800080", "2:5:1" => "#800080", "3:5:1" => "#800080", "4:5:1" => "#800080", "5:5:1" => "#800080", "6:5:1" => "#800080", "7:5:1" => "#800080", "0:6:0" => "#808000", "1:6:0" => "#808000", "2:6:0" => "#808000", "3:6:0" => "#808000", "4:6:0" => "#808000", "5:6:0" => "#808000", "6:6:0" => "#808000", "7:6:0" => "#808000", "0:6:1" => "#808000", "1:6:1" => "#808000", "2:6:1" => "#808000", "3:6:1" => "#808000", "4:6:1" => "#808000", "5:6:1" => "#808000", "6:6:1" => "#808000", "7:6:1" => "#808000", "0:7:0" => "#C0C0C0", "1:7:0" => "#C0C0C0", "2:7:0" => "#C0C0C0", "3:7:0" => "#C0C0C0", "4:7:0" => "#C0C0C0", "5:7:0" => "#C0C0C0", "6:7:0" => "#C0C0C0", "7:7:0" => "#C0C0C0", "0:7:1" => "#C0C0C0", "1:7:1" => "#C0C0C0", "2:7:1" => "#C0C0C0", "3:7:1" => "#C0C0C0", "4:7:1" => "#C0C0C0", "5:7:1" => "#C0C0C0", "6:7:1" => "#C0C0C0", "7:7:1" => "#C0C0C0");
			$color_backgr=''; $color_foregr='';
			$dim_1=substr_count($color,"<br/>")+1;
			$color=str_replace("<br/>",":",$color);
			if (isset($dim_check) and ($dim_check[0]!==$dim_0 or $dim_check[1]!==$dim_1)){return '<span style="color:#ff0000">Dimension mismatch for color and tile in colorTile.</span>';}
			$color=explode(":",$color);
			$dim_0=count($color)/$dim_1/3;
			for ($i = 1; $i <= ($dim_0*$dim_1); $i++)
			{	
				$tmp=implode(":",array_slice ($color,($i-1)*3, 3));
				$color_backgr .= $conv_color_backgr[$tmp]." ";
				$color_foregr .= $conv_color_foregr[$tmp]." ";
				if ($i % $dim_0 === 0) {$color_backgr .= "<br/>"; $color_foregr .= "<br/>";}
			}
		}
		if ($tile!==''){$tile=explode("<br/>",$tile);}
		if ($color!==''){$color_backgr=explode("<br/>",$color_backgr); $color_foregr=explode("<br/>",$color_foregr);}
		$tile_color='<table /border=0 cellpadding=0 cellspacing=0 style="'."font-size:150%; font-family: 'Courier New', 'Quicktype Mono', 'Bitstream Vera Sans Mono', 'Lucida Console',  'Lucida Sans Typewriter', monospace; font-weight:bold".'"><tr>';
		if ($image){
			for ($i = 1; $i <= ($dim_1); $i++)
			{
				if ($color!==''){$color_backgr_tmp=explode(" ",$color_backgr[$i-1]); $color_foregr_tmp=explode(" ",$color_foregr[$i-1]);}
				
				for ($j = 1; $j <= ($dim_0); $j++)
				{
					$value=$tile_reserve[($i-1)*$dim_0+$j-1];
					$x=$value%16; $y=intval($value/16);
					//echo "<br/>x=".$x.", y=".$y. ", value=".$value;
					
					if ($color==='')
					{
						$tile_color .= 
						'<td><div style="width:16px;height:16px;overflow:hidden;position:relative"><div style="position:relative;top:-'. $y*$step .'px;left:-' .$x*$step. 'px">'. $image .'</div></div>'
						.'</td>';
						//echo "<br/> sum=".($i*$dim_0+$j)." ".$j;
					}
					//<span style="color: '. $color_foregr_tmp[$j-1] .'; background:'. $color_backgr_tmp[$j-1].'">
					if ($tile!=='' and $color!=='')
					{	
						/* echo $color_backgr_tmp[$j-1];
						$tile_color .= 
						'<td><div style="background:'.$color_backgr_tmp[$j-1].';width:'. $step .';height:'. $step .';overflow:hidden;position:relative"><div style="position:relative;top:-'. $y*$step .'px;left:-' .$x*$step. 'px">'. $image .'</div></div></td>'; */
						echo $color_backgr_tmp[$j-1];
						$tile_color .= 
						'<td><div style="background:'.$color_backgr_tmp[$j-1].';width:'. $step .';height:'. $step .';overflow:hidden;position:relative"><div style="position:relative;top:-'. $y*$step .'px;left:-' .$x*$step. 'px">'. $image .'</div></div></td>';
						
					}
					if ($j==$dim_0){$tile_color .='</tr>';}
				}
				
				if (($i!=$dim_1) and ($j!=$dim_0)){$tile_color .='<tr>';
				} else {$tile_color .='</table>';}
			}
			return $tile_color;
		}	
		
		for ($i = 0; $i <= ($dim_1-1); $i++)
		{
			if ($tile!==''){$tile_tmp=explode(" ",$tile[$i]);} 
			if ($color!==''){$color_backgr_tmp=explode(" ",$color_backgr[$i]); $color_foregr_tmp=explode(" ",$color_foregr[$i]);}
			
			for ($j = 0; $j <= ($dim_0); $j++)
			{
			//  padding: 0.1em 0.2em;
				
				if ($color==='')
				{
					$tile_color .= '<td>'.$tile_tmp[$j].'</td>';
				}
				
				if ($tile!=='' and $color!=='')
				{
					$tile_color .= '<td><span style="color: '. $color_foregr_tmp[$j] .'; background:'. $color_backgr_tmp[$j].'">'.$tile_tmp[$j].'</span></td>';
				
				if ($j==$dim_0-1){$tile_color .='</tr>';}
				}
			}
			
			if (($i!=$dim_1-1) and ($j!=$dim_0-1)){$tile_color .='<tr>';
			} else {$tile_color .='</table>';}
		}
		return $tile_color;	
	}
}

/* {{#df_tile:43:222:219:221<br/>
33:214:184:221<br/>
162:211:188:221|7:0:1:6:0:0:6:0:0:6:0:0<br/>7:0:1:0:7:1:0:7:1:6:0:0<br/>7:0:1:0:7:1:0:7:1:6:0:0|[[File:Phoebus 16x16.png|link=]|] */

