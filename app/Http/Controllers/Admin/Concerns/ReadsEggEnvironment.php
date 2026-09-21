<?php

namespace Pterodactyl\Http\Controllers\Admin\Concerns;

use Illuminate\Validation\ValidationException;
use Pterodactyl\Models\Egg;

trait ReadsEggEnvironment
{
    /**
     * The variables that an offer or a plan sets for an egg, written "NAME=value" one per line. They have to be
     * variables of the egg, and every variable that the egg requires without a default value has to be given, because
     * nobody is there to fill it in when the server is made.
     *
     * @return array<string, string>
     *
     * @throws ValidationException
     */
    private function eggEnvironment(string $text, int $eggId): array
    {
        $egg = Egg::query()->with('variables')->findOrFail($eggId);
        $known = $egg->variables->keyBy('env_variable');
        $given = [];
        foreach (preg_split('/\r\n|\r|\n/', $text) ?: [] as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            [$name, $value] = array_pad(explode('=', $line, 2), 2, '');
            $name = trim($name);
            if (!$known->has($name)) {
                throw ValidationException::withMessages(['environment' => '"' . $name . '" is not a variable of this egg.']);
            }
            $given[$name] = trim($value);
        }

        foreach ($known as $name => $variable) {
            $required = str_contains((string) $variable->rules, 'required') && !str_contains((string) $variable->rules, 'nullable');
            if ($required && (string) $variable->default_value === '' && ($given[$name] ?? '') === '') {
                throw ValidationException::withMessages(['environment' => 'The egg requires "' . $name . '" and gives no default value: write it here as ' . $name . '=value.']);
            }
        }

        return $given;
    }
}
