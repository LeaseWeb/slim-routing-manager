<?php
/**
 * @author Patrick JL Laso <wld1373@gmail.com>
 * @author Stephen "TheCodeAssassin" Hoogendijk <stephen@tca0.nl>
 */

namespace Slim\Routing;

/**
 * Routing Manager package
 *
 * Class Manager
 *
 * @package Lsw\Routing
 */
class Manager
{
    /**
     * @var string
     */
    protected $cacheDir;

    /**l
     * @var array
     */
    protected $controllers;

    /**
     * @var null
     */
    protected $routePrefix;

    /**
     * @var
     */
    protected $cacheFileName = 'compiled_routes.php';


    /**
     * @param array  $controllerDirs array of directories to scan for controllers
     * @param string $cacheDir       directory where to store the cached routes
     * @param null   $routePrefix    Prefix to add to every route (except if they have @noPrefix)
     */
    public function __construct(array $controllerDirs, $cacheDir, $routePrefix = null)
    {

        // if the directory does not exist, try to create it
        if (!is_dir($cacheDir)) {
            @mkdir($cacheDir, 0777, true);
        }

        $this->cacheDir = $cacheDir;

        if ($routePrefix) {
            $this->routePrefix = $routePrefix;
        }

        // load the controllers
        if (count($controllerDirs)) {
            foreach ($controllerDirs as $controllerPath) {
                $controllers = $this->readDirectory($controllerPath);
                if (count($controllers)) {
                    $this->controllers = $controllers;
                }
            }
        }
    }


    /**
     * Generates a new compiled routes file and includes it into the application
     *
     * @throws \Exception
     *
     */
    public function generateRoutes()
    {
        $content = '';
        $hasChanged = false;

        $modTimes = array();
        $classes = $this->controllers;

        // if the cache file does not exist, update the cache
        if (!is_file($this->cacheFile())) {
            $hasChanged = true;
        } else {

            // include the cache file and get the mod times variable
            include_once $this->cacheFile();

            // if a new controller was added to the list, update the cache
            if (count($modTimes) != count($classes)) {
                $hasChanged = true;
            } else {
                foreach ($classes as $classFile) {

                    // if a controller is modified (any controller) update the cache
                    $modTime = $modTimes[$classFile];
                    $hasChanged = (filemtime($classFile) != $modTime);

                    if ($hasChanged) {
                        break;
                    }
                }
            }
        }



        // update the cache only if the controllers have changed
        if ($hasChanged) {

            foreach ($classes as $classFile) {
                $content .= $this->processClass($classFile);
            }

            require $this->writeCache($classes, $content);
        }
    }

    /**
     * gets the full path and the name of cache file
     *
     * @return string
     */
    protected function cacheFile()
    {
        return $this->cacheDir . '/' . $this->cacheFileName;
    }

    /**
     * This method writes the cache content into cache file
     *
     * @param array $classes
     * @param       $content
     *
     * @return string
     */
    protected function writeCache(array $classes, $content)
    {
        $modTimes = array();
        foreach ($classes as $classFile) {
            $modTimes[$classFile] = filemtime($classFile);
        }
        $modTimes = var_export($modTimes, true);

        $date = date("Y-m-d h:i:s");
        $content = <<<EOD
<?php


/**
 * Generated with \Lsw\Routing\Manager
 *
 * on {$date}
 */
\$modTimes = {$modTimes};
\$app = Slim\Slim::getInstance();

{$content}

EOD;
        $fileName = $this->cacheFile();
        file_put_contents($fileName, $content);

        return $fileName;
    }

