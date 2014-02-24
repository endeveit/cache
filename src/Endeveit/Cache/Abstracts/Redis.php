<?php
/**
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @author Nikita Vershinin <endeveit@gmail.com>
 * @license MIT
 */
namespace Endeveit\Cache\Abstracts;

/**
 * Abstract class for redis drivers.
 */
abstract class Redis extends Prefixable
{

    /**
     * Prefix for entries that stores tags.
     *
     * @var string
     */
    protected $tagPrefix = 'tags:';

    /**
     * Returns the identifier for the tag.
     *
     * @param  string $tag
     * @return string
     */
    protected function getTagWithPrefix($tag)
    {
        return $this->tagPrefix . $tag;
    }
}
