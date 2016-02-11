<?php
/**
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @author Igor Borodikhin <gmail@iborodikhin.net>
 * @author Nikita Vershinin <endeveit@gmail.com>
 * @license MIT
 */
namespace Endeveit\Cache\Drivers;

use Endeveit\Cache\Abstracts\InMemory;

/**
 * Driver that stores data in XCache and uses php5-xcache extension.
 */
class XCache extends InMemory
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
            'increment' => 'xcache_inc',
            'decrement' => 'xcache_dec',
            'exists'    => 'xcache_isset',
            'load'      => 'xcache_get',
            'save'      => 'xcache_set',
        );
    }

    /**
     * {@inheritdoc}
     *
     * @return boolean
     */
    protected function doFlush()
    {
        if (ini_get('xcache.admin.enable_auth')) {
            // @codeCoverageIgnoreStart
            throw new \BadMethodCallException(
                'You must set "xcache.admin.enable_auth" to "Off" in your php.ini to flush cache items.'
            );
            // @codeCoverageIgnoreEnd
        }

        xcache_clear_cache(XC_TYPE_VAR, 0);

        return true;
    }
}
