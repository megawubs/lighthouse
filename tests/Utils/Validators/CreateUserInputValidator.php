<?php


namespace Tests\Utils\Validators;


use Illuminate\Validation\Rule;
use Nuwave\Lighthouse\Execution\InputValidator;

/**
 * Class CreateUserInputValidator
 */
class CreateUserInputValidator extends InputValidator
{
    public function rules(): array
    {
        return [
            'name'     => ['min:6'],
            'email'    => ['required', 'email', Rule::unique('users', 'email')],
            'password' => ['required'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.min' => 'Name validation message.',
        ];
    }
}
