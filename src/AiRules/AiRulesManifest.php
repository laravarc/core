<?php

declare(strict_types=1);

namespace Laravarc\Core\AiRules;

final readonly class AiRulesManifest
{
    /**
     * @param  list<AiRuleEntry>  $rules
     */
    public function __construct(
        public string $package,
        public string $rulesVersion,
        public string $bootstrapReadme,
        public array $rules,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data, string $packagePath): self
    {
        $rules = [];

        foreach ($data['rules'] ?? [] as $rule) {
            if (! is_array($rule)) {
                continue;
            }

            $rules[] = AiRuleEntry::fromArray($rule, $packagePath);
        }

        usort($rules, static fn (AiRuleEntry $a, AiRuleEntry $b): int => $a->priority <=> $b->priority);

        return new self(
            package: (string) ($data['package'] ?? ''),
            rulesVersion: (string) ($data['rules_version'] ?? ''),
            bootstrapReadme: (string) ($data['bootstrap_readme'] ?? 'README.md'),
            rules: $rules,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(string $packagePath): array
    {
        return [
            'package' => $this->package,
            'rules_version' => $this->rulesVersion,
            'bootstrap_readme' => $this->bootstrapReadme,
            'package_path' => $packagePath,
            'rules' => array_map(
                static fn (AiRuleEntry $rule): array => $rule->toArray(),
                $this->rules,
            ),
        ];
    }
}
