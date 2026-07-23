#!/bin/sh
# install the dependencies for arch linux

# dependencies
# sudo pacman -Sy \
# 	git \
# 	make \
# 	deno \


# php deps
sudo pacman -Sy \
	composer \
	php \
	php-gd \
	php-intl \
	php-sqlite \

# enable extensions
sudo tee /etc/php/conf.d/50-quicklist.ini <<EOF
;extension=curl
extension=exif
extension=gmp
extension=iconv
extension=intl
extension=pdo_sqlite
;extension=zip
EOF
