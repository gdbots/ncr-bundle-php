# CHANGELOG for 0.x
This changelog references the relevant changes done in 0.x versions.


## v0.3.3
* Add service definition for `gdbots_ncr.unique_node_validator`.


## v0.3.2
* When using useAttributeAsKey in `Configuration` set `normalizeKeys(false)`.


## v0.3.1
* Add service definition for `gdbots_ncr.node_etag_enricher` to ensure node's always get a new etag on create/update.


## v0.3.0
__BREAKING CHANGES__

* Require `"gdbots/pbjx-bundle": "~0.3"` and `"gdbots/ncr": "~0.2"`.
* Change composer type to `symfony-bundle`.
* Add `pbjx.binder` tag to `gdbots_ncr.node_command_binder` service.
* Remove `curie` attribute from `pbjx.handler` tag on `gdbots_ncr.get_node_batch_request_handler` service.
* Mark all classes as final as they are not meant to be extended.


## v0.2.0
__BREAKING CHANGES__

* Register all commands in `Command` namespace using new Symfony 4 convention.
* Require `"gdbots/pbjx-bundle": "~0.2"` which requires `"symfony/framework-bundle": "^4.0"`.
* Remove `Gdbots\Bundle\NcrBundle\Form` as symfony forms is no longer apart of `gdbots/pbjx-bundle`.
* Register interfaces and classes for autowiring:
  * `Gdbots\Ncr\Ncr`
  * `Gdbots\Ncr\NcrCache`
  * `Gdbots\Ncr\NcrLazyLoader`
  * `Gdbots\Ncr\NcrSearch`


## v0.1.1
* Add support for Symfony 4.
* Add `ncr:get-node` symfony console command.


## v0.1.0
* Initial version.
