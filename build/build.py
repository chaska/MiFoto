#!/usr/bin/env python3
"""Builds dist/pkg_mifoto_<version>.zip: the package with the component and the user plugin inside.

Run:  python build/build.py
Zip entries always use "/" (Joomla's installer needs it; Compress-Archive on old PowerShell writes "\\").
Before zipping, every file the manifests name must exist and every XML must parse; otherwise nothing is written.
"""
import io
import re
import sys
import zipfile
import xml.etree.ElementTree as ET
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent
SRC = ROOT / 'src'
DIST = ROOT / 'dist'
SKIP_NAMES = {'.DS_Store', 'Thumbs.db'}
SKIP_SUFFIXES = {'.bak', '.orig', '.tmp'}

PARTS = {
	'com_mifoto.zip': SRC / 'component',
	'plg_user_mifoto.zip': SRC / 'plugins' / 'user' / 'mifoto',
	'plg_content_mifoto.zip': SRC / 'plugins' / 'content' / 'mifoto',
	'plg_engage_mifoto.zip': SRC / 'plugins' / 'engage' / 'mifoto',
	'plg_privacy_mifoto.zip': SRC / 'plugins' / 'privacy' / 'mifoto',
	'mod_mifoto_photo.zip': SRC / 'modules' / 'mifoto_photo',
}

# Manifest of each part: the module's is named after the module, the others are all "mifoto.xml".
MANIFESTS = {'mod_mifoto_photo.zip': 'mod_mifoto_photo.xml'}


def files_of(folder: Path):
	for p in sorted(folder.rglob('*')):
		if p.is_file() and p.name not in SKIP_NAMES and p.suffix not in SKIP_SUFFIXES:
			yield p


def fail(msg: str):
	print('ERROR: ' + msg)
	sys.exit(1)


def check_manifest(folder: Path, manifest: str):
	"""Every <folder>, <filename>, <language> the manifest lists must exist under its folder= attribute."""
	path = folder / manifest
	try:
		tree = ET.parse(path)
	except ET.ParseError as e:
		fail(f'{path}: XML mal formado ({e})')

	def walk(node, base):
		for child in node:
			if child.tag in ('files', 'languages', 'media'):
				walk(child, base / child.get('folder', '.'))
			elif child.tag in ('folder', 'filename', 'language') and (child.text or '').strip():
				target = base / child.text.strip()
				if not target.exists():
					fail(f'{path.name} nombra "{child.text.strip()}" y no existe en {base}')
			elif child.tag == 'administration':
				walk(child, base)
			elif child.tag in ('install', 'uninstall'):
				for sql in child.iter('file'):
					if not (folder / 'backend' / (sql.text or '').strip()).exists():
						fail(f'{path.name}: falta el SQL {sql.text}')

	walk(tree.getroot(), folder)
	return tree.getroot()


def version_of(root) -> str:
	v = root.findtext('version') or ''
	if not re.fullmatch(r'\d+\.\d+\.\d+', v):
		fail(f'version rara: {v!r}')
	return v


def entry(name: str, stored: bool = False) -> zipfile.ZipInfo:
	info = zipfile.ZipInfo(name, date_time=(2026, 10, 26, 0, 0, 0))
	info.compress_type = zipfile.ZIP_STORED if stored else zipfile.ZIP_DEFLATED
	info.external_attr = 0o644 << 16
	return info


def make_zip(folder: Path) -> bytes:
	buf = io.BytesIO()
	with zipfile.ZipFile(buf, 'w', zipfile.ZIP_DEFLATED, compresslevel=9) as z:
		for p in files_of(folder):
			z.writestr(entry(p.relative_to(folder).as_posix()), p.read_bytes())
	return buf.getvalue()


def main():
	roots = [check_manifest(folder, MANIFESTS.get(name, 'mifoto.xml')) for name, folder in PARTS.items()]
	pkg = ET.parse(SRC / 'package' / 'pkg_mifoto.xml').getroot()

	script = pkg.findtext('scriptfile')
	if script and not (SRC / 'package' / script).is_file():
		fail(f'el paquete nombra el script "{script}" y no existe en src/package')
	versions = {version_of(r) for r in roots} | {version_of(pkg)}

	# The package must list exactly the zips this script builds.
	listed = sorted((f.text or '').strip() for f in pkg.iter('file'))
	if listed != sorted(PARTS):
		fail(f'pkg_mifoto.xml lista {listed} y el script construye {sorted(PARTS)}')

	if len(versions) != 1:
		fail(f'las tres versiones no coinciden: {sorted(versions)}')

	version = versions.pop()
	DIST.mkdir(exist_ok=True)
	out = DIST / f'pkg_mifoto_{version}.zip'

	with zipfile.ZipFile(out, 'w', zipfile.ZIP_DEFLATED, compresslevel=9) as z:
		z.writestr(entry('pkg_mifoto.xml'), (SRC / 'package' / 'pkg_mifoto.xml').read_bytes())

		if script:
			z.writestr(entry(script), (SRC / 'package' / script).read_bytes())

		for name, folder in PARTS.items():
			inner = make_zip(folder)
			z.writestr(entry('packages/' + name, stored=True), inner)  # already compressed inside
			print(f'  {name}: {len(inner):,} bytes, {sum(1 for _ in files_of(folder))} archivos')

	print(f'OK {out} ({out.stat().st_size:,} bytes)')


if __name__ == '__main__':
	main()
