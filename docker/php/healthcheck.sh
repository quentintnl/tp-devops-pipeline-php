#!/bin/sh
# Healthcheck php-fpm : ping le pool via FastCGI (cgi-fcgi) sur 127.0.0.1:9000.
# Réponse attendue : "pong" (cf. ping.path/ping.response du pool www).
# Exit 0 = sain, exit non-nul = malsain → Docker marque le conteneur unhealthy.
set -e

REQUEST_METHOD=GET \
SCRIPT_NAME=/ping \
SCRIPT_FILENAME=/ping \
cgi-fcgi -bind -connect 127.0.0.1:9000 | grep -q pong
