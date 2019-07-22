#!/bin/bash

SDK_TAG=$1

NAME="yoti-for-drupal-7.x-1.x-edge.zip"
SDK_RELATIVE_PATH="sdk"

./checkout-sdk.sh "$SDK_TAG"

echo "Packing plugin ..."

cp -R "$SDK_RELATIVE_PATH" "./yoti/sdk"
zip -r "$NAME" "./yoti"
rm -rf "./yoti/sdk"

rm -rf sdk

echo "Plugin packed. File $NAME created."
echo ""