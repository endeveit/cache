<?php
/**
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @author Nikita Vershinin <endeveit@gmail.com>
 * @license MIT
 */
namespace Endeveit\Cache\Abstracts;

use Endeveit\Cache\Interfaces\Driver;
use Endeveit\Cache\Interfaces\Serializer;

/**
 * Base class for drivers that use max lifetime limit.
 */
abstract class Common implements Driver
{
    /**
     * Default options for all drivers.
     *
     * @var array
     */
    protected $defaultOptions = array(
        'lock_suffix'      => '.lock',
        'prefix_id'        => '',
        'prefix_tag'       => 'tag.',
        'serializer'       => 'BuiltIn',
        'throw_exceptions' => true,
    );

    /**
     * Driver options.
     *
     * @var array
     */
    protected $options = array();

    /**
     * Instance of serializer.
     *
     * @var \Endeveit\Cache\Interfaces\Serializer
     */
    protected $serializer = null;

    /**
     * Class constructor.
     * Available options:
     *  "lock_suffix"      => suffix for read lock key
     *  "prefix_id"        => prefix for cache keys
     *  "prefix_tag"       => prefix for cache tags
     *  "serializer"       => one of predefined serializer objects: BuiltIn or Igbinary
     *  "throw_exceptions" => exception will be thrown on read/write errors
     *
     * @codeCoverageIgnore
     * @param array $options
     */
    public function __construct(array $options = array())
    {
        $this->options = array_merge($this->defaultOptions, $options);
    }

    /**
     * {@inheritdoc}
     *
     * @param \Endeveit\Cache\Interfaces\Serializer $serializer
     */
    public function setSerializer(Serializer $serializer)
    {
        $this->serializer = $serializer;
    }

    /**
     * {@inheritdoc}
     *
     * @return \Endeveit\Cache\Interfaces\Serializer
     */
    public function getSerializer()
    {
        if (null === $this->serializer) {
            $className        = 'Endeveit\Cache\Serializers\\' . $this->getOption('serializer');
            $this->serializer = new $className();
        }

        return $this->serializer;
    }

    /**
     * {@inheritdoc}
     *
     * @param  string       $id
     * @param  integer|null $lockTimeout
     * @return mixed|false  Data on success, false on failure
     * @throws \Exception
     */
    public function load($id, $lockTimeout = null)
    {
        $result = false;
        $source = $this->doLoad($this->getPrefixedIdentifier($id));

        if (false !== $source) {
            $result = $source['data'];

            if (array_key_exists('expiresAt', $source) && ($source['expiresAt'] < time())) {
                $result = false;

                if (null !== $lockTimeout) {
                    $lockId = $this->getPrefixedIdentifier($id . $this->getOption('lock_suffix'));

                    try {
                        $exists = $this->doLoadRaw($lockId);

                        if (!$exists) {
                            // Set the lock and return false
                            $this->doSaveScalar(1, $lockId, intval($lockTimeout));

                            $result = false;
                        } else {
                            $result = $source['data'];
                        }
                    } catch (\Exception $e) {
                        if (true === $this->getOption('throw_exceptions', true)) {
                            throw $e;
                        } else {
                            return false;
                        }
                    }
                }
            }
        }

        return $result;
    }

    /**
     * {@inheritdoc}
     *
     * @param  array $identifiers
     * @return array
     */
    public function loadMany(array $identifiers)
    {
        return $this->doLoadMany(array_map(array($this, 'getPrefixedIdentifier'), $identifiers));
    }

    /**
     * {@inheritdoc}
     *
     * @param  mixed           $data
     * @param  string          $id
     * @param  array           $tags
     * @param  integer|boolean $lifetime
     * @return boolean
     * @throws \Exception
     */
    public function save($data, $id, array $tags = array(), $lifetime = false)
    {
        $source = array('data' => $data);

        if (false !== $lifetime) {
            $source['expiresAt'] = time() + intval($lifetime);
        }

        try {
            $result = $this->doSave(
                $source,
                $this->getPrefixedIdentifier($id),
                array_map(array($this, 'getPrefixedTag'), $tags)
            );
        } catch (\Exception $e) {
            if (true === $this->getOption('throw_exceptions', true)) {
                throw $e;
            } else {
                $result = false;
            }
        }

        return $result;
    }

