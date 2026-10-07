<?php
declare(strict_types=1);
namespace App\Validation;
final class ValidationException extends \RuntimeException
{
    public function __construct(public readonly array $errors) { parent::__construct('Please check the form.'); }
}
