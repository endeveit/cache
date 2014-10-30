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
        'lock_suffix'  => '.lock',
        'lock_timeout' => 10,
        'prefix_id'    => '',
        'prefix_tag'   => 'tag.'
    );

    /**
     * Driver options.
     *
     * @var array
     */
    protected $options = array();

    /**
     * Class constructor.
     * Available options:
     *  "lock_suffix" => suffix for read lock key
     *  "prefix_id"   => prefix for cache keys
     *  "prefix_tag"  => prefix for cache tags
     *
     * @param array $options
     */
    public function __construct(array $options = array())
    {
        $this->options = array_merge($this->defaultOptions, $options);
    }

    /**
     * {@inheritdoc}
     *
     * @param  string          $id
     * @param  callable        $cbGenerateData
     * @param  integer         $cbLockTimeout
     * @param  array           $tags
     * @param  integer|boolean $lifetime
     * @return mixed|false     Data on success, false on failure
     */
    public function load($id, $cbGenerateData = null, $cbLockTimeout = null, array $tags = array(), $lifetime = false)
    {
        $result = false;
        $source = $this->doLoad($this->getPrefixedIdentifier($id));

        if (false !== $source) {
            $now    = time();
            $result = $source['data'];

            if (array_key_exists('expiresAt', $source)) {
                if ($source['expiresAt'] < $now) {
                    if ((null !== $cbGenerateData) && is_callable($cbGenerateData)) {
                        $lockId = $this->getPrefixedIdentifier($id . $this->getOption('lock_suffix'));

                        if (!$this->contains($lockId)) {
                            $this->doSaveScalar(
                                1,
                                $lockId,
                                (null !== $cbLockTimeout
                                    ? intval($cbLockTimeout)
                                    : false)
                            );

                            $result = call_user_func($cbGenerateData);

                            $this->save($result, $id, $tags, $lifetime);
                        }
                    } else {
                        $result = false;
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
     */
    public function save($data, $id, array $tags = array(), $lifetime = false)
    {
        $source = array('data' => $data);

        if (false !== $lifetime) {
            $source['expiresAt'] = time() + intval($lifetime);
        }

        return $this->doSave(
            $source,
            $this->getPrefixedIdentifier($id),
            array_map(array($this, 'getPrefixedTag'), $tags),
            $lifetime
        );
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
        $data = $this->load($id);

        if (false !== $data) {
            return $this->save($data, $id, array(), $extraLifetime);
        }

        return true;
    }

    /**
     * {@inheritdoc}
     *
     * @param  string  $id
     * @param  integer $value
     * @return integer
     */
    public function increment($id, $value = 1)
    {
        $oldValue = $this->getValueForIncrementOrDecrement($id);
        $newValue = $oldValue + intval($value);

        $this->doSaveScalar($newValue, $id);

        return $newValue;
    }

    /**
     * {@inheritdoc}
     *
     * @param  string  $id
     * @param  integer $value
     * @return integer
     */
    public function decrement($id, $value = 1)
    {
        $oldValue = $this->getValueForIncrementOrDecrement($id);
        $newValue = $oldValue - intval($value);

        $this->doSaveScalar($newValue, $id);

        return $newValue;
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
     * Returns a value for incrementing or decrementing key.
     *
     * @param  string  $id
     * @return integer
     */
    protected function getValueForIncrementOrDecrement($id)
    {
        $value = $this->doLoadRaw($this->getPrefixedIdentifier($id));

        if (!$value || !is_integer($value)) {
            $value = 0;
        }

        return $value;
    }

    /**
     * Fills the not found keys with false in «loadMany» method.
     *
     * @param array $result
     * @param array $identifiers
     */
    protected function fillNotFoundKeys(array &$result, array &$identifiers)
    {
        if (count($result) != count($identifiers)) {
            $identifiers = array_map(array($this, 'getIdentifierWithoutPrefix'), $identifiers);

            foreach (array_diff($identifiers, array_keys($result)) as $notExist) {
                $result[$notExist] = false;
            }
        }
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
     * @param  scalar          $data
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
