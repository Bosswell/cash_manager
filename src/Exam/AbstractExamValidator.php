<?php


namespace App\Exam;

use App\Message\Exam\Model\UserQuestionSnippet;


abstract class AbstractExamValidator
{
    const STANDARD_MODE = 'standard';
    const SUBTRACTION_MODE = 'subtraction';

    /** @var UserQuestionSnippet[] */
    protected array $userQuestionsSnippets;
    protected array $correctOptions;

    public function setUserQuestionsSnippets(array $userQuestionsSnippets): self
    {
        $this->userQuestionsSnippets = $userQuestionsSnippets;

        return $this;
    }

    public function setCorrectOptions(array $correctOptions): self
    {
        $this->correctOptions = $correctOptions;

        return $this;
    }

    abstract function validate(): ExamResult;
    abstract static function getMode(): string;
}