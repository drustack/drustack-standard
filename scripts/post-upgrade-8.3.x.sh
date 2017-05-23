#!/usr/bin/env bash

for i in `seq 1 3`; do
    composer update -n
done

cd web
drush -y updatedb
drush -y core-cron
drush -y cache-rebuild

drush -y pm-uninstall \
    tmgmt \
    tmgmt_config \
    tmgmt_content \
    tmgmt_file \
    tmgmt_language_combination \
    tmgmt_local \
    tmgmt_locale

drush -y pm-enable \
    bootstrap_layouts \
    content_moderation \
    drustack_webform \
    field_layout \
    layout_discovery
