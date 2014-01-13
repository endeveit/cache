<?php
/**
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @author Nikita Vershinin <endeveit@gmail.com>
 * @license MIT
 */
namespace Endeveit\Cache\Drivers\Pdo;

use Endeveit\Cache\Abstracts\Pdo;
use Endeveit\Cache\Exception;

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
            $this->executeQuery('DROP INDEX IF EXISTS `' . $this->prefix . 'tag_id_index`');
            $this->executeQuery('DROP INDEX IF EXISTS `' . $this->prefix . 'tag_name_index`');
            $this->executeQuery('DROP INDEX IF EXISTS `' . $this->prefix . 'cache_id_expires_at_index`');
            $this->executeQuery('DROP TABLE IF EXISTS `' . $this->prefix . 'cache`');
            $this->executeQuery('DROP TABLE IF EXISTS `' . $this->prefix . 'tag`');

            $this->executeQuery(
                'CREATE TABLE `' . $this->prefix . 'cache` (' .
                '`id` VARCHAR(255) PRIMARY KEY, ' .
                '`data` LONGTEXT, ' .
                '`created_at` UNSIGNED INTEGER, ' .
                '`expires_at` UNSIGNED INTEGER)'
            );
            $this->executeQuery(sprintf(
                'CREATE TABLE `%stag` (`name` VARCHAR(255), `id` TEXT)',
                $this->prefix
            ));
            $this->executeQuery(sprintf(
                'CREATE INDEX `%1$stag_id_index` ON `%1$stag`(`id`)',
                $this->prefix
            ));
            $this->executeQuery(sprintf(
                'CREATE INDEX `%1$stag_name_index` ON `%1$stag`(`name`)',
                $this->prefix
            ));
            $this->executeQuery(sprintf(
                'CREATE INDEX `%1$scache_id_expires_at_index` ON `%1$scache`(`id`, `expires_at`)',
                $this->prefix
            ));

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
     * @return boolean
     */
    protected function doFlush()
    {
        $this->executeQuery(sprintf('TRUNCATE TABLE %s', $this->tables['cache']));

        return true;
    }

    /**
     * {@inheritdoc}
     *
     * @param  string $identifier
     * @return string
     */
    protected function getQuotedIdentifier($identifier)
    {
        return "`" . $identifier . "`";
    }
}
