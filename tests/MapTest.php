<?php
namespace Aura\Router;

use Yoast\PHPUnitPolyfills\TestCases\TestCase;

class MapTest extends TestCase
{
    protected $map;

    protected function set_up()
    {
        parent::set_up();
        $container = new RouterContainer();
        $this->map = $container->getMap();
    }

    protected function assertIsRoute($actual)
    {
        $this->assertInstanceof('Aura\Router\Route', $actual);
    }

    protected function assertRoute($expect, $actual)
    {
        $this->assertIsRoute($actual);
        foreach ($expect as $key => $val) {
            $this->assertSame($val, $actual->$key);
        }
    }

    public function testRouteAlreadyExists()
    {
        $this->map->route('foo', '/foo');
        $this->expectException('Aura\Router\Exception\RouteAlreadyExists');
        $this->map->route('foo', '/foo');
    }

    public function testRouteWithoutName()
    {
        $route = $this->map->route(null, '/foo');
        $this->assertEmpty($route->name);
    }

    public function testBeforeAndAfterAttach()
    {
        $this->map->route('before', '/foo');

        $this->map->attach('during.', '/during', function ($map) {
            $map->tokens(['id' => '\d+']);
            $map->allows('GET');
            $map->defaults(['zim' => 'gir']);
            $map->secure(true);
            $map->wildcard('other');
            $map->isRoutable(false);
            $map->route('bar', '/bar');
        });

        $this->map->route('after', '/baz');

        $map = $this->map->getRoutes();

        $expect = [
            'tokens' => [],
            'allows' => [],
            'defaults' => [],
            'secure' => null,
            'wildcard' => null,
            'isRoutable' => true,
        ];
        $this->assertRoute($expect, $map['before']);
        $this->assertRoute($expect, $map['after']);

        $actual = $map['during.bar'];
        $expect = [
            'tokens' => ['id' => '\d+'],
            'allows' => ['GET'],
            'defaults' => ['zim' => 'gir'],
            'secure' => true,
            'wildcard' => 'other',
            'isRoutable' => false,
        ];
        $this->assertRoute($expect, $actual);
    }

    public function testAttachInAttach()
    {
        $this->map->attach('foo.', '/foo', function ($map) {
            $map->route('index', '/index');
            $map->attach('bar.', '/bar', function ($map) {
                $map->route('index', '/index');
            });
        });

        $map = $this->map->getRoutes();

        $this->assertSame('/foo/index', $map['foo.index']->path);
        $this->assertSame('/foo/bar/index', $map['foo.bar.index']->path);
    }

    public function testGetAndSetRoutes()
    {
        $this->map->attach('page.', '/page', function ($map) {
            $map->tokens([
                'id'            => '\d+',
                'format'        => '(\.[^/]+)?',
            ]);

            $map->defaults([
                'controller' => 'page',
                'format' => null,
            ]);

            $map->route('browse', '/');
            $map->route('read', '/{id}{format}');
        });

        $actual = $this->map->getRoutes();
        $this->assertTrue(is_array($actual));
        $this->assertCount(2, $actual);
        $this->assertIsRoute($actual['page.browse']);
        $this->assertEquals('/page/', $actual['page.browse']->path);
        $this->assertIsRoute($actual['page.read']);
        $this->assertEquals('/page/{id}{format}', $actual['page.read']->path);

        // emulate caching the values
        $saved = serialize($actual);
        $restored = unserialize($saved);

        // set routes in new map from the restored values
        $container = new RouterContainer();
        $map = $container->getMap();
        $map->setRoutes($restored);
        $actual = $map->getRoutes();
        $this->assertTrue(is_array($actual));
        $this->assertCount(2, $actual);
        $this->assertIsRoute($actual['page.browse']);
        $this->assertEquals('/page/', $actual['page.browse']->path);
        $this->assertIsRoute($actual['page.read']);
        $this->assertEquals('/page/{id}{format}', $actual['page.read']->path);
    }

    public function testSort()
    {
        $container = new RouterContainer();
        $map = $container->getMap();

        $map->route('Account', '/api/account/{/t}')
            ->auth(4)
            ->allows(['GET'])
            ->wildcard('other');

        $map->route('Override', 'api/account/override')
            ->auth(1)
            ->allows(['GET'])
            ->wildcard('other');

        // Routes should be in the order they were added
        $routes = $map->getRoutes();
        $keys = array_keys($routes);
        $this->assertEquals('Account', $keys[0]);
        $this->assertEquals('Override', $keys[1]);

        // Routes should be sorted so longer ones are first
        $map->sort();
        $routes = $map->getRoutes();
        $keys = array_keys($routes);
        $this->assertEquals('Override', $keys[0]);
        $this->assertEquals('Account', $keys[1]);
    }

    public function testCleanPath()
    {
        $container = new RouterContainer();
        $map = $container->getMap();

        $path = '/';
        $this->assertEquals('/', $map->removeParamFromPath($path));

        $path = '/account/foo/{bar}/baz';
        $this->assertEquals('/account/foo//baz', $map->removeParamFromPath($path));

        $path = '/account/foo{/bar}';
        $this->assertEquals('/account/foo', $map->removeParamFromPath($path));

        $path = '/{foo}/bar/baz';
        $this->assertEquals('//bar/baz', $map->removeParamFromPath($path));

    }
}
