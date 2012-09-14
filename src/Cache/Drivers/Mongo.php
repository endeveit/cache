<?php
/**
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @author Nikita Vershinin <endeveit@gmail.com>
 * @license MIT
 */
namespace Cache\Drivers;

use Cache\Interfaces\Driver as DriverInterface;

/**
 * Driver that stores data in MongoDB.
 */
class Mongo implements DriverInterface
{

    /**
     * MongoDB object.
     *
     * @var \MongoDB
     */
    protected $db;

    /**
     * MongoDB collection object.
     *
     * @var \MongoCollection
     */
    protected $collection;

    /**
     * The class constructor.
     *
     * @param \Mongo $con
     * @param string $dbName
     * @param string $collection
     */
    public function __construct(\Mongo $con, $dbName, $collection = 'cache')
    {
        $this->db = $con->selectDB($dbName);
        $this->collection = $this->db->selectCollection($collection);
    }

    /**
     * Creates indexes, or does nothing if indexes are already exists.
     */
    public function ensureIndexes()
    {
        $this->collection->ensureIndex(
            array('id' => 1),
            array('unique' => 1, 'dropDups' => 1)
        );

        $this->collection->ensureIndex(
            array('tags' => 1)
        );
    }

    /**
     * {@inheritdoc}
     *
     * @param string $id
     * @return mixed|false Data on success, false on failure
     */
    public function load($id)
    {
        $object = $this->getById($id);

        if (null !== $object
            && (empty($object['expires_at'])
                || (!empty($object['expires_at'])
                    && ($object['expires_at']->format('U') > time())
                )
            )
        ) {
            return unserialize($object['data']);
        }

        return false;
    }

    /**
     * {@inheritdoc}
     *
     * @param mixed $data
     * @param string $id
     * @param array $tags
     * @param integer|boolean $lifetime
     * @return boolean
     */
    public function save($data, $id, array $tags = array(), $lifetime = false)
    {
        $createdAt = new \DateTime();
        $expiresAt = false !== $lifetime
            ? new \DateTime()
            : null;

        $object = array(
            'id'         => $id,
            'data'       => serialize($data),
            'tags'       => $tags,
            'created_at' => $createdAt,
        );

        if (null !== $expiresAt) {
            $expiresAt->add(new \DateInterval('PT' . intval($lifetime) . 'S'));
            $object['expires_at'] = $expiresAt;
        }

        return $this->collection->save($object);
    }

    /**
     * {@inheritdoc}
     *
     * @param string $id
     * @return boolean
     */
    public function remove($id)
    {
        return $this->collection->remove(array('id' => $id));
    }

    /**
     * {@inheritdoc}
     *
     * @param array $tags
     * @return boolean
     */
    public function removeByTags(array $tags)
    {
        $cursor = $this->collection->find(array(
            'tags' => array('$in' => $tags)
        ));

        while ($row = $cursor->getNext()) {
            $this->remove($row['id']);
        }

        return true;
    }

    /**
     * {@inheritdoc}
     *
     * @param string $id
     * @param integer $extraLifetime
     * @return boolean
     */
    public function touch($id, $extraLifetime)
    {
        $expiresAt = new \DateTime();
        $expiresAt->add(new \DateInterval('PT' . intval($extraLifetime) . 'S'));

        return $this->collection->update(
            array('id'   => $id),
            array('$set' => array('expires_at' => $expiresAt))
        );
    }

    /**
     * Returns object from collection by identifier.
     *
     * @param string $id
     * @return array|null
     */
    protected function getById($id)
    {
        $cursor = $this->collection->find(array('id' => $id));

        if ($cursor->count() > 0) {
            return $cursor->getNext();
        }

        return null;
    }

}
