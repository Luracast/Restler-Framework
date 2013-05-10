<?php
namespace Luracast\Restler;

use Luracast\Restler\Data\ApiMethodInfo;
use ReflectionClass;
use ReflectionMethod;

/**
 * Router class that routes the urls to api methods along with parameters
 *
 * @category   Framework
 * @package    Restler
 * @author     R.Arul Kumaran <arul@luracast.com>
 * @copyright  2010 Luracast
 * @license    http://www.opensource.org/licenses/lgpl-license.php LGPL
 * @link       http://luracast.com/products/restler/
 * @version    3.0.0rc4
 */
class Routes
{
    protected static $routes = array();

    /**
     * Route the public and protected methods of an Api class
     *
     * @param        $className
     * @param string $resourcePath
     */
    public static function addAPIClass($className, $resourcePath = '')
    {

        /*
         * Mapping Rules
         * =============
         *
         * - Optional parameters should not be mapped to URL
         * - If a required parameter is of primitive type
         *      - Map them to URL
         *      - Do not create routes with out it
         * - If a required parameter is not primitive type
         *      - Do not include it in URL
         */
        $reflection = new ReflectionClass($className);
        $classMetadata = CommentParser::parse($reflection->getDocComment());
        $methods = $reflection->getMethods(ReflectionMethod::IS_PUBLIC +
            ReflectionMethod::IS_PROTECTED);
        foreach ($methods as $method) {
            $methodUrl = strtolower($method->getName());
            //method name should not begin with _
            if ($methodUrl{0} == '_') {
                continue;
            }
            $doc = $method->getDocComment();
            $metadata = CommentParser::parse($doc) + $classMetadata;
            //@access should not be private
            if (isset($metadata['access'])
                && $metadata['access'] == 'private'
            ) {
                continue;
            }
            $arguments = array();
            $defaults = array();
            $params = $method->getParameters();
            $position = 0;
            $ignorePathTill = false;
            $allowAmbiguity
                = (isset($metadata['smart-auto-routing'])
                && $metadata['smart-auto-routing'] != 'true')
                || !Defaults::$smartAutoRouting;
            $metadata['resourcePath'] = $resourcePath;
            if (isset($classMetadata['description'])) {
                $metadata['classDescription'] = $classMetadata['description'];
            }
            if (isset($classMetadata['classLongDescription'])) {
                $metadata['classLongDescription']
                    = $classMetadata['longDescription'];
            }
            if (!isset($metadata['param'])) {
                $metadata['param'] = array();
            }
            foreach ($params as $param) {
                $type =
                    $param->isArray() ? 'array' : $param->getClass();
                if ($type instanceof ReflectionClass) {
                    $type = $type->getName();
                }
                $arguments[$param->getName()] = $position;
                $defaults[$position] = $param->isDefaultValueAvailable() ?
                    $param->getDefaultValue() : null;
                if (!isset($metadata['param'][$position])) {
                    $metadata['param'][$position] = array();
                }
                $m = & $metadata ['param'] [$position];
                if (isset($type)) {
                    $m['type'] = $type;
                }
                $m ['name'] = trim($param->getName(), '$ ');
                $m ['default'] = $defaults [$position];
                $m ['required'] = !$param->isOptional();

                if (isset($m[CommentParser::$embeddedDataName]['from'])) {
                    $from = $m[CommentParser::$embeddedDataName]['from'];
                } else {
                    if ((isset($type) && Util::isObjectOrArray($type))
                        || $param->getName() == Defaults::$fullRequestDataName
                    ) {
                        $from = 'body';
                    } elseif ($m['required']) {
                        $from = 'path';
                    } else {
                        $from = 'query';
                    }
                }
                $m['from'] = $from;

                if (!$allowAmbiguity && $from == 'path') {
                    $ignorePathTill = $position + 1;
                }
                $position++;
            }
            $accessLevel = 0;
            if ($method->isProtected()) {
                $accessLevel = 3;
            } elseif (isset($metadata['access'])) {
                if ($metadata['access'] == 'protected') {
                    $accessLevel = 2;
                } elseif ($metadata['access'] == 'hybrid') {
                    $accessLevel = 1;
                }
            } elseif (isset($metadata['protected'])) {
                $accessLevel = 2;
            }
            /*
            echo " access level $accessLevel for $className::"
            .$method->getName().$method->isProtected().PHP_EOL;
            */

            // take note of the order
            $call = array(
                'url' => null,
                'className' => $className,
                'path' => rtrim($resourcePath, '/'),
                'methodName' => $method->getName(),
                'arguments' => $arguments,
                'defaults' => $defaults,
                'metadata' => $metadata,
                'accessLevel' => $accessLevel,
            );
            // if manual route
            if (preg_match_all(
                '/@url\s+(GET|POST|PUT|PATCH|DELETE|HEAD|OPTIONS)'
                    . '[ \t]*\/?(\S*)/s',
                $doc, $matches, PREG_SET_ORDER
            )
            ) {
                foreach ($matches as $match) {
                    $httpMethod = $match[1];
                    $url = rtrim($resourcePath . $match[2], '/');
                    $url = preg_replace_callback('/{[^}]+}|:[^\/]+/',
                        function ($matches) use ($call) {
                            $match = trim($matches[0], '{}:');
                            $index = $call['arguments'][$match];
                            return '{' .
                                Routes::typeChar(isset($call['metadata']['param'][$index]['type'])
                                    ? $call['metadata']['param'][$index]['type']
                                    : null)
                                . $index . '}';
                        }, $url);
                    static::addPath($url, $call, $httpMethod);
                }
                //if auto route enabled, do so
            } elseif (Defaults::$autoRoutingEnabled) {
                // no configuration found so use convention
                if (preg_match_all(
                    '/^(GET|POST|PUT|PATCH|DELETE|HEAD|OPTIONS)/i',
                    $methodUrl, $matches)
                ) {
                    $httpMethod = strtoupper($matches[0][0]);
                    $methodUrl = substr($methodUrl, strlen($httpMethod));
                } else {
                    $httpMethod = 'GET';
                }
                if ($methodUrl == 'index') {
                    $methodUrl = '';
                }
                $url = empty($methodUrl) ? rtrim($resourcePath, '/')
                    : $resourcePath . $methodUrl;
                if (!$ignorePathTill) {
                    static::addPath($url, $call, $httpMethod);
                }
                $position = 1;
                foreach ($params as $param) {
                    $from = $metadata ['param'] [$position - 1] ['from'];

                    if ($from == 'body' && ($httpMethod == 'GET' ||
                        $httpMethod == 'DELETE')
                    ) {
                        $from = $metadata ['param'] [$position - 1] ['from']
                            = 'query';
                    }

                    if (!$allowAmbiguity && $from != 'path') {
                        break;
                    }
                    if (!empty($url)) {
                        $url .= '/';
                    }
                    //$call['metadata']['url'] = "$httpMethod $url{"
                    //. $param->getName() . '}';
                    $url .= '{' .
                        static::typeChar(isset($call['metadata']['param'][$position - 1]['type'])
                            ? $call['metadata']['param'][$position - 1]['type']
                            : null)
                        . ($position - 1) . '}';
                    if ($allowAmbiguity || $position == $ignorePathTill) {
                        static::addPath($url, $call, $httpMethod);
                    }
                    $position++;
                }
            }
        }
    }

