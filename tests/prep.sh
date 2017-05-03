#!/usr/bin/env bash
DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"
APP=$(dirname $DIR)
CACHELINK_DIR=$DIR/node_modules/cachelink-service

cd $DIR

mkdir -p $DIR/node_modules
chmod ug+rw $DIR/node_modules

USER=$(whoami)
USERID=$(id -u)
GROUPID=$(id -g)

echo "Running npm install as user '$USER' ($USERID:$GROUPID)..."

# Install NPM dependencies
docker run --user $USERID:$GROUPID -it --rm \
  --name cachelink_npm_install \
  -v $DIR:/tests \
  -v $HOME:$HOME \
  -e HOME=$HOME \
  -w /tests \
  node:6 \
  npm install

# Ensure build directory exists.
if [ ! -d "$CACHELINK_DIR/build" ]; then
  echo "Build directory ($CACHELINK_DIR/build) does not exist, cannot start redis.";
  exit 1;
fi

# Start redis single and redis cluster instances.
$CACHELINK_DIR/test/env/start-single.sh
$CACHELINK_DIR/test/env/start-cluster.sh

# Run cachelink instances.
docker-compose stop
docker-compose up -d

# Give some time to let cachelink services start up
echo "Waiting for cachelink services to start..."
sleep 4
