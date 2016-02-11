<?php
namespace Endeveit\Cache\Drivers;
/**
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @author Igor Borodikhin <iborodikhin@gmail.com>
 * @license MIT
 */
use Endeveit\Cache\Interfaces\Driver;
use Endeveit\Cache\Interfaces\Serializer;
use Endeveit\Cache\Serializers\BuiltIn;

/**
 * Driver that stores data in native PHP variables.
 */
class Localcache implements Driver
{
    /**
     * {@inheritdoc}
     *
     * @var \Endeveit\Cache\Interfaces\Serializer
     */
    protected $serializer = null;

    /**
     * Local cache for data.
     *
     * @var array
     */
    protected $localCache = array();

    /**
     * Tags to keys mapping.
     *
     * @var array
     */
    protected $tagsMap = array();

    /**
     * {@inheritdoc}
     *
     * @param  string       $id
     * @param  integer|null $lockTimeout
     * @return mixed|false  Data on success, false on failure
     */
    public function load($id, $lockTimeout = null)
    {
        if (!isset($this->localCache[$id])) {
            return false;
        }

        $item = $this->localCache[$id];

        return (isset($item['expiresAt']) && time() < $item['expiresAt'])
            ? $item['data']
            : false;
    }

    /**
     * {@inheritdoc}
     *
     * @param  array $identifiers
     * @return array
     */
    public function loadMany(array $identifiers)
    {
        $result = array_combine(
            $identifiers,
            array_fill(0, count($identifiers), false)
        );

        foreach ($identifiers as $id) {
            $result[$id] = $this->load($id);
        }

        return $result;
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
        $source = array('data' => $data, 'tags' => $tags);

        if (false !== $lifetime) {
            $source['expiresAt'] = time() + intval($lifetime);
        }

        $this->localCache[$id] = $source;

        foreach ($tags as $tag) {
            if (!isset($this->tagsMap[$tag])) {
                $this->tagsMap[$tag] = [];
            }

            $this->tagsMap[$tag] = array_unique(array_merge($this->tagsMap[$tag], array($id)));
        }

        return true;
    }

    /**
     * {@inheritdoc}
     *
     * @param  string  $id
     * @return boolean
     */
    public function remove($id)
    {
        if (!isset($this->localCache[$id])) {
            return true;
        }

        $item = $this->localCache[$id];

        unset($this->localCache[$id]);

        foreach ($item['tags'] as $tag) {
            if (isset($this->tagsMap[$tag]) && in_array($id, $this->tagsMap[$tag])) {
                $this->tagsMap[$tag] = array_diff($this->tagsMap[$tag], array($id));
            }
        }

        return true;
    }

    /**
     * {@inheritdoc}
     *
     * @param  array   $tags
     * @return boolean
     */
    public function removeByTags(array $tags)
    {
        foreach ($tags as $tag) {
            if (isset($this->tagsMap[$tag])) {
                foreach ($this->tagsMap[$tag] as $id) {
                    $this->remove($id);
                }
            }
        }

        return true;
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
        if (isset($this->localCache[$id])) {
            $this->localCache[$id]['expiresAt'] = $this->localCache[$id]['expiresAt'] + intval($extraLifetime);
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
        if (!isset($this->localCache[$id])) {
            return $this->save(intval($value), $id);
        }

        $item                  = $this->localCache[$id];
        $item['data']          += intval($value);
        $this->localCache[$id] = $item;

        return true;
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
        if (!isset($this->localCache[$id])) {
            return $this->save(intval($value), $id);
        }

        $item                  = $this->localCache[$id];
        $item['data']          -= intval($value);
        $this->localCache[$id] = $item;

        return true;
    }

    /**
     * {@inheritdoc}
     *
     * @param  string  $id
     * @return boolean
     */
    public function contains($id)
    {
        return isset($this->localCache[$id]);
    }

    /**
     * {@inheritdoc}
     *
     * @return boolean
     */
    public function flush()
    {
        $this->tagsMap    = array();
        $this->localCache = array();

        return true;
    }

    /**
     * {@inheritdoc}
     *
     * @param  \Endeveit\Cache\Interfaces\Serializer $serializer
     * @return void
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
            $this->serializer = new BuiltIn();
        }

        return $this->serializer;
    }
}
