<?php

declare(strict_types=1);

namespace App\Tests\Unit\Dto;

use App\Dto\CreateTransactionRequest;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class CreateTransactionRequestValidationTest extends TestCase
{
    private ValidatorInterface $validator;

    protected function setUp(): void
    {
        $this->validator = Validation::createValidatorBuilder()
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

        $violations = $this->validator->validate($dto);

        self::assertCount(0, $violations);
    }

    #[Test]
    public function missingFieldsProduceViolations(): void
    {
        $dto = new CreateTransactionRequest();

        $violations = $this->validator->validate($dto);

        self::assertGreaterThan(0, $violations->count());
    }

    #[Test]
    public function sameFromAndToAccountsFailsValidation(): void
    {
        $dto = new CreateTransactionRequest();
        $dto->from_account_id = 5;
        $dto->to_account_id = 5;
        $dto->amount = 1.0;

        $violations = $this->validator->validate($dto);

        self::assertGreaterThan(0, $violations->count());
    }
}
