#!/bin/bash
cd plugins/LeuchtfeuerFloodlightB2BBundle/Tests/Mockoon
npm install
npx mockoon-cli start --data ./floodlight_mockoon.json &
MOCKOON_PID=$!
sleep 5
cd -
export FLOODLIGHT_API_URL="http://localhost:3002"
# The CI runner ships a PHPUnit 9 config for Mautic 5; fall back to the config copied into the plugin dir.
PHPUNIT_CONFIG="plugins/LeuchtfeuerFloodlightB2BBundle/phpunit.xml"
if [ -f "ciFiles/ciFiles/phpunit_5.xml" ]; then
  cp ciFiles/ciFiles/phpunit_5.xml "$PHPUNIT_CONFIG"
fi
bin/phpunit -d memory_limit=2G --bootstrap vendor/autoload.php --configuration "$PHPUNIT_CONFIG" --group=no-ci
TEST_EXIT_CODE=$?
kill $MOCKOON_PID
exit $TEST_EXIT_CODE
