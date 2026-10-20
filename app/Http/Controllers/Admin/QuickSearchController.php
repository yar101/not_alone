<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ContentPack;
use App\Models\Order;
use App\Models\User;
use App\Models\WalletTransaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class QuickSearchController extends Controller
{
    public function search(Request $request): JsonResponse
    {
        $q = trim((string) $request->input('q', ''));
        if (mb_strlen($q) < 1 || (mb_strlen($q) < 2 && ! is_numeric($q))) {
            return response()->json([
                'users' => [],
                'orders' => [],
                'packs' => [],
                'transactions' => [],
            ]);
        }

        // 1. Users
        $users = User::withTrashed()
            ->where(function ($query) use ($q) {
                if (is_numeric($q)) {
                    $query->where('id', (int) $q);
                }
                $query->orWhere('name', 'ilike', "%{$q}%")
                    ->orWhere('email', 'ilike', "%{$q}%");
            })
            ->limit(5)
            ->get()
            ->map(fn (User $u) => [
                'id' => $u->id,
                'title' => $u->name,
                'subtitle' => $u->email,
                'badge' => $u->trashed() ? 'Удален' : ($u->is_banned ? 'Бан' : ($u->is_idol ? 'Айдол' : 'Клиент')),
                'url' => route('admin.users.show', $u->id),
                'avatar_url' => $u->avatar_url,
            ]);

        // 2. Orders
        $orders = Order::with(['customer', 'idol'])
            ->where(function ($query) use ($q) {
                if (is_numeric($q)) {
                    $query->where('id', (int) $q);
                }
                $query->orWhereHas('customer', fn ($cq) => $cq->where('name', 'ilike', "%{$q}%")->orWhere('email', 'ilike', "%{$q}%"))
                    ->orWhereHas('idol', fn ($iq) => $iq->where('name', 'ilike', "%{$q}%")->orWhere('email', 'ilike', "%{$q}%"));
            })
            ->latest('id')
            ->limit(5)
            ->get()
            ->map(fn (Order $o) => [
                'id' => $o->id,
                'title' => "Заказ #{$o->id}",
                'subtitle' => ($o->customer?->name ?? '—').' → '.($o->idol?->name ?? '—'),
                'badge' => $o->status?->label() ?? '—',
                'status_color' => $o->status?->color() ?? 'gray',
                'url' => route('admin.orders.show', $o->id),
            ]);

        // 3. Content Packs
        $packs = ContentPack::with('user:id,name')
            ->where(function ($query) use ($q) {
                if (is_numeric($q)) {
                    $query->where('id', (int) $q);
                }
                $query->orWhere('title', 'ilike', "%{$q}%")
                    ->orWhereHas('user', fn ($uq) => $uq->where('name', 'ilike', "%{$q}%"));
            })
            ->latest('id')
            ->limit(5)
            ->get()
            ->map(fn (ContentPack $p) => [
                'id' => $p->id,
                'title' => $p->title,
                'subtitle' => 'Айдол: '.($p->user?->name ?? '—').' ('.number_format($p->price, 0, '', ' ').' ₽)',
                'badge' => $p->status instanceof \BackedEnum ? $p->status->value : (string) $p->status,
                'url' => route('admin.content-packs.show', $p->id),
            ]);

        // 4. Transactions
        $transactions = WalletTransaction::with('user:id,name')
            ->where(function ($query) use ($q) {
                if (is_numeric($q)) {
                    $query->where('id', (int) $q);
                }
                $query->orWhere('idempotency_key', 'ilike', "%{$q}%")
                    ->orWhere('description', 'ilike', "%{$q}%");
            })
            ->latest('id')
            ->limit(5)
            ->get()
            ->map(fn (WalletTransaction $t) => [
                'id' => $t->id,
                'title' => "Транзакция #{$t->id} (".number_format($t->amount, 2, '.', ' ').' ₽)',
                'subtitle' => ($t->user?->name ?? 'Система').' • '.$t->description,
                'badge' => $t->status instanceof \BackedEnum ? $t->status->value : (string) $t->status,
                'url' => route('admin.transactions.index', ['search' => $t->id]),
            ]);

        return response()->json([
            'users' => $users,
            'orders' => $orders,
            'packs' => $packs,
            'transactions' => $transactions,
        ]);
    }
}
