# portal-entity-repository-generator

### usage
Run command to init configs before start using tool

`make init`

Run command with params

`make generate source=source/source.json type=model output=./output`

or directly to Docker

`docker run -it --rm portal-entity-repository-generator php bin/peg source/source.yaml model ./output`

* Source - path to file or url with swagger source.
  * In case using local file, put file to source directory and specify path to file like `source=source/oas.json`.
  * In case using URL dont forget to cover it `source='https://host/v1/swagger/source'`.
* Type - type of generated models. Accepted values:
  * api_operation_map;
  * model;
* Output - path that depend on its product or commercial structure. By default its "./output"
