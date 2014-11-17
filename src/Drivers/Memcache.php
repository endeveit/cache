<?php
/**
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @author Nikita Vershinin <endeveit@gmail.com>
 * @license MIT
 */
namespace Endeveit\Cache\Drivers;

use Endeveit\Cache\Abstracts\Common;
use Endeveit\Cache\Exception;

/**
 * Driver that stores data in Memcached and uses \Memcache.
 * This driver was inspired by the TrekkSoft AG Zend_Cache memcached backend.
 *
 * @link https://github.com/bigwhoop/taggable-zend-memcached-backend
 */
class Memcache extends Common
{

    /**
     * Separator which concatenates tags.
     *
     * @var string
     */
    protected $tagSeparator = '|';

    /**
     * {@inheritdoc}
     *
     * Additional options:
     *  "client"   => the instance of \Memcache object
     *  "compress" => boolean value which indicates to enable zlib compression or not
     *
     * @codeCoverageIgnore
     * @param  array                     $options
     * @throws \Endeveit\Cache\Exception
     */
    public function __construct(array $options = array())
    {
        if (!array_key_exists('client', $options)) {
            throw new Exception('You must provide option "client" with \Memcache object');
        }

        if (array_key_exists('compress', $options) && $options['compress']) {
            $options['compress'] = MEMCACHE_COMPRESSED;
        }

        parent::__construct($options);

        $this->validateIdentifier($this->getOption('prefix_id'));
        $this->validateIdentifier($this->getOption('prefix_tag'));
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
        $id = $this->getPrefixedIdentifier($id);

        $this->validateIdentifier($id);

        $result = $this->getOption('client')->increment($id, $value);

        if (false === $result) {
            $this->doSaveScalar($value, $id);

            $result = $value;
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
        $id = $this->getPrefixedIdentifier($id);

        $this->validateIdentifier($id);

        $result = $this->getOption('client')->decrement($id, $value);

        if (false === $result) {
            $this->doSaveScalar($value, $id);

            $result = -$value;
        }

        return $result;
    }

    /**
     * {@inheritdoc}
     *
     * @param  string      $id
     * @return mixed|false
     */
    protected function doLoad($id)
    {
        $result = $this->getOption('client')->get($id, $this->getOption('compress', 0));

        if (!empty($result) && is_array($result)) {
            return $result;
        }

        return false;
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

        foreach ($this->getOption('client')->get($identifiers, $this->getOption('compress', 0)) as $id => $entry) {
            if (!empty($entry) && is_array($entry)) {
                $result[$this->getIdentifierWithoutPrefix($id)] = $entry['data'];
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
        return $this->getOption('client')->get($id, $this->getOption('compress', 0));
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

        return $this->getOption('client')->set($id, $data, $this->getOption('compress', 0));
    }

    /**
     * {@inheritdoc}
     *
     * @param  scalar          $data
     * @param  string          $id
     * @param  integer|boolean $lifetime
     * @return boolean
     */
    protected function doSaveScalar($data, $id, $lifetime = false)
    {
        return $this->getOption('client')->set($id, $data, $this->getOption('compress', 0), $lifetime);
    }

    /**
     * Remove an items by cache tags.
     *
     * @param  array   $tags
     * @return boolean
     */
    protected function doRemoveByTags(array $tags)
    {
        foreach ($this->getIdsMatchingAnyTags($tags) as $id) {
            $this->remove($id);
        }

        return true;
    }

    /**
     * {@inheritdoc}
     *
     * @return boolean
     */
    protected function doFlush()
    {
        return $this->getOption('client')->flush();
    }

    /**
     * Validates cache identifier or a tag, throws an exception in
     * case of a problem.
     *
     * @param  string                    $id
     * @throws \Endeveit\Cache\Exception
     */
    protected function validateIdentifier($id)
    {
        if (!empty($id) && !preg_match('#^[\S]+$#', $id)) {
            throw new Exception(sprintf(
                'Identifier "%s" cannot have spaces or be more than 250 characters length.',
                $id
            ));
        }
    }

    /**
     * Save the tags for identifier.
     *
     * @param string $id
     * @param array  $tags
     */
    protected function saveTagsForId($id, array $tags)
    {
        foreach ($tags as $tag) {
            $idsInTag = $this->getIdentifiersForTag($tag);

            if (!in_array($id, $idsInTag)) {
                $idsInTag[] = $id;

                $this->doSaveScalar(implode($this->tagSeparator, $idsInTag), $tag);
            }
        }
    }

    /**
     * Return an array of stored cache ids which match given tags.
     *
     * @param  array $tags
     * @return array
     */
    protected function getIdsMatchingAnyTags($tags = array())
    {
        $result = array();

        foreach ($tags as $tag) {
            $result = array_merge($result, $this->getIdentifiersForTag($tag));
        }

        return array_unique($result);
    }

    /**
     * Returns list of identifiers for tag.
     *
     * @param  string $tag
     * @return array
     */
    protected function getIdentifiersForTag($tag)
    {
        $identifiers = $this->doLoadRaw($this->getPrefixedTag($tag));

        if (empty($identifiers)) {
            return array();
        }

        return array_map(
            array($this, 'getIdentifierWithoutPrefix'),
            explode($this->tagSeparator, (string) $identifiers)
        );
    }
}
