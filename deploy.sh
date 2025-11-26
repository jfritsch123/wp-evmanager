#!/bin/bash

echo "🔍 Checking repository status…"
git status

echo ""
echo "➕ Adding all changes…"
git add .

echo ""
echo -n "📝 Commit message (Enter = default): "
read msg

if [ -z "$msg" ]; then
  msg="Update $(date '+%Y-%m-%d %H:%M:%S')"
fi

echo "Committing as: $msg"
git commit -m "$msg"

echo ""
echo "⬆️  Pushing to origin/main…"
git push origin main

echo ""
echo "✅ Deployment finished!"
