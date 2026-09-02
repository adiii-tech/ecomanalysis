<?php

declare(strict_types=1);

namespace App\Domain\AI\Services;

use App\Domain\AI\Tools\MetricTool;
use App\Domain\AI\Tools\ToolRegistry;
use App\Models\Tenant;
use App\Models\User;
use App\Support\WidgetFilters;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * The Ask-AI agent.
 *
 * A manual tool loop rather than the SDK tool runner, because each tool call
 * has to be permission-checked against the asking user and recorded for the
 * transcript — the model's tool choice is never trusted on its own.
 */
class AnalystAgent
{
    public function __construct(
        private readonly ClaudeClient $claude,
        private readonly ToolRegistry $tools,
    ) {}

    /**
     * @param  list<array{role: string, content: mixed}>  $history
     * @return array{answer: string, tool_calls: list<array<string, mixed>>, input_tokens: int, output_tokens: int, model: string}
     */
    public function ask(User $user, Tenant $tenant, string $question, WidgetFilters $filters, array $history = []): array
    {
        $definitions = $this->tools->definitionsFor($user);

        $messages = [...$history, ['role' => 'user', 'content' => $question]];
        $toolCalls = [];
        $inputTokens = 0;
        $outputTokens = 0;

        $client = $this->claude->client();
        $maxIterations = (int) config('ai.max_tool_iterations', 8);

        for ($iteration = 0; $iteration <= $maxIterations; $iteration++) {
            $response = $client->messages->create(
                model: $this->claude->model(),
                maxTokens: $this->claude->maxTokens('chat'),
                system: $this->systemPrompt($tenant, $filters, $user),
                thinking: ['type' => 'adaptive'],
                outputConfig: ['effort' => $this->claude->effort('chat')],
                tools: $definitions,
                messages: $messages,
            );

            $inputTokens += $response->usage->inputTokens ?? 0;
            $outputTokens += $response->usage->outputTokens ?? 0;

            if ($response->stopReason !== 'tool_use') {
                return [
                    'answer' => $this->claude->textOf($response->content),
                    'tool_calls' => $toolCalls,
                    'input_tokens' => $inputTokens,
                    'output_tokens' => $outputTokens,
                    'model' => $this->claude->model(),
                ];
            }

            $results = [];

            foreach ($response->content as $block) {
                if (($block->type ?? null) !== 'tool_use') {
                    continue;
                }

                [$payload, $isError] = $this->execute($user, $block->name, (array) $block->input, $filters);

                $toolCalls[] = [
                    'tool' => $block->name,
                    'input' => $block->input,
                    'ok' => ! $isError,
                ];

                $results[] = [
                    'type' => 'tool_result',
                    'toolUseID' => $block->id,
                    'content' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    'isError' => $isError,
                ];
            }

            $messages[] = ['role' => 'assistant', 'content' => $response->content];
            $messages[] = ['role' => 'user', 'content' => $results];
        }

        // The loop is bounded so a confused model cannot bill forever.
        return [
            'answer' => "I wasn't able to settle on an answer within the tool budget for this question. Try asking about one metric at a time.",
            'tool_calls' => $toolCalls,
            'input_tokens' => $inputTokens,
            'output_tokens' => $outputTokens,
            'model' => $this->claude->model(),
        ];
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array{0: array<string, mixed>, 1: bool}
     */
    private function execute(User $user, string $name, array $input, WidgetFilters $filters): array
    {
        $tool = $this->tools->find($user, $name);

        if (! $tool instanceof MetricTool) {
            // Either hallucinated or gated — same answer either way.
            return [['error' => "No tool named [{$name}] is available to you."], true];
        }

        try {
            return [$tool->run($input, $filters), false];
        } catch (Throwable $e) {
            Log::warning('AI tool failed', ['tool' => $name, 'error' => $e->getMessage()]);

            return [['error' => 'That query failed: '.$e->getMessage()], true];
        }
    }

    private function systemPrompt(Tenant $tenant, WidgetFilters $filters, User $user): string
    {
        $currency = $tenant->currency === 'INR' ? 'Indian rupees, written like ₹1,23,456' : $tenant->currency;

        return <<<PROMPT
        You are the analyst for {$tenant->name}, an Indian D2C brand. You answer questions about their own trading data.

        The single question the product exists to answer is "am I actually making money", so never quote gross revenue on its own. Revenue resolves down a chain: gross sales, minus discounts and cancellations gives invoiced sales; minus customer returns and RTO gives net sales; minus COGS, marketplace fees, logistics, packaging and gateway fees gives contribution margin. Quote the step that actually answers the question.

        RTO means a parcel was returned to origin without ever being delivered. It is not the same as a customer return, and it is the single biggest hidden cost in Indian ecommerce — COD orders RTO far more often than prepaid ones.

        How to answer:
        - Call tools to get real numbers. Never estimate, guess, or extrapolate a figure you were not given.
        - Lead with the answer, then the number that supports it, then what to do about it. Two or three short paragraphs at most.
        - Give a verdict, not just a readout. "Prepaid is 4.4 points more profitable than COD, so push prepaid in the high-RTO states" is useful; "COD margin is 32.2%" alone is not.
        - Money is {$currency}. Percentages to one decimal place.
        - If a tool returns a caveat, respect it and pass it on. Customer metrics are D2C-only because marketplaces anonymise buyers; platform-reported ROAS double counts across networks.
        - If the data cannot answer the question, say so plainly and say what would be needed. Never invent a number to fill the gap.
        - If a tool you need is not available, tell the user this account does not have access to that data rather than guessing around it.

        Unless the user says otherwise, assume they mean the window currently selected on screen: {$filters->period->fromDate()} to {$filters->period->toDate()}, channel "{$filters->channelScope}". Today is {$this->today($tenant)}.
        PROMPT;
    }

    private function today(Tenant $tenant): string
    {
        return now($tenant->timezone)->format('l, d F Y');
    }
}
