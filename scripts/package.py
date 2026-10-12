"""Package runtime files only; credentials, local test state and original archives are excluded."""
import hashlib
from pathlib import Path
from zipfile import ZipFile, ZIP_DEFLATED

root = Path(__file__).resolve().parents[1]
dest = root / 'dist/orwo-co.zip'
dest.parent.mkdir(exist_ok=True)
files = [root / name for name in ['style.css', 'theme.json', 'functions.php', 'readme.txt']]
for folder in ['assets', 'includes', 'parts', 'templates']:
    files.extend(p for p in (root / folder).rglob('*') if p.is_file() and p.suffix != '.ttf')
if (root / 'screenshot.png').exists(): files.append(root / 'screenshot.png')
with ZipFile(dest, 'w', ZIP_DEFLATED) as z:
    for p in sorted(files):
        rel = p.relative_to(root)
        if p.is_symlink() or any(part.startswith('.') for part in rel.parts): raise ValueError('Unsafe path')
        z.write(p, 'orwo-co/' + rel.as_posix())
print(dest.name, dest.stat().st_size, hashlib.sha256(dest.read_bytes()).hexdigest())
