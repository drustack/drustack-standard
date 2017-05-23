#!/usr/bin/env bash

cd web
drush -y updb
drush -y cron
drush -y cr
drush -y pm-uninstall bootstrap_layouts layout_plugin content_moderation
