<?php
/**
 * Copyright © Qoliber. All rights reserved.
 *
 * @category    Qoliber
 * @package     Qoliber_CatalogGenerator
 * @author      Jakub Winkler <jwinkler@qoliber.com>
 */


declare(strict_types=1);

namespace Qoliber\CatalogGenerator\Task;

use Magento\UrlRewrite\Model\Storage\DbStorage;
use Qoliber\CatalogGenerator\Sql\InsertMultipleOnDuplicate;

abstract class AbstractUrlGenerator extends AbstractTask
{
    /** @var string[]  */
    public const TABLE_STRUCTURE = ['entity_type', 'entity_id', 'request_path', 'target_path', 'redirect_type',
        'store_id', 'description', 'is_autogenerated', 'metadata'];

    /** @var int  */
    public const CHUNK_SIZE = 2500;

    /** @var string  */
    public const PATH_STRUCTURE = 'catalog/%s/view/id/%d';

    /** @var int  */
    private int $_urlKeyAttributeId = 0;

    /**
     * Return url_key attribute id per entity
     *
     * @param int $entityTypeId
     * @return int
     */
    public function getUrlKeyAttributeId(int $entityTypeId): int
    {
        if ($this->_urlKeyAttributeId) {
            return $this->_urlKeyAttributeId;
        }

        return (int)$this->connection->getConnection()->fetchOne(
            $this->connection->getConnection()->select()
                ->from($this->connection->getTableName('eav_attribute'))
                ->where('entity_type_id = ?', $entityTypeId)
                ->where('attribute_code = ?', 'url_key')
        );
    }

    /**
     * Get target path
     *
     * @param string $entityType
     * @param int $entityId
     * @return string
     */
    public function getTargetPath(string $entityType, int $entityId): string
    {
        return sprintf(self::PATH_STRUCTURE, $entityType, $entityId);
    }

    /**
     * Save URLs in chunks to database
     *
     * @param mixed[] $urlRewriteArray
     * @return $this
     * @throws \Exception
     */
    public function saveAndUpdateUrls(array $urlRewriteArray): self
    {
        $insert = new InsertMultipleOnDuplicate();
        $insert->onDuplicate(['request_path']);
        $urlRewriteArray = array_filter($urlRewriteArray, function ($rewriteData) {
            return !empty($rewriteData[0]) && !empty($rewriteData[2]) &&
                !empty($rewriteData[3]);
        });

        foreach (array_chunk($urlRewriteArray, self::CHUNK_SIZE) as $dataChunk) {
            $prepareStatement = $insert->buildInsertQuery(
                DbStorage::TABLE_NAME,
                self::TABLE_STRUCTURE,
                count($dataChunk)
            );

            $this->connection->execute($prepareStatement, InsertMultipleOnDuplicate::flatten($dataChunk));
        }

        return $this;
    }

    /**
     * Prepare a row for url_rewrite table
     *
     * @param mixed $entityId
     * @param string $requestPath
     * @param string $targetPath
     * @param mixed $storeId
     * @param string $entityTypeId
     * @return string[]
     */
    public function prepareRow(
        mixed $entityId,
        string $requestPath,
        string $targetPath,
        mixed $storeId,
        string $entityTypeId
    ): array {
        return [
            $entityTypeId,
            $entityId,
            $requestPath,
            $targetPath,
            0,
            $storeId,
            null,
            1,
            null
        ];
    }

    /**
     * Get SEO Value
     *
     * @param non-empty-string $value
     * @return string
     */
    public function getSeoValue(string $value): string
    {
        $value = (string) iconv('UTF-8', 'ASCII//TRANSLIT', $value);
        $value = strtolower($value);

        return strtolower((string) preg_replace('/[^a-z0-9]+/', '-', $value));
    }

    /**
     * Get Store IDs
     *
     * @return string[]
     */
    public function getStoreIds(): array
    {
        $sql = $this->connection->getConnection()->select()
            ->from($this->connection->getConnection()->getTableName('store'))
            ->where('store_id > 0');

        return $this->connection->getConnection()->fetchCol($sql);
    }
}