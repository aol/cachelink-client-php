#!/usr/bin/env bash
DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"
APP=$(dirname $DIR)

cd $DIR

# Run in PHP container on the same network.
docker run -i --rm \
	--net host \
	--name cachelink_client_test \
	-v $APP:$APP \
	-v $HOME:$HOME \
	-v /private:/private \
	-w $APP \
	cachelink_client_test \
	$APP/vendor/bin/phpunit -c $APP/phpunit.xml
