#!/bin/bash
cd plugins/LeuchtfeuerFloodlightB2BBundle/Tests/Mockoon
npm install
npx mockoon-cli start --data ./floodlight_mockoon.json
cd -
set FLOODLIGHT_API_URL="http://localhost:3002"
bin/phpunit -d memory_limit=2G --bootstrap vendor/autoload.php --configuration Tests/phpunit.xml.dist --group=no-ci
