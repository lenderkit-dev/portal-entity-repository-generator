# LK Portal Entity Generator

## Local installation

* Clone repository
* `touch .env`
* `make build`

To debug and test tool inside the container use command
```make bash```

### Testing

To test final command usage locally you need:

* Run build with `make build`

Temporary docker image will be built with name   
```docker.io/library/peg-php-cli```.

* Replace github docker image with local one in the `docker run` commands from
"Usage" section.

## Usage

To take OAS specs from local instance you `host.docker.internal` 
with port, instead of your domain.

```
docker run -it --rm \
    -v ./output:/app/output \ 
    -e PEG_OAS='https://host.docker.internal:8001/v2/swagger/source' \ 
    -e PEG_OP=model \
    -e PEG_MODULE=users \
     docker.io/library/peg-php-cli
```

Available operations (`PEG_OP`):

* `api_operation_map` Generate API operations map file for specified module.
* `model` Generate model classes for specified module.

### Simplify usage in project

To simplify usage you may prepare makefile commands, like this:

```makefile
OAS_SRC ?= 'https://host.docker.internal:8001/v2/swagger/source'

generate.models:
	docker run -it --rm \
		-v ${P}:/app/output  \
		-e PEG_OAS=${OAS_SRC} \
		-e PEG_OP=model \
		-e PEG_MODULE=${M} \
		docker.io/library/peg-php-cli;
	sudo chown -R $$(whoami):$$(whoami) ${P}

generate.api:
	docker run -it --rm \
		-v ${P}:/app/output  \
		-e PEG_OAS=${OAS_SRC} \
		-e PEG_OP=api_operation_map \
		-e PEG_MODULE=${M} \
		docker.io/library/peg-php-cli;
	sudo chown -R $$(whoami):$$(whoami) ${P}
```

By default, it will use local server to get OAS specs.

Use as follows:

```bash
make generate.models M=users P=./modules/core
make generate.api M=users P=./modules/core
```

OR with custom OAS source:

```bash
make generate.models M=users P=./modules/core OAS_SRC='https://host.docker.internal:8001/v2/swagger/source'
```
