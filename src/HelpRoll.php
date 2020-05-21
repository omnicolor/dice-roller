<?php

declare(strict_types=1);

namespace RollBot;

/**
 * Handle a user asking for generic help.
 */
class HelpRoll implements ConfigurableInterface, DiscordInterface, SlackInterface
{
    /**
     * @var array
     */
    protected $config;

    /**
     * Return help information formatted for Slack.
     * @return Response
     */
    public function getSlackResponse(): Response
    {
        $response = new Response();
        $response->attachments[] = [
            'title' => 'About RollBot',
            'text' => sprintf(
                'RollBot is a Slack/Discord bot that lets you roll dice '
                    . 'appropriate for various RPG systems. For example, if '
                    . 'you are playing The Expanse, it will roll three dice, '
                    . 'marking one of them as the "drama die", adding up the '
                    . 'result with the number you give for your '
                    . 'attribute+focus score, and return the result along with '
                    . 'any stunt points.' . PHP_EOL . PHP_EOL
                    . 'If your game uses <%s|Commlink> as well, links in the '
                    . 'app will automatically roll in Slack, and changes made '
                    . 'to your character via Slack will appear in Commlink.',
                $this->config['web']
            ),
        ];
        $response->attachments[] = [
            'title' => 'Supported Systems',
            'text' => 'This channel is not registered for any of the '
                . 'systems.' . PHP_EOL
                . '· The Expanse' . PHP_EOL
                . '· Shadowrun Anarchy' . PHP_EOL
                . '· Shadowrun 5th Edition' . PHP_EOL
                . '· Shadowrun 6th Edition' . PHP_EOL
                . '· Star Trek Adventures' . PHP_EOL,
        ];
        $response->attachments[] = [
            'title' => 'Commands For Unregistered Channels',
            'text' => '`help` - Show help' . PHP_EOL
                . '`XdY[+-M] [T]` - Roll X dice with Y pips, adding or '
                . 'subtracting M from the total, with optional T text',
        ];
        return $response;
    }

    /**
     * Return help information formatted for Discord.
     * @return string
     */
    public function getDiscordResponse(): string
    {
        return sprintf(
            'RollBot is a Slack/Discord bot that lets you roll dice '
            . 'appropriate for various RPG systems. For example, if '
            . 'you are playing The Expanse, it will roll three dice, '
            . 'marking one of them as the "drama die", adding up the '
            . 'result with the number you give for your '
            . 'attribute+focus score, and return the result along with '
            . 'any stunt points.' . PHP_EOL . PHP_EOL
            . 'If your game uses Commlink (%s) as well, links in the '
            . 'web app will automatically roll in Slack and Discord, and '
            . 'changes made to your character via Slack or Discord will appear '
            . 'in the web app.' . PHP_EOL . PHP_EOL
            . '**Supported Systems**' . PHP_EOL
            . 'This channel is not registered for any of the systems.' . PHP_EOL
            . '· The Expanse' . PHP_EOL
            . '· Shadowrun Anarchy' . PHP_EOL
            . '· Shadowrun 5th Edition' . PHP_EOL
            . '· Shadowrun 6th Edition' . PHP_EOL
            . '· Star Trek Adventures' . PHP_EOL . PHP_EOL
            . '**Commands For Unregistered Channels**' . PHP_EOL
            . '`help` - Show help' . PHP_EOL
            . '`XdY[+-M] [T]` - Roll X dice with Y pips, adding or '
            . 'subtracting M from the total, with optional T text',
            $this->config['web']
        );
    }

    /**
     * Return help formatted for Slack.
     * @deprecated
     * @return string
     */
    public function __toString(): string
    {
        $response = new Response();
        $response->text = 'RollBot is a dice roller and character manager (deprecated)';
        $response->attachments[] = [
            'title' => 'About RollBot',
            'text' => sprintf(
                'RollBot is a Slack bot that lets you roll dice '
                    . 'appropriate for various RPG systems. For example, if '
                    . 'you are playing The Expanse, it will roll three dice, '
                    . 'marking one of them as the "drama die", adding up the '
                    . 'result with the number you give for your '
                    . 'attribute+focus score, and return the result along with '
                    . 'any stunt points.' . PHP_EOL . PHP_EOL
                    . 'If your game uses <%s|Commlink> as well, links in the '
                    . 'app will automatically roll in Slack, and changes made '
                    . 'to your character via Slack will appear in Commlink.',
                $this->config['web']
            ),
        ];
        $response->attachments[] = [
            'title' => 'Supported Systems',
            'text' => 'The current channel is not registered for any of the '
                . 'systems.' . PHP_EOL
                . '· Shadowrun Anarchy' . PHP_EOL
                . '· Shadowrun 5th Edition' . PHP_EOL
                . '· Shadowrun 6th Edition' . PHP_EOL
                . '· The Expanse',
        ];
        $response->attachments[] = [
            'title' => 'Commands For Unregistered Channels',
            'text' => '`help` - Show help' . PHP_EOL
                . '`register` - Register this channel' . PHP_EOL,
        ];
        return (string)$response;
    }

    /**
     * Set the configuration parameters for the object.
     * @param array $config
     * @return HelpRoll
     */
    public function setConfig(array $config): ConfigurableInterface
    {
        $this->config = $config;
        return $this;
    }
}
