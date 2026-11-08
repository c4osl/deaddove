#!/bin/bash

set -euo pipefail

SOURCE_DIR="$HOME/GitHub/deaddove/"
SVN_REPO="$HOME/svn/dead-dove/"
README_FILE="${SOURCE_DIR}readme.txt"
PLUGIN_FILE="${SOURCE_DIR}content-warning.php"
SVN_USERNAME="${SVN_USERNAME:-c4osl}"

if ! command -v svn >/dev/null 2>&1; then
    echo "Error: svn is not installed."
    exit 1
fi

if [[ ! -d "${SVN_REPO}.svn" ]]; then
    echo "Error: ${SVN_REPO} is not an SVN checkout."
    echo "Create it with: svn checkout https://plugins.svn.wordpress.org/dead-dove ${SVN_REPO}"
    exit 1
fi

if [[ ! -f "$README_FILE" ]]; then
    echo "Error: Could not find readme.txt at $README_FILE"
    exit 1
fi

STABLE_TAG=$(
    grep -i '^Stable tag:' "$README_FILE" |
        sed -E 's/^[Ss]table [Tt]ag:[[:space:]]*//' |
        tr -d '\r' |
        xargs
)

if [[ -z "$STABLE_TAG" ]]; then
    echo "Error: Could not extract Stable tag from $README_FILE"
    exit 1
fi

if [[ "$STABLE_TAG" == "trunk" ]]; then
    echo "Error: Stable tag is set to 'trunk', so no version tag can be created."
    exit 1
fi

PLUGIN_VERSION=$(
    grep -m1 -E '^[[:space:]]*\*[[:space:]]*Version:' "$PLUGIN_FILE" |
        sed -E 's/^.*Version:[[:space:]]*//' |
        tr -d '\r' |
        xargs
)

if [[ "$PLUGIN_VERSION" != "$STABLE_TAG" ]]; then
    echo "Error: Stable tag ($STABLE_TAG) does not match the plugin header version ($PLUGIN_VERSION)."
    exit 1
fi

echo "Stable tag detected: $STABLE_TAG"

echo "Syncing plugin files into trunk..."
rsync -a --delete \
    --exclude='.*' \
    --exclude='/assets/' \
    --exclude='/node_modules/' \
    --exclude='/generated-icon.png' \
    --exclude='/package.json' \
    --exclude='/package-lock.json' \
    --exclude='/eslint.config.mjs' \
    --exclude='/phpcs.xml' \
    --exclude='/vendor/' \
    --exclude='/tests/' \
    --exclude='/phpunit.xml.dist' \
    --exclude='/composer.json' \
    --exclude='/composer.lock' \
    --exclude='/release-to-svn.sh' \
    "$SOURCE_DIR" "${SVN_REPO}trunk/"

if [[ -d "${SOURCE_DIR}assets" ]]; then
    echo "Syncing assets..."
    rsync -a --delete --exclude='.*' "${SOURCE_DIR}assets/" "${SVN_REPO}assets/"
fi

echo "Removing deleted files from SVN index..."
svn status "$SVN_REPO" | awk '/^!/ {print substr($0,9)}' | while read -r missing; do
    if [[ -n "$missing" ]]; then
        svn rm --force "$missing"
    fi
done

echo "Adding new files to SVN..."
svn add --force "${SVN_REPO}trunk" "${SVN_REPO}assets" --auto-props --parents --depth infinity

TAG_PATH="${SVN_REPO}tags/${STABLE_TAG}"

if svn info "$TAG_PATH" >/dev/null 2>&1; then
    echo "Tag ${STABLE_TAG} already exists at ${TAG_PATH}"
    echo "Skipping svn copy."
else
    echo "Creating SVN tag ${STABLE_TAG} from trunk..."
    svn copy "${SVN_REPO}trunk" "$TAG_PATH"
fi

echo "Done."
echo "Review changes with: svn status ${SVN_REPO}"
echo "Then commit with: svn commit -m \"Release version ${STABLE_TAG}\" ${SVN_REPO} --username ${SVN_USERNAME}"
