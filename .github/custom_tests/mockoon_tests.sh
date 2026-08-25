#!/bin/bash
cd plugins/LeuchtfeuerFloodlightB2BBundle/Tests/Mockoon
npm install
npx mockoon-cli start --data ./floodlight_mockoon.json &
MOCKOON_PID=$!
sleep 5
cd -
export FLOODLIGHT_API_URL="http://localhost:3002"
bin/phpunit -d memory_limit=2G --bootstrap vendor/autoload.php --group=no-ci
TEST_EXIT_CODE=$?
kill $MOCKOON_PID
exit $TEST_EXIT_CODE
