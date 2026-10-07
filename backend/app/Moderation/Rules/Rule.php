<?php

namespace App\Moderation\Rules;

use App\Moderation\Action;
use App\Moderation\MessageText;
use Illuminate\Validation\Rule as ValidationRule;

/**
 * Правило модерации. Настройки описываются полями (fields), по ним строятся значения по умолчанию,
 * валидация и форма в админке.
 */
abstract class Rule
{
    /** Уникальный ключ правила, хранится в БД */
    abstract public function key(): string;

    /**
     * Поля настроек, специфичные для правила.
     *
     * @return list<array{name: string, type: 'list'|'select'|'number', default: mixed, options?: list<string>, min?: int, max?: int}>
     */
    abstract protected function ruleFields(): array;

    /**
     * Возвращает причину нарушения (для журнала) или null, если сообщение в порядке.
     */
    abstract public function check(MessageText $message, array $settings): ?string;

    /**
     * Все поля: специфичные + общие (действие при нарушении).
     */
    public function fields(): array
    {
        return [
            ...$this->ruleFields(),
            ['name' => 'action', 'type' => 'select', 'options' => array_column(Action::cases(), 'value'), 'default' => Action::Delete->value],
            ['name' => 'mute_minutes', 'type' => 'number', 'min' => 1, 'max' => 10080, 'default' => 60],
        ];
    }

    public function defaults(): array
    {
        return array_column($this->fields(), 'default', 'name');
    }

    /**
     * Настройки из БД, дополненные значениями по умолчанию.
     */
    public function settings(array $stored): array
    {
        return array_intersect_key([...$this->defaults(), ...$stored], $this->defaults());
    }

    /**
     * Правила валидации настроек для запроса из админки (ключи с префиксом settings.).
     */
    public function validationRules(): array
    {
        $rules = [];

        foreach ($this->fields() as $field) {
            $key = "settings.{$field['name']}";

            match ($field['type']) {
                'list' => [
                    $rules[$key] = ['present', 'array', 'max:500'],
                    $rules["$key.*"] = ['string', 'min:1', 'max:200'],
                ],
                'select' => $rules[$key] = ['required', ValidationRule::in($field['options'])],
                'number' => $rules[$key] = ['required', 'integer', "min:{$field['min']}", "max:{$field['max']}"],
            };
        }

        return $rules;
    }
}
