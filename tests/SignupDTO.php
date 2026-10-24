<?php

namespace Io\I18n\Tests;

use Io\Attributes\{Email, NotBlank};
use Io\FromArray;

final class SignupDTO
{
    use FromArray;

    #[NotBlank]
    #[Email]
    public ?string $email = null;
}
