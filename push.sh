#!/usr/bin/env bash
# push.sh — local workspace -> remote server
# Usage:
#   ./push.sh            preview changes, then ask before applying
#   ./push.sh -y         apply without asking
#   ./push.sh --delete   also remove remote files missing locally
set -o pipefail

REMOTE_HOST="lys@207.148.92.171"
REMOTE_PORT=22
REMOTE_DIR="/home/lys/workspace"
LOCAL_DIR="$HOME/claude-file/workspace"

EXCLUDES=(
  --exclude ".DS_Store"
  --exclude "node_modules/"
  --exclude ".venv/"
  --exclude "__pycache__/"
  --exclude "*.log"
  --exclude ".sass-cache/"
  --exclude "_sync/"
)

YES=0
DELETE=""
for arg in "$@"; do
  case "$arg" in
    -y|--yes)  YES=1 ;;
    --delete)  DELETE="--delete" ;;
    -h|--help) sed -n '2,6p' "$0"; exit 0 ;;
    *) echo "Unknown argument: $arg" >&2; exit 1 ;;
  esac
done

[[ -d "$LOCAL_DIR" ]] || { echo "本地目录不存在: $LOCAL_DIR" >&2; exit 1; }

RSYNC=(rsync -az --human-readable $DELETE "${EXCLUDES[@]}"
       -e "ssh -p ${REMOTE_PORT} -o ConnectTimeout=10")
SRC="${LOCAL_DIR}/"
DST="${REMOTE_HOST}:${REMOTE_DIR}/"

echo "本地 ${LOCAL_DIR}"
echo "  -> 远程 ${REMOTE_HOST}:${REMOTE_DIR}"
echo
echo "正在检查差异..."

PLAN=$("${RSYNC[@]}" --dry-run --itemize-changes "$SRC" "$DST" 2>&1)
RC=$?
if (( RC != 0 )); then
  echo "❌ 连接或 rsync 失败 (exit $RC):" >&2
  echo "$PLAN" >&2
  echo >&2
  echo "先单独测试 SSH:  ssh ${REMOTE_HOST} date" >&2
  exit "$RC"
fi

CHANGES=$(grep -c '^[<>ch.*]' <<<"$PLAN")
if (( CHANGES == 0 )); then
  echo "✅ 已是最新，无需同步。"
  exit 0
fi

echo "$PLAN" | grep '^[<>ch.*]' | head -40
(( CHANGES > 40 )) && echo "... 以及另外 $((CHANGES - 40)) 项"
echo
echo "共 $CHANGES 项变化。"

if (( ! YES )); then
  read -r -p "执行同步? [y/N] " ok
  [[ "$ok" == [yY] ]] || { echo "已取消，未改动任何文件。"; exit 0; }
fi

echo
"${RSYNC[@]}" --progress "$SRC" "$DST" || exit $?
echo
echo "✅ 完成：本地 -> 远程"