    /**
     * @access private
     */
    public static function typeChar($type = null)
    {
        if (!$type) {
            return 's';
        }
        switch ($type{0}) {
            case 'i':
            case 'f':
                return 'n';
        }
        return 's';
    }

    protected static function addPath($path, array $call, $httpMethod = 'GET')
    {
        $call['url'] = preg_replace_callback(
            "/\{\S(\d+)\}/",
            function ($matches) use ($call) {
                return '{' . $call['metadata']['param'][$matches[1]]['name'] . '}';
            },
            $path
        );
        //check for wildcard routes
        if (substr($path, -1, 1) == '*') {
            $path = rtrim($path, '/*');
            static::$routes['*'][$path][$httpMethod] = $call;
        } else {
            static::$routes[$path][$httpMethod] = $call;
        }
    }

    /**
     * Find the api method for the given url and http method
     *
     * @param string  $path       Requested url path
     * @param string  $httpMethod GET|POST|PUT|PATCH|DELETE etc
     * @param array   $data       Data collected from the request
     *
     * @return ApiMethodInfo
     * @throws RestException
     */
    public static function find($path, $httpMethod, array $data = array())
    {
        $p =& static::$routes;
        $status = 404;
        $message = null;
        $methods = array();
        if (isset($p[$path][$httpMethod])) {
            //static route
            return static::populate($p[$path][$httpMethod], $data);
        } elseif (isset($p['*'])) {
            //wildcard routes
            uksort($p['*'], function ($a, $b) {
                return strlen($b) - strlen($a);
            });
            foreach ($p['*'] as $key => $value) {
                if (strpos($path, $key) === 0 && isset($value[$httpMethod])) {
                    //path found, convert rest of the path to parameters
                    $path = substr($path, strlen($key) + 1);
                    $call = ApiMethodInfo::__set_state($value[$httpMethod]);
                    $call->parameters = empty($path) ? array() : explode('/', $path);
                    return $call;
                }
            }
        }
        //dynamic route
        ksort($p);
        foreach ($p as $key => $value) {
            if (!isset($value[$httpMethod])) {
                continue;
            }
            $regex = str_replace(array('{', '}'),
                array('(?P<', '>[^/]+)'), $key);
            if (preg_match_all(":^$regex$:i", $path, $matches, PREG_SET_ORDER)) {
                $matches = $matches[0];
                $found = true;
                foreach ($matches as $k => $v) {
                    if (is_numeric($k)) {
                        unset($matches[$k]);
                        continue;
                    }
                    $index = intval(substr($k, 1));
                    $details = $value[$httpMethod]['metadata']['param'][$index];
                    if ($k{0} == 's' || strpos($k, static::typeOf($v)) === 0) {
                        $data[$details['name']] = $v;
                    } else {
                        $status = 400;
                        $message = 'invalid value specified for `' . $details['name'] . '`';
                        $found = false;
                        break;
                    }
                }
                if ($found) {
                    return static::populate($value[$httpMethod], $data);
                }
            }
        }
        if ($status == 404) {
            //check if other methods are allowed
            if (isset($p[$path])) {
                $status = 405;
                $methods = array_keys($p[$path]);
            }
        }
        if ($status == 405) {
            header('Allow: ' . implode(', ', $methods));
        }
        throw new RestException($status, $message);
    }

    /**
     * Populates the parameter values
     *
     * @param array $call
     * @param       $data
     *
     * @return ApiMethodInfo
     *
     * @access private
     */
    protected static function populate(array $call, $data)
    {
        $call['parameters'] = $call['defaults'];
        foreach ($data as $key => $value) {
            if (isset($call['arguments'][$key])) {
                $call['parameters'][$call['arguments'][$key]] = $value;
            }
        }
        return ApiMethodInfo::__set_state($call);
    }

    /**
     * @access private
     */
    protected static function typeOf($var)
    {
        if (is_numeric($var)) {
            return 'n';
        }
        if ($var == 'true' || $var == 'false') {
            return 'b';
        }
        return 's';
    }

    /**
     * Import previously created routes from cache
     *
     * @param array $routes
     */
    public static function fromArray(array $routes)
    {
        static::$routes = $routes;
    }

    /**
     * Export current routes for caching
     *
     * @return array
     */
    public static function toArray()
    {
        return static::$routes;
    }
}