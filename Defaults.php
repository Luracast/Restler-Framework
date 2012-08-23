<?php
namespace Luracast\Restler;

/**
 * Static class to hold all restler defaults, change the values to suit your
 * needs in the gateway file (index.php), you may also allow the api users to
 * change them per request by adding the properties to Defaults::$overridables
 *
 * @category   Framework
 * @package    restler
 * @author     R.Arul Kumaran <arul@luracast.com>
 * @copyright  2010 Luracast
 * @license    http://www.opensource.org/licenses/lgpl-license.php LGPL
 * @link       http://luracast.com/products/restler/
 * @version    3.0.0
 */
class Defaults
{
    /**
     * @var bool HTTP status codes are set on all responses by default.
     * Some clients (like flash, mobile) have trouble dealing with non-200
     * status codes on error responses.
     *
     * You can set it to true to force a HTTP 200 status code on all responses,
     * even when errors occur. If you suppress status codes, look for an error
     * response to determine if an error occurred.
     */
    public static $suppressResponseCode = false;

    /**
     * @var string default Cache-Control string that is set in the header
     */
    public static $headerCacheControl = 'no-cache, must-revalidate';

    /**
     * @var int sets the content to expire immediately when set to zero
     * alternatively you can specify the number of seconds the content will
     * expire. This setting can be altered at api level using php doc comment
     * with @expires numOfSeconds
     */
    public static $headerExpires = 0;
    public static $throttle = 0;

    /**
     * @var array use 'alternativeName'=> 'actualName' to set alternative
     * names that can be used to represent the api method parameters and/or
     * static properties of Defaults
     */
    public static $aliases = array(
        /**
         * suppress_response_codes=true as an URL parameter to force
         * a HTTP 200 status code on all responses
         */
        'suppress_response_codes' => 'suppressResponseCode',
    );

    public static $fromComments = array(

        /**
         * use PHPDoc comments such as the following
         * `@cache no-cache, must-revalidate` to set the Cache-Control header
         * for a specific api method
         */
        'cache' => 'headerCacheControl',

        /**
         * use PHPDoc comments such as the following
         * `@expires ` to set the Expires header
         * for a specific api method
         */
        'expires' => 'headerExpires',
    );

    /**
     * @var array determines the defaults that can be overridden by the api
     * user by passing them as URL parameters
     */
    public static $overridables = array(
        'suppressResponseCode',
    );

    /**
     * @var array contains validation details for defaults to be used when
     * set through URL parameters
     */
    public static $validation = array(
        'suppressResponseCode' => array('type' => 'bool'),
        'headerExpires' => array('type' => 'int', 'min' => 0),
    );

    /**
     * Use this method to set value to a static properly of Defaults when
     * you want to make sure only proper values are taken in with the help of
     * validation
     *
     * @static
     *
     * @param $name name of the static property
     * @param $value value to set the property to
     *
     * @return bool
     */
    public static function setProperty($name, $value){
        if(!property_exists(self, $name))return false
        if(@is_array(Defaults::$validation[$name])){
            $info = new ValidationInfo(Defaults::$validation[$name]);
            $value = DefaultValidator::validate($value, $info);
        }
        Defaults::$$name = $value;
        return true;
    }

}
