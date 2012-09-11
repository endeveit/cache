<?php
/**
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @author Nikita Vershinin <endeveit@gmail.com>
 * @license MIT
 */
namespace Cache\Drivers;

use Cache\Exception;
use Cache\Interfaces\Driver as DriverInterface;

/**
 * Driver that stores data in relational database and uses PDO to work with it.
 */
abstract class Pdo implements DriverInterface
{

    /**
     * The max lifetime of the data in cache (31 days).
     *
     * @const integer
     */
    const MAX_LIFETIME = 2678400;

    /**
     * The class constructor.
     *
     * @var \PDO
     */
    protected $dbh = null;

    /**
     * Do ve have transactions enabled.
     *
     * @var boolean
     */
    protected $transactionsEnabled = true;

    /**
     * The class constructor.
     *
     * @param \PDO $dbh
     */
    public function __construct(\PDO $dbh)
    {
        $this->dbh = $dbh;
    }

    /**
     * {@inheritdoc}
     *
     * @param string $id
     * @return mixed|false Data on success, false on failure
     */
    public function load($id)
    {
        $sql = sprintf(
            'SELECT %s, %s FROM %s WHERE %s = :id',
            $this->getFieldNameQuoted('data'),
            $this->getFieldNameQuoted('expire'),
            $this->getTableNameQuoted('cache'),
            $this->getFieldNameQuoted('id')
        );

        $result = $this->fetchRows($sql, array('id' => $id));
        if (!empty($result) && $result[0]['expire'] > time()) {
            return $result['data'];
        }

        false;
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
        if (false !== $lifetime) {
            $lifetime = (integer) $lifetime;
            $lifetime = ($lifetime > self::MAX_LIFETIME
                ? self::MAX_LIFETIME
                : $lifetime);
        } else {
            $lifetime = (integer) $lifetime;
        }

        $mktime = time();
        $expire = $mktime + $lifetime;

        $quotedTable = $this->getTableNameQuoted('cache');
        $quotedId    = $this->getFieldNameQuoted('id');

        $this->transactionsEnabled = false;

        $this->dbh->beginTransaction();

        try {
            $this->executeQuery(
                sprintf(
                    'DELETE FROM %s WHERE %s = :id',
                    $quotedTable,
                    $quotedId
                ),
                array('id' => $id)
            );

            $this->executeQuery(
                sprintf(
                    'INSERT INTO %s (%s, %s, %s, %s) VALUES ' .
                        '(:id, :data, :mktime, :expire)',
                    $quotedTable,
                    $quotedId,
                    $this->getFieldNameQuoted('data'),
                    $this->getFieldNameQuoted('mktime'),
                    $this->getFieldNameQuoted('expire')
                ),
                array(
                    'id'     => $id,
                    'data'   => $data,
                    'mktime' => $mktime,
                    'expire' => $expire,
                )
            );

            foreach ($tags as $tag) {
                $this->registerTag($id, $tag);
            }

            $this->dbh->commit();
        } catch (Exception $e) {
            $this->dbh->rollBack();
            $this->transactionsEnabled = true;

            throw $e;
        }

        $this->transactionsEnabled = true;

        return true;
    }

    /**
     * {@inheritdoc}
     *
     * @param string $id
     * @return boolean
     */
    public function remove($id)
    {
        $params   = array('id' => $id);
        $quotedId = $this->getFieldNameQuoted('id');

        $this->executeQuery(
            sprintf(
                'DELETE FROM %s WHERE %s = :id',
                $this->getTableNameQuoted('cache'),
                $quotedId
            ),
            $params
        );

        $this->executeQuery(
            sprintf(
                'DELETE FROM %s WHERE %s = :id',
                $this->getTableNameQuoted('tag'),
                $quotedId
            ),
            $params
        );

        return true;
    }

