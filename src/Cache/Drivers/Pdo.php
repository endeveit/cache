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
     * Tables prefix.
     *
     * @var string
     */
    protected $prefix = null;

    /**
     * Names of tables.
     *
     * @var array
     */
    protected $rawTables = array('cache', 'tag');

    /**
     * Names of fields.
     *
     * @var array
     */
    protected $rawFields = array('id', 'data', 'mktime', 'expire', 'name');

    /**
     * Quoted names of tables used in driver.
     *
     * @var array
     */
    protected $tables = array();

    /**
     * Quoted names of fields used in tables.
     *
     * @var array
     */
    protected $fields = array();

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
     * @param string $prefix
     */
    public function __construct(\PDO $dbh, $prefix = 'cache_')
    {
        $this->dbh    = $dbh;
        $this->prefix = strval($prefix);

        $this->prepareIdentifiers();
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
            'SELECT %s, %s FROM %s WHERE %s = ?',
            $this->fields['data'],
            $this->fields['expire'],
            $this->tables['cache'],
            $this->fields['id']
        );

        $result = $this->fetchRows($sql, array($id));
        if (!empty($result)
            && ($result[0]['expire'] == 0 || $result[0]['expire'] > time())) {
            return unserialize($result['data']);
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
        $this->validateIdentifier($id);

        if (false !== $lifetime) {
            $lifetime = (integer) $lifetime;
            $lifetime = ($lifetime > self::MAX_LIFETIME
                ? self::MAX_LIFETIME
                : $lifetime);
        } else {
            $lifetime = (integer) $lifetime;
        }

        $this->transactionsEnabled = false;

        $this->dbh->beginTransaction();

        try {
            $this->executeQuery(
                sprintf(
                    'DELETE FROM %s WHERE %s = ?',
                    $this->tables['cache'],
                    $this->fields['id']
                ),
                array($id)
            );

            $mktime = time();
            $expire = $mktime + $lifetime;

            $this->executeQuery(
                sprintf(
                    'INSERT INTO %s (%s, %s, %s, %s) VALUES ' .
                        '(?, ?, ?, ?)',
                    $this->tables['cache'],
                    $this->fields['id'],
                    $this->fields['data'],
                    $this->fields['mktime'],
                    $this->fields['expire']
                ),
                array($id, serialize($data), $mktime, $expire)
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
        $params = array($id);

        $this->executeQuery(
            sprintf(
                'DELETE FROM %s WHERE %s = ?',
                $this->tables['cache'],
                $this->fields['id']
            ),
            $params
        );

        $this->executeQuery(
            sprintf(
                'DELETE FROM %s WHERE %s = ?',
                $this->tables['tag'],
                $this->fields['id']
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
        $this->validateIdentifier($id);

        $row = $this->fetchRows(
            sprintf(
                'SELECT %s FROM %s WHERE %s = ? AND (%s = 0 OR %s > ?)',
                $this->fields['expire'],
                $this->tables['cache'],
                $this->fields['id'],
                $this->fields['expire'],
                $this->fields['expire']
            ),
            array($id, time())
        );

        if (empty($row)) {
            return false;
        }

        $this->executeQuery(
            sprintf(
                'UPDATE %s SET %s = ?, %s = ? WHERE %s = ?',
                $this->tables['cache'],
                $this->fields['mktime'],
                $this->fields['expire'],
                $this->fields['id']
            ),
            array(time(), $row[0]['expire'] + $extraLifetime, $id)
        );

        return true;
    }

    /**
     * Quote tables names and fields names.
     */
    protected function prepareIdentifiers()
    {
        foreach ($this->rawTables as $name) {
            $this->tables[$name] = $this->getQuotedIdentifier(
                $this->prefix . $name
            );
        }

        foreach ($this->rawFields as $name) {
            $this->fields[$name] = $this->getQuotedIdentifier($name);
        }
    }

    /**
     * Validates cache identifier or a tag, throws an exception in
     * case of a problem.
     *
     * @param string $id
     * @throws \Cache\Exception
     */
    protected function validateIdentifier($id)
    {
        if (!is_string($id) || strlen($id) > 255) {
            throw new Exception(
                'Invalid identifier: ' .
                    'must be a string less than 255 chars length.'
            );
        }
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
        $this->validateIdentifier($tag);

        $params = array($tag, $id);

        $this->executeQuery(
            sprintf(
                'DELETE FROM %s WHERE %s = ? AND %s = ?',
                $this->tables['tag'],
                $this->fields['name'],
                $this->fields['id']
            ),
            $params
        );

        $this->executeQuery(
            sprintf(
                'INSERT INTO %s (%s, %s) VALUES (?, ?)',
                $this->tables['tag'],
                $this->fields['name'],
                $this->fields['id']
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
        $first  = true;
        $result = array();
        $sql    = sprintf(
            'SELECT DISTINCT(%s) FROM %s WHERE %s = ?',
            $this->fields['id'],
            $this->tables['tag'],
            $this->fields['name']
        );

        foreach (array_unique($tags) as $tag) {
            $rows = $this->fetchRows($sql, array($tag), \PDO::FETCH_COLUMN);

            if ($first) {
                $result = $rows;
                $first  = false;
            } else {
                $result = array_intersect($result, $rows);
            }
        }

        return $result;
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
     * @param integer $fetchStyle
     * @return array
     * @throws \Cache\Exception
     */
    protected function fetchRows($sql, $params = array(), $fetchStyle = \PDO::FETCH_ASSOC)
    {
        $stmt = $this->dbh->prepare($sql);

        if (!$stmt || !$stmt->execute($params)) {
            $this->throwException();
        }

        return $stmt->fetchAll($fetchStyle);
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
     * Builds the tables structure in database.
     *
     * @abstract
     */
    abstract public function buildStructure();

    /**
     * Returns quoted identifier (table name or field name).
     *
     * @abstract
     * @param string $identifier
     * @return string
     */
    abstract protected function getQuotedIdentifier($identifier);

}
