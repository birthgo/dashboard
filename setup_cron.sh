#!/bin/sh
# Migration deliberately does not replace crontabs or enable a second writer.
printf '%s\n' 'No cron changed. Read MIGRATION_VM53.md and inspect the existing .52/.155 jobs first.'
printf '%s\n' 'The .155 GitHub Pages exporter remains unchanged. Enable appointments exporter on .53 only after owner cutover.'
