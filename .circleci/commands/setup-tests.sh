#!/usr/bin/env bash

directory="$PWD/.circleci/build/"

echo 'Configuring Magento for testing'
cp "$PWD"/.circleci/phpunit.xml.dist "$directory"phpunit.xml.dist
