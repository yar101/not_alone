<?php

use App\Contracts\MandarinGatewayInterface;
use App\Exceptions\MandarinGatewayException;
use App\Models\User;
use App\Services\Mandarin\StubMandarinGateway;

uses(Tests\TestCase::class);

test('container resolves StubMandarinGateway in testing environment', function () {
    $gateway = app(MandarinGatewayInterface::class);

    expect($gateway)->toBeInstanceOf(StubMandarinGateway::class);
});

test('createTopUpInvoice returns mock payment and qr urls', function () {
    $gateway = new StubMandarinGateway;
    $user = new User(['email' => 'client@test.com', 'name' => 'Test Client']);
    $user->id = 123;

    $invoice = $gateway->createTopUpInvoice($user, 1000.00, 'https://example.com/return', ['coin_pack_id' => 4]);

    expect($invoice['id'])->toStartWith('stub_inv_')
        ->and($invoice['payment_url'])->toContain('/stub-mandarin/pay/')
        ->and($invoice['qr_code_url'])->toContain('/stub-mandarin/qr/')
        ->and($invoice['status'])->toBe('pending')
        ->and($invoice['amount'])->toBe(1000.00);
});

test('createTopUpInvoice throws exception for non-positive amount', function () {
    $gateway = new StubMandarinGateway;
    $user = new User;
    $user->id = 123;

    $gateway->createTopUpInvoice($user, 0.00, 'https://example.com/return');
})->throws(MandarinGatewayException::class);

test('bindSelfEmployedInn validates 12 digits and binds INN', function () {
    $gateway = new StubMandarinGateway;

    $result = $gateway->bindSelfEmployedInn('770123456789');

    expect($result['status'])->toBe('bound')
        ->and($result['inn'])->toBe('770123456789')
        ->and($result['bind_id'])->toStartWith('stub_bind_');
});

test('bindSelfEmployedInn throws exception on invalid INN length', function () {
    $gateway = new StubMandarinGateway;

    $gateway->bindSelfEmployedInn('12345');
})->throws(MandarinGatewayException::class);

test('bindSelfEmployedInn throws exception on 000000000000', function () {
    $gateway = new StubMandarinGateway;

    $gateway->bindSelfEmployedInn('000000000000');
})->throws(MandarinGatewayException::class);

test('checkSelfEmployedStatus handles normal, inactive, and limit exceeded cases', function () {
    $gateway = new StubMandarinGateway;

    // Normal active case
    $normal = $gateway->checkSelfEmployedStatus('770123456789');
    expect($normal['is_self_employed'])->toBeTrue()
        ->and($normal['limit_exceeded'])->toBeFalse();

    // Inactive case (...0000)
    $inactive = $gateway->checkSelfEmployedStatus('770123450000');
    expect($inactive['is_self_employed'])->toBeFalse()
        ->and($inactive['status'])->toBe('inactive');

    // Limit exceeded case (...9999)
    $exceeded = $gateway->checkSelfEmployedStatus('770123459999');
    expect($exceeded['is_self_employed'])->toBeTrue()
        ->and($exceeded['limit_exceeded'])->toBeTrue();
});

test('registerSelfEmployedReceipt generates valid mock FNS receipt url', function () {
    $gateway = new StubMandarinGateway;

    $receipt = $gateway->registerSelfEmployedReceipt(
        '770123456789',
        1500.00,
        'Услуга совместного времяпрепровождения'
    );

    expect($receipt['receipt_url'])->toContain('https://npd.nalog.ru/receipt/stub-')
        ->and($receipt['amount'])->toBe(1500.00)
        ->and($receipt['receipt_id'])->toStartWith('stub_rcpt_');
});

test('executePayout handles settled, rejected, and pending_bank scenarios', function () {
    $gateway = new StubMandarinGateway;
    $idol = new User(['is_idol' => true]);
    $idol->id = 456;

    // Success (default)
    $settled = $gateway->executePayout($idol, 2500.00, ['phone' => '+79991234567', 'bank_id' => 'sber'], 'idemp_1');
    expect($settled['status'])->toBe('settled');

    // Rejected (...0000)
    $rejected = $gateway->executePayout($idol, 2500.00, ['phone' => '+79991230000', 'bank_id' => 'sber'], 'idemp_2');
    expect($rejected['status'])->toBe('rejected');

    // PendingBank (...9999)
    $pending = $gateway->executePayout($idol, 2500.00, ['phone' => '+79991239999', 'bank_id' => 'sber'], 'idemp_3');
    expect($pending['status'])->toBe('pending_bank');
});

test('getOperationStatus and refundTopUp return valid structures', function () {
    $gateway = new StubMandarinGateway;

    $status = $gateway->getOperationStatus('op_pending_123');
    expect($status['status'])->toBe('pending_bank');

    $refund = $gateway->refundTopUp('inv_123', 500.00, 'Возврат по ст. 32 ЗОЗПП');
    expect($refund['status'])->toBe('succeeded')
        ->and($refund['refund_id'])->toStartWith('stub_ref_');
});
