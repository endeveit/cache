<?php
/**
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @author Nikita Vershinin <endeveit@gmail.com>
 * @license MIT
 */
namespace Endeveit\Cache\Drivers;

use Endeveit\Cache\Interfaces\Driver;
use Endeveit\Cache\Interfaces\Serializer;
use Endeveit\Cache\Serializers\BuiltIn;

/**
 * The stub object used only to implement the Driver interfaces.
 * @codeCoverageIgnore
 */
class BlackHole implements Driver
{

    /**
     * {@inheritdoc}
     *
     * @var \Endeveit\Cache\Interfaces\Serializer
     */
    protected $serializer = null;

    /**
     * {@inheritdoc}
     *
     * @param  string       $id
     * @param  integer|null $lockTimeout
     * @return mixed|false  Data on success, false on failure
     */
    public function load($id, $lockTimeout = null)
    {
        return false;
    }

    /**
     * {@inheritdoc}
     *
     * @param  array $identifiers
     * @return array
     */
    public function loadMany(array $identifiers)
    {
        return array_combine(
            $identifiers,
            array_fill(0, count($identifiers), false)
        );
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
        return intval($value);
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
        return -intval($value);
    }

    /**
     * {@inheritdoc}
     *
     * @param  string  $id
     * @return boolean
     */
    public function contains($id)
    {
        return false;
    }

    /**
     * {@inheritdoc}
     *
     * @return boolean
     */
    public function flush()
    {
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
