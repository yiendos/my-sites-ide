#!/usr/bin/env bash

missing=false;

## is docker installed
if ! command -v docker > /dev/null; then
  missing=true;
  echo "* Docker is not installed"

fi

## is php installed
if ! command -v php > /dev/null; then
  missing=true;
  echo "* php is not installed";

fi

#which composer
if ! command -v composer > /dev/null; then
  missing=true;
  echo "* composer is not installed";

fi

if  $missing ; then
  exit 1;
fi

echo "\n\n * This is launch command, your approach is looking real good from here";
exit 0;