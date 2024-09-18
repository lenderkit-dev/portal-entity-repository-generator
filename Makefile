SHELL=/bin/bash

DC := docker compose
DC_EXEC := ${DC} exec
DC_RUN := ${DC} run --rm

##
# @command init 	Initialize important configuration files and environment
##
init:
	@if [ ! -f 'config/config.php' ]; then \
		echo 'Copying config.php file...'; \
		cp config/config.example.php config/config.php; \
	fi;
	@if [ ! -f 'docker-compose.yml' ]; then \
		echo 'Copying docker-compose.yml file...'; \
		cp docker-compose.example.yml docker-compose.yml; \
	fi;
	@echo 'NOTE: Please check your configuration in "config/config.php" before run.'
	@echo 'NOTE: Please check your configuration in "docker-compose.yml" before run.'
	@echo ''

##
# @command up 		Up/start docker-compose stack "web" container with dependencies. Aliases: `run` (back compatibility for CI/CD)
##
run: up
up:
	${DC} up -d --force-recreate

##
# @command down 	Down docker-compose stack and clean volumes. Aliases: `stop` (back compatibility for CI/CD)
##
stop: down
down:
	${DC} down -v

##
# @command build 	Build new PHP docker image
##
build:
	${DC} build

##
# @command php-bash 	Open app container bash (PHP-FPM)
##
bash:
	${DC_EXEC} portal-entity-repository-generator bash

generate:
	docker run -it --rm portal-entity-repository-generator php bin/peg $(source) $(type) $(output)
