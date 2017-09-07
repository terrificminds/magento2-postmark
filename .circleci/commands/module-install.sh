#!/usr/bin/env bash

directory="$PWD/.circleci/build/"

mkdir -p "$directory"app/code/SUMOHeavy/Postmark
rsync -av --progress "$PWD"/* "$directory"app/code/SUMOHeavy/Postmark/ --exclude ".circleci" --exclude ".git"

cd "$directory"
php bin/magento module:enable SUMOHeavy_Postmark
php bin/magento setup:upgrade
