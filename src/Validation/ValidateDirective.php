<?php

namespace Nuwave\Lighthouse\Validation;

use Closure;
use GraphQL\Type\Definition\ResolveInfo;
use Illuminate\Contracts\Validation\Factory as ValidationFactory;
use Nuwave\Lighthouse\Exceptions\ValidationException;
use Nuwave\Lighthouse\Execution\Arguments\Undefined;
use Nuwave\Lighthouse\Schema\Directives\BaseDirective;
use Nuwave\Lighthouse\Schema\Values\FieldValue;
use Nuwave\Lighthouse\Support\Contracts\DefinedDirective;
use Nuwave\Lighthouse\Support\Contracts\FieldMiddleware;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

class ValidateDirective extends BaseDirective implements FieldMiddleware, DefinedDirective
{
    /**
     * @var \Illuminate\Contracts\Validation\Factory
     */
    protected $validationFactory;

    /**
     * @param  \Illuminate\Contracts\Validation\Factory  $validationFactory
     * @return void
     */
    public function __construct(ValidationFactory $validationFactory)
    {
        $this->validationFactory = $validationFactory;
    }

    public static function definition()
    {
        return /** @lang GraphQL */ <<<GRAPHQL
"""
Run validation on a field.
"""
directive @validate on FIELD_DEFINITION
GRAPHQL;
    }

    public static function make(): self
    {
        return app(self::class);
    }

    /**
     * Resolve the field directive.
     *
     * @param  \Nuwave\Lighthouse\Schema\Values\FieldValue  $fieldValue
     * @param  \Closure  $next
     * @return \Nuwave\Lighthouse\Schema\Values\FieldValue
     */
    public function handleField(FieldValue $fieldValue, Closure $next): FieldValue
    {
        $resolver = $fieldValue->getResolver();

        return $next(
            $fieldValue->setResolver(
                function ($root, array $args, GraphQLContext $context, ResolveInfo $resolveInfo) use ($resolver) {
                    $argumentSet = $resolveInfo->argumentSet;
                    $rulesGatherer = new RulesGatherer($argumentSet);

                    $validator = $this->validationFactory
                        ->make(
                            $args,
                            $rulesGatherer->rules,
                            $rulesGatherer->messages,
                            // The presence of those custom attributes ensures we get a GraphQLValidator
                            [
                                'root' => $root,
                                'context' => $context,
                                'resolveInfo' => $resolveInfo,
                            ]
                        );

                    if ($validator->fails()) {
                        $path = implode(
                            '.',
                            $resolveInfo->path
                        );

                        throw new ValidationException("Validation failed for the field [$path].", $validator);
                    }

                    $withoutUndefined = Undefined::removeUndefined($argumentSet);
                    $resolveInfo->argumentSet = $withoutUndefined;

                    return $resolver($root, $args, $context, $resolveInfo);
                }
            )
        );
    }
}
