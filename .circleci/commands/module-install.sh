#!/usr/bin/env bash

directory="$PWD/.circleci/build/"

mkdir -p "$directory"app/code/Ripen/Postmark
rsync -av --progress "$PWD"/* "$directory"app/code/Ripen/Postmark/ --exclude ".circleci" --exclude ".git"

cd "$directory"
php bin/magento module:enable Ripen_Postmark
php bin/magento setup:upgrade
