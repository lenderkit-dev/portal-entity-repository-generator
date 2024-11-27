.PHONY: info up down build bash generate.models generate.api

SHELL=/bin/bash

DC := docker compose
DC_EXEC := ${DC} exec
DC_RUN := ${DC} run --rm

info:
	@echo "Portal entity generator commands helper"

##
# @command up 		Up/start docker-compose stack
##
up:
	${DC} up -d --force-recreate

##
# @command down 	Down docker-compose stack and clean volumes. Aliases: `stop` (back compatibility for CI/CD)
##
down:
	${DC} down -v

##
# @command build 	Build docker images
##
build:
	${DC} build

##
# @command bash 	Open app container bash (PHP-CLI)
##
bash:
	${DC_RUN} php-cli bash

# default local path
OAS_SRC ?= 'https://api.lktest.hds108.jcdev.net/v2/swagger/source'

generate.models:
	docker run -it --rm \
		-v ${P}:/app/output  \
		-e PEG_OAS=${OAS_SRC} \
		-e PEG_OP=model \
		-e PEG_MODULE=${M} \
		ghcr.io/lenderkit-dev/portal-entities-generator
	sudo chown -R $$(whoami):$$(whoami) ${P}

generate.api:
	docker run -it --rm \
		-v ${P}:/app/output  \
		-e PEG_OAS=${OAS_SRC} \
		-e PEG_OP=api_operation_map \
		-e PEG_MODULE=${M} \
		ghcr.io/lenderkit-dev/portal-entities-generator;
	sudo chown -R $$(whoami):$$(whoami) ${P}
