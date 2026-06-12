#!/usr/bin/env bash
# yunusemrevurgun.com - Per-site FTP sync script
# Site:    yunusemrevurgun.com
# Local:   /home/yemre/Desktop/FTPSites-personal/yunusemrevurgun.com
# Script:  /home/yemre/Desktop/FTPSites-personal/yunusemrevurgun.com/dev/scripts/ftp-sync.sh
#
# Refuses to run unless it sits inside .../yunusemrevurgun.com/dev/scripts/.
# Loads creds from dev/.env (chmod 600). Never prints the password.
# Defaults: dry-run + no-delete on push. Both must be explicitly disabled.

set -Eeuo pipefail
IFS=$'\n\t'

SITE_DOMAIN="yunusemrevurgun.com"
SCRIPT_PATH="$(readlink -f "${BASH_SOURCE[0]}")"
SITE_ROOT="$(dirname "$(dirname "$(dirname "$SCRIPT_PATH")")")"
ENV_FILE="${SITE_ROOT}/dev/.env"
LOG_DIR="${SITE_ROOT}/dev/logs"
BACKUP_ROOT="${SITE_ROOT}/dev/_backups"

if [[ -t 1 ]]; then
  C_RED=$'\033[31m'; C_YEL=$'\033[33m'; C_GRN=$'\033[32m'
  C_CYN=$'\033[36m'; C_DIM=$'\033[2m';  C_RST=$'\033[0m'
else
  C_RED=""; C_YEL=""; C_GRN=""; C_CYN=""; C_DIM=""; C_RST=""
fi

log()  { printf '%s[%s]%s %s\n' "$C_DIM" "$(date -u +'%Y-%m-%dT%H:%M:%SZ')" "$C_RST" "$*"; }
warn() { printf '%s[WARN]%s %s\n' "$C_YEL" "$C_RST" "$*" >&2; }
err()  { printf '%s[ERR ]%s %s\n' "$C_RED" "$C_RST" "$*" >&2; }
ok()   { printf '%s[OK  ]%s %s\n' "$C_GRN" "$C_RST" "$*"; }
hdr()  { printf '\n%s== %s ==%s\n' "$C_CYN" "$*" "$C_RST"; }
die()  { err "$*"; exit 1; }

print_help() {
  cat <<EOF
${C_CYN}yunusemrevurgun.com FTP sync${C_RST}

Usage:  $0 <command> [flags]

Commands:
  ${C_GRN}discover${C_RST}      Read-only: list remote FTP root + detect landing
  ${C_GRN}pull${C_RST}          Mirror remote -> local (never deletes local)
  ${C_GRN}backup${C_RST}        Snapshot local mirror to dev/_backups/<UTC>/
  ${C_GRN}push-dry-run${C_RST}  Show what push would upload (no writes)
  ${C_GRN}push${C_RST}          Upload local -> remote (dry-run if DRY_RUN_DEFAULT=true)
  ${C_GRN}push-delete${C_RST}   Like push, but allows remote deletion (DANGEROUS)
  ${C_GRN}verify${C_RST}        Re-run discover + diff local vs remote listing
  ${C_GRN}help${C_RST}          This text

Safety:
  - Refuses to run unless it sits inside ${SITE_DOMAIN}/dev/scripts/
  - Loads creds only from ${SITE_DOMAIN}/dev/.env (chmod 600)
  - Never prints FTP password
  - Local dev/, _backups/, logs/, .git/, .env* are ALWAYS excluded
EOF
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

  hdr "yunusemrevurgun.com sync - identity"
  log "Site       : $SITE_DOMAIN"
  log "Local root : $LOCAL_SITE_DIR"
  log "FTP host   : $FTP_HOST"
  log "FTP user   : $FTP_USER"
  log "FTP root   : ${FTP_REMOTE_ROOT:-/}"
  log "TLS        : ${FTP_USE_TLS:-false}"
  echo ""
}

lftp_run() {
  local cmds="$1"; local logf="${2:-}"

  local tmpf; tmpf=$(mktemp /tmp/lftp-XXXXXX)
  chmod 600 "$tmpf"
  trap "rm -f '$tmpf'" EXIT INT TERM

  {
    echo "set ssl:verify-certificate no"
    [[ "${FTP_USE_TLS:-false}" == "true" ]] && echo "set ftp:ssl-force true"
    [[ "${FTP_USE_TLS:-false}" == "true" ]] && echo "set ftp:ssl-protect-data true"
    echo "set ftp:passive-mode ${FTP_PASSIVE_MODE:-true}"
    echo "set net:timeout ${FTP_TIMEOUT:-30}"
    echo "open ${FTP_HOST}"
    echo "user \"${FTP_USER}\" \"${FTP_PASS}\""
    echo "${cmds}"
    echo "quit"
  } > "$tmpf"

  if [[ -n "$logf" ]]; then
    lftp -f "$tmpf" 2>&1 | sed "s/${FTP_PASS}/***/g" | tee -a "$logf"
  else
    lftp -f "$tmpf" | sed "s/${FTP_PASS}/***/g"
  fi
  rm -f "$tmpf"
  trap - EXIT INT TERM
}

build_excludes() {
  cat <<'EOF'
dev/
dev/**
_backups/
_backups/**
logs/
logs/**
.git/
.github/
.github/**
.gitignore
.env
.env.*
.backup/
.backup/**
staging_deploy_*/
error_log
error_log.txt
.ftpquota
.codex
rate_limit_storage.json
debug_updates.txt
debug_updates_view.txt
*.log
*.sql
*.zip
*.tar*
*.bak
node_modules/
vendor/
tmp/
cache/
.DS_Store
Thumbs.db
EOF
}

