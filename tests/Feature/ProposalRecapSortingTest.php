<?php

declare(strict_types=1);

use App\Models\Proposal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use OpenSpout\Reader\XLSX\Reader;

uses(RefreshDatabase::class);

it('sorts proposal recap items by doctor, medicine, date, and principal with whitelist protection', function (): void {
    // p1: older date (2026-11-01), doctor 'dr. Ahmad', medicine 'Zithromax', principal 'Pfizer'
    $p1 = Proposal::create(['doctor_name' => 'dr. Ahmad', 'proposal_date' => '2026-11-01', 'period_year' => 2026]);
    $p1->items()->create(['medicine_name' => 'Zithromax', 'principal_name' => 'Pfizer']);

    // p2: newer date (2026-11-10), doctor 'dr. Zaidan', medicine 'Amoxicillin', principal 'Sanbe'
    $p2 = Proposal::create(['doctor_name' => 'dr. Zaidan', 'proposal_date' => '2026-11-10', 'period_year' => 2026]);
    $p2->items()->create(['medicine_name' => 'Amoxicillin', 'principal_name' => 'Sanbe']);

    // 1. Sort by doctor asc: dr. Ahmad should appear before dr. Zaidan
    $respDoctorAsc = $this->get(route('proposals.index', ['sort_by' => 'doctor', 'sort_dir' => 'asc']))->assertOk();
    expect($respDoctorAsc->viewData('items')->pluck('doctor_name')->all())
        ->toBe(['dr. Ahmad', 'dr. Zaidan']);
    $tableDoctorAsc = substr($respDoctorAsc->getContent(), strpos($respDoctorAsc->getContent(), '<tbody'));
    expect(strpos($tableDoctorAsc, 'dr. Ahmad'))->toBeLessThan(strpos($tableDoctorAsc, 'dr. Zaidan'));

    // 2. Sort by doctor desc: dr. Zaidan should appear before dr. Ahmad
    $respDoctorDesc = $this->get(route('proposals.index', ['sort_by' => 'doctor', 'sort_dir' => 'desc']))->assertOk();
    expect($respDoctorDesc->viewData('items')->pluck('doctor_name')->all())
        ->toBe(['dr. Zaidan', 'dr. Ahmad']);
    $tableDoctorDesc = substr($respDoctorDesc->getContent(), strpos($respDoctorDesc->getContent(), '<tbody'));
    expect(strpos($tableDoctorDesc, 'dr. Zaidan'))->toBeLessThan(strpos($tableDoctorDesc, 'dr. Ahmad'));

    // 3. Sort by medicine asc: Amoxicillin should appear before Zithromax
    $respMedAsc = $this->get(route('proposals.index', ['sort_by' => 'medicine', 'sort_dir' => 'asc']))->assertOk();
    expect($respMedAsc->viewData('items')->pluck('medicine_name')->all())
        ->toBe(['Amoxicillin', 'Zithromax']);
    $tableMedAsc = substr($respMedAsc->getContent(), strpos($respMedAsc->getContent(), '<tbody'));
    expect(strpos($tableMedAsc, 'Amoxicillin'))->toBeLessThan(strpos($tableMedAsc, 'Zithromax'));

    // 4. Sort by date asc: 2026-11-01 (Ahmad) should appear before 2026-11-10 (Zaidan)
    $respDateAsc = $this->get(route('proposals.index', ['sort_by' => 'date', 'sort_dir' => 'asc']))->assertOk();
    expect($respDateAsc->viewData('items')->pluck('doctor_name')->all())
        ->toBe(['dr. Ahmad', 'dr. Zaidan']);
    $tableDateAsc = substr($respDateAsc->getContent(), strpos($respDateAsc->getContent(), '<tbody'));
    expect(strpos($tableDateAsc, 'dr. Ahmad'))->toBeLessThan(strpos($tableDateAsc, 'dr. Zaidan'));

    // 5. Sort by principal asc: Pfizer should appear before Sanbe
    $respPrinAsc = $this->get(route('proposals.index', ['sort_by' => 'principal', 'sort_dir' => 'asc']))->assertOk();
    expect($respPrinAsc->viewData('items')->pluck('principal_name')->all())
        ->toBe(['Pfizer', 'Sanbe']);

    // 6. Sort by period desc
    $respPeriodDesc = $this->get(route('proposals.index', ['sort_by' => 'period', 'sort_dir' => 'desc']))->assertOk();
    expect($respPeriodDesc->viewData('items')->count())->toBe(2);

    // 7. Sort by created_at asc
    $respCreatedAsc = $this->get(route('proposals.index', ['sort_by' => 'created_at', 'sort_dir' => 'asc']))->assertOk();
    expect($respCreatedAsc->viewData('items')->count())->toBe(2);

    // 8. Malicious sort_by falls back safely to default order (date desc: Zaidan before Ahmad)
    $respMalicious = $this->get(route('proposals.index', ['sort_by' => 'id;DROP TABLE proposals;--', 'sort_dir' => 'asc']))
        ->assertOk();
    expect($respMalicious->viewData('items')->pluck('doctor_name')->all())
        ->toBe(['dr. Zaidan', 'dr. Ahmad']);
    $tableMalicious = substr($respMalicious->getContent(), strpos($respMalicious->getContent(), '<tbody'));
    expect(strpos($tableMalicious, 'dr. Zaidan'))->toBeLessThan(strpos($tableMalicious, 'dr. Ahmad'));
});

