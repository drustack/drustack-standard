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
    node-sass \
    sass-lint


# Install PHP
LC_ALL=C.UTF-8 add-apt-repository -y ppa:ondrej/php

$APT_GET update
$APT_GET install \
    php-apcu \
    php-apcu-bc \
    php-geoip \
    php-memcached \
    php-uuid \
    php-xdebug \
    php7.2-bcmath \
    php7.2-bz2 \
    php7.2-cli \
    php7.2-curl \
    php7.2-gd \
    php7.2-imap \
    php7.2-intl \
    php7.2-json \
    php7.2-ldap \
    php7.2-mbstring \
    php7.2-mysql \
    php7.2-opcache \
    php7.2-pgsql \
    php7.2-soap \
    php7.2-sqlite3 \
    php7.2-tidy \
    php7.2-xml \
    php7.2-xmlrpc \
    php7.2-xsl \
    php7.2-zip

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
