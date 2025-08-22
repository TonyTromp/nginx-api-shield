FROM openresty/openresty:alpine-fat

# Install git (needed by luarocks), dnsmasq, and build dependencies
RUN apk update && apk add --no-cache git dnsmasq

# Install lua-resty-redis via luarocks from path included in the image
RUN /usr/local/openresty/luajit/bin/luarocks install lua-resty-redis

# Copy your nginx.conf to appropriate directory
COPY nginx.conf /usr/local/openresty/nginx/conf/nginx.conf

# Create dnsmasq configuration directory
RUN mkdir -p /etc/dnsmasq.d

# Copy dnsmasq configuration (optional - you can create this file separately)
# COPY dnsmasq.conf /etc/dnsmasq.conf

EXPOSE 80 53

# Start both dnsmasq and nginx
CMD ["sh", "-c", "dnsmasq -d & nginx -g 'daemon off;'"]
