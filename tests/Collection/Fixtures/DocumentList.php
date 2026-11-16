<?php

declare(strict_types=1);

namespace Doctrine\Bundle\DoctrineBundle\Tests\Collection\Fixtures;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Collection\DelegateCollection;
use Traversable;

/** @template-implements DelegateCollection<int, Document> */
final class DocumentList implements DelegateCollection
{
    /** @param Collection<int, Document> $collection */
    public function __construct(
        public readonly TenantContext $tenant,
        private Collection $collection = new ArrayCollection(),
    ) {
    }

    /** @return Traversable<int, Document> */
    public function getIterator(): Traversable
    {
        return $this->collection->getIterator();
    }

    public function add(Document $document): void
    {
        $this->collection->add($document);
    }
}
