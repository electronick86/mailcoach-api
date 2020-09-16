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

        if($payload_event == "CampaignOpenedEvent" || $payload_event == "CampaignLinkClickedEvent"){
            $payload_payload = $property ? optional($eventPayload->$property->with("subscriber"))->toArray() : null;
        }
        else{
           $payload_payload = $property ? optional($eventPayload->$property)->toArray() : null;
        }

        $payload = [
            'event'   => $payload_event,
            'payload' => $payload_payload,
        ];
        
        
        $payload = [
            'event'   => Str::replaceFirst('Spatie\\Mailcoach\\Events\\', '', $eventName),
            'payload' => $property ? optional($eventPayload->$property)->toArray() : null,
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
