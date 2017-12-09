#!/usr/bin/env bash

directory="$PWD/.circleci/build/"

mgver="2.2.1"

if [[ -f "$directory"app/etc/env.php ]]; then
    echo "Magento $mgver appears already to be installed."
    exit 0
fi

echo "Downloading Magento $mgver"
mkdir "$directory"
cd "$directory"
curl -LSs "https://github.com/magento/magento2/archive/$mgver.tar.gz" | tar --strip-components=1 -xzf-

composer install

echo "Installing Magento $mgver"
php -dmemory_limit=1g -f bin/magento setup:install \
    --language='en_US' \
    --timezone='America/New_York' \
    --db-host='127.0.0.1' \
    --db-name='circle_test' \
    --db-user='ubuntu' \
    --db-password 'ubuntu' \
    --base-url='http://circleci.magento.local/' \
    --use-rewrites='1' \
    --use-secure='0' \
    --use-secure-admin='1' \
    --admin-user='admin' \
    --admin-lastname='Doe' \
    --admin-firstname='John' \
    --admin-email='john.doe@example.com' \
    --admin-password='password123' \
    --session-save='files' \
    --backend-frontname='admin' \
    --currency='USD' \
    --base-url-secure='https://circleci.magento.local/'
