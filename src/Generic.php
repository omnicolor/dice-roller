<?php

declare(strict_types=1);

namespace RollBot;

/**
 * Generic dice roller.
 */
class Generic implements DiscordInterface, SlackInterface
{
    /**
     * Number of dice to roll.
     * @var int
     */
    protected int $dice;

    /**
     * Optional text describing what the roll was for.
     * @var string
     */
    protected string $forText = '';

    /**
     * Modifier for the roll.
     * @var int
     */
    protected int $modifier = 0;

    /**
     * Optional text for a plus or minus modifier.
     * @var string
     */
    protected string $modifyingText = '';

    /**
     * Number of potential pips per die.
     * @var int
     */
    protected int $pips;

    /**
     * Remaining text in the command.
     * @var string
     */
    protected string $remaining;

    /**
     * Collection of what the user actually rolled.
     * @var int[]
     */
    protected array $rolls = [];

    /**
     * User that threw the dice.
     * @var string
     */
    protected string $userId;

    /**
     * Constructor.
     * @param string $text
     * @param string $userId
     * @throws \RuntimeException
     */
    public function __construct(string $text, string $userId)
    {
        $this->userId = $userId;

        $matches = [];
        preg_match('/(\d+)d(\d+)(.*)/i', $text, $matches);

        // Remove the pattern that matched.
        array_shift($matches);

        $this->dice = (int)array_shift($matches);
        if ($this->dice > 100) {
            throw new \RuntimeException('LOL. No, just no.');
        }
        $this->pips = (int)array_shift($matches);
        $this->remaining = trim(array_shift($matches));

        $this->parseModifier();

        if ($this->remaining) {
            $this->forText = sprintf(', for "%s"', $this->remaining);
        }

        for ($i = 0; $i < $this->dice; $i++) {
            $this->rolls[] = random_int(1, $this->pips);
        }
    }

    /**
     * See if the remaining text is a plus or minus modifier.
     */
    protected function parseModifier(): void
    {
        $matches = [];
        if (!preg_match('/^([+-]\d+) ?(.*)?/', $this->remaining, $matches)) {
            return;
        }
        if (!is_numeric($matches[1])) {
            return;
        }
        $this->modifier = (int)$matches[1];
        $this->remaining = $matches[2];
        if ($this->modifier === 0) {
            return;
        }
        if ($this->modifier > 0) {
            $this->modifyingText = sprintf(' adding %d', abs($this->modifier));
            return;
        }
        $this->modifyingText = sprintf(' subtracting %d', abs($this->modifier));
    }

    /**
     * Create a Slack response for the roll.
     * @return Response
     */
    public function getSlackResponse(): Response
    {
        $response = new Response();
        $response->text = sprintf(
            '<@%s> rolled %d %d-sided %s%s%s',
            $this->userId,
            $this->dice,
            $this->pips,
            $this->dice === 1 ? 'die' : 'dice',
            $this->modifyingText,
            $this->forText
        );
        $response->attachments[] = [
            'text' => array_sum($this->rolls) + $this->modifier,
            'footer' => implode(' ', $this->rolls),
        ];
        $response->toChannel = true;
        return $response;
    }

    /**
     * Format the dice roll for Discord.
     * @return string
     */
    public function getDiscordResponse(): string
    {
        return sprintf(
            '%s rolled %d %d-sided %s%s%s' . PHP_EOL . 'Total: **%s**' . PHP_EOL
            . 'Rolls: %s',
            $this->userId,
            $this->dice,
            $this->pips,
            $this->dice === 1 ? 'die' : 'dice',
            $this->modifyingText,
            $this->forText,
            array_sum($this->rolls) + $this->modifier,
            implode(' ', $this->rolls)
        );
    }

    /**
     * Return the roll formatted for Slack.
     * @deprecated
     * @return string
     */
    public function __toString(): string
    {
        $response = new Response();
        $response->text = sprintf(
            '<@%s> rolled %d %d-sided dice%s%s',
            $this->userId,
            $this->dice,
            $this->pips,
            $this->modifyingText,
            $this->forText
        );
        $response->attachments[] = [
            'text' => array_sum($this->rolls) + $this->modifier,
            'footer' => implode(' ', $this->rolls) . ' (deprecated)',
        ];
        $response->toChannel = true;
        return (string)$response;
    }
}
