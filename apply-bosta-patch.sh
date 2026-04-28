#!/bin/bash

PATCH_FILE="patches/bosta-area-filter.patch"
TARGET_FILE="plugins/bosta-woocommerce/bosta-woocommerce.php"

echo "🔧 Applying Bosta patch..."

# تأكد إن الباتش موجود
if [ ! -f "$PATCH_FILE" ]; then
    echo "❌ Patch file not found: $PATCH_FILE"
    exit 1
fi

# تأكد إن الملف موجود
if [ ! -f "$TARGET_FILE" ]; then
    echo "❌ Target file not found: $TARGET_FILE"
    exit 1
fi

# جرّب dry run الأول
echo "🔍 Checking patch..."
patch --dry-run -p1 < "$PATCH_FILE" > /dev/null 2>&1

if [ $? -ne 0 ]; then
    echo "⚠️ Patch cannot be applied cleanly"
    echo "👉 Maybe already applied or plugin updated"
    exit 1
fi

# تطبيق الباتش
echo "🚀 Applying patch..."
patch -p1 < "$PATCH_FILE"

if [ $? -eq 0 ]; then
    echo "✅ Patch applied successfully!"
else
    echo "❌ Patch failed"
    exit 1
fi
