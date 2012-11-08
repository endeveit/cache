<?php
/**
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @author Nikita Vershinin <endeveit@gmail.com>
 * @license MIT
 */
namespace Cache\Abstractions;

use Cache\Interfaces\Driver as DriverInterface;

/**
 * Base class for drivers that use max lifetime limit.
 */
abstract class Common implements DriverInterface
{
    /**
     * The max lifetime of the data in cache (31 days).
     *
     * @const integer
     */
    const MAX_LIFETIME = 2678400;

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
        $this->save($newValue, $id);

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
        $this->save($newValue, $id);

        return $newValue;
    }

    /**
     * Returns final lifetime not greater than self::MAX_LIFETIME.
     *
     * @param  boolean|integer $lifetime
     * @return integer
     */
    protected function getFinalLifetime($lifetime = false)
    {
        if (false !== $lifetime) {
            $lifetime = (integer) $lifetime;
            $lifetime = ($lifetime > self::MAX_LIFETIME
                ? self::MAX_LIFETIME
                : $lifetime);
        } else {
            $lifetime = 0;
        }

        return $lifetime;
    }

    /**
     * Returns a value for incrementing or decrementing key.
     *
     * @param  string  $id
     * @return integer
     */
    protected function getValueForIncrementOrDecrement($id)
    {
        $value = $this->load($id);

        if (!$value || !is_integer($value)) {
            $value = 0;
        }

        return $value;
    }

}
