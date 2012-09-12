<?php
/**
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @author Nikita Vershinin <endeveit@gmail.com>
 * @license MIT
 */
namespace Cache\Drivers\Pdo;

use Cache\Exception;
use Cache\Drivers\Pdo;

/**
 * PDO MySQL cache driver.
 */
class MySQL extends Pdo
{

    /**
     * {@inheritdoc}
     */
    public function buildStructure()
    {
        $this->transactionsEnabled = false;

        $this->dbh->beginTransaction();

        try {
            $this->executeQuery('DROP INDEX IF EXISTS `' . $this->prefix .
                'tag_id_index`');
            $this->executeQuery('DROP INDEX IF EXISTS `' . $this->prefix .
                'tag_name_index`');
            $this->executeQuery('DROP INDEX IF EXISTS `' . $this->prefix .
                'cache_id_expire_index`');
            $this->executeQuery('DROP TABLE IF EXISTS `' . $this->prefix .
                'cache`');
            $this->executeQuery('DROP TABLE IF EXISTS `' . $this->prefix .
                'tag`');

            $this->executeQuery(
                'CREATE TABLE `' . $this->prefix . 'cache` (' .
                    '`id` VARCHAR(255) PRIMARY KEY, ' .
                    '`data` LONGTEXT, ' .
                    '`mktime` UNSIGNED INTEGER, ' .
                    '`expire` UNSIGNED INTEGER)');
            $this->executeQuery(
                'CREATE TABLE `' . $this->prefix .
                    'tag` (`name` VARCHAR(255), `id` TEXT)');
            $this->executeQuery(
                'CREATE INDEX `' . $this->prefix .
                    'tag_id_index` ON `' . $this->prefix . 'tag`(`id`)');
            $this->executeQuery(
                'CREATE INDEX `' . $this->prefix .
                    'tag_name_index` ON `' . $this->prefix . 'tag`(`name`)');
            $this->executeQuery(
                'CREATE INDEX `' . $this->prefix .
                    'cache_id_expire_index` ON `' . $this->prefix .
                        'cache`(`id`, `expire`)');

            $this->dbh->commit();
        } catch (Exception $e) {
            $this->dbh->rollBack();
            $this->transactionsEnabled = true;

            throw $e;
        }

        $this->transactionsEnabled = true;
    }

    /**
     * {@inheritdoc}
     *
     * @param string $identifier
     * @return string
     */
    protected function getQuotedIdentifier($identifier)
    {
        return "`" . $identifier . "`";
    }

}
