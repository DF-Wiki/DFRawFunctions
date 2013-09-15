#############
DFRawFunction
#############

============
Introduction
============

DFRawFunctions is a MediaWiki extension designed for parsing Dwarf
Fortress raws.

.. contents::

df_type
----
Usage: ``{{#df_type:data|object|requirement|type|number|description}}``

Finds any object with filled requirement. Take a look at examples for additional info.
Number could be:

* *(blank)*	
  - returns whole list of types, numbered and comma separated
* ``-1``
  - returns the very last input with fulfilled requirements and type	
* ``N``
  - returns reaction number N, no formatting
* ``N:FORMAT``
  - returns reaction number N, wiki table formatting and description
* ``CHECK``
  - return last type number (number of reaction for workshop etc.)
* ``N:CHECK``
  - checks if Nth Type is the last one returns error if it's not, returns nothing if number is correct
* ``FIRST_ONLY``
  - returns only first tag after type, if you won't use FIRST_ONLY at example 2, you'll get keybind in par with name
* ``ORDER``
  - type will be compared from the beginning, by default - randomly
* ``DOUBLES``
  - checks for doubles in reaction, returns nothing if no doubles

	
Example 1::

{{#df_type:Masterwork:reaction_kobold.txt|REACTION|BUILDING:BONEYARD_KOBOLD|NAME|1:FORMAT|[[Vermin]] in not useless.}}

Output 1::

'''1. make bone studs(2) from vermin''' || [[Vermin]] in not useless.

Example 2::

{{#df_type:Masterwork:reaction_kobold.txt|REACTION|SKIN_TANNED:REAGENT|BUILDING|CHECK:FIRST_ONLY|}}  
{{#df_type:Masterwork:reaction_kobold.txt|REACTION|SKIN_TANNED:REAGENT|BUILDING|FIRST_ONLY||}}.

Output 2::

'''There is 11 BUILDINGs in total.''' TANNER, LEATHERTRIMMER, SCALECLEANER, CHITINSCRAPPER, SHELLSCULPTOR, ALTAR_GRIBLIN, TRAPSHOP, BONECHIPPER, LEATHERUPHOLSTERY, BOMBARDIER, BREEDING_WARREN.


df_keybind
----
Parses a raw keybinding string into a readable result.

Usage: ``{{#df_keybind:string|display_text|separator}}``

Parameters:

* ``string``
  - The raw keybinding string
* ``display_text`` (Optional, default: $1)
  - The text to be displayed for each key. $1 or \1 will be replaced by the key's value.
* ``seperator`` (Optional, default: -)
  - The text displayed between each key

Example::

{{#df_keybind:SHIFT_ALT_E|[$1]|+}}

Output::

[Alt]+[E]

df_building
----
Provides information about workshops and furnaces. 

Usage: ``{{#df_building:data|duilding|options}}``

* building - should be either workshop or furnace with syntax as follows:  ``BUILDING_FURNACE:MAGMA_GENERATOR`` or ``NAME:Magma Generator (Dwarf)``.
* options - you have to put ":" between parameters, their position won't matter.
 * ``LOCATION`` - returnts tiled image, depicting passability and work location (not implemented)
 * ``TILE`` - returns tiled image of workshop
 * ``COLOR`` - returns tiled and coloured image of workshop
 * ``N`` - where N is 0, 1 or 2, specifies building stage (3 by default)
 * ``BUILD_ITEM`` - returns build items with quantities

Example 1::

{{#df_building:Masterwork:building_kobold.txt|BUILDING_WORKSHOP:GONG|COLOR:3}}

Output 1:: 

Colorful image

Example 2::

{{#df_building:Masterwork:building_furnaces_masterwork.txt|BUILDING_FURNACE:GUNSMITH|BUILD_ITEM}}

Output 2:: 

1 science discovery(gunsmith), <b>BMW</b> 4 blocks, <b>A</b> 1 anvil

df_tile
----
Makes HTML and wiki supported tiles from ones used in raws. Only TILE is mandatory. Three other values can be omitted.

**Syntax:** ``{{#df_tile:TILE|COLOR|IMAGE|STEP}}``

* TILE is tiles from raws, &lt;br/> should be placed between lines
* COLOR is same as TILES, but color
* IMAGE is a wiki styled image link
* STEP is size of tile in pixels

**Input:**  ``{{#df_tile:43:222:219<br/>33:214:184|3:5:1:3:5:1:3:5:1<br/>3:5:1:3:5:1:3:5:1|[[File:Phoebus 16x16.png|link=]]|16}}``

**Output:** Coloruful image

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

