#!/usr/bin/env bash
# yunusemrevurgun.com - Targeted FTP delete of dead files
# ONLY deletes files from a hard-coded allowlist of paths this session
# has identified as dead code (zero references in source).
# Loads creds from dev/.env (chmod 600). Never prints the password.
# DEFAULT: dry-run. Pass --apply to actually delete.

set -Eeuo pipefail
IFS=$'\n\t'

SITE_DOMAIN="yunusemrevurgun.com"
SCRIPT_PATH="$(readlink -f "${BASH_SOURCE[0]}")"
SITE_ROOT="$(dirname "$(dirname "$(dirname "$SCRIPT_PATH")")")"
ENV_FILE="${SITE_ROOT}/dev/.env"
LOG_DIR="${SITE_ROOT}/dev/logs"

APPLY=0
DRY_RUN=1
for arg in "$@"; do
  case "$arg" in
    --apply) APPLY=1; DRY_RUN=0 ;;
    --dry-run) DRY_RUN=1 ;;
    -h|--help)
      echo "Usage: $0 [--apply|--dry-run]"
      echo "  Default: dry-run. Pass --apply to actually delete."
      echo "  Deletes only the files in the hard-coded DEAD_FILES list."
      exit 0
      ;;
  esac
done

# --- Hard-coded allowlist of dead files (zero refs verified locally) ---
DEAD_FILES=(
  # Orphaned CSS files from old theme (removed in brand kit rebuild)
  "assets/css/landing.css"
  "assets/css/about.css"
  "assets/css/gallery.css"
  "assets/css/home.css"
  "assets/css/legal.css"
  "assets/css/search.css"
  "assets/css/updates.css"
  "assets/css/travel.css"
  "assets/css/contact.css"
  "assets/css/portfolio.css"
  "assets/css/blog.css"
  "assets/css/components/coffee-mug.css"
  "assets/css/components/minimal-navbar.css"
  "assets/css/components/question-options.css"
  "assets/css/components/pagination.css"
  "assets/css/components/page-search.css"
  # Old video assets (no longer referenced)
  "assets/videos/70s_computer_room_during_bluehour_seaside_town_cinematic_vintage.mp4"
  "assets/videos/cosmic_starfield_journey.mp4"
  "assets/videos/dark_cinematic_particles.mp4"
  "assets/videos/ethereal_garden_at_night.mp4"
  # Old profile image (moved reference to gallery but file may exist on remote)
  "assets/images/landing_pfp.jpg"
  "assets/images/landing_pfp2.jpg"
  "assets/images/landing_pfp2.png"
  "assets/images/about_pfp.png"
  "assets/images/aboutpagepfp.webp"
)

is_safe_remote_path() {
  local r="$1"
  case "$r" in
    *..*) return 1 ;;
    /etc/*|/usr/*|/var/*|/lib*|/bin/*|/sbin*|/boot/*|/proc/*|/sys/*|/dev/*|/run/*|/snap/*|/lost+found*|/swap.img|/home/*|/root/*|/opt/*|/srv/*|/mnt/*|/media/*|/tmp/*|/selinux/*)
      return 1 ;;
  esac
  return 0
}

if [[ -t 1 ]]; then
  C_RED=$'\033[31m'; C_YEL=$'\033[33m'; C_GRN=$'\033[32m'
  C_CYN=$'\033[36m'; C_DIM=$'\033[2m';  C_RST=$'\033[0m'
else
  C_RED=""; C_YEL=""; C_GRN=""; C_CYN=""; C_DIM=""; C_RST=""
fi

log()  { printf '%s[%s]%s %s\n' "$C_DIM" "$(date -u +'%Y-%m-%dT%H:%M:%SZ')" "$C_RST" "$*"; }
err()  { printf '%s[ERR ]%s %s\n' "$C_RED" "$C_RST" "$*" >&2; }
ok()   { printf '%s[OK  ]%s %s\n' "$C_GRN" "$C_RST" "$*"; }
hdr()  { printf '\n%s== %s ==%s\n' "$C_CYN" "$*" "$C_RST"; }
die()  { err "$*"; exit 1; }

preflight() {
  [[ "$(basename "$SITE_ROOT")" == "$SITE_DOMAIN" ]] || die "Refusing: SITE_ROOT='$SITE_ROOT'"
  [[ -f "$ENV_FILE" ]] || die "Missing $ENV_FILE"
  local mode; mode=$(stat -c '%a' "$ENV_FILE" 2>/dev/null || stat -f '%Lp' "$ENV_FILE")
  [[ "$mode" == "600" || "$mode" == "400" ]] || log "WARN: $ENV_FILE mode=$mode (recommended 600)"
  command -v lftp >/dev/null || die "lftp required"
  set -a; source "$ENV_FILE"; set +a
  [[ "${SITE_DOMAIN:-}" == "$SITE_DOMAIN" ]] || die "SITE_DOMAIN mismatch"
  [[ -n "${FTP_HOST:-}" && -n "${FTP_USER:-}" && -n "${FTP_PASS:-}" ]] || die "FTP creds missing"
  hdr "yunusemrevurgun.com - targeted delete (dry_run=$DRY_RUN)"
  log "Will attempt to delete ${#DEAD_FILES[@]} file(s) from remote."
  echo ""
}

run_delete() {
  local stamp; stamp=$(date -u +'%Y%m%dT%H%M%SZ')
  local logf="${LOG_DIR}/targeted-delete.${stamp}.log"
  : > "$logf"

  # Build lftp commands (only rm files that are confirmed dead and in safe paths)
  local cmds_file; cmds_file=$(mktemp)
  for f in "${DEAD_FILES[@]}"; do
    if ! is_safe_remote_path "/$f"; then
      err "Refused (unsafe): $f"
      continue
    fi
    # rm with continue flag; ignore if missing
    printf 'rm -f "%s/%s"\n' "$FTP_REMOTE_ROOT" "$f" >> "$cmds_file"
    log "  - $f"
  done

  local tmpf; tmpf=$(mktemp /tmp/lftp-XXXXXX)
  chmod 600 "$tmpf"
  {
    echo "set ssl:verify-certificate no"
    [[ "${FTP_USE_TLS:-false}" == "true" ]] && echo "set ftp:ssl-force true"
    echo "set ftp:passive-mode ${FTP_PASSIVE_MODE:-true}"
    echo "set net:timeout ${FTP_TIMEOUT:-30}"
    echo "open ${FTP_HOST}"
    echo "user \"${FTP_USER}\" \"${FTP_PASS}\""
    cat "$cmds_file"
    echo "quit"
  } > "$tmpf"
  rm -f "$cmds_file"

  if [[ "$DRY_RUN" == "1" ]]; then
    log "DRY RUN - commands that would execute:"
    sed "s/${FTP_PASS}/***/g" "$tmpf"
    ok "Dry-run complete. Re-run with --apply to actually delete. Log: $logf"
  else
    log "APPLYING - deleting ${#DEAD_FILES[@]} file(s)..."
    lftp -f "$tmpf" 2>&1 | sed "s/${FTP_PASS}/***/g" | tee -a "$logf"
    ok "Delete complete. Log: $logf"
  fi
  rm -f "$tmpf"
}

preflight
run_delete
