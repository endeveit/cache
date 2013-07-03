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
 * User: endeveit
 * Date: 03.07.13
 * Time: 10:25
 */
abstract class Redis extends Common
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
