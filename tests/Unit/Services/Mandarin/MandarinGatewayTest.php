<?php

use App\Exceptions\MandarinGatewayException;
use App\Models\User;
use App\Services\Mandarin\MandarinGateway;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;

uses(Tests\TestCase::class);

beforeEach(function () {
    Config::set('services.mandarin.mid', 'test_mid_123');
    Config::set('services.mandarin.secret', 'test_secret_456');
    Config::set('services.mandarin.api_url', 'https://secure-app.mandarin.io');
    Config::set('services.mandarin.self_employed_url', 'https://api.psp.io/self-employed-2/v1');
    Config::set('services.mandarin.self_employed_token', 'se_token_789');
    Config::set('services.mandarin.nominal_account', '40817810000000000001');
});

test('MandarinGateway creates invoice with proper Basic auth and payload', function () {
    Http::fake([
        'https://secure-app.mandarin.io/api/v1/public/invoices/' => Http::response([
            'id' => 'inv_real_999',
            'userWebLink' => 'https://secure-app.mandarin.io/pay/inv_real_999',
            'qrCode' => 'https://secure-app.mandarin.io/qr/inv_real_999',
            'status' => 'pending',
        ], 200),
    ]);

    $gateway = new MandarinGateway;
    $user = new User(['email' => 'user@example.com', 'name' => 'Alice']);
    $user->id = 77;

    $res = $gateway->createTopUpInvoice($user, 500.00, 'https://notalone.app/wallet/callback', ['coin_pack_id' => 2]);

    expect($res['id'])->toBe('inv_real_999')
        ->and($res['payment_url'])->toBe('https://secure-app.mandarin.io/pay/inv_real_999')
        ->and($res['status'])->toBe('pending');

    Http::assertSent(function ($request) {
        return $request->url() === 'https://secure-app.mandarin.io/api/v1/public/invoices/'
            && $request->hasHeader('Authorization', 'Basic '.base64_encode('test_mid_123:test_secret_456'))
            && $request['payment']['amount'] === '500.00'
            && $request['custom_values']['user_id'] === 77;
    });
});

test('MandarinGateway binds INN with Bearer and MID headers', function () {
    Http::fake([
        'https://api.psp.io/self-employed-2/v1/tin/bind' => Http::response([
            'id' => 'bind_888',
            'status' => 'pending_user_confirmation',
        ], 200),
    ]);

    $gateway = new MandarinGateway;
    $res = $gateway->bindSelfEmployedInn('770123456789');

    expect($res['bind_id'])->toBe('bind_888')
        ->and($res['status'])->toBe('pending_user_confirmation');

    Http::assertSent(function ($request) {
        return $request->url() === 'https://api.psp.io/self-employed-2/v1/tin/bind'
            && $request->hasHeader('Authorization', 'Bearer se_token_789')
            && $request->hasHeader('MID', 'test_mid_123')
            && $request['tin'] === '770123456789';
    });
});

test('MandarinGateway registers 422-FZ receipt', function () {
    Http::fake([
        'https://api.psp.io/self-employed-2/v1/cheques/register' => Http::response([
            'id' => 'cheque_555',
            'url' => 'https://npd.nalog.ru/receipt/cheque_555',
            'created_at' => '2026-11-23T20:00:00Z',
        ], 200),
    ]);

    $gateway = new MandarinGateway;
    $receipt = $gateway->registerSelfEmployedReceipt('770123456789', 2000.00, 'Совместное общение');

    expect($receipt['receipt_id'])->toBe('cheque_555')
        ->and($receipt['receipt_url'])->toBe('https://npd.nalog.ru/receipt/cheque_555');
});

test('MandarinGateway executes SBP payout from nominal account', function () {
    Http::fake([
        'https://secure-app.mandarin.io/api/v1/payouts/sbp' => Http::response([
            'id' => 'payout_111',
            'status' => 'success',
        ], 200),
    ]);

    $gateway = new MandarinGateway;
    $idol = new User(['is_idol' => true]);
    $idol->id = 88;

    $payout = $gateway->executePayout($idol, 3000.00, [
        'phone' => '+79998887766',
        'bank_id' => '100000000111',
    ], 'idemp_key_111');

    expect($payout['payout_id'])->toBe('payout_111')
        ->and($payout['status'])->toBe('settled');

    Http::assertSent(function ($request) {
        return $request['sender']['account'] === '40817810000000000001'
            && $request['recipient']['phone'] === '+79998887766'
            && $request['payment']['amount'] === '3000.00';
    });
});

test('MandarinGateway throws MandarinGatewayException on API error', function () {
    Http::fake([
        'https://secure-app.mandarin.io/api/v1/public/invoices/' => Http::response([
            'error' => 'Invalid amount',
        ], 400),
    ]);

    $gateway = new MandarinGateway;
    $user = new User;
    $user->id = 1;

    $gateway->createTopUpInvoice($user, 100.00, 'https://example.com');
})->throws(MandarinGatewayException::class);
