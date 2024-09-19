# portal-entity-repository-generator

### usage
Run command to init configs before start using tool

set env variables to use without params

`docker run -it --rm -v ./source:/app/source --env-file .env  -v ./output:/app/output portal-entity-repository-generator:latest`

or provide it directly with command

`docker run -it --rm -v ./source:/app/source --env-file .env  -v ./output:/app/output portal-entity-repository-generator:latest <source> <type> <output> <module>`

### Parameters:
* Source - path to file or url with swagger source.
  * In case using local file, put file to source directory and specify path to file like `source/oas.json`.
  * In case using URL dont forget to cover it `https://host/v1/swagger/source`.
* Type - type of generated models. Accepted values:
  * api_operation_map;
  * model;
* Output - path that depend on its product or commercial structure. By default its "./output"
* Module - module to apply filter by it
