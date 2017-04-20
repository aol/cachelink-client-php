#!/usr/bin/env bash
DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"
APP=$DIR/..
CACHELINK_DIR=$DIR/node_modules/cachelink-service

cd $DIR

# Install NPM dependencies
docker run -it --rm \
	--name cachelink_npm_install \
	-v $DIR:/tests \
	-w /tests \
	node:6 \
	npm install

mkdir -p $CACHELINK_DIR/build

# Start redis single and redis cluster instances.
$CACHELINK_DIR/test/env/start-single.sh
$CACHELINK_DIR/test/env/start-cluster.sh

# Run cachelink instances.
docker-compose stop
docker-compose up -d

# Build PHP container for running tests.
docker rm -f cachelink_client_test
docker build . -t cachelink_client_test
