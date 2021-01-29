<?php /** @noinspection PhpUndefinedMethodInspection */

namespace Leeovery\MailcoachApi\Actions\Webhook;

use Illuminate\Support\Str;
use Spatie\Mailcoach\Models\Subscriber;
use Spatie\WebhookServer\WebhookCall;
use Spatie\Mailcoach\Models\CampaignLink;
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
            $subscriber = Subscriber::whereId( $payload_payload['subscriber_id'] )->with("emailList")->first();

            $payload_payload = array_merge( $payload_payload, [
                    "subscriber_email"=> $subscriber->email,
                    "email_list_name" => $subscriber->emailList->name,
            ]);
        }

        if( array_key_exists('campaign_link_id', $payload_payload)){
            $campaignLink = CampaignLink::whereId( $payload_payload['campaign_link_id'] )->first();

            $payload_payload = array_merge( $payload_payload, [
                "campaign_link_url"=> optional($campaignLink)->url
            ]);
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
