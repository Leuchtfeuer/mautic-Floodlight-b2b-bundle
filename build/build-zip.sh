#!/usr/bin/env bash
set -euo pipefail

# Builds the self-contained release ZIP (bundles jeremykendall/php-domain-parser,
# scoped via php-scoper). Usage: build-zip.sh [version]
#
# Scopes only Vendor/Pdp, not the whole plugin: scoping everything previously
# broke a real install (missed Twig in the exclude-namespaces blacklist).

PLUGIN_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
VERSION="${1:-dev}"

cd "$PLUGIN_DIR"
rm -rf build/vendor build/composer.lock staging-scoped "LeuchtfeuerFloodlightB2BBundle-${VERSION}.zip"

echo "==> Fetching current jeremykendall/php-domain-parser + php-scoper"
(cd build && composer update --no-interaction --prefer-dist)

echo "==> Staging plugin source"
mkdir -p staging-scoped/LeuchtfeuerFloodlightB2BBundle
rsync -a \
  --exclude='.git' \
  --exclude='.github' \
  --exclude='build' \
  --exclude='staging-scoped' \
  --exclude='Tests' \
  ./ staging-scoped/LeuchtfeuerFloodlightB2BBundle/

echo "==> Copying vendored library into staged plugin"
mkdir -p staging-scoped/LeuchtfeuerFloodlightB2BBundle/Vendor/Pdp
cp -r build/vendor/jeremykendall/php-domain-parser/src/. staging-scoped/LeuchtfeuerFloodlightB2BBundle/Vendor/Pdp/
cp build/vendor/jeremykendall/php-domain-parser/LICENSE staging-scoped/LeuchtfeuerFloodlightB2BBundle/Vendor/Pdp/LICENSE

echo "==> Scoping vendored library namespace (Vendor/Pdp only)"
cp build/scoper.inc.php staging-scoped/LeuchtfeuerFloodlightB2BBundle/scoper.inc.php
(
  cd staging-scoped/LeuchtfeuerFloodlightB2BBundle
  "$PLUGIN_DIR/build/vendor/bin/php-scoper" add-prefix \
    --config=scoper.inc.php \
    --output-dir=../pdp-scoped \
    --force
)
rm -rf staging-scoped/LeuchtfeuerFloodlightB2BBundle/Vendor/Pdp
mv staging-scoped/pdp-scoped staging-scoped/LeuchtfeuerFloodlightB2BBundle/Vendor/Pdp
rm staging-scoped/LeuchtfeuerFloodlightB2BBundle/scoper.inc.php

echo "==> Rewriting Pdp references in the plugin's own files"
find staging-scoped/LeuchtfeuerFloodlightB2BBundle -name '*.php' -not -path '*/Vendor/*' -print0 \
  | xargs -0 sed -i -E 's/(^|[^A-Za-z0-9_])Pdp\\/\1MauticPlugin\\LeuchtfeuerFloodlightB2BBundle\\Vendor\\Pdp\\/g'

echo "==> Linting staged plugin"
find staging-scoped/LeuchtfeuerFloodlightB2BBundle -name '*.php' -print0 | xargs -0 -n1 php -l

echo "==> Packaging ZIP"
(cd staging-scoped && zip -rq "../LeuchtfeuerFloodlightB2BBundle-${VERSION}.zip" LeuchtfeuerFloodlightB2BBundle)

echo "==> Cleaning up build artifacts (keeping only the ZIP)"
rm -rf build/vendor build/composer.lock staging-scoped

echo "==> Done: LeuchtfeuerFloodlightB2BBundle-${VERSION}.zip"
