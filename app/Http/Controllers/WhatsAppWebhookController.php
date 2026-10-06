<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessWhatsAppWebhook;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class WhatsAppWebhookController extends Controller
{
    public function verify(Request $request): Response
    {
        $expected = config('services.whatsapp.webhook_verify_token');
        $provided = $request->query('hub_verify_token', $request->query('hub.verify_token'));
        if ($request->query('hub_mode', $request->query('hub.mode')) !== 'subscribe' || ! is_string($expected) || $expected === ''
            || ! is_string($provided) || ! hash_equals($expected, $provided)) {
            return response('Invalid verification token.', 403);
        }

        return response((string) $request->query('hub_challenge', $request->query('hub.challenge')), 200)->header('Content-Type', 'text/plain');
    }

    public function receive(Request $request): Response
    {
        $secret = config('services.whatsapp.app_secret');
        $signature = $request->header('X-Hub-Signature-256');
        if (! is_string($secret) || $secret === '' || ! is_string($signature)
            || ! hash_equals('sha256='.hash_hmac('sha256', $request->getContent(), $secret), $signature)) {
            return response('Invalid signature.', 403);
        }
        if ($request->input('object') !== 'whatsapp_business_account') {
            return response('OK', 200);
        }
        $events = [];
        $entries = $request->input('entry', []);
        foreach (is_array($entries) ? $entries : [] as $entry) {
            if (! is_array($entry)) {
                continue;
            }
            foreach (is_array($entry['changes'] ?? null) ? $entry['changes'] : [] as $change) {
                if (! is_array($change)) {
                    continue;
                }
                if (($change['field'] ?? null) !== 'messages') {
                    continue;
                }
                $phoneId = data_get($change, 'value.metadata.phone_number_id');
                $statuses = data_get($change, 'value.statuses', []);
                foreach (is_array($statuses) ? $statuses : [] as $status) {
                    if (! is_string($phoneId) || ! is_array($status)) {
                        continue;
                    }
                    $events[] = [
                        'waba_id' => $entry['id'] ?? null, 'phone_number_id' => $phoneId,
                        'id' => $status['id'] ?? null, 'status' => $status['status'] ?? null,
                        'timestamp' => $status['timestamp'] ?? null, 'code' => data_get($status, 'errors.0.code'),
                    ];
                }
            }
        }
        if ($events !== []) {
            ProcessWhatsAppWebhook::dispatch($events);
        }

        return response('OK', 200);
    }
}
