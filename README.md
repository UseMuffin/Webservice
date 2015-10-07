# Webservice

[![Build Status](https://img.shields.io/travis/UseMuffin/Webservice/master.svg?style=flat-square)](https://travis-ci.org/UseMuffin/Webservice)
[![Coverage](https://img.shields.io/coveralls/UseMuffin/Webservice/master.svg?style=flat-square)](https://coveralls.io/r/UseMuffin/Webservice)
[![Total Downloads](https://img.shields.io/packagist/dt/muffin/webservice.svg?style=flat-square)](https://packagist.org/packages/muffin/webservice)
[![License](https://img.shields.io/badge/license-MIT-blue.svg?style=flat-square)](LICENSE)

Extremely simplistic webservices for CakePHP 3.

## Install

Using [Composer][composer]:

```
composer require muffin/webservice:dev-master
```

You then need to load the plugin. You can use the shell command:

```
bin/cake plugin load Muffin/Webservice
```

or by manually adding statement shown below to `boostrap.php`:

```php
Plugin::load('Muffin/Webservice', ['bootstrap' => true]);
```

## Usage

### As base for a driver

You can only use this plugin as a base to a separate plugin or to manage custom webservice
drivers connections.

Until an official documentation is written, [David Yell][1] wrote a good [post to get you started][2].

[1]:https://github.com/davidyell
[2]:http://jedistirfry.co.uk/blog/2015-09/connecting-to-a-web-service/

### As an ORM

#### Create a webservice

```php
<?php

namespace App\Webservice;

use App\Model\Resource\Article;
use Cake\Network\Http\Client;
use Muffin\Webservice\QueryLogger;
use Muffin\Webservice\ResultSet;
use Muffin\Webservice\WebserviceInterface;
use Muffin\Webservice\Query;

class ArticlesWebservice implements WebserviceInterface
{

    public function logger(QueryLogger $logger = null)
    {
        return new QueryLogger();
    }

    public function execute(Query $query)
    {
        switch ($query->action()) {
            case Query::ACTION_READ:
                $client = new Client();
                $response = $client->get('http://example.com/articles.json');

                if (!$response->isOk()) {
                    return false;
                }

                $resources = [];
                foreach ($response->articles as $article) {
                    $resources[] = new Article([
                        'id' => $article->id,
                        'title' => $article->title,
                        'body' => $article->body
                    ], [
                        'markClean' => true,
                        'markNew' => false,
                    ]);
                }

                return new ResultSet($resources, $response->total);
        }

        return false;
    }
}
```

#### Create an endpoint

```php
<?php

namespace App\Model\Endpoint;

use Cake\Datasource\ConnectionManager;
use Muffin\Webservice\Model\Endpoint;

class ArticlesEndpoint extends Endpoint
{

    public function initialize(array $config)
    {
        $this->webservice(ConnectionManager::get('ArticlesWebservice')->webservice());
    }
}
```

#### Create a resource

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

use Cake\Event\Event;

class ArticlesController extends AppController
{

    public function beforeFilter(Event $event)
    {
        $this->loadModel('Articles', 'Endpoint');
    }

    public function index()
    {
        $articles = $this->Articles->find();
    }

}
```

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

Copyright (c) 2015, [Use Muffin] and licensed under [The MIT License][mit].

[cakephp]:http://cakephp.org
[composer]:http://getcomposer.org
[mit]:http://www.opensource.org/licenses/mit-license.php
[muffin]:http://usemuffin.com
