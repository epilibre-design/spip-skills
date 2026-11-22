#!/usr/bin/env sh
# Cross-skill link check for skills/**/*.md:
# - a backticked skill name (`spip-…`, `spip5-…`, `plugin-…`) must be a directory of skills/
#   (NOT_SKILLS lists tool and project names that share the prefix);
# - a `references/<file>.md` path must exist: `../` paths relative to the file, other paths in the
#   file's own skill or in a skill named on the same line or the two lines above.
set -eu
ROOT=$(CDPATH= cd -- "$(dirname -- "$0")/../.." && pwd)
SKILLS="$ROOT/skills"
NOT_SKILLS=' spip-cli spip-league spip-security spip-team '
find -L "$SKILLS" -name '*.md' | sort | while read -r f; do
  rel=${f#"$SKILLS/"}
  awk -v rel="$rel" -v dir="$(dirname "$f")" -v skills="$SKILLS" -v own="${rel%%/*}" -v not="$NOT_SKILLS" '
    function exists(p,  r) { r = (system("test -f \"" p "\"") == 0); return r }
    function isdir(p) { return system("test -d \"" p "\"") == 0 }
    {
      ctx = prev2 " " prev1 " " $0
      line = $0
      while (match(line, /`(spip5?|plugin)-[a-z0-9-]+`/)) {
        name = substr(line, RSTART + 1, RLENGTH - 2); line = substr(line, RSTART + RLENGTH)
        if (index(not, " " name " ") == 0 && !isdir(skills "/" name)) print rel ":" NR ": missing skill " name
      }
      line = $0
      while (match(line, /(\.\.\/)*([a-z0-9-]+\/)?references\/[a-z0-9-]+\.md/)) {
        p = substr(line, RSTART, RLENGTH); line = substr(line, RSTART + RLENGTH)
        if (p ~ /^\.\.\//) { if (!exists(dir "/" p)) print rel ":" NR ": missing " p; continue }
        if (p !~ /^references\//) { if (!exists(skills "/" p)) print rel ":" NR ": missing " p; continue }
        ok = exists(skills "/" own "/" p)
        c = ctx
        while (!ok && match(c, /(spip5?|plugin)-[a-z0-9-]+/)) {
          s = substr(c, RSTART, RLENGTH); c = substr(c, RSTART + RLENGTH)
          ok = exists(skills "/" s "/" p)
        }
        if (!ok) print rel ":" NR ": missing " p
      }
      prev2 = prev1; prev1 = $0
    }' "$f"
done > "${TMPDIR:-/tmp}/check-skill-links.$$"
status=0
if [ -s "${TMPDIR:-/tmp}/check-skill-links.$$" ]; then cat "${TMPDIR:-/tmp}/check-skill-links.$$"; status=1; fi
rm -f "${TMPDIR:-/tmp}/check-skill-links.$$"
exit $status
