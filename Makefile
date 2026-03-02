.PHONY: test test-unit test-integration codestyle phpstan devcontainer docker-up docker-down

test:
	vendor/bin/phpunit --color=always --no-coverage --display-all-issues

test-unit:
	vendor/bin/phpunit --color=always --no-coverage --display-all-issues --testsuite unit

test-integration:
	vendor/bin/phpunit --color=always --no-coverage --display-all-issues --testsuite integration

codestyle:
	vendor/bin/php-cs-fixer check --config=.php-cs-fixer.php .

phpstan:
	vendor/bin/phpstan analyse --memory-limit=4G --configuration=phpstan.neon --no-progress --no-interaction src/ tests/

devcontainer:
	docker build -t power-modules-devcontainer -f DockerfileDevContainer .

docker-up:
	docker compose up -d --wait

docker-down:
	docker compose down
