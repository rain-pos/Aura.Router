<?php
/**
 *
 * This file is part of the Aura for PHP.
 *
 * @package Aura.Router
 *
 * @license http://opensource.org/licenses/bsd-license.php BSD
 *
 */
namespace Aura\Router;

use ArrayObject;

/**
 *
 * Generates URL paths from routes.
 *
 * @package Aura.Router
 *
 */
class Generator
{
    /**
     *
     * Gets the path for a Route with data replacements for param tokens.
     *
     * @param Route $route The route to generate a path for.
     *
     * @param array $data An array of key-value pairs to interpolate into the
     * param tokens in the path for the Route. Keys that do not map to
     * params are discarded; param tokens that have no mapped key are left in
     * place.
     *
     * @return string
     *
     */
    public function generate(Route $route, $data = array())
    {
        $path = $route->path;
        $data = $this->generateData($route, $data);
        $repl = $this->generateTokenReplacements($data);
        $repl = $this->generateOptionalReplacements($path, $repl, $data);
        $path = strtr($path, $repl);
        $path = $this->generateWildcardReplacement($route, $path, $data);
        return $path;
    }

    /**
     *
     * Generates the data for token replacements.
     *
     * @param Route $route The route to work with.
     *
     * @param array $data Data for the token replacements.
     *
     * @return array
     *
     */
    protected function generateData(Route $route, array $data)
    {
        // the data for replacements
        $data = array_merge($route->values, $data);

        // use a callable to modify the data?
        if ($route->generate) {
            // pass the data as an object, not as an array, so we can avoid
            // tricky hacks for references
            $arrobj = new ArrayObject($data);
            // modify
            call_user_func($route->generate, $arrobj);
            // convert back to array
            $data = $arrobj->getArrayCopy();
        }

        return $data;
    }

    /**
     *
     * Generates urlencoded data for token replacements.
     *
     * @param array $data Data for the token replacements.
     *
     * @return array
     *
     */
    protected function generateTokenReplacements($data)
    {
        $repl = array();
        foreach ($data as $key => $val) {
            if (is_scalar($val) || $val === null) {
                $repl["{{$key}}"] = rawurlencode($val);
            }
        }
        return $repl;
    }

    /**
     *
     * Generates replacements for params in the generated path.
     *
     * @param string $path The generated path.
     *
     * @param array $repl The token replacements.
     *
     * @param array $data The original data.
     *
     * @return string
     *
     */
    protected function generateOptionalReplacements($path, $repl, $data)
    {
        // replacements for optional params, if any
        preg_match('#{/([a-z][a-zA-Z0-9_,]*)}#', $path, $matches);
        if (! $matches) {
            return $repl;
        }

        // this is the full token to replace in the path
        $key = $matches[0];
        // start with an empty replacement
        $repl[$key] = '';
        // the optional param names in the token
        $names = explode(',', $matches[1]);
        // look for data for each of the param names
        foreach ($names as $name) {
            // is there data for this optional param?
            if (! isset($data[$name])) {
                // options are *sequentially* optional, so if one is
                // missing, we're done
                break;
            }
            // encode the optional value
            if (is_scalar($data[$name])) {
                $repl[$key] .= '/' . rawurlencode($data[$name]);
            }
        }
        return $repl;
    }

    /**
     *
     * Generates a wildcard replacement in the generated path.
     *
     * @param Route $route The route to work with.
     *
     * @param string $path The generated path.
     *
     * @param array $data Data for the token replacements.
     *
     * @return string
     *
     */
    protected function generateWildcardReplacement(Route $route, $path, $data)
    {
        $wildcard = $route->wildcard;
        if ($wildcard && isset($data[$wildcard])) {
            $path = rtrim($path, '/');
            foreach ($data[$wildcard] as $val) {
                // encode the wildcard value
                if (is_scalar($val)) {
                    $path .= '/' . rawurlencode($val);
                }
            }
        }
        return $path;
    }
}