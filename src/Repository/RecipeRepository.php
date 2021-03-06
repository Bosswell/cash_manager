<?php

namespace App\Repository;

use App\Entity\Recipe;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Query\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method Recipe|null find($id, $lockMode = null, $lockVersion = null)
 * @method Recipe|null findOneBy(array $criteria, array $orderBy = null)
 * @method Recipe[]    findAll()
 * @method Recipe[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class RecipeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Recipe::class);
    }

    public function getRecipesListQuery(int $userId, ?string $searchBy, string $orderBy = 'r.id', string $orderDirection = 'DESC'): QueryBuilder
    {
        $connection = $this->getEntityManager()->getConnection();

        $qb = $connection->createQueryBuilder()
            ->select('r.id, r.name, r.created_at')
            ->from('recipe', 'r')
            ->innerJoin('r', 'user', 'u', 'u.id = r.user_id')
            ->where('u.id = :id')
            ->andWhere('r.is_deleted = 0')
            ->setParameter(':id', $userId);

        if (!is_null($searchBy)) {
            $qb->andWhere('r.name LIKE :searchBy');
            $qb->setParameter(':searchBy', '%' . $searchBy . '%');
        }

        $qb->orderBy($orderBy, $orderDirection);

        return $qb;
    }
}
