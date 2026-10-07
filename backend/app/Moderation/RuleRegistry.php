<?php

namespace App\Moderation;

use App\Moderation\Rules\Rule;

class RuleRegistry
{
    /** @var array<string, Rule> */
    private array $rules = [];

    /**
     * @param  list<class-string<Rule>>  $classes
     */
    public function __construct(array $classes)
    {
        foreach ($classes as $class) {
            $rule = app($class);
            $this->rules[$rule->key()] = $rule;
        }
    }

    /**
     * @return array<string, Rule>
     */
    public function all(): array
    {
        return $this->rules;
    }

    public function get(string $key): ?Rule
    {
        return $this->rules[$key] ?? null;
    }
}
