<?php

declare(strict_types=1);

namespace App\Services;

use App\Entity\AuditLog;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;

final class AuditLogger
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly Security $security,
    ) {}

    public function log(string $action): void
    {
        $user = $this->security->getUser();

        $entry = new AuditLog();
        $entry->setUserId($user?->getId());
        $entry->setAction($action);

        $this->em->persist($entry);
        $this->em->flush();
    }
}
