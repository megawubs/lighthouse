<?php


namespace Tests\Integration\Schema\Directives;


use Illuminate\Validation\Rule;
use Nuwave\Lighthouse\Execution\InputTypeValidator;
use Tests\DBTestCase;
use Tests\Utils\Models\Company;
use Tests\Utils\Models\User;

/**
 * Class ValidatorDirectiveTest
 */
class ValidatorDirectiveTest extends DBTestCase
{

    public function testInputTypeValidator()
    {
        config()->set('lighthouse.namespaces.validators', [__NAMESPACE__]);
        $this->schema .= /** @lang GraphQL */
            '
        type User {
          id: ID!
          name: String!
          email: String!
        }

        input CreateUserInput @validate {
          name: String!
          email: String!
          password: String!
        }

        type Mutation {
          createUser(input: CreateUserInput! @spread): User @create
        }
        ';

        $response = $this->graphQL(
        /** @lang GraphQL */ '
                mutation ($input: CreateUserInput!){
                  createUser(input: $input){
                   email
                }
              }
                ',
                             [
                                 'input' => [
                                     'name'     => 'User',
                                     'email'    => 'user@company.test',
                                     'password' => 'supersecret',
                                 ]
                             ]
        );

        $response->assertJson([
                                  'data' => [
                                      'createUser' => [
                                          'email' => 'user@company.test'
                                      ]
                                  ]
                              ]);

    }

    /** @test */
    public function testNestedInputTypeValidator()
    {
        $this->schema .= /** @lang GraphQL */
            '
        type Company {
          id: ID!
          name: String!
        }

        type User {
          id: ID!
          name: String!
          email: String!
          company: Company! @belongsTo
        }

        input UpdateUserInput @validate(validator: "Tests\\\\Integration\\\\Schema\\\\Directives\\\\UpdateUserInputValidator") {
          id: ID!
          name: String
          email: String
          password: String
          company: ManageCompanyRelation
        }

        input ManageCompanyRelation {
          update: UpdateCompanyInput
        }

        input UpdateCompanyInput @validate(validator: "Tests\\\\Integration\\\\Schema\\\\Directives\\\\UpdateCompanyInputValidator") {
          id: ID!
          name: String!
        }

        type Mutation {
          updateUser(input: UpdateUserInput! @spread): User @update
        }
        ';
        $company = factory(Company::class)->create(['name' => 'The Company']);
        $user = factory(User::class)->create(['company_id' => $company->id]);

        $response = $this->graphQL(/** @lang GraphQL */ '
        mutation ($input: UpdateUserInput!){
          updateUser(input: $input){
            company {
              name
            }
          }
        }
        ', [
            'input' => [
                'id'      => $user->id,
                'company' => [
                    'update' => [
                        'id'   => $company->id,
                        'name' => 'The Company'
                    ]
                ]
            ]
        ]);

        $response->assertJson([
                                  'data' => [
                                      'updateUser' => [
                                          'company' => [
                                              'name' => 'The Company'
                                          ]
                                      ]
                                  ]
                              ]);
    }

    public function testValidationMessages()
    {
        config()->set('lighthouse.namespaces.validators', [__NAMESPACE__]);
        $this->schema .= /** @lang GraphQL */
            '
        type User {
          id: ID!
          name: String!
          email: String!
        }

        input CreateUserInput @validate {
          name: String!
          email: String!
          password: String!
        }

        type Mutation {
          createUser(input: CreateUserInput! @spread): User @create
        }
        ';

        $response = $this->graphQL(
        /** @lang GraphQL */ '
                mutation ($input: CreateUserInput!){
                  createUser(input: $input){
                   email
                }
              }
                ',
                             [
                                 'input' => [
                                     'name'     => 'short',
                                     'email'    => 'user@company.test',
                                     'password' => 'supersecret',
                                 ]
                             ]
        );

        $this->assertValidationError($response, 'input.name', 'name validation message');

    }
}

class CreateUserInputValidator extends InputTypeValidator
{

    public function rules(): array
    {
        return [
            'name'     => ['min:6'],
            'email'    => ['required', 'email', Rule::unique('users', 'email')],
            'password' => ['required']
        ];
    }

    public function messages(): array
    {
        return [
            'name.min' => 'Name validation message.',
        ];
    }
}

class UpdateUserInputValidator extends InputTypeValidator
{

    function rules(): array
    {
        $user = $this->model(User::class);

        return [
            'email' => [
                'email',
                Rule::unique('users', 'email')->ignore($user)
            ],
        ];
    }

    function messages(): array
    {
        return [];
    }
}

class UpdateCompanyInputValidator extends InputTypeValidator
{

    public function rules(): array
    {
        $company = $this->model(Company::class);

        return [
            'name' => [Rule::unique('companies', 'name')->ignore($company)],
        ];
    }

    public function messages(): array
    {
        return [];
    }
}