cmd_discover() {
  hdr "Discover - $SITE_DOMAIN"
  local logf="${LOG_DIR}/discover.$(date -u +'%Y%m%dT%H%M%SZ').log"
  : > "$logf"
  log "Connecting (read-only) to $FTP_HOST ..."
  lftp_run "
    pwd
    echo '--- pwd end ---'
    cls -1 --sort=name | head -200
    echo '--- top-200 end ---'
  " "$logf"
  for d in "" "public_html" "public_html/" "www" "htdocs" "yunusemrevurgun.com"; do
    log "Probe: cd '$d'"
    if [[ -z "$d" ]]; then
      lftp_run "pwd; cls -1 | head -20; echo '--- probe end ---'" "$logf" || true
    else
      lftp_run "cd '$d'; pwd; cls -1 | head -20; echo '--- probe end ---'" "$logf" || true
    fi
  done
  ok "Discover complete. Log: $logf"
}

cmd_pull() {
  hdr "Pull - remote -> local"
  local stamp; stamp=$(date -u +'%Y%m%dT%H%M%SZ')
  local logf="${LOG_DIR}/pull.${stamp}.log"
  : > "$logf"

  if ls "$SITE_ROOT/" | grep -qvE '^(dev|\.gitkeep)$' 2>/dev/null; then
    warn "Local mirror non-empty; snapshotting to dev/_backups/${stamp}/"
    mkdir -p "${BACKUP_ROOT}/${stamp}"
    rsync -a --exclude='dev/' --exclude='_backups/' --exclude='logs/' --exclude='.git/' \
      "${SITE_ROOT}/" "${BACKUP_ROOT}/${stamp}/" 2>>"$logf" || true
  fi

  local excludes_file; excludes_file=$(mktemp)
  build_excludes > "$excludes_file"
  log "Mirroring remote -> local (no delete) ..."
  lftp_run "
    set mirror:overwrite false;
    mirror --verbose=1 --no-perms --no-umask --exclude-glob-from=$excludes_file \
      ${FTP_REMOTE_ROOT}/ .
  " "$logf"
  rm -f "$excludes_file"
  ok "Pull complete. Log: $logf"
}

cmd_backup() {
  hdr "Backup - local mirror snapshot"
  local stamp; stamp=$(date -u +'%Y%m%dT%H%M%SZ')
  local dest="${BACKUP_ROOT}/${stamp}"
  mkdir -p "$dest"
  log "Snapshotting $SITE_ROOT -> $dest"
  rsync -a --exclude='dev/' --exclude='_backups/' --exclude='logs/' --exclude='.git/' \
    "${SITE_ROOT}/" "${dest}/"
  ok "Backup stored: $dest"
  echo "$dest" > "${BACKUP_ROOT}/.latest"
}

cmd_push() {
  local allow_delete="$1"
  local dry_run="${DRY_RUN_DEFAULT:-true}"
  [[ "$allow_delete" == "true" ]] && { dry_run="false"; warn "--allow-delete set: remote files may be deleted"; }
  hdr "Push - local -> remote (dry_run=$dry_run delete=$allow_delete)"
  local stamp; stamp=$(date -u +'%Y%m%dT%H%M%SZ')
  local logf="${LOG_DIR}/push.${stamp}.log"
  : > "$logf"

  local excludes_file; excludes_file=$(mktemp)
  build_excludes > "$excludes_file"

  if [[ "$dry_run" == "true" ]]; then
    log "DRY RUN - showing what would change ..."
    lftp_run "
      set mirror:dry-run true;
      mirror --reverse --dry-run --verbose=1 --no-perms --no-umask \
        --exclude-glob-from=$excludes_file \
        ${FTP_REMOTE_ROOT}/ .
    " "$logf"
  else
    log "REAL PUSH - mirroring local -> remote ..."
    local extra=""
    [[ "$allow_delete" == "true" ]] && extra="--delete"
    lftp_run "
      mirror --reverse --verbose=1 --no-perms --no-umask \
        --exclude-glob-from=$excludes_file $extra \
        ${FTP_REMOTE_ROOT}/ .
    " "$logf"
  fi
  rm -f "$excludes_file"
  ok "Push complete. Log: $logf"
}

cmd_verify() {
  hdr "Verify - $SITE_DOMAIN"
  local logf="${LOG_DIR}/verify.$(date -u +'%Y%m%dT%H%M%SZ').log"
  : > "$logf"
  log "Remote listing (top 100) ..."
  lftp_run "cd ${FTP_REMOTE_ROOT} 2>/dev/null || cd /; cls -1 --sort=name | head -100" "$logf"
  log "Local listing (top 100, dev/_backups/logs/.git excluded) ..."
  {
    find "$SITE_ROOT" -mindepth 1 -maxdepth 2 \
      -not -path "$SITE_ROOT/dev*" \
      -not -path "$SITE_ROOT/_backups*" \
      -not -path "$SITE_ROOT/logs*" \
      -not -path "$SITE_ROOT/.git*" \
      -printf '%P\n' | sort | head -100
  } | tee -a "$logf"
  ok "Verify complete. Log: $logf"
}

main() {
  local cmd="${1:-help}"; shift || true
  case "$cmd" in
    help|-h|--help) print_help; exit 0 ;;
  esac
  preflight
  case "$cmd" in
    discover)     cmd_discover ;;
    pull)         cmd_pull ;;
    backup)       cmd_backup ;;
    push)         cmd_push "false" ;;
    push-dry-run) cmd_push "false" ;;
    push-delete)  cmd_push "true" ;;
    verify)       cmd_verify ;;
    *) err "Unknown: $cmd"; print_help; exit 2 ;;
  esac
}
main "$@"
