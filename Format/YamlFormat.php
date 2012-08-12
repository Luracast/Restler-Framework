<?php
namespace Luracast\Restler\Format;

/**
 * YAML Format for Restler Framework
 * @category   Framework
 * @package    restler
 * @subpackage format
 * @author     R.Arul Kumaran <arul@luracast.com>
 * @copyright  2010 Luracast
 * @license    http://www.opensource.org/licenses/lgpl-license.php LGPL
 * @link       http://luracast.com/products/restler/
 */
use Symfony\Component\Yaml\Yaml;
use Luracast\Restler\RestlerHelper;

class YamlFormat implements iFormat
{
    const MIME ='text/plain';
    const EXTENSION = 'yaml';

    public function getMIMEMap()
    {
        return array(YamlFormat::EXTENSION=>YamlFormat::MIME);
    }
    public function getMIME()
    {
        return YamlFormat::MIME;
    }
    public function getExtension()
    {
        return YamlFormat::EXTENSION;
    }
    public function setMIME($mime)
    {
        // do nothing
    }
    public function setExtension($extension)
    {
        // do nothing
    }

    public function encode($data, $humanReadable = false)
    {
//		require_once 'sfyaml.php';
        return @Yaml::dump ( RestlerHelper::objectToArray ( $data ) );
    }

    public function decode($data)
    {
//		require_once 'sfyaml.php';
        return Yaml::parse ( $data );
    }

    public function __toString()
    {
        return $this->getExtension ();
    }

    public function setCharset($charset)
    {
        // TODO Auto-generated method stub
    }

    public function getCharset()
    {
        // TODO Auto-generated method stub
    }
}
