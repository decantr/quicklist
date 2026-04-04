#!/bin/sh
# install the dependencies for alpine linux

# dependencies
apk add \
	git \
	make \
	npm \


# php deps
apk add \
	composer \
	php \
	php-dom \
	php-exif \
	php-fileinfo \
	php-gd \
	php-intl \
	php-pcntl \
	php-pdo \
	php-pdo_sqlite \
	php-session \
	php-simplexml \
	php-tokenizer \
	php-xml \
	php-xmlwriter \
