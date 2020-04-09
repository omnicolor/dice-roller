<?php

declare(strict_types=1);

namespace RollBot;

/**
 * Handle a user asking for generic help.
 */
class HelpRoll implements ConfigurableInterface
{
    /**
     * @var array
     */
    protected $config;

    /**
     * Return help formatted for Slack.
     */
    public function __toString(): string
    {
        $response = new Response();
        $response->text = 'RollBot is a dice roller and character manager';
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
