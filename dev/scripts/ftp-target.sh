#!/usr/bin/env bash
# yunusemrevurgun.com - Targeted FTP upload of changed files
# Loads creds from dev/.env (chmod 600). Never prints the password.
# DEFAULT: dry-run. Pass --apply to actually upload.

set -Eeuo pipefail
IFS=$'\n\t'

SITE_DOMAIN="yunusemrevurgun.com"
SCRIPT_PATH="$(readlink -f "${BASH_SOURCE[0]}")"
SITE_ROOT="$(dirname "$(dirname "$(dirname "$SCRIPT_PATH")")")"
ENV_FILE="${SITE_ROOT}/dev/.env"
LOG_DIR="${SITE_ROOT}/dev/logs"

if [[ -t 1 ]]; then
  C_RED=$'\033[31m'; C_YEL=$'\033[33m'; C_GRN=$'\033[32m'
  C_CYN=$'\033[36m'; C_DIM=$'\033[2m';  C_RST=$'\033[0m'
else
  C_RED=""; C_YEL=""; C_GRN=""; C_CYN=""; C_DIM=""; C_RST=""
fi

log()  { printf '%s[%s]%s %s\n' "$C_DIM" "$(date -u +'%Y-%m-%dT%H:%M:%SZ')" "$C_RST" "$*"; }
warn() { printf '%s[WARN]%s %s\n' "$C_YEL" "$C_RST" "$*$" >&2 || true; }
err()  { printf '%s[ERR ]%s %s\n' "$C_RED" "$C_RST" "$*" >&2; }
ok()   { printf '%s[OK  ]%s %s\n' "$C_GRN" "$C_RST" "$*"; }
hdr()  { printf '\n%s== %s ==%s\n' "$C_CYN" "$*" "$C_RST"; }
die()  { err "$*"; exit 1; }

APPLY=0
DRY_RUN=1
ARGS=()
FLAG_MODE="before-dashdash"
for arg in "$@"; do
  case "$arg" in
    --apply) APPLY=1; DRY_RUN=0 ;;
    --dry-run) DRY_RUN=1 ;;
    --)
      FLAG_MODE="after-dashdash"
      ;;
    -h|--help)
      echo "Usage: $0 [--apply|--dry-run] [-- file1 file2 ...]"
      echo "  Default: dry-run. Pass --apply to actually upload."
      echo "  If no files given after '--', uses the curated allowlist for this session."
      exit 0
      ;;
    *)
      if [[ "$FLAG_MODE" == "after-dashdash" ]]; then
        ARGS+=("$arg")
      fi
      ;;
  esac
done

# --- Curated allowlist of files changed in this session ---
# Format: relative-to-SITE_ROOT
ALLOWLIST=(
  # Brand kit CSS rewrite
  "assets/css/variables.css"
  "assets/css/ui-rebuild.css"
  "assets/css/base.css"
  # Brand kit portrait asset
  "assets/images/yunus-emre-vurgun-portrait.jpg"
  # Home page blueprint hero rewrite
  "home.php"
  # About page single portrait
  "views/about.php"
  # Shared UI: navbar wordmark, removed video function, theme-color
  "views/includes/ui.php"
  "views/includes/StaticPageSeoTags.php"
  # Blog default OG image
  "views/blog-post.php"
  # Legal pages (removed video backgrounds)
  "views/legal/privacy.php"
  "views/legal/terms.php"
  "views/legal/cookies.php"
  # JS cleanup (removed homeHeroIntro)
  "assets/js/ui-interactions.js"
  # Admin theme-color updates
  "views/admin/includes/header.php"
  "views/admin/login.php"
  "views/admin/favicon/index.php"
  # Updates pages — author pfp with brand kit portrait
  "views/updates.php"
  "views/update-single.php"
  # Favicon from brand kit portrait
  "assets/images/favicon-pfp.png"
  "assets/images/favicon-pfp-32.png"
  # Brand kit CSS surface + overlay fixes
  "assets/css/ui-rebuild.css"
  "assets/css/yunobot.css"
  # Landing/home page (router uses views/home.php)
  "views/home.php"
  # Contact page glass containers + email to yunus@yunus.email
  "views/contact.php"
  "models/Contact.php"
  # Search page fix
  "views/search.php"
)

