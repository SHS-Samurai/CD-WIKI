import os
from pathlib import Path


def ensure_private_parent(path: Path, storage_root) -> None:
    root = Path(storage_root).resolve()
    parent = path.parent.resolve(strict=False)
    parent.relative_to(root)

    root.mkdir(mode=0o700, parents=True, exist_ok=True)
    parent.mkdir(mode=0o700, parents=True, exist_ok=True)
    if os.name != "posix":
        return

    os.chmod(root, 0o700)
    current = root
    for part in parent.relative_to(root).parts:
        current /= part
        os.chmod(current, 0o700)


def secure_private_file(path: Path) -> None:
    if os.name == "posix":
        os.chmod(path, 0o600)
