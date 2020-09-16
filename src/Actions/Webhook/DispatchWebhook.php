<?php /** @noinspection PhpUndefinedMethodInspection */

namespace Leeovery\MailcoachApi\Actions\Webhook;

use Illuminate\Support\Str;
use Spatie\WebhookServer\WebhookCall;
use Leeovery\MailcoachApi\Models\Webhook;
use Leeovery\MailcoachApi\Support\Triggers;

class DispatchWebhook
{
    private Triggers $triggers;

    public function __construct(Triggers $triggers)
    {
        $this->triggers = $triggers;
    }

    public function execute($eventName, $eventPayload)
    {
        if (! $this->triggers->hasEvent($eventName)) {
            return;
        }

        $triggerKey = $this->triggers->getTriggerKey($eventName);
        $property = array_keys(get_object_vars($eventPayload))[0] ?? null;

        $payload_event = Str::replaceFirst('Spatie\\Mailcoach\\Events\\', '', $eventName);
        $payload_payload = $property ? optional($eventPayload->$property)->toArray() : null;

        if( array_key_exists('subscriber_id', $payload_payload)){
            $payload_payload = array_merge( $payload_payload, ["subscriber_email"=> Subscriber::find($payload_payload['subscriber_id'])->email ]);
        }

        $payload = [
            'event'   => $payload_event,
            'payload' => $payload_payload,
        ];

        Webhook::query()
               ->withTrigger($triggerKey)
               ->isActive()
               ->each(function (Webhook $webhook) use ($payload) {
                   WebhookCall::create()
                              ->url($webhook->url)
                              ->payload($payload)
                              ->meta(['webhook_id' => $webhook->uuid])
                              ->useSecret(config('mailcoach-api.webhooks.secret'))
                              ->dispatch();
               });
    }
}