it('ensures live csv export ignores sort_by and stays chronological default', function (): void {
    config(['formulary.feed_token' => 'secret-test-token']);

    $p1 = Proposal::create(['doctor_name' => 'dr. Ahmad', 'proposal_date' => '2026-11-01', 'period_year' => 2026]);
    $p1->items()->create(['medicine_name' => 'Zithromax', 'principal_name' => 'Pfizer']);

    $p2 = Proposal::create(['doctor_name' => 'dr. Zaidan', 'proposal_date' => '2026-11-10', 'period_year' => 2026]);
    $p2->items()->create(['medicine_name' => 'Amoxicillin', 'principal_name' => 'Sanbe']);

    // Even with sort_by=doctor&sort_dir=asc, CSV must stay in chronological default (date desc: Zaidan before Ahmad)
    $resp = $this->get('/exports/proposals.csv?token=secret-test-token&sort_by=doctor&sort_dir=asc')->assertOk();
    $csv = $resp->streamedContent();

    expect(strpos($csv, 'dr. Zaidan'))->toBeLessThan(strpos($csv, 'dr. Ahmad'));
});

it('ensures xlsx export respects active sorting filters', function (): void {
    $p1 = Proposal::create(['doctor_name' => 'dr. Ahmad', 'proposal_date' => '2026-11-01', 'period_year' => 2026]);
    $p1->items()->create(['medicine_name' => 'Zithromax', 'principal_name' => 'Pfizer']);

    $p2 = Proposal::create(['doctor_name' => 'dr. Zaidan', 'proposal_date' => '2026-11-10', 'period_year' => 2026]);
    $p2->items()->create(['medicine_name' => 'Amoxicillin', 'principal_name' => 'Sanbe']);

    // XLSX with sort_by=medicine&sort_dir=desc should have Zithromax before Amoxicillin
    $resp = $this->get(route('exports.proposals.xlsx', ['sort_by' => 'medicine', 'sort_dir' => 'desc']))->assertOk();

    $tmpFile = tempnam(sys_get_temp_dir(), 'test_xlsx_sort_').'.xlsx';
    file_put_contents($tmpFile, $resp->streamedContent());

    $reader = new Reader;
    $reader->open($tmpFile);

    $medicines = [];
    foreach ($reader->getSheetIterator() as $sheet) {
        foreach ($sheet->getRowIterator() as $row) {
            $cells = $row->toArray();
            if (isset($cells[4]) && in_array($cells[4], ['Amoxicillin', 'Zithromax'], true)) {
                $medicines[] = (string) $cells[4];
            }
        }
    }
    $reader->close();
    @unlink($tmpFile);

    expect($medicines)->toBe(['Zithromax', 'Amoxicillin']);
});
