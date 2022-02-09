# Webservice

[![Build Status](https://img.shields.io/travis/UseMuffin/Webservice/master.svg?style=flat-square)](https://github.com/UseMuffin/Webservice/actions?query=workflow%3ACI+branch%3Amaster)
[![Coverage](https://img.shields.io/codecov/c/github/UseMuffin/Webservice/master.svg?style=flat-square)](https://codecov.io/github/UseMuffin/Webservice)
[![Total Downloads](https://img.shields.io/packagist/dt/muffin/webservice.svg?style=flat-square)](https://packagist.org/packages/muffin/webservice)
[![License](https://img.shields.io/badge/license-MIT-blue.svg?style=flat-square)](LICENSE)

Bringing the power of the CakePHP ORM to your favourite webservices.

## Install

Using [Composer][composer]:

```
composer require muffin/webservice
```

You then need to load the plugin. You can use the shell command:

```
bin/cake plugin load Muffin/Webservice
```

## Usage

### Datasource Configuration

In your `app.php`, add a new `webservice` config under `Datasources`:

```php
    'Datasources' => [
        // Other db config here
        'webservice' => [
            'className' => \Muffin\Webservice\Datasource\Connection::class,
            'service' => 'Articles',
            // Any additional keys will be set as Driver's config.
        ],
    ],
```

If you are making a plugin then conventionally the datasource config key name
should be underscored version of plugin name.

### As an ORM

#### Create driver

```php
<?php
namespace App\Webservice\Driver;

use Cake\Http\Client;
use Muffin\Webservice\Driver\AbstractDriver;

class Articles extends AbstractDriver
{
    /**
     * Initialize is used to easily extend the constructor.
     */
    public function initialize(): void
    {
        $this->setClient(new Client([
            'host' => 'example.com'
        ]));
    }
}
```

#### Create a webservice

```php
<?php
namespace App\Webservice;

use Muffin\Webservice\Datasource\Query;
use Muffin\Webservice\Datasource\ResultSet;
use Muffin\Webservice\Webservice\Webservice;

class ArticlesWebservice extends Webservice
{
    /**
     * Executes a query with the read action using the Cake HTTP Client
     */
    protected function _executeReadQuery(Query $query, array $options = [])
    {
        $response = $this->getDriver()->getClient()->get('/articles.json');

        if (!$response->isOk()) {
            return false;
        }

        $resources = $this->_transformResults($query->endpoint(), $response->json['articles']);

        return new ResultSet($resources, count($resources));
    }
}
```

#### Create an endpoint (optional)

```php
<?php
namespace App\Model\Endpoint;

use Muffin\Webservice\Model\Endpoint;

class ArticlesEndpoint extends Endpoint
{
}
```

#### Create a resource (optional)

```php
<?php
namespace App\Model\Resource;

use Muffin\Webservice\Model\Resource;

class Article extends Resource
{
}
```

#### Use it

```php
<?php
namespace App\Controller;

use Muffin\Webservice\Model\EndpointLocator;

class ArticlesController extends AppController
{
    // Either set the default model type to "Endpoint" or explicitly specify
    // model type in loadModel() call as shown below.
    protected $_modelType = 'Endpoint';

    public function index()
    {
        // This is required only if you haven't set `$_modelType` property to
        // "Endpoint" as shown above.
        $this->loadModel('Articles', 'Endpoint');

        $articles = $this->Articles->find();
    }
}
```

### As base for a driver

You can also use this plugin as a base to a separate plugin or to manage custom webservice
drivers connections.

Until official documentation is written, [David Yell][1] wrote a good [post to get you started][2].

[1]:https://github.com/davidyell
[2]:https://github.com/davidyell/davidyell.github.com/blob/source/_posts/2015-09-11-connecting-to-a-web-service.markdown

## Implementations of webservices

### As an ORM

The following plugins use the Webservice ORM to give you easy access to all kinds of webservices:

- [GitHub plugin](https://github.com/cvo-technologies/cakephp-github) - Provides access to the GitHub REST APIs.
- [NS plugin](https://github.com/Qarox/cakephp-nsapi) - Provides access to the NS (Nederlandse Spoorwegen) APIs.
- [Stagemarkt plugin](https://github.com/ICT-College/cakephp-stagemarkt) - Provides access to the SBB Stagemarkt REST APIs.
- [Twitter plugin](https://github.com/cvo-technologies/cakephp-twitter) - Provides access to the Twitter REST and streaming APIs.

### As a driver

The following plugins implement a Webservice driver with their own methods:

- [GitHub plugin](https://github.com/UseMuffin/Github) - Provides access to the GitHub REST APIs.
- [Pusher plugin](https://github.com/UseMuffin/Pusher) - Provides access to the Pusher APIs.
- [TMDB plugin](https://github.com/drmonkeyninja/cakephp-tmdb) - Provides access to the TMDB APIs.

## Patches & Features

* Fork
* Mod, fix
* Test - this is important, so it's not unintentionally broken
* Commit - do not mess with license, todo, version, etc. (if you do change any, bump them into commits of
their own that I can ignore when I pull)
* Pull request - bonus point for topic branches

To ensure your PRs are considered for upstream, you MUST follow the CakePHP coding standards.

## Bugs & Feedback

http://github.com/usemuffin/webservice/issues

## License

Copyright (c) 2015-Present, [Use Muffin] and licensed under [The MIT License][mit].

[cakephp]:http://cakephp.org
[composer]:http://getcomposer.org
[mit]:http://www.opensource.org/licenses/mit-license.php
[muffin]:http://usemuffin.com
