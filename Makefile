.PHONY: php_qa php_lint php_cs php_csf phpstan php_tests php_coverage python_qa python_tests python_coverage

all:
	@$(MAKE) -pRrq -f $(lastword $(MAKEFILE_LIST)) : 2>/dev/null | awk -v RS= -F: '/^# File/,/^# Finished Make data base/ {if ($$1 !~ "^[#.]") {print $$1}}' | sort | egrep -v -e '^[^[:alnum:]]' -e '^$@$$' | xargs

vendor: composer.json composer.lock
	composer install

php_qa: php_lint phpstan php_cs

php_lint: vendor
	vendor/bin/linter src tests

php_cs: vendor
	vendor/bin/codesniffer src tests

php_csf: vendor
	vendor/bin/codefixer src tests

phpstan: vendor
	vendor/bin/phpstan analyse -c phpstan.neon src

php_tests: vendor
	vendor/bin/tester -s -p php --colors 1 -C tests/cases

php_coverage: vendor
	vendor/bin/tester -s -p php --colors 1 -C --coverage ./coverage.xml --coverage-src ./src tests/cases

pylint:
	python -m pip install pylint

mypy:
	python -m pip install mypy

black:
	python -m pip install black

isort:
	python -m pip install isort

python_qa: python_cs python_types python_isort python_black

python_cs: pylint
	pylint **/*.py

python_types: mypy
	mypy **/*.py

python_isort: isort
	isort **/*.py --check

python_black: black
	black **/*.py --check

python_tests:
	python -m unittest

python_coverage:
	coverage run --source=fastybird_modbus_connector -m unittest
