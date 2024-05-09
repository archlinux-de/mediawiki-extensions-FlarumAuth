COMPOSER := 'composer --no-interaction'

install:
	{{COMPOSER}} update --prefer-stable

test:
	{{COMPOSER}} validate --strict --no-check-lock
	vendor/bin/phpcs
	vendor/bin/phpstan analyse --memory-limit=-1
	vendor/bin/phpunit

test-coverage:
	php -d zend_extension=xdebug -d xdebug.mode=coverage -d memory_limit=-1 vendor/bin/phpunit --coverage-html coverage
