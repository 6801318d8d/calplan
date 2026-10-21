#!/bin/sh
# Builds the Nextcloud App Store archive build/supercalplan-<version>.tar.gz from
# the committed HEAD: one top folder "supercalplan" holding the app, without the
# tests, development files and screenshots (the App Store loads those from GitHub).
set -eu
cd "$(dirname "$0")/.."
version=$(sed -n 's:.*<version>\(.*\)</version>.*:\1:p' appinfo/info.xml | head -n 1)
mkdir -p build
archive="build/supercalplan-$version.tar.gz"
git archive --format=tar --prefix=supercalplan/ HEAD -- . \
	':(exclude)tests' ':(exclude)scripts' ':(exclude)img/screenshots' \
	':(exclude)phpunit.xml' ':(exclude)composer.json' ':(exclude)composer.lock' \
	':(exclude).gitignore' ':(exclude)ENGINEERING.md' ':(exclude)TODO.md' \
	| gzip -9n > "$archive"
echo "$archive"
