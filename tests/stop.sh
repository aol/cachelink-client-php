#!/usr/bin/env bash
DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"
APP=$DIR/..
CACHELINK_DIR=$DIR/node_modules/cachelink-service

cd $DIR

# Stop the test.
docker kill cachelink_client_test
docker rm -f cachelink_client_test

# Stop redis instances.
docker kill cachelink_test_redis_cluster
docker rm -f cachelink_test_redis_cluster
docker kill cachelink_test_redis_single
docker rm -f cachelink_test_redis_single

# Stop cachelink instances.
docker-compose stop
