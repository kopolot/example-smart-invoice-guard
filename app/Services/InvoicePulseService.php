<?php

namespace App\Services;

use App\Models\Invoice;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;

class InvoicePulseService
{
    /**
     * Atomically record a visit using Redis SET (unique visitors), HASH (counters),
     * and ZSET (per-owner heat ranking) in a single Lua script.
     *
     * @return array{views: int, uniqueVisitors: int, isNewVisitor: bool, lastSeenAt: string}
     */
    public function record(Invoice $invoice, string $visitorId): array
    {
        $seenAt = now()->toIso8601String();

        /** @var array{0: string, 1: string, 2: int} $result */
        $result = Redis::eval(
            <<<'LUA'
            local added = redis.call('SADD', KEYS[1], ARGV[1])
            redis.call('HINCRBY', KEYS[2], 'views', 1)
            if added == 1 then
                redis.call('HINCRBY', KEYS[2], 'unique_visitors', 1)
            end
            redis.call('HSET', KEYS[2],
                'last_seen_at', ARGV[2],
                'last_visitor', ARGV[1],
                'number', ARGV[3],
                'owner_id', ARGV[4]
            )
            redis.call('ZINCRBY', KEYS[3], 1, ARGV[5])
            return {
                redis.call('HGET', KEYS[2], 'views'),
                redis.call('HGET', KEYS[2], 'unique_visitors'),
                added
            }
            LUA,
            3,
            $this->visitorsKey($invoice->id),
            $this->statsKey($invoice->id),
            $this->rankingKey((int) $invoice->user_id),
            $visitorId,
            $seenAt,
            (string) $invoice->number,
            (string) $invoice->user_id,
            (string) $invoice->id,
        );

        return [
            'views' => (int) $result[0],
            'uniqueVisitors' => (int) $result[1],
            'isNewVisitor' => (int) $result[2] === 1,
            'lastSeenAt' => $seenAt,
        ];
    }

    public function recordFromRequest(Invoice $invoice, Request $request): array
    {
        return $this->record($invoice, $this->visitorId($request));
    }

    /**
     * @return array{views: int, uniqueVisitors: int, lastSeenAt: ?string, lastVisitor: ?string, number: ?string}
     */
    public function forInvoice(int $invoiceId): array
    {
        /** @var array<string, string> $stats */
        $stats = Redis::hGetAll($this->statsKey($invoiceId));

        return [
            'views' => (int) ($stats['views'] ?? 0),
            'uniqueVisitors' => (int) ($stats['unique_visitors'] ?? 0),
            'lastSeenAt' => $stats['last_seen_at'] ?? null,
            'lastVisitor' => $stats['last_visitor'] ?? null,
            'number' => $stats['number'] ?? null,
        ];
    }

    /**
     * @return list<array{invoiceId: int, number: string, views: int, uniqueVisitors: int, heat: float, lastSeenAt: ?string}>
     */
    public function hotForUser(int $userId, int $limit = 5): array
    {
        /** @var array<string, float> $ranked */
        $ranked = Redis::zRevRange($this->rankingKey($userId), 0, max(0, $limit - 1), true);

        if ($ranked === []) {
            return [];
        }

        $items = [];

        foreach ($ranked as $invoiceId => $heat) {
            $stats = $this->forInvoice((int) $invoiceId);

            $items[] = [
                'invoiceId' => (int) $invoiceId,
                'number' => $stats['number'] ?? (string) $invoiceId,
                'views' => $stats['views'],
                'uniqueVisitors' => $stats['uniqueVisitors'],
                'heat' => (float) $heat,
                'lastSeenAt' => $stats['lastSeenAt'],
            ];
        }

        return $items;
    }

    /**
     * @return list<string>
     */
    public function visitors(int $invoiceId): array
    {
        /** @var list<string> $members */
        $members = Redis::sMembers($this->visitorsKey($invoiceId));

        return $members;
    }

    public function forget(Invoice $invoice): void
    {
        Redis::del(
            $this->statsKey($invoice->id),
            $this->visitorsKey($invoice->id),
        );
        Redis::zRem($this->rankingKey((int) $invoice->user_id), (string) $invoice->id);
    }

    public function visitorId(Request $request): string
    {
        if ($request->user() !== null) {
            return 'user:'.$request->user()->id;
        }

        $sessionId = $request->session()->getId();

        if ($sessionId !== '') {
            return 'guest:'.$sessionId;
        }

        return 'guest:'.Str::lower(hash('sha256', $request->ip().'|'.$request->userAgent()));
    }

    public function statsKey(int $invoiceId): string
    {
        return "invoice-pulse:{$invoiceId}:stats";
    }

    public function visitorsKey(int $invoiceId): string
    {
        return "invoice-pulse:{$invoiceId}:visitors";
    }

    public function rankingKey(int $userId): string
    {
        return "user:{$userId}:invoice-pulse";
    }
}
