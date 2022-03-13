# Nix PHPUnit Elasticsearch

The goal of this repository is to help reproduce an issue with using elasticsearch client inside phpunit tests 
in php 7.4.28 runtime at MacOS operating system. 

## Nix

First step is to [install nix](https://nixos.org/download.html).

## Docker 

Following command will initialize docker containers and let them run in background. 

```
cp docker-compose.yaml.dist docker-compose.yaml
docker-compose up -d
```

## Nix Shell

Open your terminal app and type following command in the repository root: 

```
nix-shell
```

This will initialize nix shell with all required software dependencies like php, composer etc.

## PHP

Install all code dependencies
```
composer install
```

Create ES Index

```
bin/init
```

## Reproducing issue

Failing test is basically doing 3 steps: 

- index document
- get document
- delete all documents in index

2.5k times, where each step is a standalone test (data provider is yielding next documents)

```
vendor/bin/phpunit  tests/NixPHPUnitEs/FailingTest.php --testdox --verbose
```

In my case, it starts failing around 339 iteration with following error:

```
 ✘ Es with data set #339 [1001.98 ms]
   │
   │ Elasticsearch\Common\Exceptions\Curl\OperationTimeoutException: cURL error 28: Connection timeout after 1000 ms
   │
   │ /Users/xxx/Workspace/nix-phpunit-es/vendor/elasticsearch/elasticsearch/src/Elasticsearch/Connections/Connection.php:584
   │ /Users/xxx/Workspace/nix-phpunit-es/vendor/elasticsearch/elasticsearch/src/Elasticsearch/Connections/Connection.php:274
   │ /Users/xxx/Workspace/nix-phpunit-es/vendor/react/promise/src/FulfilledPromise.php:28
   │ /Users/xxx/Workspace/nix-phpunit-es/vendor/ezimuel/ringphp/src/Future/CompletedFutureValue.php:55
   │ /Users/xxx/Workspace/nix-phpunit-es/vendor/ezimuel/ringphp/src/Core.php:341
   │ /Users/xxx/Workspace/nix-phpunit-es/vendor/elasticsearch/elasticsearch/src/Elasticsearch/Connections/Connection.php:345
   │ /Users/xxx/Workspace/nix-phpunit-es/vendor/elasticsearch/elasticsearch/src/Elasticsearch/Connections/Connection.php:241
   │ /Users/xxx/Workspace/nix-phpunit-es/vendor/elasticsearch/elasticsearch/src/Elasticsearch/Transport.php:110
   │ /Users/xxx/Workspace/nix-phpunit-es/vendor/elasticsearch/elasticsearch/src/Elasticsearch/Client.php:1929
   │ /Users/xxx/Workspace/nix-phpunit-es/vendor/elasticsearch/elasticsearch/src/Elasticsearch/Client.php:911
   │ /Users/xxx/Workspace/nix-phpunit-es/tests/NixPHPUnitEs/FailingTest.php:34
   │ phpvfscomposer:///Users/xxx/Workspace/nix-phpunit-es/vendor/phpunit/phpunit/phpunit:97
   │
```


Just to confirm that the issue has nothing to do with ES itself, here is passing test:

Passing test is doing exactly same thing, 10k times, but there is no generator used, 
everything happens in a single, 10k iterations loop.  

```
vendor/bin/phpunit  tests/NixPHPUnitEs/PassingTest.php --testdox --verbose
```

## Findings 

This behavior is probably more related to macOS libcurl than php itself as suggested [here](https://twitter.com/adrianslowikpl/status/1502351055669141506?s=20&t=RRUkDnaNqBbNH4oPZ4bHFg)

There are two solutions to make [Failingtest](tests/NixPHPUnitEs/FailingTest.php) work: 

* set curl option `CURLOPT_FORBID_REUSE => true` [documentation](https://curl.se/libcurl/c/CURLOPT_FORBID_REUSE.html)
* use custom CurlHandler `ClientBuilder::create()->setHandler(new CurlHandler(['max_handles' => 0]))` for the elasticsearch client.

I'm still not sure what is the root cuase for this behavior and why on Linux everything works out of the box.

Below some findings: 

> This issue does not exist at PHP installed at Ubuntu 20.04 (and probably other distros)

> If we force trigger curl_close (by calling destructor on curl handler) tests are passing regardless of the number of iterations

> Elasticsearch seems to not be the problem here, even that curl is throwing timeouts  

> When failing test is executing 2400 iterations it's passing, it seems that at my laptop, the limit is 2403, 2404 starts breaking.

> Nix seems to not be the problem, php installed through brew is acting the same. 

> I was able to reproduce this issue at macbooks with Intel and M1 processors. 

