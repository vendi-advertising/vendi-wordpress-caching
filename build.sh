#!/bin/bash
cd ../
rm -f vendi-wordpress-caching.zip
zip -r9 vendi-wordpress-caching.zip vendi-wordpress-caching/  -x "*/.*" -x *.git* -x tests/* -x bin/* -x .travis.yml -x .editorconfig -x .distignore -x phpunit.xml -x Gruntfile.js -x build.sh -x run-phpunit.sh -x phpunit.xml.dist -x package.json
cd vendi-wordpress-caching