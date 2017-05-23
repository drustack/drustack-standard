#!/usr/bin/env bash

for i in `seq 1 3`; do
    composer update -n
done

cd web
drush -y updatedb
drush -y core-cron
drush -y cache-rebuild
drush -y pm-enable module_filter
drush -y pm-uninstall bootstrap_layouts layout_plugin content_moderation
