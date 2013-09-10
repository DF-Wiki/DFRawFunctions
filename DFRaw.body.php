<?php

ini_set('display_errors', 'On');
error_reporting(E_ALL);

class DFRaw
{
	private static function raw (&$parser, $type = '', $data = '', $object = '', $id = '', $notfound = '')
	{	
		if ($type==''){return '<span style="color:#ff0000">TYPE is mising!</span>';}
		$type=explode(":",$type);
		for ($i=0; $i <= (count($type)-1); $i++)
		{
			switch ($type[$i])
			{
				case "TEST":
				return "SUCCESS";
			}
		}
	
	}	
}