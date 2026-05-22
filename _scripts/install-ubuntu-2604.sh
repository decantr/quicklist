#!/bin/sh
# install the dependencies for alpine linux

# dependencies
sudo apt install \
	git \
	make

# php deps
# php-fpm needed to avoid pulling apache
sudo apt install \
	composer \
	php \
	php-dom \
	php-exif \
	php-fileinfo \
	php-fpm \
	php-gd \
	php-intl \
	php-pdo \
	php-simplexml \
	php-sqlite3 \
	php-tokenizer \
	php-xml \
	php-xmlwriter
