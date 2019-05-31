<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Seo;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Uuid\Uuid;

class SeoResolver implements SeoResolverInterface
{
    /**
     * @var Connection
     */
    private $connection;

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    public function resolveSeoPath(string $languageId, string $salesChannelId, string $pathInfo): ?array
    {
        $seoPathInfo = ltrim($pathInfo, '/');
        if ($seoPathInfo === '') {
            return ['pathInfo' => '/', 'isCanonical' => false];
        }

        $seoPath = $this->connection->createQueryBuilder()
            ->select('id', 'path_info pathInfo', 'is_canonical isCanonical')
            ->from('seo_url')
            ->where('language_id = :language_id')
            ->andWhere('(sales_channel_id = :sales_channel_id OR sales_channel_id IS NULL)')
            ->andWhere('seo_path_info = :seoPath')
            ->andWhere('is_valid = 1')
            ->orderBy('seo_path_info')
            ->addOrderBy('sales_channel_id IS NULL') // sales_channel_specific comes first
            ->setMaxResults(1)
            ->setParameter('language_id', Uuid::fromHexToBytes($languageId))
            ->setParameter('sales_channel_id', Uuid::fromHexToBytes($salesChannelId))
            ->setParameter('seoPath', $seoPathInfo)
            ->execute()
            ->fetch();

        $seoPath = $seoPath !== false
            ? $seoPath
            : ['pathInfo' => $seoPathInfo, 'isCanonical' => false];

        if (!$seoPath['isCanonical']) {
            $query = $this->connection->createQueryBuilder()
                ->select('path_info pathInfo', 'seo_path_info seoPathInfo')
                ->from('seo_url')
                ->where('language_id = :language_id')
                ->andWhere('sales_channel_id = :sales_channel_id')
                ->andWhere('id != :id')
                ->andWhere('path_info = :pathInfo')
                ->andWhere('is_valid = 1')
                ->andWhere('is_canonical = 1')
                ->setMaxResults(1)
                ->setParameter('language_id', Uuid::fromHexToBytes($languageId))
                ->setParameter('sales_channel_id', Uuid::fromHexToBytes($salesChannelId))
                ->setParameter('id', $seoPath['id'] ?? '')
                ->setParameter('pathInfo', '/' . trim($seoPath['pathInfo'], '/'));

            $canonical = $query->execute()->fetch();
            if ($canonical) {
                $seoPath['canonicalPathInfo'] = '/' . trim($canonical['seoPathInfo'], '/');
            }
        }

        $seoPath['pathInfo'] = '/' . trim($seoPath['pathInfo'], '/') . '/';

        return $seoPath;
    }
}
