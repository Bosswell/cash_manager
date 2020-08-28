<?php

namespace App\Controller;

use App\ApiController;
use App\Entity\TransactionType;
use App\Entity\User;
use App\Http\ApiResponse;
use App\Message\CreateTransactionMessage;
use App\Repository\TransactionRepository;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Doctrine\DBAL\Query\QueryBuilder;
use Pagerfanta\Doctrine\DBAL\QueryAdapter;
use Pagerfanta\Pagerfanta;


class TransactionController extends ApiController
{
    /**
     * @Route("/transaction", name="create_transaction", methods={"POST"})
     * @ParamConverter("createTransaction", class="App\Message\CreateTransactionMessage", converter="message_converter")
     */
    public function createAction(CreateTransactionMessage $message)
    {
        $this->transactionFacade->createTransaction($message);

        return new ApiResponse('Transaction has been successfully created', Response::HTTP_CREATED);
    }

    /**
     * @Route("/transaction/summary", name="get_transaction_summary", methods={"GET"})
     */
    public function getTransactionSummary(TransactionRepository $repository)
    {
        $data = $repository->findAllTransactionSummary();

        return new ApiResponse(
            (bool)$data ? 'No results' : 'Found entries',
            Response::HTTP_OK,
            $data
        );
    }

    /**
     * @Route("/transaction/types/list", name="list_transaction_types", methods={"GET"})
     */
    public function listTypesAction()
    {
        $transactionTypes = $this
            ->getDoctrine()
            ->getRepository(TransactionType::class)
            ->findAll()
        ;

        return new ApiResponse('', Response::HTTP_OK, $transactionTypes);
    }

    /**
     * @Route("/transaction/list", name="list_transaction", methods={"GET"})
     */
    public function listTransactionsAction(Request $request, TransactionRepository $repository)
    {
        /** @var User $user */
        $user = $this->getUser();
        $countQueryBuilderModifier = function (QueryBuilder $queryBuilder): void {
            $queryBuilder->select('COUNT(DISTINCT t.id) AS total_results')
                ->setMaxResults(1);
        };

        $qb = $repository->getTransactionListQuery($user->getId(), $request->get('transactionTypeId'));
        $adapter = new QueryAdapter($qb, $countQueryBuilderModifier);
        $pagerfanta = new Pagerfanta($adapter);

        $nbPages = $pagerfanta->getNbPages();
        $nbPage = $request->get('page') ?? 1;

        $pagerfanta->setCurrentPage($nbPage > $nbPages ? $nbPages : $nbPage );
        
        return new ApiResponse('Found entries', Response::HTTP_OK, [
            'pages' => $nbPages,
            'currentPage' => $pagerfanta->getCurrentPage(),
            'hasNextPage' => $pagerfanta->hasNextPage(),
            'results' => $pagerfanta->getCurrentPageResults()
        ]);
    }
}
