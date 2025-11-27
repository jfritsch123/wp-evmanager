#!/bin/bash

echo "🔄 Pulling latest changes from origin/main…"
git pull origin main

if [ $? -eq 0 ]; then
    echo "✅ Repository is now up to date!"
else
    echo "❌ Pull failed. Check for merge conflicts or network problems."
fi
