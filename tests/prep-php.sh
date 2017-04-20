#!/usr/bin/env bash
DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"

cd $DIR

# Build PHP container for running tests.
docker rm -f cachelink_client_test
docker build . -t cachelink_client_test
