ncr-bundle-php
=============

[![Build Status](https://api.travis-ci.org/gdbots/ncr-bundle-php.svg)](https://travis-ci.org/gdbots/ncr-bundle-php)

Symfony bundle that integrates [gdbots/ncr](https://github.com/gdbots/ncr-php) library.


# Configuration
Follow the standard [bundle install](http://symfony.com/doc/current/bundles/installation.html)
using __gdbots/ncr-bundle__ as the composer package name.

> The examples below assume you're running the DynamoDb Ncr and Elastica NcrSearch.

__config/packages/ncr.yml:__

```yaml
# many of the configurations below are defaults so you can remove them
# from your configuration, added here for reference

parameters:
  cloud_region: 'us-west-2'
  es_clusters:
    default:
      debug: '%kernel.debug%'
      timeout: 300 # default
      persistent: true # default
      round_robin: true # default
      servers:
        - {host: '127.0.0.1', port: 9200} # default

gdbots_ncr:
  ncr:
    provider: dynamodb
    memoizing:
      enabled: true # default
      #
      # IMPORTANT read_through notes for the memoizer
      #
      # If true, the NcrCache will be updated when a cache miss occurs.
      # When the Pbjx request bus is in memory, then you'd want this
      # to be false so there aren't two processes updating the NcrCache.
      # One is the memoizer and the other are event listeners which are
      # updating cache after successful get node requests.
      read_through: false # default
    psr6:
      enabled: true # default
      # by default this cache is the same cache as your application
      # it is recommend to configure this separately because Ncr items
      # can be in cache much longer than app cache, for example, they
      # don't need to be cleared on app deployments.
      provider: cache.app # default
      read_through: true # default
      # to customize the cache keys or tweak cache times
      # you must provide your own class and override those methods.
      # see Psr6Ncr::getCacheKey and Psr6Ncr::beforeSaveCacheItem
      #class: Acme\Ncr\Repository\Psr6Ncr
    ncr_cache:
      max_items: 500 # default
    dynamodb:
      config:
        # these apply to batch get operations
        batch_size: 100 # default
        pool_size: 25 # default
      table_manager:
        # multi-tenant applications will likely need to provide a custom
        # table manager so node tables can be derived at runtime.
        # class: Acme\Ncr\Repository\DynamoDb\TableManager
        table_name_prefix: my-ncr # defaults to: "%kernel.environment%-ncr"
        node_tables:
          # any SchemaCurie not defined here will end up using the default
          # the entire default key is not needed unless you're changing it
          default:
            class: Gdbots\Ncr\Repository\DynamoDb\NodeTable # default
            table_name: multi # default
          'acme:article':
            table_name: article
          'acme:user':
            # class is optional but needed if you want to have custom
            # global secondary indexes or customize items before they
            # are put into the table.
            class: Acme\Ncr\Repository\DynamoDb\UserTable
            table_name: user
  ncr_search:
    provider: elastica
    elastica:
      # your app will at some point need to customize the queries
      # override the class so you can provide these customizations.
      class: Acme\Ncr\Search\Elastica\ElasticaNcrSearch
      query_timeout: '500ms' # default
      clusters: '%es_clusters%'
      index_manager:
        # multi-tenant apps will probably need to use a custom class
        #class: Acme\Ncr\Search\Elastica\IndexManager
        index_prefix: my-ncr # defaults to: "%kernel.environment%-ncr"
        indexes:
          default:
            number_of_shards: 5 # default
            number_of_replicas: 1 # default
        types:
          # types do not need to be configured unless there is a custom
          # mapping or they must be put into different indexes or types
          # than the automatic handling would provide
          'acme:user':
            index_name: members # default is "default"
            type_name: member # defaults to message of qname, i.e. "user" in this example
            mapper_class: Acme\Ncr\Search\Elastica\UserMapper

# typically these would be in services.yml file.
services:
  # If you are using AWS ElasticSearch service, use AwsAuthV4ClientManager
  gdbots_ncr.ncr_search.elastica.client_manager:
    class: Gdbots\Ncr\Search\Elastica\AwsAuthV4ClientManager
    arguments:
      - '@aws_credentials'
      - '%cloud_region%'
      - '%es_clusters%'
      - '@logger'
    tags:
      - {name: monolog.logger, channel: ncr_search}

  # recommended, create your own lazy loading handler
  # to optimize batch requests.
  acme_ncr.ncr_lazy_loading_handler:
    class: Acme\Ncr\NcrLazyLoadingHandler
    public: false
    arguments: ['@ncr_lazy_loader']
    tags:
      - {name: pbjx.event_subscriber}

```


# Controllers
It is recommended to have data retrieval be the responsibility of Pbjx requests, however,
that strategy doesn't work for all uses cases.  Use Symfony autowiring and typehint the
interface in your constructor or setter methods to get key Ncr services.

Autowiring supported for these interfaces:

* `Gdbots\Ncr\Ncr`
* `Gdbots\Ncr\NcrCache`
* `Gdbots\Ncr\NcrLazyLoader`
* `Gdbots\Ncr\NcrSearch`


# Twig Extension
The `NcrExtension` provides a function called `ncr_get_node`.  It is important to note
that this does __NOT__ make a query to get a node, instead it pulls from `NcrCache`.

> This might change in the future, but this strategy eliminates horribly performing
> twig templates that make Ncr queries.

The function will accept a `MessageRef`, `NodeRef` or a string version of a NodeRef.

__Example use of ncr_get_node:__

```txt
{% set created_by = ncr_get_node(pbj.get('created_by_ref')) %}
Here is the creator:
{{ created_by }}
```

__Other twig functions (documentation wip):__

+ ncr_deref_nodes
+ ncr_get_preloaded_nodes
+ ncr_get_preloaded_published_nodes
+ ncr_preload_node
+ ncr_preload_nodes
+ ncr_preload_embedded_nodes
+ ncr_to_node_ref


# Console Commands
This library provides the basics for creating and extracting data from the Ncr services.
Run the Symfony console and look for __ncr__ commands.

```txt
ncr:create-search-storage           Creates the NcrSearch storage.
ncr:create-storage                  Creates the Ncr storage.
ncr:describe-search-storage         Describes the NcrSearch storage.
ncr:describe-storage                Describes the Ncr storage.
ncr:export-nodes                    Pipes nodes from the Ncr to STDOUT.
ncr:get-node                        Fetches a single node by its NodeRef and writes to STDOUT.
ncr:reindex-nodes                   Pipes nodes from the Ncr and reindexes them.
```

Review the `--help` on the ncr commands for more details.
