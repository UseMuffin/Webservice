<?php
declare(strict_types=1);

/**
 * CakePHP(tm) : Rapid Development Framework (http://cakephp.org)
 * Copyright (c) Cake Software Foundation, Inc. (http://cakefoundation.org)
 *
 * Licensed under The MIT License
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright     Cake Software Foundation, Inc. (http://cakefoundation.org)
 * @link          http://cakephp.org CakePHP(tm) Project
 * @license       http://www.opensource.org/licenses/mit-license.php MIT License
 */

use Cake\Cache\Cache;
use Cake\Core\Configure;
use Cake\Datasource\ConnectionManager;
use Cake\Log\Log;
use Muffin\Webservice\Datasource\Connection;
use TestApp\Webservice\Driver\TestDriver;

require_once 'vendor/autoload.php';

// Path constants to a few helpful things.
define('ROOT', dirname(__DIR__) . DS);
const CAKE_CORE_INCLUDE_PATH = ROOT . 'vendor' . DS . 'cakephp' . DS . 'cakephp';
const CORE_PATH = ROOT . 'vendor' . DS . 'cakephp' . DS . 'cakephp' . DS;
const CAKE = CORE_PATH . 'src' . DS;
const TESTS = ROOT . 'tests';
const APP = ROOT . 'tests' . DS . 'test_app' . DS;
const APP_DIR = 'test_app';
const WEBROOT_DIR = 'webroot';
const WWW_ROOT = APP . 'webroot' . DS;
define('TMP', sys_get_temp_dir() . DS);
const CONFIG = APP . 'config' . DS;
const CACHE = TMP;
const LOGS = TMP;

require_once CORE_PATH . 'config/bootstrap.php';

date_default_timezone_set('UTC');
mb_internal_encoding('UTF-8');

Configure::write('debug', true);
Configure::write('App', [
    'namespace' => 'TestApp',
    'encoding' => 'UTF-8',
    'base' => false,
    'baseUrl' => false,
    'dir' => 'src',
    'webroot' => 'webroot',
    'www_root' => APP . 'webroot',
    'fullBaseUrl' => 'http://localhost',
    'imageBaseUrl' => 'img/',
    'jsBaseUrl' => 'js/',
    'cssBaseUrl' => 'css/',
    'paths' => [
        'plugins' => [APP . 'Plugin' . DS],
        'templates' => [APP . 'Template' . DS],
    ],
]);
Configure::write('Session', [
    'defaults' => 'php',
]);

Cache::setConfig([
    '_cake_core_' => [
        'engine' => 'File',
        'prefix' => 'cake_core_',
        'serialize' => true,
    ],
    '_cake_model_' => [
        'engine' => 'File',
        'prefix' => 'cake_model_',
        'serialize' => true,
    ],
    'default' => [
        'engine' => 'File',
        'prefix' => 'default_',
        'serialize' => true,
    ],
]);

if (!getenv('DB_DSN')) {
    putenv('DB_DSN=sqlite:///:memory:');
}

ConnectionManager::setConfig('test', [
    'className' => Connection::class,
    'driver' => TestDriver::class,
] + ConnectionManager::parseDsn(getenv('DB_DSN')));

Log::setConfig([
    'debug' => [
        'engine' => 'Cake\Log\Engine\FileLog',
        'levels' => ['notice', 'info', 'debug'],
        'file' => 'debug',
    ],
    'error' => [
        'engine' => 'Cake\Log\Engine\FileLog',
        'levels' => ['warning', 'error', 'critical', 'alert', 'emergency'],
        'file' => 'error',
    ],
]);

Configure::write('Error.ignoredDeprecationPaths', [
    'ests/TestCase/BootstrapTest.php',
]);
