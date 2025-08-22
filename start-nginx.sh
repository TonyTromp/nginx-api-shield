#!/bin/bash

# Replace the placeholder with the actual upstream value
sed "s/UPSTREAM_PLACEHOLDER/$PROTECTED_UPSTREAM/g" /etc/nginx/nginx.conf.template > /etc/nginx/nginx.conf

# Start nginx
exec dnsmasq -d & nginx -g "daemon off;"

# CMD ["sh", "-c", "dnsmasq -d & nginx -g 'daemon off;'"]