    /**
     * {@inheritdoc}
     *
     * @param array $tags
     * @return boolean
     */
    public function removeByTags(array $tags)
    {
        $identifiers = $this->getIdsMatchingTags($tags);

        $this->transactionsEnabled = false;

        $this->dbh->beginTransaction();

        try {
            foreach ($identifiers as $id) {
                $this->remove($id);
            }

            $this->dbh->commit();
        } catch (Exception $e) {
            $this->dbh->rollBack();
            $this->transactionsEnabled = true;

            throw $e;
        }

        $this->transactionsEnabled = true;

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
        $quotedTable  = $this->getTableNameQuoted('cache');
        $quotedId     = $this->getFieldNameQuoted('id');
        $quotedExpire = $this->getFieldNameQuoted('expire');
        $row          = $this->fetchRows(
            sprintf(
                'SELECT %s FROM %s WHERE %s = :id AND (%s = 0 OR %s > :expire)',
                $quotedExpire,
                $quotedTable,
                $quotedId,
                $quotedExpire,
                $quotedExpire
            ),
            array(
                'id'     => $id,
                'expire' => time(),
            )
        );

        if (empty($row)) {
            return false;
        }

        $this->executeQuery(
            sprintf(
                'UPDATE %s SET %s = :mktime, %s = :expire WHERE %s = :id',
                $quotedTable,
                $this->getFieldNameQuoted('mktime'),
                $quotedExpire,
                $quotedId
            ),
            array(
                'mktime' => time(),
                'expire' => $row[0]['expire'] + $extraLifetime,
                'id'     => $id,
            )
        );

        return true;
    }

    /**
     * Register a cache id with the given tag.
     *
     * @param string $id
     * @param string $tag
     * @return boolean
     * @throws \Cache\Exception
     */
    protected function registerTag($id, $tag)
    {
        $params      = array('name' => $tag, 'id' => $id);
        $quotedTable = $this->getTableNameQuoted('tag');
        $quotedId    = $this->getFieldNameQuoted('id');
        $quotedName  = $this->getFieldNameQuoted('name');

        $this->executeQuery(
            sprintf(
                'DELETE FROM %s WHERE %s = :name AND %s = :id',
                $quotedTable,
                $quotedName,
                $quotedId
            ),
            $params
        );

        $this->executeQuery(
            sprintf(
                'INSERT INTO %s (%s, %s) VALUES (:name, :id)',
                $quotedTable,
                $quotedId,
                $quotedName
            ),
            $params
        );

        return true;
    }

    /**
     * Return an array of stored cache ids which match given tags
     *
     * In case of multiple tags, a logical AND is made between tags
     *
     * @param  array $tags
     * @return array
     */
    protected function getIdsMatchingTags($tags = array())
    {
        $first    = true;
        $ids      = array();
        $result   = array();
        $quotedId = $this->getFieldNameQuoted('id');
        $sql      = sprintf(
            'SELECT DISTINCT(%s) AS %s FROM %s WHERE %s = :name',
            $quotedId,
            $this->getTableNameQuoted('tag'),
            $this->getFieldNameQuoted('name')
        );

        foreach ($tags as $tag) {
            $rows = $this->fetchRows($sql, array('name' => $tag));
            $ids2 = array();

            foreach ($rows as $row) {
                $ids2[] = $row['id'];
            }

            if ($first) {
                $ids   = $ids2;
                $first = false;
            } else {
                $ids = array_intersect($ids, $ids2);
            }
        }

        foreach ($ids as $id) {
            $result[] = $id;
        }

        return array_unique($result);
    }

    /**
     * Executes an SQL query.
     *
     * @param string $sql
     * @param array $params
     * @throws \Cache\Exception
     */
    protected function executeQuery($sql, $params = array())
    {
        $stmt = $this->dbh->prepare($sql);
        if (!$stmt) {
            $this->throwException();
        }

        if ($this->transactionsEnabled) {
            $this->dbh->beginTransaction();

            try {
                if (!$stmt->execute($params)) {
                    $this->throwException();
                }

                $this->dbh->commit();
            } catch (Exception $e) {
                $this->dbh->rollBack();

                throw $e;
            }
        } else {
            if (!$stmt->execute($params)) {
                $this->throwException();
            }
        }
    }

    /**
     * Executes SQL and return result of fetch.
     *
     * @param string $sql
     * @param array $params
     * @return array
     * @throws \Cache\Exception
     */
    protected function fetchRows($sql, $params = array())
    {
        $stmt = $this->dbh->prepare($sql);

        if (!$stmt || $stmt->execute($params)) {
            $this->throwException();
        }

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Throws exception when database error occurs.
     *
     * @throws \Cache\Exception
     */
    protected function throwException()
    {
        $error = $this->dbh->errorInfo();

        throw new Exception(
            'Cannot execute query, error: ' . $error[2],
            $error[1]
        );
    }

    /**
     * Returns quoted table name.
     *
     * @abstract
     * @param string $tableName
     * @return string
     */
    abstract protected function getTableNameQuoted($tableName);

    /**
     * Returns quoted field name.
     *
     * @abstract
     * @param string $fieldName
     * @return string
     */
    abstract protected function getFieldNameQuoted($fieldName);

}
