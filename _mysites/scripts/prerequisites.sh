#!/usr/bin/env bash

missing=false;

## is docker installed
if ! command -v docker > /dev/null; then
  missing=true;
  echo "* Docker is not installed"

fi
## is git installed
if ! command -v git > /dev/null; then
  missing=true;
  echo "* git is not installed";
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

## is brew installed
if ! command -v brew > /dev/null; then
  missing=true
  echo "* brew is not installed";
fi

if  $missing ; then
  exit 1;
fi

exit 0;