    /**
     * {@inheritdoc}
     *
     * @param  string  $id
     * @return boolean
     */
    public function remove($id)
    {
        return $this->save(false, $id, array(), -1);
    }

    /**
     * {@inheritdoc}
     *
     * @param  array   $tags
     * @return boolean
     */
    public function removeByTags(array $tags)
    {
        return $this->doRemoveByTags($tags);
    }

    /**
     * {@inheritdoc}
     *
     * @param  string  $id
     * @param  integer $extraLifetime
     * @return boolean
     */
    public function touch($id, $extraLifetime)
    {
        $result = false;
        $data   = $this->load($id);

        if (false !== $data) {
            $result = $this->save($data, $id, array(), $extraLifetime);
        }

        return $result;
    }

    /**
     * {@inheritdoc}
     *
     * @param  string  $id
     * @return boolean
     */
    public function contains($id)
    {
        return (false !== $this->load($id)) ? true : false;
    }

    /**
     * {@inheritdoc}
     *
     * @return boolean
     */
    public function flush()
    {
        return $this->doFlush();
    }

    /**
     * Returns configuration option.
     *
     * @param  string $name
     * @param  mixed  $default
     * @return mixed
     */
    protected function getOption($name, $default = null)
    {
        return array_key_exists($name, $this->options) ? $this->options[$name] : $default;
    }

    /**
     * Returns prefixed identifier.
     *
     * @param  string $id
     * @return string
     */
    protected function getPrefixedIdentifier($id)
    {
        return $this->getOption('prefix_id') . $id;
    }

    /**
     * Returns identifier without prefix.
     *
     * @param  string $id
     * @return string
     */
    protected function getIdentifierWithoutPrefix($id)
    {
        return substr($id, strlen($this->getOption('prefix_id')));
    }

    /**
     * Returns prefixed tag.
     *
     * @param  string $tag
     * @return string
     */
    protected function getPrefixedTag($tag)
    {
        return $this->getOption('prefix_id') . $this->getOption('prefix_tag') . $tag;
    }

    /**
     * Fills the not found keys with false in «loadMany» method.
     *
     * @param array $result
     * @param array $identifiers
     */
    protected function fillNotFoundKeys(array &$result, array &$identifiers)
    {
        $tmp = array();
        $ids = array_map(array($this, 'getIdentifierWithoutPrefix'), $identifiers);

        if (count($result) != count($identifiers)) {
            foreach (array_diff($ids, array_keys($result)) as $notExist) {
                $result[$notExist] = false;
            }
        }

        // Sort the results according to the order in $identifiers variable
        foreach ($ids as $id) {
            $tmp[$id] = $result[$id];
        }

        $result = $tmp;
    }

    /**
     * Returns processed value from loaded data.
     *
     * @param  mixed         $source
     * @return array|boolean
     */
    protected function getProcessedLoadedValue($source)
    {
        if (false !== $source) {
            if (is_array($source) && array_key_exists('data', $source)) {
                return $source;
            } elseif (is_scalar($source)) {
                return array(
                    'data' => $source,
                );
            }
        }

        return false;
    }

    /**
     * Returns an item through selected driver.
     *
     * @param  string      $id
     * @return array|false
     */
    abstract protected function doLoad($id);

    /**
     * Returns many items at once through selected driver.
     *
     * @param  array $identifiers
     * @return array
     */
    abstract protected function doLoadMany(array $identifiers);

    /**
     * Returns raw value from drivers.
     *
     * @param  string      $id
     * @return mixed|false
     */
    abstract protected function doLoadRaw($id);

    /**
     * Store an item through selected driver.
     *
     * @param  mixed   $data
     * @param  string  $id
     * @param  array   $tags
     * @return boolean
     */
    abstract protected function doSave($data, $id, array $tags = array());

    /**
     * Store raw value in selected driver.
     *
     * @param  mixed           $data
     * @param  string          $id
     * @param  integer|boolean $lifetime
     * @return boolean
     */
    abstract protected function doSaveScalar($data, $id, $lifetime = false);

    /**
     * Remove an items by cache tags through selected driver.
     *
     * @param  array   $tags
     * @return boolean
     */
    abstract protected function doRemoveByTags(array $tags);

    /**
     * Drops all items from cache through selected driver.
     *
     * @return boolean
     */
    abstract protected function doFlush();
}
