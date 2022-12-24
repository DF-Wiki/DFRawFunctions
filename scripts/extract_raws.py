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

for entry in zf.namelist():
    entry_path = pathlib.PurePath(entry)
    if (not entry_path.is_absolute()
        and entry_path.is_relative_to('data/vanilla')
        and len(entry_path.parts) > 2
        and entry_path.parts[2].startswith('vanilla_')
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
        shutil.copyfileobj(in_file, out_file)
