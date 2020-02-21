<?php

namespace Nuwave\Lighthouse\Support\Contracts;

interface ProvidesRules
{
    /**
     * Return validation rules for multiple arguments.
     *
     * @return array
     */
    public function rules(): array;

    /**
     * Return custom messages for the rules.
     *
     * @return array
     */
    public function messages(): array;
}
