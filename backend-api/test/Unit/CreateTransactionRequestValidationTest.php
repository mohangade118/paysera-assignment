<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Dto\CreateTransactionRequest;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Validation;

final class CreateTransactionRequestValidationTest extends TestCase
{
    private function validator(): \Symfony\Component\Validator\Validator\ValidatorInterface
    {
        return Validation::createValidatorBuilder()
            ->enableAttributeMapping()
            ->getValidator();
    }

    #[Test]
    public function validRequestHasNoViolations(): void
    {
        $dto = new CreateTransactionRequest();
        $dto->from_account_id = 1;
        $dto->to_account_id = 2;
        $dto->amount = 10.5;
        $dto->note = 'x';
        $dto->receipt = 'r';

        $violations = $this->validator()->validate($dto);
        self::assertCount(0, $violations);
    }

    #[Test]
    #[DataProvider('invalidAmountProvider')]
    public function amountMustBePositive(mixed $amount, int $expectedCount): void
    {
        $dto = new CreateTransactionRequest();
        $dto->from_account_id = 1;
        $dto->to_account_id = 2;
        $dto->amount = $amount;

        $violations = $this->validator()->validate($dto);
        self::assertCount($expectedCount, $violations);
    }

    /**
     * @return iterable<string, array{0: mixed, 1: int}>
     */
    public static function invalidAmountProvider(): iterable
    {
        yield 'zero' => [0.0, 1];
        yield 'negative' => [-1.0, 1];
    }

    #[Test]
    public function fromAndToAccountsMustDiffer(): void
    {
        $dto = new CreateTransactionRequest();
        $dto->from_account_id = 5;
        $dto->to_account_id = 5;
        $dto->amount = 1.0;

        $violations = $this->validator()->validate($dto);
        self::assertGreaterThan(0, count($violations));
    }
}
