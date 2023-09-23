This repository is meant to help reproduce a memory leak issue in PHPUnit/PHPStan when using data providers in
`TypeInferenceTest` discussed [here](https://github.com/phpstan/phpstan/discussions/9914).

## Steps to reproduce

1. Clone this repository
2. Run `composer install`
3. Run `vendor/bin/phpunit`

Running the tests takes about 2-3 minutes and 5 GB of memory.

Note: the tests are currently failing, and I'm not sure why (something to do with how PHPStan loads the extensions I guess).
But it doesn't really matter, as the goal is to investigate that memory leak.

## Blackfire comparison

https://blackfire.io/profiles/compare/705f6f94-abfd-4998-8ff3-88ff143ec220/graph
