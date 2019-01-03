#!/bin/bash
NAME="yoti-for-drupal-7.x-1.x-edge.zip"

TAG=$1
DEFAULT_TAG='1.2.1'
SDK_RELATIVE_PATH="sdk"

if [ "$TAG" = "" ]; then
    TAG=$DEFAULT_TAG
fi

echo "Pulling tag $TAG.zip ..."

curl https://github.com/getyoti/yoti-php-sdk/archive/$TAG.zip -O -L
unzip $TAG.zip -d sdk
mv sdk/yoti-php-sdk-$TAG/src/* sdk
rm -rf sdk/yoti-php-sdk-$TAG

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
