<?php

namespace App\Services;

use App\Models\WhatsAppAccount;
use App\Models\WhatsAppTemplate;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class MetaWhatsAppClient
{
    private function endpoint(string $path): string
    {
        return 'https://graph.facebook.com/'.config('services.whatsapp.graph_version').'/'.$path;
    }

    private function request(WhatsAppAccount $account): PendingRequest
    {
        return Http::withToken($account->access_token)->acceptJson()->connectTimeout(5)->timeout(15);
    }

    /** @return array{ok: bool, code: string|null, data: array<string, mixed>} */
    private function result(Response $response): array
    {
        $data = $response->json();
        if (! is_array($data)) {
            $data = [];
        }
        $code = data_get($data, 'error.code');

        return ['ok' => $response->successful(), 'code' => is_numeric($code) ? (string) (int) $code : (string) $response->status(), 'data' => $data];
    }

    /**
     * The follow-up a human should act on for a Meta error code, prefixed with a
     * space, or an empty string when there is nothing useful to add. Meta's own
     * message text is deliberately never included: it echoes request data back
     * into stored errors and the UI.
     */
    public static function suffix(string $code): string
    {
        $hint = match ($code) {
            '190' => 'The access token is invalid or expired. Replace it with a System User token generated in Meta Business Settings with its expiry set to Never, then verify again.',
            default => null,
        };

        return $hint === null ? '' : ' '.$hint;
    }

    /**
     * @param  list<array{type: string, parameters: list<array{type: string, text: string}>}>  $components
     * @return array{ok: bool, code: string|null, data: array<string, mixed>}
     */
    public function send(WhatsAppAccount $account, string $recipient, WhatsAppTemplate $template, array $components): array
    {
        $payload = [
            'messaging_product' => 'whatsapp', 'to' => app(WhatsAppPhone::class)->toMetaRecipient($recipient),
            'type' => 'template',
            'template' => ['name' => $template->name, 'language' => ['code' => $template->language]],
        ];
        if ($components !== []) {
            $payload['template']['components'] = $components;
        }

        return $this->result($this->request($account)->post($this->endpoint($account->phone_number_id.'/messages'), $payload));
    }

    /** @return array{ok: bool, code: string|null, data: array<string, mixed>} */
    public function phoneNumbers(WhatsAppAccount $account): array
    {
        $after = null;
        $numbers = [];
        for ($page = 0; $page < 10; $page++) {
            $params = ['fields' => 'id,display_phone_number', 'limit' => 100];
            if ($after !== null) {
                $params['after'] = $after;
            }
            $result = $this->result($this->request($account)->get($this->endpoint($account->waba_id.'/phone_numbers'), $params));
            if (! $result['ok']) {
                return $result;
            }
            foreach (data_get($result['data'], 'data', []) as $number) {
                $numbers[] = $number;
            }
            $after = data_get($result['data'], 'paging.cursors.after');
            if (! is_string($after) || ! data_get($result['data'], 'paging.next')) {
                break;
            }
        }

        return ['ok' => true, 'code' => null, 'data' => ['data' => $numbers]];
    }

    /** @return array{ok: bool, code: string|null, data: array<string, mixed>} */
    public function templates(WhatsAppAccount $account): array
    {
        $after = null;
        $templates = [];
        for ($page = 0; $page < 10; $page++) {
            $params = ['fields' => 'name,language,status,category,components', 'limit' => 100];
            if ($after !== null) {
                $params['after'] = $after;
            }
            $result = $this->result($this->request($account)->get($this->endpoint($account->waba_id.'/message_templates'), $params));
            if (! $result['ok']) {
                return $result;
            }
            foreach (data_get($result['data'], 'data', []) as $template) {
                $templates[] = $template;
            }
            $after = data_get($result['data'], 'paging.cursors.after');
            if (! is_string($after) || ! data_get($result['data'], 'paging.next')) {
                break;
            }
        }

        return ['ok' => true, 'code' => null, 'data' => ['data' => $templates]];
    }
}
