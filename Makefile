SHELL=/bin/bash

DC := docker compose
DC_EXEC := ${DC} exec
DC_RUN := ${DC} run --rm

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
	docker run -it --rm portal-entity-repository-generator php bin/peg $(source) $(type) $(module) $(output)
