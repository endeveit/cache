<?php
/**
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @author Nikita Vershinin <endeveit@gmail.com>
 * @license MIT
 */
namespace Cache\Drivers;

/**
 * Driver that stores data in MongoDB.
 */
class Mongo extends Common
{

    /**
     * MongoDB object.
     *
     * @var \MongoClient
     */
    protected $client;

    /**
     * MongoDB collection object.
     *
     * @var \MongoCollection
     */
    protected $collection;

    /**
     * The class constructor.
     *
     * @param \MongoClient $client
     * @param string       $dbName
     * @param string       $collection
     */
    public function __construct(\MongoClient $client, $dbName, $collection = 'cache')
    {
        $this->client = $client->selectDB($dbName);
        $this->collection = $this->client->selectCollection($collection);
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
     * @param  string      $id
     * @return mixed|false
     */
    protected function doLoad($id)
    {
        $cursor = $this->collection->find(array('id' => $id));

        if ($cursor->count() > 0) {
            $doc = $cursor->getNext();

            if (empty($doc['expires_at'])
                || (!empty($doc['expires_at']) && ($doc['expires_at']->sec > time()))) {
                return unserialize($doc['data']);
            }
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
        $now    = time();
        $cursor = $this->collection->find(array(
            'id' => array('$in' => $identifiers)
        ));

        foreach ($cursor as $doc) {
            if (empty($doc['expires_at'])
                || (!empty($doc['expires_at']) && ($doc['expires_at']->sec > $now))) {

                $result[$doc['id']] = unserialize($doc['data']);
            }
        }

        $this->fillNotFoundKeys($result, $identifiers);

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
    protected function doSave($data, $id, array $tags = array(), $lifetime = false)
    {
        $expiresAt = false !== $lifetime
            ? new \DateTime()
            : null;

        $object = array(
            'id'         => $id,
            'data'       => serialize($data),
            'tags'       => $tags,
            'created_at' => new \MongoDate(),
        );

        if (null !== $expiresAt) {
            $expiresAt->add(new \DateInterval('PT' . intval($lifetime) . 'S'));
            $object['expires_at'] = new \MongoDate($expiresAt->format('U'));;
        }

        $this->doRemove($id);

        return $this->collection->insert($object);
    }

    /**
     * {@inheritdoc}
     *
     * @param  string  $id
     * @return boolean
     */
    protected function doRemove($id)
    {
        return $this->collection->remove(array('id' => $id));
    }

    /**
     * {@inheritdoc}
     *
     * @param  array   $tags
     * @return boolean
     */
    protected function doRemoveByTags(array $tags)
    {
        $cursor = $this->collection->find(array(
            'tags' => array('$in' => $tags)
        ));

        while ($row = $cursor->getNext()) {
            $this->doRemove($row['id']);
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
    protected function doTouch($id, $extraLifetime)
    {
        $expiresAt = new \DateTime();
        $expiresAt->add(new \DateInterval('PT' . intval($extraLifetime) . 'S'));

        return $this->collection->update(
            array('id'   => $id),
            array('$set' => array('expires_at' => $expiresAt))
        );
    }

}
