install:
	composer install --optimize-autoloader --classmap-authoritative
	deno install --minimum-dependency-age=P1W

# dev =========================================================================
clear:
	php artisan optimize:clear

dev:
	deno run dev

fmt:
	./vendor/bin/php-cs-fixer fix $(_file)

bump-deps:
	composer update -W \
		laravel/framework \
		laravel/boost \
		laravel/fortify \
		laravel/tinker \
		livewire/livewire \
		livewire/flux \

	deno update --minimum-dependency-age=P1W

# test ========================================================================
test:
	php artisan test --parallel

test-coverage:
	herd coverage vendor/bin/pest --coverage

# deploy ======================================================================
build:
	# deno run build - TODO restore this after alpine updates its repos
	npm run build
	php artisan optimize:clear
	php artisan optimize

# setup =======================================================================
setup:
	sh ./_scripts/setup.sh

update:
	sh ./_scripts/update.sh
