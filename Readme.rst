#############
DFRawFunction
#############

============
Introduction
============

DFRawFunctions is a MediaWiki extension designed for parsing Dwarf
Fortress raws.

.. contents::


df_raw
------
Searches through a raw file and returns raws for a specific object. If
only "data" is specified, the entire contents of the raw file are
returned.

Usage: {{#df_raw:data|object|id|notfound}}
Parameters:
* data
- Either a filename (of the format "namespace:raw_file.txt") or its
  contents.
* object
- The object type to search for.
* id
- The ID of the object you are searching for. Objects begin with
  "[object:id]" (e.g. [INORGANIC:SANDSTONE] or [CREATURE:DWARF])
* notfound
- The string to be returned if the specified entity could not be located.

Example: {{#df_raw:DF2012:creature_standard.txt|CREATURE|DWARF|Not found!}}


df_tag
------
Checks if a particular tag exists, optionally with a specific token at
a specific offset. Returns 1 if found, otherwise returns nothing.

Usage: {{#df_tag:data|type|offset|entry}}
Parameters:
* data
- The raws for a single object.
* type
- The tag type you are searching for.
* offset
- Optional, specifies an offset to check for a specific value.
* entry
- Optional, specifies the actual value to look for at the above offset.

Example: {{#df_tag:[dwarf raws]|PERSONALITY|1|IMMODERATION}}


df_tagentry
-----------
Finds the Nth tag of the specified type, with any number of specific
tokens at specific offsets, and returns the token at the specified
offset.

Usage: {{#df_tagentry:data|type|num|offset|notfound|matches...}}
Parameters:
* data
- The raws for a single object.
* type
- The tag type you are searching for.
* num
- The instance of the tag you want to fetch. Specify a negative number
  to count from the end.
* offset
- The offset of the token to be returned. Specify a pair of numbers
  separated by colons in order to return a range of tokens (also
  separated by colons).
* notfound
- The string to be returned if the specified entity could not be
  located.
* matches
- Zero or more match conditions. Match conditions are of the format
  "offset:value". Only the Nth tag which satisfies all match conditions
  will be returned.

Example: {{#df_tagentry:[dwarf raws]|PERSONALITY|0|2:4|Unknown!|1:IMMODERATION}}


df_tagvalue
-----------
Finds the Nth tag of the specified type and returns all of its values,
separated by colons.

Usage: {{#df_tagvalue:data|type|num|notfound}}
Parameters:
* data
- The raws for a single object.
* type
- The tag type you are searching for.
* num
- The instance of the tag you want to fetch. Specify a negative number
  to count from the end.
* notfound
- The string to be returned if the specified entity could not be
  located.

Example: {{#df_tagentry:[dwarf raws]|BODY_SIZE|0|Unknown!}}


df_foreachtag
-------------
Iterates across all tags of the specified type and outputs a formatted
string for each one.

Usage: {{#df_foreachtag:data|type|string}}
Parameters:
* data
- The raws for a single object.
* type
- The tag type you are searching for.
* string
- A format string into which token values can be substituted using \1,
  \2, ..., \9. The first parameter is the tag name itself. Currently
  does not support more than 9 parameters.

Example: {{#df_foreachtag:[stone raws]|ENVIRONMENT_SPEC|"\2"}}


df_foreachtoken
---------------
Iterates across a set of tokens in specific groups and outputs a
formatted string for each one.

Usage: {{#df_foreachtoken:data|offset|group|string}}
Parameters:
* data
- A colon-separated list of values, usually the output from
  df_tagvalue.
* offset
- How many tokens to ignore from the beginning of the list.
* group
- How many tokens should be parsed at once.
* string
- A format string into which token values can be substituted using \1,
  \2, ..., \9. Currently does not support more than 9 parameters.

Example: {{#df_foreachtoken:
           {{#df_tagvalue:[dwarf raws]|TL_COLOR_MODIFIER|0}}
         |0|2|"\1"}}


df_makelist
-----------
Iterates across all objects in a single raw file and outputs a string
for each one.

Usage: {{#df_makelist:data|object|string|extracts...}}
Parameters:
* data
- Either a filename (of the format "namespace:raw_file.txt") or its
  contents.
* object
- The object type to iterate across.
* string
- A format string into which values can be substituted using \1, \2,
  ..., \9. Currently does not support more than 9 parameters.
* extracts
- Zero or more token extraction parameters. Extraction parameters are
  of the format "type:offset:checkoffset:checkvalue", where the first
  matching tag of "type" will return the token at "offset" if the token
  at "checkoffset" has the value "checkvalue". If "checkoffset" is set
  to -1, the checkvalue is ignored.
- For material definitions, the format "STATE:type:state" can also be
  used, where "type" and "state" are fed into df_statedesc below.
- The order in which the extraction parameters are defined will
  determine the substitution values used - the first will use \1, the
  second will use \2, etc.

Example: {{#df_makelist:[all stone raws]|INORGANIC|"\2 \1"|
           ENVIRONMENT_SPEC:2:1:MAGNETITE|STATE:NAME:SOLID}}


df_statedesc
------------
Parses a material definition and returns its name for a particular
state.

Usage: {{#df_statedesc:data|type|state}}
Parameters:
* data
- The raws for a single material.
* type
- Either NAME or ADJ, to specify whether the noun or adjective form
  should be returned.
* state
- The state type whose name should be returned. Valid values are SOLID,
  POWDER, PASTE, PRESSED, LIQUID, and GAS.

Example: {{#df_statedesc:[stone raw]|NAME|SPOLID}}


df_cvariation
-------------
Parses a creature entry and decodes variation information.

Usage: {{#df_cvariation:data|base|variation...}}
Parameters:
* data
- The raws for a single creature.
* base
- The raw file which contains the "base" creature - either a filename
  (of the format "namespace:raw_file.txt") or its contents.
* variation...
- One or more raw files which contain creature variation data - either
  filenames (of the format "namespace:raw_file.txt") or their contents.

Example: {{#df_cvariation:
           {{#df_raw:DF2012:creature_large_temperate.txt|
             CREATURE|BADGER, GIANT}}|
           DF2012:creature_large_temperate.txt|
           DF2012:cvariation_default.txt}}


mreplace
--------
Performs multiple simple string replacements on the data specified.

Usage: {{#mreplace:data|from|to|from|to|...}}

delay
-----
Returns "{{parm1|parm2|parm3|...}}", intended for delayed evaluation of
templates and parser functions when used with df_foreachtag,
df_foreachtoken, and df_makelist.

Usage: {{#delay:parm1|parm2|parm3|...}}

eval
----
Evaluates all parser functions and template calls in the specified
data. Intended for usage with df_foreachtag, df_foreachtoken, and
df_makelist.

Usage: {{#eval:data}}

df_type
----
usage: {{#df_type:data|object|requirement|type|number|description}}

Finds any object with filled requirement. Returns type.
Number could be:
	"-1"		returns the very last input with fulfilled requirements and Type
	""			returns whole list of Types, numbered and comma separated
	"N"			returns reaction number N, no formatting
	"N:FORMAT" 	returns reaction number N, wiki table formatting and Description
	"N:CHECK"	checks if Nth Type is the last one, returns error if it's not.
	
Input:  {{#df_type:Masterwork:reaction_kobold.txt|REACTION|BUILDING:BONEYARD_KOBOLD|NAME|1:FORMAT|[[Vermin]] in not useless.}}
Output: 

df_keybind
----
Makes readable keybind raws. from "CUSTOM_SHIFT_ALT_CTRL_S".

Input:  {{#df_keybind:CUSTOM_SHIFT_ALT_CTRL_S}}
Output: Alt+Ctrl+S

df_tile
----