# Sanity: refuse anything outside SITE_ROOT and refuse system paths.
is_safe_local() {
  local f="$1"
  case "$f" in
    dev/*|dev) return 1 ;;
    .git/*|.git) return 1 ;;
    _backups/*|_backups) return 1 ;;
    logs/*|logs) return 1 ;;
    .env|.env.*) return 1 ;;
    *pass*|*secret*|*creds*) return 1 ;;
  esac
  return 0
}

# Files to push (CLI args override allowlist)
TARGETS=()
if [[ ${#ARGS[@]} -gt 0 ]]; then
  TARGETS=("${ARGS[@]}")
else
  TARGETS=("${ALLOWLIST[@]}")
fi

# Refuse system-style remote targets (defense in depth)
is_safe_remote_path() {
  local r="$1"
  case "$r" in
    /etc/*|/usr/*|/var/*|/lib/*|/lib64/*|/bin/*|/sbin*|/boot/*|/proc/*|/sys/*|/dev/*|/run/*|/snap/*|/lost+found*|/swap.img|/home/*|/root/*|/opt/*|/srv/*|/mnt/*|/media/*|/tmp/*|/selinux/*)
      return 1 ;;
  esac
  return 0
}

preflight() {
  [[ "$(basename "$SITE_ROOT")" == "$SITE_DOMAIN" ]] || die "Refusing: SITE_ROOT='$SITE_ROOT' (expected .../$SITE_DOMAIN)"
  [[ -f "$ENV_FILE" ]] || die "Missing $ENV_FILE"
  local mode; mode=$(stat -c '%a' "$ENV_FILE" 2>/dev/null || stat -f '%Lp' "$ENV_FILE")
  [[ "$mode" == "600" || "$mode" == "400" ]] || warn "$ENV_FILE mode=$mode (recommended 600)"
  command -v lftp >/dev/null || die "lftp required (sudo apt install lftp)"
  set -a; source "$ENV_FILE"; set +a
  [[ "${SITE_DOMAIN:-}" == "$SITE_DOMAIN" ]] || die "SITE_DOMAIN mismatch in $ENV_FILE"
  [[ "${LOCAL_SITE_DIR:-}" == "$SITE_ROOT" ]] || die "LOCAL_SITE_DIR in .env does not match this script's site root"
  [[ -n "${FTP_HOST:-}" && -n "${FTP_USER:-}" && -n "${FTP_PASS:-}" ]] \
    || die "FTP_HOST/FTP_USER/FTP_PASS missing in $ENV_FILE"
  hdr "yunusemrevurgun.com - targeted upload (dry_run=$DRY_RUN)"
  log "Site       : $SITE_DOMAIN"
  log "Local root : $LOCAL_SITE_DIR"
  log "FTP host   : $FTP_HOST"
  log "FTP user   : $FTP_USER"
  log "FTP root   : ${FTP_REMOTE_ROOT:-/}  (user-confirmed: this IS the website root for this FTP user)"
  echo ""
}

# Build lftp commands for one file
build_lftp_script() {
  local rel="$1"
  local local_path="$SITE_ROOT/$rel"
  local remote_dir remote_basename

  if ! is_safe_local "$rel"; then err "Refused (unsafe local pattern): $rel"; return 1; fi
  # Note: we only upload into a fixed safe tree; remote is always $FTP_REMOTE_ROOT/<rel>
  # Defence: also reject any rel that traverses upward
  case "$rel" in
    *..*) err "Refused (path traversal in local rel): $rel"; return 1 ;;
  esac
  [[ -f "$local_path" ]] || { err "Missing local file: $local_path"; return 1; }

  remote_dir="$(dirname "$rel")"
  remote_basename="$(basename "$rel")"

  # Ensure remote dir exists (mkdir -p on remote) then put the file
  if [[ "$remote_dir" != "." ]]; then
    # cd into nested dirs; lftp mkdir -p uses multiple mkdir
    local IFS='/'
    local accum=""
    local part
    for part in $remote_dir; do
      [[ -z "$part" ]] && continue
      accum="${accum}/${part}"
      printf 'mkdir -p "%s/%s"\n' "$FTP_REMOTE_ROOT" "$accum"
    done
  fi
  printf 'put -O "%s/%s" "%s"\n' "$FTP_REMOTE_ROOT" "$remote_dir" "$local_path"
}

run_targeted() {
  local stamp; stamp=$(date -u +'%Y%m%dT%H%M%SZ')
  local logf="${LOG_DIR}/targeted.${stamp}.log"
  : > "$logf"

  # Verify each target exists locally and is allowed
  local cmds=()
  for rel in "${TARGETS[@]}"; do
    if ! build_lftp_script "$rel" >> /tmp/lftp-cmds-$$ 2>>"$logf"; then
      err "Skip: $rel"
    else
      log "  + $rel"
    fi
  done

  if [[ ! -s /tmp/lftp-cmds-$$ ]]; then
    err "No valid targets."
    rm -f /tmp/lftp-cmds-$$
    exit 1
  fi

  local tmpf; tmpf=$(mktemp /tmp/lftp-XXXXXX)
  chmod 600 "$tmpf"
  {
    echo "set ssl:verify-certificate no"
    [[ "${FTP_USE_TLS:-false}" == "true" ]] && echo "set ftp:ssl-force true"
    echo "set ftp:passive-mode ${FTP_PASSIVE_MODE:-true}"
    echo "set net:timeout ${FTP_TIMEOUT:-30}"
    echo "open ${FTP_HOST}"
    echo "user \"${FTP_USER}\" \"${FTP_PASS}\""
    cat /tmp/lftp-cmds-$$
    echo "quit"
  } > "$tmpf"
  rm -f /tmp/lftp-cmds-$$

  if [[ "$DRY_RUN" == "1" ]]; then
    log "DRY RUN - showing commands that would execute:"
    sed "s/${FTP_PASS}/***/g" "$tmpf"
    ok "Dry-run complete. Re-run with --apply to actually upload. Log: $logf"
  else
    log "APPLYING - uploading ${#TARGETS[@]} file(s)..."
    lftp -f "$tmpf" 2>&1 | sed "s/${FTP_PASS}/***/g" | tee -a "$logf"
    ok "Upload complete. Log: $logf"
  fi
  rm -f "$tmpf"
}

preflight
run_targeted
