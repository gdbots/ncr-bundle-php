# CHANGELOG for 0.x
This changelog references the relevant changes done in 0.x versions.


## v0.3.13
* Add `NcrExtension::derefNodes` and twig function `ncr_deref_nodes`.


## v0.3.12
* Add optional namespace argument to all NcrExtension twig methods and pass through to NcrPreloader. 


## v0.3.11
* Use `app_env` parameter if available instead of `kernel.environment`.


## v0.3.10
* Add `$andClear` argument to `NcrExtension::getPreloaded[Published]Nodes(bool $andClear = true)` so twig functions `ncr_get_preloaded_nodes` and `ncr_get_preloaded_published_nodes` don't return the same preloaded nodes.
* Add `NcrExtension::isNodePublished` and twig function `ncr_is_node_published`.


## v0.3.9
* Update `gdbots_ncr.ncr_request_interceptor` service definition to provide `cache.app` as first argument since `gdbots/ncr` v0.3.11 adds caching for slug lookups.


## v0.3.8
* Add twig functions in NcrExtension:
  * ncr_preload_embedded_nodes
  * ncr_to_node_ref


## v0.3.7
* Actually return the preloaded nodes in the NcrExtension.


## v0.3.6
* Fix invalid service config for `gdbots_ncr.twig.ncr_extension`.


## v0.3.5
* Add service definition for `ncr_preloader`.
* Add twig functions to access NcrPreloader:
  * ncr_get_preloaded_nodes
  * ncr_get_preloaded_published_nodes
  * ncr_preload_node
  * ncr_preload_nodes


## v0.3.4
* Add service definition for `gdbots_ncr.node_idempotency_validator`.


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
