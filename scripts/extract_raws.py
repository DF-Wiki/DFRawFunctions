#!/usr/bin/env python3

'''
Extract raws from a DF archive file.

Only DF 50.xx for Windows is currently supported.
'''

import argparse
import os
import pathlib
import shutil
import zipfile

parser = argparse.ArgumentParser()
parser.add_argument('input_file')
parser.add_argument('output_dir')
parser.add_argument('--overwrite', action='store_true', help='Remove output_dir entirely if it exists')
args = parser.parse_args()

zf = zipfile.ZipFile(args.input_file)
raw_files = {}  # name -> full path in archive

def is_valid_raw_path(path):
    if entry_path.is_relative_to('data/vanilla'):
        # v50+
        return len(entry_path.parts) > 2 and entry_path.parts[2].startswith('vanilla_')
    elif entry_path.is_relative_to('raw/objects'):
        # pre-v50
        return len(entry_path.parts) == 3
    return False

for entry in zf.namelist():
    entry_path = pathlib.PurePath(entry)
    if (not entry_path.is_absolute()
        and is_valid_raw_path(entry_path)
        and entry_path.suffix == '.txt'
        and entry_path.name != 'info.txt'
    ):
        if entry_path.name in raw_files:
            raise RuntimeError('Duplicate filename: %r' % str(entry_path))
        else:
            raw_files[entry_path.name] = entry

if os.path.exists(args.output_dir):
    if args.overwrite:
        shutil.rmtree(args.output_dir)
    else:
        print('error: output directory already exists:', args.output_dir)
        exit(1)

os.mkdir(args.output_dir)

for out_filename, in_filepath in raw_files.items():
    with zf.open(in_filepath) as in_file, open(os.path.join(args.output_dir, out_filename), 'wb') as out_file:
        contents = in_file.read()
        contents = contents.replace(b'\r\n', b'\n')
        # Convert to UTF-8 to fix Moose raws and language files. The parser functions can't handle CP437.
        contents = contents.decode('cp437').encode('utf8')
        out_file.write(contents)
