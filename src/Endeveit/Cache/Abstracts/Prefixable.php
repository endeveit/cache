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
 * Abstract class for drivers that supports prefixable identifiers.
 */
abstract class Prefixable extends Common
{

    /**
     * Prefix for identifiers.
     *
     * @var string
     */
    protected $identifierPrefix = '';

    /**
     * Returns prefixed identifier.
     *
     * @param  string $id
     * @return string
     */
    protected function getPrefixedIdentifier($id)
    {
        return $this->identifierPrefix . $id;
    }

    /**
     * Returns identifier without prefix.
     *
     * @param  string $id
     * @return string
     */
    protected function getIdentifierWithoutPrefix($id)
    {
        if (!empty($this->identifierPrefix)) {
            return substr($id, strlen($this->identifierPrefix));
        }

        return $id;
    }
}
