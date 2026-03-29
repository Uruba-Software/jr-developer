<?php

namespace App\Services;

use App\Contracts\ToolRunner;
use App\Exceptions\ToolNotFoundException;

class ToolRegistry
{
    /** @var ToolRunner[] */
    private array $runners = [];

    public function register(ToolRunner $runner): void
    {
        $this->runners[] = $runner;
    }

    /**
     * @param  ToolRunner[]  $runners
     */
    public function registerMany(array $runners): void
    {
        foreach ($runners as $runner) {
            $this->register($runner);
        }
    }

    public function find(string $tool): ToolRunner
    {
        foreach ($this->runners as $runner) {
            if ($runner->supports($tool)) {
                return $runner;
            }
        }

        throw new ToolNotFoundException($tool);
    }

    public function has(string $tool): bool
    {
        foreach ($this->runners as $runner) {
            if ($runner->supports($tool)) {
                return true;
            }
        }

        return false;
    }

    /** @return ToolRunner[] */
    public function all(): array
    {
        return $this->runners;
    }
}
