# dev =========================================================================
clear:
	php artisan optimize:clear

build:
	npm run build
	php artisan optimize:clear
	php artisan optimize

fmt:
	./vendor/bin/php-cs-fixer fix

# test ========================================================================
test:
	php artisan test --parallel

test-full:
	rm composer.lock package-lock.json
	composer install
	npm install
	npm run build
	php artisan optimize:clear
	php artisan test --parallel

# setup ======================================================================
setup:
	sh ./_setup/alpine.sh
	cp -n .env.example .env
	composer install --no-dev --optimize-autoloader --classmap-authoritative
	npm install --production --omit=dev

update:
	git pull
	rm composer.lock package-lock.json
	composer install --no-dev --optimize-autoloader --classmap-authoritative
	npm install --production
	php artisan migrate --force
	make build
