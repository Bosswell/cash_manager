<?php

namespace App\Repository;

use App\Entity\ExamHistory;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method ExamHistory|null find($id, $lockMode = null, $lockVersion = null)
 * @method ExamHistory|null findOneBy(array $criteria, array $orderBy = null)
 * @method ExamHistory[]    findAll()
 * @method ExamHistory[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ExamHistoryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ExamHistory::class);
    }
}
