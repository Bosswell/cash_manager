<?php

namespace App\Service;

use App\ApiException;
use App\Entity\Transaction;
use App\Entity\TransactionType;
use App\Message\CreateTransactionMessage;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;


class TransactionFacade
{
    private EntityManagerInterface $em;
    private ObjectValidator $validator;
    private ?TokenInterface $token;

    public function __construct(EntityManagerInterface $entityManager, ObjectValidator $validator, ContainerInterface $container)
    {
        $this->em = $entityManager;
        $this->validator = $validator;
        /** @var TokenStorageInterface $tokenStorage */
        $tokenStorage = $container->get('security.token_storage');
        $this->token = $tokenStorage ? $tokenStorage->getToken() : null;
    }

    /**
     * @param CreateTransactionMessage $message
     * @throws ApiException
     */
    public function createTransaction(CreateTransactionMessage $message): void
    {
        /** @var TransactionType|null $transactionType */
        $transactionType = $this->em
            ->getRepository(TransactionType::class)
            ->find($message->getTransactionTypeId());

        if (is_null($transactionType)) {
            throw ApiException::entityNotFound(
                $message->getTransactionTypeId(),
                ['Invalid transaction type value']
            );
        }
        
        $transaction = new Transaction(
            $message->getAmount(),
            $message->getDescription(),
            $transactionType,
            $this->token->getUser()
        );

        $this->validator->validate($transaction);
        $this->em->persist($transaction);
        $this->em->flush();
    }
}