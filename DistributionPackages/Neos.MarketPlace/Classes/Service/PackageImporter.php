<?php
declare(strict_types=1);

namespace Neos\MarketPlace\Service;

/*
 * This file is part of the Neos.MarketPlace package.
 *
 * (c) Contributors of the Neos Project - www.neos.io
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

use Neos\ContentRepository\Domain\Model\NodeInterface;
use Neos\Eel\FlowQuery\FlowQuery;
use Neos\Flow\Annotations as Flow;
use Neos\MarketPlace\Domain\Model\Storage;
use Packagist\Api\Result\Package;

/**
 * Package Importer
 *
 * @Flow\Scope("singleton")
 * @api
 */
class PackageImporter
{
    private bool $forceUpdates = false;

    private array $processedPackages = [];

    public function forceUpdates(bool $forceUpdates): void
    {
        $this->forceUpdates = $forceUpdates;
    }

    public function process(Package $package): NodeInterface
    {
        $node = (new PackageConverter($this->forceUpdates))->convert($package);
        $this->processedPackages[$package->getName()] = true;
        return $node;
    }

    /**
     * Remove local package not preset in the processed packages list
     *
     * @throws \Neos\Eel\Exception
     * @throws \Neos\ContentRepository\Exception\NodeException
     * @throws \Neos\MarketPlace\Exception
     */
    public function cleanupPackages(?callable $callback = null): int
    {
        $count = 0;
        $storageNode = (new Storage())->node();
        $query = new FlowQuery([$storageNode]);
        $query = $query->find('[instanceof Neos.MarketPlace:Package]');
        $upstreamPackages = $this->getProcessedPackages();
        foreach ($query as $package) {
            /** @var NodeInterface $package */
            if (in_array($package->getProperty('title'), $upstreamPackages, true)) {
                continue;
            }
            $package->remove();
            if ($callback !== null) {
                $callback($package);
            }
            $this->emitPackageDeleted($package);
            $count++;
        }
        return $count;
    }

    /**
     * Remove vendors without packages
     *
     * @throws \Neos\Eel\Exception
     * @throws \Neos\MarketPlace\Exception
     */
    public function cleanupVendors(?callable $callback = null): int
    {
        $count = 0;
        $storageNode = (new Storage())->node();
        $query = new FlowQuery([$storageNode]);
        $query = $query->find('[instanceof Neos.MarketPlace:Vendor]');
        foreach ($query as $vendor) {
            /** @var NodeInterface $vendor */
            $hasPackageQuery = new FlowQuery([$vendor]);
            $packageCount = $hasPackageQuery->find('[instanceof Neos.MarketPlace:Package]')->count();
            if ($packageCount > 0) {
                continue;
            }
            $vendor->remove();
            if ($callback !== null) {
                $callback($vendor);
            }
            $this->emitVendorDeleted($vendor);
            $count++;
        }
        return $count;
    }

    public function getProcessedPackages(): array
    {
        return array_keys(array_filter($this->processedPackages));
    }

    public function getProcessedPackagesCount(): int
    {
        return count($this->getProcessedPackages());
    }

    /**
     * Signals that a package node was deleted.
     *
     * @Flow\Signal
     * @param NodeInterface $node
     * @return void
     */
    protected function emitPackageDeleted(NodeInterface $node): void
    {
    }

    /**
     * Signals that a package node was deleted.
     *
     * @Flow\Signal
     * @param NodeInterface $node
     * @return void
     */
    protected function emitVendorDeleted(NodeInterface $node): void
    {
    }
}
