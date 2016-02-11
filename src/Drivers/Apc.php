<?php
/**
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @author Nikita Vershinin <endeveit@gmail.com>
 * @license MIT
 */
namespace Endeveit\Cache\Drivers;

use Endeveit\Cache\Abstracts\InMemory;

/**
 * Driver that stores data in APC and uses php5-apc extension.
 */
class Apc extends InMemory
{
    /**
     * {@inheritdoc}
     *
     * @codeCoverageIgnore
     * @param array $options
     */
    public function __construct(array $options = array())
    {
        parent::__construct($options);

        $this->callbacks = array(
            'increment' => 'apc_inc',
            'decrement' => 'apc_dec',
            'exists'    => 'apc_exists',
            'load'      => 'apc_fetch',
            'save'      => 'apc_store',
        );
    }

    /**
     * {@inheritdoc}
     *
     * @return boolean
     */
    protected function doFlush()
    {
        return apc_clear_cache('user');
    }
}
