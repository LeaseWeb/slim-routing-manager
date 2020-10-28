## Deprecated

This repository is no longer being actively maintained. We encourage you to not use this code.
If you rely on this code you might want to fork the repository to keep your systems from breaking, if we remove this repository in the future.


Slim Routing Manager - Annotation based routing manager for Slim Framework
===

This repository was forked from [JLaso's RoutingCacheManager](https://www.github.com/jlaso/slim-routing-manager-sample), it features some enhancements 
such as a more advanced form of caching routes and support for the latest version of Slim.

I would like to thank Patrick JL Laso for providing the original code that this component is based on.

Initialising the Routing Manager
----

To initialise the routing manager, you need to add the following lines to your index.php file that bootstrap's your slim application:
```
    // Initialize the Routing Manager
    $routingManager = new RoutingManager(
        array(
            '/path/to/controllers/'
        ), '/path/to/cache/dir/'
    );
    $routingManager->generateRoutes();
```

The routing manager supports multiple directory's to scan for controllers. Which can be useful if you have a 
frontend/backend type system.

Route prefixing
---

The routing manager supports adding a prefix to each route (such as API version). This can be set by either running 
$routingManager->setRoutePrefix('/v1'); or by defining it in the constructor of the Routing Manager itself. 

If there is a controller action you do not wish routes being prefixed for you can use the @noPrefix annotation.

Controller structure
---

The structure of the controller itself does not really matter for the Routing Manager. The only thing that it requires
is defining the routes through annotations. 

The following annotations are supported by the routing manager:

| Annotation | Value                      | Description                                                                |
|------------|----------------------------|----------------------------------------------------------------------------|
| @Route     | The slim-compliant route   | Proving the route to the controller action                                 |
| @noPrefix  | None                       | Skip the prefix being attached to the route if a route prefix has been set |
| @Method    | get/post/delete/put/option | The HTTP method used for the route                                         |
| @Name      | String                     | The name of the route                                                      |

The parameters and everything else related to the function itself does not defer from the official Slim standard.
So default variables can be defined the same way you would normally define routes in Slim.

However we do recommend naming the controller actions xAction (similar to symfony 2 controller actions).


Example:
```
    /**
     * Class TestController
     *
     * @package Lsw\Controller
     */
    class TestController
    {
        /**
         * Test action
         *
         * @Route('/test/index(/:param1)(/:param2)')
         *
         * @Method('GET')
         * @Name('test.index')
         *
         * @param $param1
         * @param $param2
         *
         */
        public function indexAction($param1 = 1, $param2 = 2)
        {
            echo sprintf('param1: %s, param2: %s', $param1, $param2);
        }
    
    }
```

### Multiple routes

The Routing manager also supports multiple routes to be defined for each action:

```
  /**
         * Test action
         *
         * @Route('/test/index(/:param1)(/:param2)')
         * @Route('/alternative-route/index(/:param1)(/:param2)')
         * @Route('/alternative-route-2/index/:param1/:param2')
         *
         * @Method('GET')
         * @Name('test.index')
         *
         * @param $param1
         * @param $param2
         *
         */
```

The following routes will be generated:

```
    $app->map("/test/index(/:param1)(/:param2)", "\Lsw\Controller\TestController:indexAction")->via("GET")->name("test.index");
    $app->map("/alternative-route/index(/:param1)(/:param2)", "\Lsw\Controller\TestController:indexAction")->via("GET")->name("test.index");
    $app->map("/alternative-route2/index/:param1/:param2", "\Lsw\Controller\TestController:indexAction")->via("GET")->name("test.index");
```

All the routes are processed independently from each other. This means some routes can require parameters to be passed
and some can leave them optional in the route. The actual requirement check which throws an exception however is
controlled by the controller action and not by the route itself so it only impacts the validity of the route.


Caching
---

The 'cache' path must be writable for the http/apache daemon user.

The very nature of this library is to create slim routes based on annotations. So the routing manager will scan
the controllers only if the controllers themselves are changed or the cached routes file is being removed.

By default the generated compiled_routes.php will generate the following structure:

```
    /**
     * Generated with \Lsw\Routing\Manager
     *
     * on 2015-02-10 02:24:41
     */
    $modTimes = array (
      '/srv/shared/sho/RoutingManagerTest/src/Lsw/Controller/TestController.php' => 1423574678    
    );
    $app = Slim\Slim::getInstance();
    
    $app->map("/test/index", "\Lsw\Controller\TestController:indexAction")->via("GET")->name("test.index");
    
```

The modTimes array stores the modification times of the controllers that are being scanned by the routing manager.
If a controller is changed the file's modification time will differ from the one stored in this array and the cache will be refreshed.