    /**
     * @param $classFile
     *
     * @return string
     * @throws \Exception
     */
    protected function processClass($classFile)
    {
        $content = file_get_contents($classFile);
        $result = '';

        // Look for class definitions, ie: 'class MyClass [extends ParentClass] {'
        preg_match_all('/class\s+(\w*)\s*(extends\s+)?([^{])*/s', $content, $mclass, PREG_SET_ORDER);
        $className = $mclass[0][1];
        if (!$className) {
            throw new \Exception(sprintf('class not found in %s', $classFile));
        }

        // Regex explained:
        // \/\*\*                -> exactly '/**'
        // ((?!\*\/).)*          -> anything except '*/'
        // \*\/                  -> exactly '*/'
        // ((?!(\*\/|{)).)*      -> anything except '*/' and '{'
        // {                     -> exactly '{'
        // /s                    -> consider line breaks with '.' special character
        // So basically, we are looking for phpdoc blocks (/** ... */) followed by a brace opening.
        // The 'anything except' parts prevent selecting multiple phpdoc blocks together
        preg_match_all('/(\/\*\*((?!\*\/).)*\*\/((?!(\*\/|{)).)*{)/s', $content, $match, PREG_PATTERN_ORDER);

        foreach ($match[0] as $k => $m) {
            if (!substr_count($m, 'class')) {
                $function = substr_count($m, 'function') ? 'yes' : 'no';
                if ($function == 'yes') {
                    preg_match_all('/(\/\*\*.*\*\/)/s', $m, $mc, PREG_PATTERN_ORDER);
                    $comments = nl2br($mc[0][0]);
                    $noPrefix = strpos($comments, '@noPrefix') !== false;

                    // Look for function definitions, ie: '[public ][static ] function myFunction([...]) {'
                    preg_match_all('/\*\/\s+(public\s+)?(static\s+)?function\s+([^\(]*)\(/s', $m, $mf, PREG_SET_ORDER);
                    if (!empty($mf)) {

                        $functionName = $mf[0][3];
                        preg_match_all("/\*\s+@Route\s*\('([^']*)'\)/s", $comments, $params, PREG_SET_ORDER);

                        foreach ($params as $route) {

                            if ($this->routePrefix && !$noPrefix) {

                                // add a prefix if it was given
                                $route = $this->routePrefix . $route[1];

                            } else {
                                $route = $route[1];
                            }

                            preg_match_all("/\*\s+@Method\s*\('([^']*)'\)/s", $comments, $params, PREG_SET_ORDER);
                            $method = isset($params[0][1]) ? strtoupper($params[0][1]) : 'GET';
                            preg_match_all("/\*\s+@Name\s*\('([^']*)'\)/s", $comments, $params, PREG_SET_ORDER);
                            $name = strtolower($params[0][1]);

                            $result .= str_replace('\\', '\\\\', sprintf(
                                '$app->map("%s", "\Lsw\Controller\%s:%s")->via("%s")->name("%s");' . PHP_EOL,
                                $route,
                                $className,
                                $functionName,
                                str_replace(',', '","', $method),
                                $name
                            ));
                        }
                    }
                }
            }
        }

        return $result;
    }


    /**
     * Reads the contents of this dir and returns only dirs
     * that have first letter capitalized
     *
     * @param string $dir Directory name
     *
     * @return array
     */
    protected function readDirectory($dir)
    {
        $entries = array();
        foreach (scandir($dir) as $entry) {
            if (($entry != '.') && ($entry != '..')) {
                $current = "$dir/$entry";
                if ($current != $dir) {
                    if (is_dir($current)) {
                        $aux = $this->readDirectory($current);
                        $entries = array_merge($entries, $aux);
                    } else {
                        if (preg_match("/\w*?Controller.php/", $entry)) {
                            $entries[] = $current;
                        }
                    }
                }
            }
        }

        return $entries;
    }

    /**
     * @return null
     */
    public function getRoutePrefix()
    {
        return $this->routePrefix;
    }

    /**
     * @param null $routePrefix
     */
    public function setRoutePrefix($routePrefix)
    {
        $this->routePrefix = $routePrefix;
    }

    /**
     * @return mixed
     */
    public function getCacheFileName()
    {
        return $this->cacheFileName;
    }

    /**
     * @param mixed $cacheFileName
     */
    public function setCacheFileName($cacheFileName)
    {
        $this->cacheFileName = $cacheFileName;
    }
}
