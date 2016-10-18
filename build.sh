#!/bin/bash
cd ../
rm -f vendi-wordpress-caching.zip
zip -r9 vendi-wordpress-caching.zip vendi-wordpress-caching/  -x "*/.*" -x vendi-wordpress-caching/.git* -x vendi-wordpress-caching/tests/* -x vendi-wordpress-caching/bin/* -x vendi-wordpress-caching/.travis.yml -x vendi-wordpress-caching/.editorconfig -x vendi-wordpress-caching/.distignore -x vendi-wordpress-caching/phpunit.xml -x vendi-wordpress-caching/Gruntfile.js -x vendi-wordpress-caching/build.sh -x vendi-wordpress-caching/run-phpunit.sh -x vendi-wordpress-caching/phpunit.xml.dist -x vendi-wordpress-caching/package.json
cd vendi-wordpress-caching