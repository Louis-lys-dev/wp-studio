#!/usr/bin/env bash
# pull.sh — 把远程服务器上的 workspace 同步到本地
# 用法:
#   ./pull.sh              预演(dry-run)，只打印将要发生的变化，不动文件
#   ./pull.sh -y           真正执行
#   ./pull.sh -y --delete  真正执行，并删除本地多出来的文件（让本地完全等于远程）
set -euo pipefail

REMOTE_HOST="lys@207.148.92.171"
REMOTE_PORT=22
REMOTE_DIR="/home/lys/workspace"
LOCAL_DIR="/Users/louis/claude-file/workspace"

EXCLUDES=(
  --exclude ".DS_Store"
  --exclude "node_modules/"
  --exclude ".venv/"
  --exclude "__pycache__/"
  --exclude "*.log"
  --exclude ".sass-cache/"
  --exclude "_sync/"
)

APPLY=0
DELETE=()
for arg in "$@"; do
  case "$arg" in
    -y|--yes)    APPLY=1 ;;
    --delete)    DELETE=(--delete) ;;
    -h|--help)   sed -n '2,7p' "$0"; exit 0 ;;
    *) echo "未知参数: $arg" >&2; exit 1 ;;
  esac
done

[[ -d "$LOCAL_DIR" ]] || { echo "本地目录不存在: $LOCAL_DIR" >&2; exit 1; }

DRY=(--dry-run)
(( APPLY )) && DRY=()

if (( APPLY )) && (( ${#DELETE[@]} )); then
  echo "⚠️  --delete 会删除本地 $LOCAL_DIR 下远程没有的文件。"
  read -r -p "确认继续? 输入 yes: " ok
  [[ "$ok" == "yes" ]] || { echo "已取消"; exit 1; }
fi

(( APPLY )) || echo "=== 预演模式（不会改动任何文件），确认无误后加 -y 执行 ==="

# 注意结尾的斜杠：同步目录「内容」而不是目录本身
rsync -az --human-readable --progress --partial \
  -e "ssh -p ${REMOTE_PORT}" \
  "${DRY[@]}" "${DELETE[@]}" "${EXCLUDES[@]}" \
  "${REMOTE_HOST}:${REMOTE_DIR}/" "${LOCAL_DIR}/"

echo "完成: 远程 -> 本地"
