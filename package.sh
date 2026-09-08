#!/usr/bin/env bash
set -euo pipefail

SRC="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
DEST="$(cd "$SRC/.." && pwd)"
VER="1.0.0"

if [[ -f "$SRC/jsontoimg.php" ]]; then
  parsed="$(grep -E '^\s+\* Version:' "$SRC/jsontoimg.php" | head -n 1 | sed 's/.*Version:[[:space:]]*//' | tr -d '[:space:]')"
  if [[ -n "$parsed" ]]; then
    VER="$parsed"
  fi
fi

# WordPress Upload Plugin uses the zip filename as the destination folder.
# The zip MUST be named jsontoimg.zip and contain jsontoimg/jsontoimg.php.
OUT="$DEST/jsontoimg.zip"
OUT_VER="$DEST/jsontoimg-${VER}.zip"
STAGE="$(mktemp -d "${TMPDIR:-/tmp}/jsontoimg-pkg.XXXXXX")"
cleanup() { rm -rf "$STAGE"; }
trap cleanup EXIT

mkdir -p "$STAGE/jsontoimg"

if command -v rsync >/dev/null 2>&1; then
  rsync -a \
    --exclude node_modules \
    --exclude .git \
    --exclude graft \
    --exclude '*.zip' \
    --exclude package.bat \
    --exclude package.sh \
    "$SRC/" "$STAGE/jsontoimg/"
else
  tar -C "$SRC" \
    --exclude=node_modules \
    --exclude=.git \
    --exclude=graft \
    --exclude='*.zip' \
    --exclude=package.bat \
    --exclude=package.sh \
    -cf - . | tar -C "$STAGE/jsontoimg" -xf -
fi

rm -f "$OUT" "$OUT_VER"

if command -v zip >/dev/null 2>&1; then
  (cd "$STAGE" && zip -rq "$OUT" jsontoimg)
else
  (cd "$STAGE" && tar -a -cf "$OUT" jsontoimg)
fi

cp -f "$OUT" "$OUT_VER"

echo "Created $OUT"
echo "Created $OUT_VER (GitHub/archive only — do not upload this in WP Admin)"
echo
echo "Upload jsontoimg.zip via Plugins → Add New → Upload Plugin."
