#!/bin/bash

# (c) Wong Hoi Sing Edison <hswong3i@pantarei-design.com>
#
# Licensed under the Apache License, Version 2.0 (the "License");
# you may not use this file except in compliance with the License.
# You may obtain a copy of the License at
#
#     http://www.apache.org/licenses/LICENSE-2.0
#
# Unless required by applicable law or agreed to in writing, software
# distributed under the License is distributed on an "AS IS" BASIS,
# WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
# See the License for the specific language governing permissions and
# limitations under the License.

# This script bootstrap you command line interface for Drupal development.
#
# Assume running with Ubuntu Xenial/Bionic, just bootstrap for php-cli but
# NOT mod_php nor php-fpm. Also install Node.js for SASS/SCSS development.

set -ex

PATH="$HOME/bin:$HOME/.local/bin:$PATH"

export DEBIAN_FRONTEND=noninteractive
APT_GET='apt-get -qy -o DPkg::options::=--force-confdef -o DPkg::options::=--force-confold -o APT::Install-Suggests=0 -o APT::Install-Recommends=0'

# Install depedencies
$APT_GET update
$APT_GET install \
    apt-transport-https \
    ca-certificates \
    debian-archive-keyring \
    debian-keyring \
    mysql-client \
    postgresql-client \
    python-software-properties \
    software-properties-common \
    sqlite3


# Install Node.js
curl -sL https://deb.nodesource.com/gpgkey/nodesource.gpg.key | apt-key add -
add-apt-repository -y "deb https://deb.nodesource.com/node_10.x $(lsb_release -sc) main"

$APT_GET update
$APT_GET install \
    nodejs

npm install --unsafe-perm -g -S -C /root \
    node-sass


# Install PHP
LC_ALL=C.UTF-8 add-apt-repository -y ppa:ondrej/php

$APT_GET update
$APT_GET install \
    php-apcu \
    php-geoip \
    php-memcached \
    php-uuid \
    php-xdebug \
    php5.6-bcmath \
    php5.6-bz2 \
    php5.6-cli \
    php5.6-curl \
    php5.6-gd \
    php5.6-imap \
    php5.6-intl \
    php5.6-json \
    php5.6-ldap \
    php5.6-mbstring \
    php5.6-mysql \
    php5.6-opcache \
    php5.6-pgsql \
    php5.6-soap \
    php5.6-sqlite3 \
    php5.6-tidy \
    php5.6-xml \
    php5.6-xmlrpc \
    php5.6-xsl \
    php5.6-zip

curl -sL https://getcomposer.org/download/1.6.5/composer.phar > $HOME/bin/composer
chmod 0777 $HOME/bin/composer
composer self-update --no-interaction

COMPOSER_BIN_DIR=$HOME/bin composer global require -n \
    hirak/prestissimo:@stable \
    consolidation/cgr:@stable

CGR_BIN_DIR=$HOME/bin cgr require -n \
    drupal/coder:@stable \
    drupal/console:~1.0 \
    drush/drush:~8.0 \
    friendsofphp/php-cs-fixer:@stable \
    phpunit/phpunit:~5.7 \
    sami/sami:@stable \
    squizlabs/php_codesniffer:@stable

CGR_BIN_DIR=$HOME/bin cgr update

drush -y @none dl utf8mb4_convert-7.x
drush -y @none dl registry_rebuild-7.x
