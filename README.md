# portal-entity-repository-generator

### usage

`php script.php \<source> \<type>`

* Source - path to file or url with swagger source
* Type - type of generated models. Accepted values:
  * api_operation_map;
  * model;
  * general;

Models will be generated to `output` directory