<?php

namespace App\Exam;

use App\ApiException;
use App\Entity\Exam;
use App\Entity\ExamHistory;
use App\Message\Exam\StartExamMessage;
use App\Message\Exam\ValidateExamMessage;
use App\Repository\ExamHistoryRepository;
use App\Repository\ExamRepository;
use App\Service\ObjectValidator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;


class ExamFacade
{
    private ExamRepository $examRepository;
    private EntityManagerInterface $entityManager;
    private ExamHistoryRepository $historyRepository;
    private Serializer $serializer;
    private ObjectValidator $validator;
    private ExamValidatorContainer $validatorContainer;

    public function __construct(
        ExamRepository $examRepository,
        EntityManagerInterface $entityManager,
        ExamHistoryRepository $historyRepository,
        Serializer $serializer,
        ObjectValidator $validator,
        ExamValidatorContainer $validatorContainer
    ) {
        $this->examRepository = $examRepository;
        $this->entityManager = $entityManager;
        $this->historyRepository = $historyRepository;
        $this->serializer = $serializer;
        $this->validator = $validator;
        $this->validatorContainer = $validatorContainer;
    }

    /**
     * @throws ApiException
     */
    public function validateExam(ValidateExamMessage $message): ExamResult
    {
        /** @var ExamHistory|null $history */
        $history = $this->historyRepository->find($message->getHistoryId());

        $this->handleValidationExceptions($history, $message->getExamId());

        $normalizer = new CorrectOptionsNormalizer();
        $correctOptions = $normalizer->normalizeArray(
            $this->examRepository->getCorrectOptions($message->getExamId())
        );

        $result = $this->validatorContainer
            ->getValidator($history->getMode())
            ->setCorrectOptions($correctOptions)
            ->setUserQuestionsSnippets($message->getUserQuestionsSnippets())
            ->validate();

        $result->setCorrectOptions($correctOptions);

        $history
            ->setResult($result->toArray())
            ->setSnippet($message->getUserQuestionsSnippets())
            ->setPercentage($result->getPercentage())
            ->finish();

        $this->entityManager->flush();

        return $result;
    }

    /**
     * @param StartExamMessage $message
     * @return ExamHistory
     * @throws ApiException
     */
    public function startExam(StartExamMessage $message): ExamHistory
    {
        $exam = $this->examRepository->findOneBy([
            'id' => $message->getExamId(),
            'code' => $message->getCode()
        ]);

        $this->handleExamStartExceptions($exam);

        try {
            $normalizedExam = $this->serializer->normalize($exam, null, [
                'groups' => 'default',
                ObjectNormalizer::ENABLE_MAX_DEPTH => true
            ]);
        } catch (\Throwable $ex) {
            throw new ApiException($ex->getMessage(), $ex->getCode());
        }

        $history = new ExamHistory(
            $exam,
            $message->getUserId(),
            $message->getUsername(),
            $message->getUserNumber(),
            $normalizedExam,
            $exam->getMode(),
            $message->getUserGroup()
        );
        $this->validator->validate($history);
        $this->entityManager->persist($history);
        $this->entityManager->flush();

        return $history;
    }

    /**
     * @throws ApiException
     */
    public function checkExamValidity(int $examId): void
    {
        $validityInfo = $this->examRepository->getExamValidityInfo($examId);

        if (empty($validityInfo)) {
            throw new ApiException( 'Exam does not contain questions.', 0);
        }

        foreach ($validityInfo as [$totalValidOptions, $questionId]) {
            if ($totalValidOptions) {
                continue;
            }

            throw new ApiException(
                sprintf('Exam contain invalid questions. Question [ id = %s ] does not contain correct options.', $questionId),
                0
            );
        }
    }

    /**
     * @throws ApiException
     */
    private function handleExamStartExceptions(?Exam $exam): void
    {
        if (is_null($exam) || $exam->isDeleted()) {
            throw new ApiException('Exam has not been found', Response::HTTP_NOT_FOUND);
        }

        if (!$exam->isAvailable()) {
            throw new ApiException('Exam is not available', Response::HTTP_NOT_FOUND);
        }

        if ($exam->getQuestions()->isEmpty()) {
            throw new ApiException('Exam does not contain any questions', Response::HTTP_NOT_FOUND);
        }

        $this->checkExamValidity($exam->getId());
    }

    /**
     * @throws ApiException
     */
    private function handleValidationExceptions(?ExamHistory $history, int $examId): void
    {
        if (is_null($history)) {
            throw new ApiException('You are trying to validate exam which does`t exist', Response::HTTP_BAD_REQUEST);
        }

        if (!$history->isActive()) {
            throw new ApiException('Exam has already been validated', Response::HTTP_BAD_REQUEST);
        }

        if (!$examId) {
            throw new ApiException('Exam is not specified', Response::HTTP_NOT_FOUND);
        }
    }
}