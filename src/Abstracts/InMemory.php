<?php
/**
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @author Nikita Vershinin <endeveit@gmail.com>
 * @license MIT
 */
namespace Endeveit\Cache\Abstracts;

use Endeveit\Cache\Drivers\Memcache;

/**
 * Base class for in memory drivers.
 */
abstract class InMemory extends Memcache
{
    /**
     * List of callback functions.
     *
     * @var array
     */
    protected $callbacks = array(
        'increment' => null,
        'decrement' => null,
        'exists'    => null,
        'load'      => null,
        'save'      => null,
    );

    /**
     * {@inheritdoc}
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
     * @param  string  $id
     * @param  integer $value
     * @return integer
     */
    public function increment($id, $value = 1)
    {
        $id     = $this->getPrefixedIdentifier($id);
        $result = $value;

        if ($this->callbacks['exists']($id)) {
            $result = $this->callbacks['increment']($id, $value);
        } else {
            $this->doSaveScalar($value, $id);
        }

        return $result;
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
        $id     = $this->getPrefixedIdentifier($id);
        $result = -$value;

        if ($this->callbacks['exists']($id)) {
            $result = $this->callbacks['decrement']($id, $value);
        } else {
            $this->doSaveScalar($result, $id);
        }

        return $result;
    }

    /**
     * {@inheritdoc}
     *
     * @param  string        $id
     * @return boolean|mixed
     */
    protected function doLoad($id)
    {
        return $this->getProcessedLoadedValue($this->callbacks['load']($id));
    }

    /**
     * {@inheritdoc}
     *
     * @param  array $identifiers
     * @return array
     */
    protected function doLoadMany(array $identifiers)
    {
        $result = array();
        $now    = time();

        foreach ($identifiers as $id) {
            $i      = $this->getIdentifierWithoutPrefix($id);
            $source = $this->getProcessedLoadedValue($this->callbacks['load']($id));

            if (false !== $source) {
                if (array_key_exists('expiresAt', $source) && ($source['expiresAt'] < $now)) {
                    $result[$i] = false;
                } else {
                    $result[$i] = $source['data'];
                }
            }
        }

        $this->fillNotFoundKeys($result, $identifiers);

        return $result;
    }

    /**
     * {@inheritdoc}
     *
     * @param  string      $id
     * @return mixed|false
     */
    protected function doLoadRaw($id)
    {
        return $this->callbacks['load']($id);
    }

    /**
     * {@inheritdoc}
     *
     * @param  mixed   $data
     * @param  string  $id
     * @param  array   $tags
     * @return boolean
     */
    protected function doSave($data, $id, array $tags = array())
    {
        $this->validateIdentifier($id);

        if (!empty($tags)) {
            $this->saveTagsForId($id, $tags);
        }

        return $this->callbacks['save']($id, $data);
    }

    /**
     * {@inheritdoc}
     *
     * @param  mixed           $data
     * @param  string          $id
     * @param  integer|boolean $lifetime
     * @return boolean
     */
    protected function doSaveScalar($data, $id, $lifetime = false)
    {
        return $this->callbacks['save']($id, $data, $lifetime);
    }
}
