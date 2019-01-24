#!/bin/bash
NAME="yoti-for-drupal-7.x-1.x-edge.zip"

SDK_TAG=$1
DEFAULT_SDK_TAG='2.0.0'
SDK_RELATIVE_PATH="sdk"

if [ "$SDK_TAG" = "" ]; then
    SDK_TAG=$DEFAULT_SDK_TAG
fi

echo "Pulling PHP SDK TAG $SDK_TAG.zip ..."

curl https://github.com/getyoti/yoti-php-sdk/archive/$SDK_TAG.zip -O -L
unzip $SDK_TAG.zip -d sdk
mv sdk/yoti-php-sdk-$SDK_TAG/src/* sdk
rm -rf sdk/yoti-php-sdk-$SDK_TAG

if [ ! -d "./yoti" ]; then
    echo "ERROR: Must be in directory containing ./yoti folder"
    exit
fi

if [ ! -d "$SDK_RELATIVE_PATH" ]; then
    "ERROR: Could not find SDK in $SDK_RELATIVE_PATH"
    exit
fi

echo "Packing plugin ..."

# move sdk symlink (used in symlink-plugin-to-site.sh)
sym_exist=0
if [ -L "./yoti/sdk" ]; then
    mv "./yoti/sdk" "./__sdk-sym";
    sym_exist=1
fi

cp -R "$SDK_RELATIVE_PATH" "./yoti/sdk"
zip -r "$NAME" "./yoti"
rm -rf "./yoti/sdk"

# move symlink back
if [ $sym_exist ]; then
    mv "./__sdk-sym" "./yoti/sdk"
fi
rm -rf sdk
echo "Plugin packed. File $NAME created."
echo ""
