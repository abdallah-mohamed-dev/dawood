<?php

use App\Models\Customer;
use App\Models\Room;
use App\Models\User;
use Illuminate\Support\Facades\File;

beforeEach(function () {
    $this->admin = User::factory()->create();
});

test('guests cannot view the backup page', function () {
    $this->get(route('backup.index'))->assertRedirect(route('login'));
});

test('guests cannot download the database backup', function () {
    $this->get(route('backup.database'))->assertRedirect(route('login'));
});

test('the backup page shows a link to download the database', function () {
    $this->actingAs($this->admin)
        ->get(route('backup.index'))
        ->assertOk()
        ->assertSee(route('backup.database'), false);
});

test('an admin can download a full copy of the database', function () {
    $response = $this->actingAs($this->admin)->get(route('backup.database'));

    $response->assertOk();
    $response->assertHeader('content-disposition');
    expect($response->headers->get('content-disposition'))
        ->toContain('attachment')
        ->toContain('.sqlite');
});

test('guests cannot download the csv archive', function () {
    $this->get(route('backup.csv'))->assertRedirect(route('login'));
});

test('an admin can download a csv archive containing every table', function () {
    $customer = Customer::factory()->create();
    Room::factory()->for($customer)->create();

    $response = $this->actingAs($this->admin)->get(route('backup.csv'));

    $response->assertOk();
    expect($response->headers->get('content-disposition'))
        ->toContain('attachment')
        ->toContain('.zip');

    $zipPath = storage_path('app/backup-tmp/'.uniqid('backup-test-').'.zip');
    File::ensureDirectoryExists(dirname($zipPath));
    file_put_contents($zipPath, $response->streamedContent());

    $zip = new ZipArchive;
    $zip->open($zipPath);

    expect($zip->locateName('customers.csv'))->not->toBeFalse();
    expect($zip->locateName('rooms.csv'))->not->toBeFalse();
    expect($zip->locateName('materials.csv'))->not->toBeFalse();
    expect($zip->locateName('cashbox_transactions.csv'))->not->toBeFalse();

    $customersCsv = $zip->getFromName('customers.csv');
    expect($customersCsv)->toContain('name')
        ->toContain($customer->name);

    $roomsCsv = $zip->getFromName('rooms.csv');
    expect($roomsCsv)->toContain('status')
        ->toContain('draft');

    $zip->close();
    unlink($zipPath);
});

test('the csv archive has one file for every exported table', function () {
    $response = $this->actingAs($this->admin)->get(route('backup.csv'));
    $response->assertOk();

    $zipPath = storage_path('app/backup-tmp/'.uniqid('backup-test-').'.zip');
    File::ensureDirectoryExists(dirname($zipPath));
    file_put_contents($zipPath, $response->streamedContent());

    $zip = new ZipArchive;
    $zip->open($zipPath);

    $tables = ['customers', 'rooms', 'materials', 'inventory_batches', 'inventory_movements', 'room_materials', 'room_costs', 'customer_payments', 'expenses', 'expense_categories', 'cashbox_transactions', 'partners', 'partner_withdrawals', 'debts', 'material_types', 'settings', 'activity_logs'];

    foreach ($tables as $table) {
        expect($zip->locateName($table.'.csv'))->not->toBeFalse("missing {$table}.csv");
    }

    $zip->close();
    unlink($zipPath);
});
