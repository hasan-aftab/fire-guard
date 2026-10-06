<?php

namespace App\Actions;

use App\Models\ReminderDelivery;
use App\Models\WhatsAppTemplate;
use InvalidArgumentException;

class WhatsAppTemplatePayload
{
    /** @return array<string, string> */
    public function values(ReminderDelivery $delivery): array
    {
        return [
            'client_name' => $delivery->client_name,
            'site_name' => $delivery->site_name,
            'subject_name' => $delivery->subject_name,
            'due_date' => $delivery->due_date->format('j F Y'),
            'timing' => $delivery->reminder_offset < 0 ? 'Overdue' : ($delivery->reminder_offset === 0 ? 'Due today' : $delivery->reminder_offset.' days away'),
        ];
    }

    /**
     * @param  array<string, string>  $values
     * @return list<array{type: string, parameters: list<array{type: string, text: string}>}>
     */
    public function components(WhatsAppTemplate $template, array $values): array
    {
        $parameters = [];
        foreach ($template->parameter_keys as $key) {
            if (! isset($values[$key])) {
                throw new InvalidArgumentException('Template parameter mapping is incomplete.');
            }
            $parameters[] = ['type' => 'text', 'text' => $values[$key]];
        }

        return $parameters === [] ? [] : [['type' => 'body', 'parameters' => $parameters]];
    }
}
